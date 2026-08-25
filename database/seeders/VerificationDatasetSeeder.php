<?php

namespace Database\Seeders;

use App\Enums\CycleStatus;
use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\Quality;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\Scopes\CurrentExploitation;
use App\Services\Indicators\IndicatorService;
use App\Services\Rules\RecommendationService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Le jeu d'épreuve du mémoire (SPEC-CORRECTION section 10.1).
 *
 * CE DOSSIER EST UNE CONSTRUCTION, PAS UN RELEVÉ. Les quantités et les
 * coûts viennent des ordres de grandeur de la fiche togolaise de la
 * tomate et ne décrivent pas les pratiques réelles de la ferme ; ils ont
 * été choisis à rebours pour que la chaîne de calcul retombe exactement
 * sur les indicateurs publiés dans le mémoire.
 *
 * Structure imposée : une exploitation, trois producteurs du même
 * village, deux parcelles de tomate, deux campagnes, plusieurs passages
 * de récolte par parcelle. NE PAS ajouter de parcelles de contexte pour
 * atteindre cinq : le mémoire écrit que la zone du jeu d'épreuve ne
 * contient que deux parcelles de tomate, et c'est de cette observation
 * qu'est né le seuil de cinq. La famille C y est donc suspendue, et le
 * moteur produit quatre recommandations sur la parcelle en écart
 * (TOM-A01, TOM-A02, TOM-A03, TOM-B01), aucune sur la conforme.
 */
class VerificationDatasetSeeder extends Seeder
{
    public const VILLAGE = 'Kpéwa';

    /**
     * Les indicateurs que les deux parcelles doivent retrouver
     * exactement (SPEC-CORRECTION section 10.1). Les deux vendent à
     * 250 FCFA le kilogramme : c'est ce prix qui rend rendement, coût et
     * marge cohérents entre eux.
     *
     * @var array<string, array{yield_kg_ha: float, production_cost_ha: float, gross_margin_ha: float}>
     */
    public const EXPECTED_INDICATORS = [
        'écart' => ['yield_kg_ha' => 7500.0, 'production_cost_ha' => 620000.0, 'gross_margin_ha' => 1255000.0],
        'conforme' => ['yield_kg_ha' => 12500.0, 'production_cost_ha' => 1450000.0, 'gross_margin_ha' => 1675000.0],
    ];

    /**
     * Les codes attendus du moteur sur la parcelle en écart. TOM-C01
     * n'y figure pas : la zone ne compte que deux parcelles comparables,
     * la famille C est suspendue.
     *
     * @var array<int, string>
     */
    public const EXPECTED_CODES_ON_DEVIANT_PLOT = [
        'TOM-A01',
        'TOM-A02',
        'TOM-A03',
        'TOM-B01',
    ];

    /**
     * Les deux cycles construits, indexés par leur rôle dans le mémoire.
     *
     * @var array<string, CropCycle>
     */
    private array $cycles = [];

    public function __construct(
        private readonly IndicatorService $indicators,
        private readonly RecommendationService $engine,
    ) {}

    public function run(): void
    {
        $exploitation = Exploitation::factory()->create([
            'name' => 'Exploitation du jeu d\'épreuve (construction)',
            'region' => 'Maritime',
            'prefecture' => 'Zio',
        ]);

        // Le seeder tourne hors requête HTTP : l'exploitation courante
        // est posée à la main, sinon le trait n'a personne à interroger.
        CurrentExploitation::set($exploitation->id);

        try {
            $this->build($exploitation);
        } finally {
            CurrentExploitation::clear();
        }
    }

    /**
     * @return array<string, CropCycle>
     */
    public function cycles(): array
    {
        return $this->cycles;
    }

