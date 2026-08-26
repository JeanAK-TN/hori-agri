<?php

namespace App\Console\Commands;

use App\Services\Rules\RulesFileValidator;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Exposes the controls of SPEC section 10.4 without writing anything.
 *
 * It reads the same file and runs the same validator as the seeder, so
 * a green run really means the seeding will hold up.
 */
class RulesValidate extends Command
{
    protected $signature = 'rules:validate
                            {--fichier= : Chemin d\'un autre fichier de règles à contrôler}';

    protected $description = 'Contrôle le fichier des règles agronomiques sans rien écrire en base';

    public function handle(RulesFileValidator $validator): int
    {
        $path = $this->option('fichier') ?? database_path('seeders/data/rules.json');

        try {
            $entries = $this->read((string) $path);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $problems = $validator->problems($entries);

        if ($problems !== []) {
            $this->components->error(sprintf(
                '%d problème(s) dans %s :',
                count($problems),
                basename((string) $path),
            ));

            foreach ($problems as $problem) {
                $this->components->bulletList([$problem]);
            }

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d règles contrôlées : schéma, conditions évaluables, champs connus, '
            .'répartitions, source unique et absence de seuil simulé.',
            count($entries),
        ));

        $this->table(
            ['Répartition', 'Attendu'],
            [
                ['Familles', $this->describe(RulesFileValidator::EXPECTED_BY_FAMILY)],
                ['Cultures', $this->describe(RulesFileValidator::EXPECTED_BY_CROP)],
                ['Origine du seuil', $this->describe(RulesFileValidator::EXPECTED_BY_THRESHOLD)],
                ['Sévérité', $this->describe(RulesFileValidator::EXPECTED_BY_SEVERITY)],
            ],
        );

        // Un seuil simulé ne passe plus par un avertissement : le
        // validateur l'a déjà refusé plus haut (SPEC-CORRECTION 7).
        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(sprintf(
                'Fichier de règles introuvable : %s. Il est fourni par l\'auteur du mémoire (SPEC section 10).',
                $path,
            ));
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException('Le fichier de règles doit contenir un tableau JSON d\'entrées.');
        }

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException('Chaque entrée du fichier de règles doit être un objet JSON.');
            }
        }

        /** @var array<int, array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, int>  $expected
     */
    private function describe(array $expected): string
    {
        $parts = [];

        foreach ($expected as $key => $count) {
            $parts[] = $count.' '.$key;
        }

        return implode(', ', $parts);
    }
}
