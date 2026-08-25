<?php

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\User;
use App\Services\Import\ImportService;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/*
| SPEC section 11.3 : le traitement ne s'interrompt pas à la première
| ligne fautive, il dresse la liste des rejets ligne par ligne, et un
| second dépôt du même fichier ne duplique pas ce qui est enregistré.
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->technician = User::factory()->technician()->for($this->exploitation)->create();

    $this->farmer = Farmer::factory()->for($this->exploitation)->create([
        'phone' => '90112233',
        'first_name' => 'Kossi',
        'last_name' => 'Amegan',
    ]);
    $this->plot = Plot::factory()->ofFarmer($this->farmer)->create(['name' => 'Le bas-fond']);
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create(['label' => '2026 grande saison']);
    $this->crop = Crop::factory()->named('Tomate')->create();

    $this->cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->create();

    $this->actingAs($this->technician);
});

/**
 * @param  array<int, array<int, string>>  $rows
 */
function csvFile(array $rows): UploadedFile
{
    $lines = [implode(ImportService::SEPARATOR, ImportService::COLUMNS)];

    foreach ($rows as $row) {
        $lines[] = implode(ImportService::SEPARATOR, $row);
    }

    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, implode("\n", $lines));

    return new UploadedFile($path, 'import.csv', 'text/csv', test: true);
}

/**
 * @return array<int, string>
 */
function operationRow(
    string $date = '20/04/2026',
    string $type = 'fertilization',
    string $input = 'npk',
    string $quantity = '2',
    string $unit = 'sac_50kg',
    string $cost = '24000',
    string $phone = '90112233',
    string $plot = 'Le bas-fond',
    string $crop = 'Tomate',
    string $campaign = '2026 grande saison',
): array {
    return [
        'operation', $phone, $plot, $crop, $campaign,
        $type, $input, $date, 'NPK 15-15-15', $quantity, $unit, $cost,
        '', '', '', '', '',
    ];
}

/**
 * @return array<int, string>
 */
function harvestRow(string $date = '15/08/2026', string $quantity = '675'): array
{
    return [
        'recolte', '90112233', 'Le bas-fond', 'Tomate', '2026 grande saison',
        '', '', $date, '', '', '', '',
        $quantity, '200', 'good', '', '',
    ];
}

test('a valid file records its operations and harvests', function () {
    $this->post(route('imports.store'), [
        'fichier' => csvFile([operationRow(), harvestRow()]),
    ])->assertRedirect(route('imports.index'));

    expect(Operation::count())->toBe(1)
        ->and(Harvest::count())->toBe(1);

    $operation = Operation::first();

    expect($operation->type)->toBe(OperationType::Fertilization)
        ->and($operation->input_type)->toBe(InputType::Npk)
        ->and($operation->unit)->toBe(QuantityUnit::Bag50Kg)
        ->and($operation->date->toDateString())->toBe('2026-04-20')
        ->and($operation->exploitation_id)->toBe($this->exploitation->id);
});

test('the run does not stop at the first faulty line', function () {
    $this->post(route('imports.store'), [
        'fichier' => csvFile([
            operationRow(),
            operationRow(date: '32/13/2026'),          // date illisible
            operationRow(date: '21/04/2026', type: 'inconnu'), // type inconnu
            operationRow(date: '22/04/2026'),
        ]),
    ]);

    // Les deux lignes valables sont enregistrées malgré les deux fautes.
    expect(Operation::count())->toBe(2);

    $this->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/index')
            ->where('rapport.total', 4)
            ->where('rapport.created', 2)
            ->where('rapport.rejected', 2)
            ->has('rapport.lines', 4),
        );
});

test('every rejection carries its own reason, line by line', function () {
    $this->post(route('imports.store'), [
        'fichier' => csvFile([
            operationRow(date: '32/13/2026'),
            operationRow(date: '21/04/2026', phone: '99999999'),
            operationRow(date: '22/04/2026', plot: 'Parcelle inconnue'),
            operationRow(date: '23/04/2026', campaign: 'Campagne inconnue'),
            operationRow(date: '05/12/2026'),
            operationRow(date: '24/04/2026', type: 'tillage', input: 'npk'),
        ]),
    ]);

    $this->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rapport.rejected', 6)
            ->where('rapport.lines.0.line', 2)
            ->where('rapport.lines.0.reason', 'La date est illisible : attendu JJ/MM/AAAA.')
            ->where('rapport.lines.1.reason', 'Aucun producteur de votre exploitation ne porte le numéro 99999999.')
            ->where('rapport.lines.2.reason', 'Le producteur Kossi Amegan n\'a aucune parcelle nommée « Parcelle inconnue ».')
            ->where('rapport.lines.3.reason', 'La campagne « Campagne inconnue » est inconnue de votre exploitation.')
            ->where('rapport.lines.4.reason', 'La date sort des bornes de la campagne (01/03/2026 au 30/11/2026).')
            ->where('rapport.lines.5.reason', 'Une opération de ce type n\'apporte aucun intrant : laissez la nature de l\'intrant vide.'),
        );

    expect(Operation::count())->toBe(0);
});