    private function build(Exploitation $exploitation): void
    {
        $tomato = Crop::firstWhere('name', 'Tomate');

        if ($tomato === null) {
            throw new RuntimeException(
                'La culture « Tomate » manque : chargez CropSeeder avant le jeu d\'épreuve.',
            );
        }

        // Deux campagnes : la saison passée, vide, et la saison courante
        // qui porte les deux cycles. Les deux cycles partagent campagne,
        // culture et village : la zone de comparaison en compte deux.
        Campaign::factory()->for($exploitation)
            ->between('2025-03-01', '2025-11-30')
            ->create(['label' => 'Campagne 2025']);

        $campaign = Campaign::factory()->for($exploitation)
            ->between('2026-03-01', '2026-11-30')
            ->create(['label' => 'Campagne 2026']);

        // Trois producteurs du même village ; le troisième n'a pas encore
        // de parcelle suivie.
        $kossi = $this->farmer($exploitation, 'Kossi', 'Amenyo');
        $abra = $this->farmer($exploitation, 'Abra', 'Kekeli');
        $this->farmer($exploitation, 'Sena', 'Dogbe');

        $this->cycles['écart'] = $this->deviantPlot($kossi, $campaign, $tomato);
        $this->cycles['conforme'] = $this->conformingPlot($abra, $campaign, $tomato);

        foreach ($this->cycles as $cycle) {
            $cycle->refresh();
            $cycle->close();

            $this->indicators->computeFor($cycle);
            $this->engine->runFor($cycle->fresh());
        }
    }

    /**
     * Parcelle en écart : 0,20 ha, plantation claire, fumure incomplète
     * et tardive.
     *
     * 1 500 kg récoltés à 250 FCFA, 124 000 FCFA d'opérations :
     * 1 500 / 0,20 = 7 500 kg/ha ; 124 000 / 0,20 = 620 000 FCFA/ha ;
     * (375 000 - 124 000) / 0,20 = 1 255 000 FCFA/ha.
     */
    private function deviantPlot(Farmer $farmer, Campaign $campaign, Crop $tomato): CropCycle
    {
        $cycle = $this->cycle(
            $farmer,
            $campaign,
            $tomato,
            plotName: 'Planche du bas-fond',
            areaHa: 0.20,
            sowingDate: '2026-03-20',
            transplantingDate: '2026-04-20',
            // Plantation claire : bien sous les 33 000 plants/ha.
            density: 20000,
            staked: false,
        );

        $this->operations($cycle, [
            ['2026-03-10', OperationType::Tillage, null, null, null, 15000, null, 'Billonnage'],
            // 100 kg de fumier : 0,5 t/ha, sous le minimum de la fiche.
            ['2026-03-15', OperationType::Fertilization, InputType::Organic, 2, QuantityUnit::Bag50Kg, 10000, null, null],
            ['2026-03-20', OperationType::Sowing, InputType::Seed, 0.1, QuantityUnit::Kilogram, 5000, null, 'Semis en pépinière'],
            ['2026-05-01', OperationType::Irrigation, null, null, null, 20000, null, null],
            // Premier apport de couverture à 25 jours : hors fenêtre, et
            // 200 kg/ha en tout, loin des 350 attendus.
            ['2026-05-15', OperationType::Fertilization, InputType::Npk, 40, QuantityUnit::Kilogram, 24000, null, null],
            ['2026-05-20', OperationType::Weeding, null, null, null, 8000, null, null],
            ['2026-06-01', OperationType::Treatment, InputType::Insecticide, 1, QuantityUnit::Litre, 12000, 15.0, 'Pression de Tuta absoluta'],
            ['2026-07-05', OperationType::Harvest, null, null, null, 30000, null, 'Main-d\'œuvre de récolte'],
        ]);

        // La tomate se récolte en plusieurs passages.
        $this->harvest($cycle, '2026-07-05', 600, 250, Quality::Average);
        $this->harvest($cycle, '2026-07-20', 500, 250, Quality::Average);
        $this->harvest($cycle, '2026-08-01', 400, 250, Quality::Poor);

        return $cycle;
    }

