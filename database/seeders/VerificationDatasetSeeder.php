<?php

namespace Database\Seeders;

use App\Enums\CycleStatus;
use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\Quality;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\Scopes\CurrentCooperative;
use App\Services\Indicators\IndicatorService;
use App\Services\Rules\RecommendationService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Le jeu d'épreuve du mémoire (SPEC section 14.3).
 *
 * CE DOSSIER EST UNE CONSTRUCTION, PAS UN RELEVÉ. Aucun de ces
 * producteurs, aucune de ces parcelles, aucune de ces opérations n'existe.
 * Les quantités et les coûts ont été choisis à rebours pour que les
 * indicateurs calculés tombent exactement sur les valeurs publiées dans
 * le mémoire : ils servent à vérifier la chaîne de calcul, jamais à
 * documenter une pratique agricole.
 *
 * Structure imposée : une coopérative, trois producteurs du même village,
 * quatre parcelles, deux campagnes, vingt et une opérations, cinq
 * récoltes, dont deux échelonnées sur le cycle de manioc.
 */
class VerificationDatasetSeeder extends Seeder
{
    public const VILLAGE = 'Kpéwa';

    /**
     * Les indicateurs que les quatre cycles doivent retrouver, à l'unité
     * de la première décimale près (SPEC section 14.3).
     *
     * @var array<int, array{yield_kg_ha: float, production_cost_ha: float, gross_margin_ha: float}>
     */
    public const EXPECTED_INDICATORS = [
        1 => ['yield_kg_ha' => 900.0, 'production_cost_ha' => 97333.3, 'gross_margin_ha' => 82666.7],
        2 => ['yield_kg_ha' => 800.0, 'production_cost_ha' => 74000.0, 'gross_margin_ha' => 206000.0],
        3 => ['yield_kg_ha' => 9000.0, 'production_cost_ha' => 80000.0, 'gross_margin_ha' => 730000.0],
        4 => ['yield_kg_ha' => 2600.0, 'production_cost_ha' => 185500.0, 'gross_margin_ha' => 334500.0],
    ];

    /**
     * Les recommandations constatées sur ce dossier avec le fichier de
     * règles du dépôt, code par code.
     *
     * Le mémoire en annonce treize ; il en sort quatre ici, et l'écart
     * s'explique en un mot : LES SEUILS DE rules.json SONT SIMULÉS. Le
     * compte est figé pour détecter une dérive du moteur, pas pour
     * prétendre reproduire le chiffre du mémoire. Il sera à reprendre le
     * jour où les valeurs ITRA remplaceront les fixtures.
     *
     * @var array<int, array<int, string>>
     */
    public const EXPECTED_RECOMMENDATION_CODES = [
        // Semis hors fenêtre, dose de NPK et apport d'urée insuffisants.
        1 => ['CAL-MAIS-02', 'FERT-MAIS-01', 'FERT-MAIS-02'],
        // Semis tardif ; l'inoculation ayant eu lieu, INOC-SOJA-01 se tait.
        2 => ['CAL-SOJA-01'],
        // Manioc conduit sans écart repéré par les seuils simulés.
        3 => [],
        // Maïs conduit selon le référentiel : le moteur reste silencieux.
        4 => [],
    ];

    public function __construct(
        private readonly IndicatorService $indicators,
        private readonly RecommendationService $engine,
    ) {}

    /**
     * Les quatre cycles, dans l'ordre du tableau du mémoire.
     *
     * @var array<int, CropCycle>
     */
    private array $cycles = [];

    public function run(): void
    {
        $cooperative = Cooperative::factory()->create([
            'name' => 'Coopérative du jeu d\'épreuve (construction)',
            'region' => 'Kara',
            'prefecture' => 'Kozah',
        ]);

        // Le seeder tourne hors requête HTTP : la coopérative courante
        // est posée à la main, sinon le trait n'a personne à interroger.
        CurrentCooperative::set($cooperative->id);

        try {
            $this->build($cooperative);
        } finally {
            CurrentCooperative::clear();
        }
    }

    /**
     * Les cycles construits, dans l'ordre du mémoire, une fois le seeder
     * passé.
     *
     * @return array<int, CropCycle>
     */
    public function cycles(): array
    {
        return $this->cycles;
    }