test('a second deposit of the same file does not duplicate anything', function () {
    $rows = [operationRow(), operationRow(date: '21/04/2026'), harvestRow()];

    $this->post(route('imports.store'), ['fichier' => csvFile($rows)]);

    expect(Operation::count())->toBe(2)->and(Harvest::count())->toBe(1);

    $this->post(route('imports.store'), ['fichier' => csvFile($rows)]);

    expect(Operation::count())->toBe(2)->and(Harvest::count())->toBe(1);

    $this->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rapport.created', 0)
            ->where('rapport.ignored', 3),
        );
});

test('a corrected file adds only its corrected lines', function () {
    $this->post(route('imports.store'), [
        'fichier' => csvFile([operationRow(), operationRow(date: '32/13/2026')]),
    ]);

    expect(Operation::count())->toBe(1);

    // Le technicien corrige la date fautive et redépose le fichier entier.
    $this->post(route('imports.store'), [
        'fichier' => csvFile([operationRow(), operationRow(date: '21/04/2026')]),
    ]);

    expect(Operation::count())->toBe(2);

    $this->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rapport.created', 1)
            ->where('rapport.ignored', 1),
        );
});

test('a file of several hundred lines produces a line-by-line report', function () {
    $rows = [];

    for ($day = 1; $day <= 200; $day++) {
        $date = Carbon\Carbon::parse('2026-03-01')->addDays($day)->format('d/m/Y');
        $rows[] = operationRow(date: $date, quantity: (string) $day);
    }

    // Une ligne sur dix est fautive.
    for ($i = 0; $i < 20; $i++) {
        $rows[] = operationRow(date: '32/13/2026');
    }

    $this->post(route('imports.store'), ['fichier' => csvFile($rows)]);

    expect(Operation::count())->toBe(200);

    $this->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rapport.total', 220)
            ->where('rapport.created', 200)
            ->where('rapport.rejected', 20)
            ->has('rapport.lines', 220),
        );
});

test('the import never creates a cycle that is not already open', function () {
    $otherCrop = Crop::factory()->named('Gombo')->create();

    expect($otherCrop)->not->toBeNull();

    $this->post(route('imports.store'), [
        'fichier' => csvFile([operationRow(crop: 'Gombo')]),
    ]);

    $this->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rapport.rejected', 1)
            ->where('rapport.lines.0.reason', 'Aucun cycle ouvert sur « Le bas-fond » pour Gombo en 2026 grande saison : ouvrez-le avant d\'importer.'),
        );

    expect(CropCycle::count())->toBe(1);
});

test('the file accepts French labels and the decimal comma', function () {
    $this->post(route('imports.store'), [
        'fichier' => csvFile([
            operationRow(type: 'Fertilisation', input: 'NPK', quantity: '2,5', unit: 'Sac de 50 kg'),
        ]),
    ]);

    $operation = Operation::first();

    expect($operation)->not->toBeNull()
        ->and($operation->type)->toBe(OperationType::Fertilization)
        ->and($operation->input_type)->toBe(InputType::Npk)
        ->and((float) $operation->quantity)->toBe(2.5);
});

test('a file whose header misses a column is refused as a whole', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, "ligne_type;date\noperation;20/04/2026");

    $this->post(route('imports.store'), [
        'fichier' => new UploadedFile($path, 'import.csv', 'text/csv', test: true),
    ]);

    $this->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rapport.rejected', 1)
            ->where('rapport.lines.0.reason', fn (string $reason) => str_contains($reason, 'Colonnes manquantes')),
        );

    expect(Operation::count())->toBe(0);
});

test('an import of another exploitation finds nothing to write', function () {
    $foreignTechnician = User::factory()->technician()->create();

    $this->actingAs($foreignTechnician)
        ->post(route('imports.store'), ['fichier' => csvFile([operationRow()])]);

    $this->actingAs($foreignTechnician)
        ->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rapport.rejected', 1));

    expect(Operation::withoutGlobalScopes()->count())->toBe(0);
});