    /**
     * Parcelle conforme : 0,25 ha, conduite conforme mais très
     * fertilisée.
     *
     * 3 125 kg récoltés à 250 FCFA, 362 500 FCFA d'opérations :
     * 3 125 / 0,25 = 12 500 kg/ha ; 362 500 / 0,25 = 1 450 000 FCFA/ha ;
     * (781 250 - 362 500) / 0,25 = 1 675 000 FCFA/ha.
     */
    private function conformingPlot(Farmer $farmer, Campaign $campaign, Crop $tomato): CropCycle
    {
        $cycle = $this->cycle(
            $farmer,
            $campaign,
            $tomato,
            plotName: 'Planche du puits',
            areaHa: 0.25,
            sowingDate: '2026-03-15',
            transplantingDate: '2026-04-10',
            // L'écartement de la fiche : 0,60 m sur 0,50 m.
            density: 33000,
            staked: true,
        );

        $this->operations($cycle, [
            ['2026-03-08', OperationType::Tillage, null, null, null, 25000, null, 'Planches surélevées'],
            // 300 kg de compost : 1,2 t/ha, au dessus du minimum.
            ['2026-03-12', OperationType::Fertilization, InputType::Organic, 6, QuantityUnit::Bag50Kg, 45000, null, null],
            ['2026-03-15', OperationType::Sowing, InputType::Seed, 0.2, QuantityUnit::Kilogram, 15000, null, 'Semis en pépinière'],
            // Trois fractions de couverture vers 15, 45 et 65 jours :
            // 100 kg en tout, soit 400 kg/ha.
            ['2026-04-25', OperationType::Fertilization, InputType::Npk, 1, QuantityUnit::Bag50Kg, 40000, null, null],
            ['2026-04-30', OperationType::Irrigation, null, null, null, 60000, null, null],
            ['2026-05-05', OperationType::Weeding, null, null, null, 15000, null, null],
            ['2026-05-25', OperationType::Fertilization, InputType::Npk, 1, QuantityUnit::Bag25Kg, 20000, null, null],
            ['2026-06-01', OperationType::Treatment, InputType::Insecticide, 2, QuantityUnit::Litre, 47500, 2.0, null],
            ['2026-06-14', OperationType::Fertilization, InputType::Npk, 1, QuantityUnit::Bag25Kg, 20000, null, null],
            ['2026-07-15', OperationType::Harvest, null, null, null, 75000, null, 'Main-d\'œuvre de récolte'],
        ]);

        $this->harvest($cycle, '2026-07-15', 1200, 250, Quality::Good);
        $this->harvest($cycle, '2026-08-01', 1100, 250, Quality::Good);
        $this->harvest($cycle, '2026-08-20', 825, 250, Quality::Good);

        return $cycle;
    }

    private function farmer(Exploitation $exploitation, string $firstName, string $lastName): Farmer
    {
        return Farmer::factory()->for($exploitation)->inVillage(self::VILLAGE)->create([
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
        string $transplantingDate,
        int $density,
        bool $staked,
    ): CropCycle {
        $plot = Plot::factory()->ofFarmer($farmer)->withArea($areaHa)->create([
            'name' => $plotName,
            'soil_preparation' => 'planches',
        ]);

        return CropCycle::factory()->on($plot, $campaign, $crop)->create([
            'sowing_date' => $sowingDate,
            'transplanting_date' => $transplantingDate,
            'density' => $density,
            'staked' => $staked,
            'status' => CycleStatus::InProgress,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: OperationType, 2: InputType|null, 3: float|int|null, 4: QuantityUnit|null, 5: float, 6: float|null, 7: string|null}>  $rows
     */
    private function operations(CropCycle $cycle, array $rows): void
    {
        foreach ($rows as [$date, $type, $inputType, $quantity, $unit, $cost, $observedRate, $notes]) {
            Operation::factory()->forCycle($cycle)->create([
                'date' => $date,
                'type' => $type,
                'input_type' => $inputType,
                'product' => null,
                'quantity' => $quantity,
                'unit' => $unit,
                'cost' => $cost,
                'observed_rate' => $observedRate,
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