    private function build(Cooperative $cooperative): void
    {
        $maize = $this->crop('Maïs');
        $soy = $this->crop('Soja');
        $cassava = $this->crop('Manioc');

        $firstCampaign = Campaign::factory()->for($cooperative)
            ->between('2025-03-01', '2025-11-30')
            ->create(['label' => 'Campagne 2025']);

        $secondCampaign = Campaign::factory()->for($cooperative)
            ->between('2026-03-01', '2026-11-30')
            ->create(['label' => 'Campagne 2026']);

        // Trois producteurs du même village : la zone de comparaison ne
        // se déclenchera pas pour autant, quatre cycles restant sous le
        // seuil de cinq (SPEC section 9.3).
        $kodjo = $this->farmer($cooperative, 'Kodjo', 'Amegah');
        $afi = $this->farmer($cooperative, 'Afi', 'Bakoumey');
        $sena = $this->farmer($cooperative, 'Sena', 'Doe');

        $this->cycles[1] = $this->firstCycle($kodjo, $firstCampaign, $maize);
        $this->cycles[2] = $this->secondCycle($afi, $firstCampaign, $soy);
        $this->cycles[3] = $this->thirdCycle($sena, $secondCampaign, $cassava);
        $this->cycles[4] = $this->fourthCycle($kodjo, $secondCampaign, $maize);

        foreach ($this->cycles as $cycle) {
            $cycle->refresh();
            $cycle->close();

            $this->indicators->computeFor($cycle);
            $this->engine->runFor($cycle->fresh());
        }
    }

    /**
     * Cycle 1 — maïs sur 0,75 ha, conduit en dessous du référentiel.
     *
     * 675 kg récoltés à 200 FCFA le kilogramme, 73 000 FCFA d'opérations.
     * 675 / 0,75 = 900 kg/ha ; 73 000 / 0,75 = 97 333,3 FCFA/ha ;
     * (135 000 − 73 000) / 0,75 = 82 666,7 FCFA/ha.
     */
    private function firstCycle(Farmer $farmer, Campaign $campaign, Crop $crop): CropCycle
    {
        $cycle = $this->cycle($farmer, $campaign, $crop, 'Parcelle du bas-fond', 0.75, '2025-04-15');

        $this->operations($cycle, [
            ['2025-04-05', OperationType::Tillage, null, null, null, 15000, 'Labour attelé'],
            ['2025-04-15', OperationType::Sowing, InputType::Seed, 15, QuantityUnit::Kilogram, 9000, null],
            ['2025-05-05', OperationType::Fertilization, InputType::Npk, 1, QuantityUnit::Bag50Kg, 22000, null],
            // Premier sarclage tardif, trente-cinq jours après le semis.
            ['2025-05-20', OperationType::Weeding, null, null, null, 8000, null],
            ['2025-06-10', OperationType::Fertilization, InputType::Urea, 25, QuantityUnit::Kilogram, 13000, null],
            ['2025-07-01', OperationType::Treatment, InputType::Herbicide, 2, QuantityUnit::Litre, 6000, null],
        ]);

        $this->harvest($cycle, '2025-08-20', 675, 200, Quality::Average);

        return $cycle;
    }

    /**
     * Cycle 2 — soja sur 0,50 ha.
     *
     * 400 kg à 350 FCFA, 37 000 FCFA d'opérations.
     * 400 / 0,5 = 800 kg/ha ; 37 000 / 0,5 = 74 000 FCFA/ha ;
     * (140 000 − 37 000) / 0,5 = 206 000 FCFA/ha.
     */
    private function secondCycle(Farmer $farmer, Campaign $campaign, Crop $crop): CropCycle
    {
        $cycle = $this->cycle($farmer, $campaign, $crop, 'Parcelle de la route', 0.50, '2025-05-02');

        $this->operations($cycle, [
            ['2025-04-10', OperationType::Tillage, null, null, null, 9000, null],
            ['2025-05-02', OperationType::Sowing, InputType::Seed, 30, QuantityUnit::Kilogram, 8000, null],
            ['2025-05-02', OperationType::Treatment, InputType::Inoculant, 1, QuantityUnit::Kilogram, 4000, 'Inoculation des semences'],
            ['2025-06-01', OperationType::Weeding, null, null, null, 7000, null],
            ['2025-06-15', OperationType::Fertilization, InputType::Npk, 20, QuantityUnit::Kilogram, 9000, null],
        ]);

        $this->harvest($cycle, '2025-09-10', 400, 350, Quality::Good);

        return $cycle;
    }

