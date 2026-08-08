<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Reads the reference files of database/seeders/data (SPEC section 10).
 *
 * A missing or malformed file stops the seeding with a clear message:
 * replacement data is never invented, since every value in these files
 * is an agronomic reference (SPEC rule 1).
 */
trait ReadsReferenceData
{
    /**
     * Overridable so that tests can point the seeder at another
     * directory, in particular an empty one.
     */
    public ?string $dataDirectory = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readReferenceData(string $filename): array
    {
        $directory = $this->dataDirectory ?? database_path('seeders/data');
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! is_file($path)) {
            throw new RuntimeException(sprintf(
                'Fichier de référence « %s » absent de %s. Il est fourni par '
                .'l\'auteur du mémoire (SPEC section 10) : le seeder ne crée '
                .'aucune donnée de remplacement.',
                $filename,
                $directory,
            ));
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException(sprintf(
                'Fichier de référence « %s » illisible : un tableau JSON d\'entrées est attendu.',
                $filename,
            ));
        }

        foreach ($decoded as $index => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException(sprintf(
                    'Fichier de référence « %s », entrée %d : un objet JSON est attendu.',
                    $filename,
                    $index + 1,
                ));
            }
        }

        /** @var array<int, array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * Check that an entry carries every key of the schema of section 10.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $required
     */
    protected function assertEntryHasKeys(string $filename, int $position, array $entry, array $required): void
    {
        $missing = array_diff($required, array_keys($entry));

        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'Fichier de référence « %s », entrée %d : clés manquantes (%s).',
                $filename,
                $position,
                implode(', ', $missing),
            ));
        }
    }

    /**
     * Count the entries still carrying the simulated-value marker, so
     * that the seeder can say it loudly.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    protected function countSimulatedEntries(array $entries): int
    {
        return count(array_filter(
            $entries,
            static fn (array $entry): bool => str_contains(
                (string) ($entry['_source'] ?? ''),
                'SIMULÉ',
            ),
        ));
    }
}
