<?php

namespace Database\Seeders;

use App\Models\Crop;
use Database\Seeders\Concerns\ReadsReferenceData;
use Illuminate\Database\Seeder;

/**
 * Loads the common crop registry from database/seeders/data/crops.json.
 */
class CropSeeder extends Seeder
{
    use ReadsReferenceData;

    private const FILENAME = 'crops.json';

    private const REQUIRED_KEYS = [
        'name',
        'family',
        'cycle_days',
        'reference_density',
        'reference_yield_kg_ha',
        'npk_needs',
    ];

    public function run(): void
    {
        $entries = $this->readReferenceData(self::FILENAME);

        foreach ($entries as $index => $entry) {
            $this->assertEntryHasKeys(self::FILENAME, $index + 1, $entry, self::REQUIRED_KEYS);

            Crop::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'family' => $entry['family'],
                    'cycle_days' => $entry['cycle_days'],
                    'reference_density' => $entry['reference_density'],
                    'reference_yield_kg_ha' => $entry['reference_yield_kg_ha'],
                    'npk_needs' => $entry['npk_needs'],
                ],
            );
        }

        $simulated = $this->countSimulatedEntries($entries);

        // Le seeder tourne aussi hors console (tests, jeu d'épreuve) :
        // la propriété command n'est alors pas initialisée.
        if ($simulated > 0 && $this->command !== null) {
            $this->command->warn(sprintf(
                '%d culture(s) chargée(s) avec des valeurs SIMULÉES : à remplacer '
                .'par les références publiées avant toute exploitation (SPEC règle 1).',
                $simulated,
            ));
        }
    }
}