    /**
     * Cycle 3 — manioc sur 1,20 ha, avec deux récoltes échelonnées.
     *
     * 4 800 puis 6 000 kg à 90 FCFA, 96 000 FCFA d'opérations.
     * 10 800 / 1,2 = 9 000 kg/ha ; 96 000 / 1,2 = 80 000 FCFA/ha ;
     * (972 000 − 96 000) / 1,2 = 730 000 FCFA/ha.
     */
    private function thirdCycle(Farmer $farmer, Campaign $campaign, Crop $crop): CropCycle
    {
        $cycle = $this->cycle($farmer, $campaign, $crop, 'Parcelle du plateau', 1.20, '2026-04-05');

        $this->operations($cycle, [
            ['2026-03-20', OperationType::Tillage, null, null, null, 25000, 'Buttage'],
            ['2026-04-05', OperationType::Sowing, InputType::Seed, 12000, QuantityUnit::Unit, 20000, 'Boutures'],
            ['2026-05-15', OperationType::Weeding, null, null, null, 18000, null],
            ['2026-06-01', OperationType::Fertilization, InputType::Npk, 1, QuantityUnit::Bag50Kg, 18000, null],
            ['2026-07-10', OperationType::Weeding, null, null, null, 15000, null],
        ]);

        $this->harvest($cycle, '2026-10-05', 4800, 90, Quality::Good);
        $this->harvest($cycle, '2026-11-15', 6000, 90, Quality::Good);

        return $cycle;
    }

    /**
     * Cycle 4 — maïs sur 1,00 ha, conduit selon le référentiel.
     *
     * 2 600 kg à 200 FCFA, 185 500 FCFA d'opérations.
     * 2 600 / 1 = 2 600 kg/ha ; 185 500 / 1 = 185 500 FCFA/ha ;
     * (520 000 − 185 500) / 1 = 334 500 FCFA/ha.
     */
    private function fourthCycle(Farmer $farmer, Campaign $campaign, Crop $crop): CropCycle
    {
        $cycle = $this->cycle($farmer, $campaign, $crop, 'Parcelle de la maison', 1.00, '2026-04-18');

        $this->operations($cycle, [
            ['2026-04-01', OperationType::Tillage, null, null, null, 28000, null],
            ['2026-04-18', OperationType::Sowing, InputType::Seed, 25, QuantityUnit::Kilogram, 17500, null],
            ['2026-05-10', OperationType::Fertilization, InputType::Npk, 3, QuantityUnit::Bag50Kg, 66000, null],
            // Premier sarclage à quinze jours, comme le veut le référentiel.
            ['2026-05-03', OperationType::Weeding, null, null, null, 30000, null],
            ['2026-06-15', OperationType::Fertilization, InputType::Urea, 2, QuantityUnit::Bag50Kg, 44000, null],
        ]);

        $this->harvest($cycle, '2026-09-20', 2600, 200, Quality::Good);

        return $cycle;
    }

    private function crop(string $name): Crop
    {
        $crop = Crop::where('name', $name)->first();

        if ($crop === null) {
            throw new RuntimeException(sprintf(
                'La culture « %s » manque : chargez CropSeeder avant le jeu d\'épreuve.',
                $name,
            ));
        }

        return $crop;
    }

    private function farmer(Cooperative $cooperative, string $firstName, string $lastName): Farmer
    {
        return Farmer::factory()->for($cooperative)->inVillage(self::VILLAGE)->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    private function cycle(
        Farmer $farmer,
        Campaign $campaign,
        Crop $crop,
        string $plotName,
        float $areaHa,
        string $sowingDate,
    ): CropCycle {
        $plot = Plot::factory()->ofFarmer($farmer)->withArea($areaHa)->create(['name' => $plotName]);

        return CropCycle::factory()->on($plot, $campaign, $crop)->create([
            'sowing_date' => $sowingDate,
            'status' => CycleStatus::InProgress,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: OperationType, 2: InputType|null, 3: float|int|null, 4: QuantityUnit|null, 5: float, 6: string|null}>  $rows
     */
    private function operations(CropCycle $cycle, array $rows): void
    {
        foreach ($rows as [$date, $type, $inputType, $quantity, $unit, $cost, $notes]) {
            Operation::factory()->forCycle($cycle)->create([
                'date' => $date,
                'type' => $type,
                'input_type' => $inputType,
                'product' => null,
                'quantity' => $quantity,
                'unit' => $unit,
                'cost' => $cost,
                'notes' => $notes,
            ]);
        }
    }

    private function harvest(
        CropCycle $cycle,
        string $date,
        float $quantityKg,
        float $unitPrice,
        Quality $quality,
    ): void {
        Harvest::factory()->forCycle($cycle)->create([
            'date' => $date,
            'quantity_kg' => $quantityKg,
            'unit_price' => $unitPrice,
            'quality' => $quality,
            'post_harvest_losses_kg' => null,
        ]);
    }
}
