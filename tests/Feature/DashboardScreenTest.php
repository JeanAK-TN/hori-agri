<?php

use App\Enums\RecoStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Exploitation;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\User;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\DashboardService;
use Inertia\Testing\AssertableInertia as Assert;

/*
| SPEC section 12 : les filtres fonctionnent, et l'export reprend
| exactement ce que l'écran affiche.
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->admin = User::factory()->exploitationAdmin()->for($this->exploitation)->create();

    $this->maize = Crop::factory()->named('Maïs')->create();
    $this->soy = Crop::factory()->named('Soja')->create();

    $this->campaign = Campaign::factory()->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')->create(['label' => 'Campagne 2026']);

    $this->actingAs($this->admin);
});

test('the screen carries every aggregate the specification asks for', function () {
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $this->get(route('exploitation-dashboard.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tableau-de-bord/index')
            ->where('headline.cycles', 1)
            // JSON ne distingue pas 1000 de 1000.0 : on compare la valeur.
            ->where('headline.yield_kg_ha', fn (int|float $yield) => (float) $yield === 1000.0)
            ->has('byCrop', 1)
            ->has('byVillage', 1)
            ->has('byCampaign', 1)
            ->has('bySeverity', 3)
            ->has('appliedShare')
            ->has('belowZoneMedian')
            ->has('campaigns', 1)
            ->has('crops', 2),
        );
});

test('the screen is closed to everyone but the exploitation administrator', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('exploitation-dashboard.index'))
        ->assertForbidden();
})->with(['superAdmin', 'technician', 'farmer']);

test('the filters narrow the screen the way they narrow the service', function () {
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->exploitation, $this->soy, $this->campaign, 'Bassar', 1.0, 400, 30000);

    $this->get(route('exploitation-dashboard.index', ['culture' => $this->maize->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('headline.cycles', 1)
            // JSON ne distingue pas 1000 de 1000.0 : on compare la valeur.
            ->where('headline.yield_kg_ha', fn (int|float $yield) => (float) $yield === 1000.0)
            ->has('byCrop', 1)
            ->where('byCrop.0.label', 'Maïs')
            ->where('filters.culture', $this->maize->id),
        );
});

test('an unknown filter value simply narrows to nothing', function () {
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $this->get(route('exploitation-dashboard.index', ['culture' => 999999]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('headline.cycles', 0)
            ->has('byCrop', 0),
        );
});

/*
| L'export.
*/

test('the export repeats exactly what the screen shows, field by field', function () {
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->exploitation, $this->soy, $this->campaign, 'Kara', 1.0, 400, 30000);

    $expected = app(DashboardService::class)->summary(new DashboardFilters);

    $response = $this->get(route('exploitation-dashboard.export'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('synthese-campagne.csv');

    $lines = parseCsv($response->streamedContent());

    // L'en-tête, puis autant de lignes que la synthèse en compte.
    expect($lines)->toHaveCount(count($expected) + 1)
        ->and($lines[0])->toBe([
            'Regroupement',
            'Libellé',
            'Cycles',
            'Rendement moyen (kg/ha)',
            'Coût moyen (FCFA/ha)',
            'Marge brute moyenne (FCFA/ha)',
        ]);

    foreach ($expected as $index => $row) {
        expect($lines[$index + 1])->toBe([
            $row['grouping'],
            $row['label'],
            (string) $row['cycles'],
            frenchNumber($row['yield_kg_ha']),
            frenchNumber($row['production_cost_ha']),
            frenchNumber($row['gross_margin_ha']),
        ], 'ligne '.$index);
    }
});

test('the export carries the byte order mark a French spreadsheet expects', function () {
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $content = $this->get(route('exploitation-dashboard.export'))->streamedContent();

    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        // Les accents survivent au passage.
        ->and($content)->toContain('Maïs');
});

test('the export obeys the same filters as the screen', function () {
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->exploitation, $this->soy, $this->campaign, 'Kara', 1.0, 400, 30000);

    $content = $this->get(route('exploitation-dashboard.export', [
        'culture' => $this->maize->id,
    ]))->streamedContent();

    expect($content)->toContain('Maïs')
        ->and($content)->not->toContain('Soja')
        ->and($content)->not->toContain('Kara');
});

test('an incalculable value stays empty in the file rather than becoming a zero', function () {
    // Sans prix de vente, la marge brute est incalculable.
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000, null);

    $lines = parseCsv($this->get(route('exploitation-dashboard.export'))->streamedContent());

    // Regroupement, libellé, cycles, rendement, coût, marge vide.
    expect($lines[1][3])->toBe('1000')
        ->and($lines[1][5])->toBe('');
});

test('the export of another exploitation never leaks into this one', function () {
    cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $otherExploitation = Exploitation::factory()->create();
    $otherCampaign = Campaign::factory()->for($otherExploitation)
        ->between('2026-03-01', '2026-11-30')->create(['label' => 'Campagne voisine']);

    cycleYielding($otherExploitation, $this->maize, $otherCampaign, 'Dapaong', 1.0, 9000, 50000);

    $this->actingAs($this->admin);

    $content = $this->get(route('exploitation-dashboard.export'))->streamedContent();

    expect($content)->not->toContain('Dapaong')
        ->and($content)->not->toContain('Campagne voisine')
        ->and($content)->toContain('Bassar');
});

test('the recommendations of the screen match those of the service', function () {
    $cycle = cycleYielding($this->exploitation, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    Recommendation::factory()
        ->forCycle($cycle)
        ->fromRule(Rule::factory()->create())
        ->status(RecoStatus::Applied)
        ->create();

    $this->get(route('exploitation-dashboard.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appliedShare.applied', 1)
            ->where('appliedShare.answered', 1)
            ->where('appliedShare.rate', fn (int|float $rate) => (float) $rate === 1.0),
        );
});

test('an empty exploitation gets an honest screen rather than a broken one', function () {
    $this->get(route('exploitation-dashboard.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('headline.cycles', 0)
            ->where('headline.yield_kg_ha', null)
            ->has('byCrop', 0)
            ->where('appliedShare.rate', null)
            ->where('belowZoneMedian.below', 0),
        );

    // L'export d'une exploitation vide ne contient que son en-tête.
    expect(parseCsv($this->get(route('exploitation-dashboard.export'))->streamedContent()))
        ->toHaveCount(1);
});

/**
 * Le service arrondit à deux décimales ; le fichier écrit la virgule
 * décimale, et laisse vide ce qui est incalculable.
 */
function frenchNumber(?float $value): string
{
    return $value === null ? '' : str_replace('.', ',', (string) $value);
}

/**
 * @return array<int, array<int, string>>
 */
function parseCsv(string $content): array
{
    $content = str_replace("\xEF\xBB\xBF", '', $content);

    $lines = [];

    foreach (explode("\n", trim($content)) as $line) {
        $line = rtrim($line, "\r");

        if ($line !== '') {
            $lines[] = str_getcsv($line, ';', '"', '');
        }
    }

    return $lines;
}
