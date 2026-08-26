<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\Rule;
use App\Services\Rules\RulesFileValidator;
use Database\Seeders\Concerns\ReadsReferenceData;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads the common knowledge base from database/seeders/data/rules.json.
 *
 * Nothing is written before every control of SPEC section 10.4 has
 * passed: a file that does not hold up stops the seeding rather than
 * loading a knowledge base nobody can trust.
 */
class RuleSeeder extends Seeder
{
    use ReadsReferenceData;

    private const FILENAME = 'rules.json';

    public function __construct(private readonly RulesFileValidator $validator) {}

    public function run(): void
    {
        $entries = $this->readReferenceData(self::FILENAME);

        $problems = $this->validator->problems($entries);

        if ($problems !== []) {
            throw new RuntimeException(sprintf(
                "Le fichier « %s » ne passe pas les contrôles de la SPEC section 10.4 :\n  - %s",
                self::FILENAME,
                implode("\n  - ", $problems),
            ));
        }

        foreach ($entries as $entry) {
            $crop = $entry['crop'] === null
                ? null
                : Crop::firstWhere('name', $entry['crop']);

            if ($entry['crop'] !== null && $crop === null) {
                throw new RuntimeException(sprintf(
                    'Règle %s : la culture « %s » est absente du référentiel. Chargez crops.json d\'abord.',
                    $entry['code'],
                    $entry['crop'],
                ));
            }

            // Les règles du fichier sont communes à toutes les
            // exploitations : exploitation_id reste nul.
            Rule::withoutGlobalScopes()->updateOrCreate(
                ['exploitation_id' => null, 'code' => $entry['code']],
                [
                    'crop_id' => $crop?->id,
                    'family' => $entry['family'],
                    'condition' => $entry['condition'],
                    'severity' => $entry['severity'],
                    'message' => $entry['message'],
                    'action' => $entry['action'],
                    'estimated_gain' => $entry['estimated_gain'],
                    'source' => $entry['source'],
                    'threshold_status' => $entry['threshold_status'],
                    'threshold_note' => $entry['threshold_note'],
                    'enabled' => $entry['enabled'],
                ],
            );
        }

    }
}
