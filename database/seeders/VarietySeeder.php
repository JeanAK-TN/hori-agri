<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\Variety;
use Database\Seeders\Concerns\ReadsReferenceData;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads the common variety registry from
 * database/seeders/data/varieties.json. Runs after CropSeeder: each
 * entry references a crop by name.
 */
class VarietySeeder extends Seeder
{
    use ReadsReferenceData;

    private const FILENAME = 'varieties.json';

    private const REQUIRED_KEYS = ['crop', 'name', 'cycle_days', 'potential_yield_kg_ha'];

    public function run(): void
    {
        $entries = $this->readReferenceData(self::FILENAME);

        foreach ($entries as $index => $entry) {
            $position = $index + 1;

            $this->assertEntryHasKeys(self::FILENAME, $position, $entry, self::REQUIRED_KEYS);

            $crop = Crop::firstWhere('name', $entry['crop']);

            if ($crop === null) {
                throw new RuntimeException(sprintf(
                    'Fichier de référence « %s », entrée %d : la culture « %s » '
                    .'est inconnue du référentiel. Chargez crops.json d\'abord.',
                    self::FILENAME,
                    $position,
                    $entry['crop'],
                ));
            }

            Variety::updateOrCreate(
                ['crop_id' => $crop->id, 'name' => $entry['name']],
                [
                    'cycle_days' => $entry['cycle_days'],
                    'potential_yield_kg_ha' => $entry['potential_yield_kg_ha'],
                ],
            );
        }

        $simulated = $this->countSimulatedEntries($entries);

        // Le seeder tourne aussi hors console (tests, jeu d'épreuve) :
        // la propriété command n'est alors pas initialisée.
        if ($simulated > 0 && $this->command !== null) {
            $this->command->warn(sprintf(
                '%d variété(s) chargée(s) avec des valeurs SIMULÉES : à remplacer '
                .'par les références publiées avant toute exploitation (SPEC règle 1).',
                $simulated,
            ));
        }
    }
}
