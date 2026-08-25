<?php

namespace App\Services\Import;

use App\Concerns\OperationValidationRules;
use App\Enums\HasLabel;
use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\Quality;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use BackedEnum;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Reads a deposited CSV file and records the operations and harvests it
 * carries (SPEC section 11.3).
 *
 * Three rules drive the whole class:
 *
 *  - the run never stops at the first faulty line: it walks the file to
 *    the end and reports every rejection with its reason;
 *  - a second deposit of the same corrected file must not duplicate what
 *    is already recorded, which a natural key per line settles;
 *  - a line is written inside its own transaction, so one failure never
 *    rolls back the lines that were fine.
 */
class ImportService
{
    use OperationValidationRules;

    public const SEPARATOR = ';';

    /**
     * Columns of the expected file, in order. They are documented on the
     * deposit screen so the technician can build the file from a
     * spreadsheet.
     */
    public const COLUMNS = [
        'ligne_type',
        'telephone_producteur',
        'nom_parcelle',
        'culture',
        'campagne',
        'type_operation',
        'type_intrant',
        'date',
        'produit',
        'quantite',
        'unite',
        'cout',
        'quantite_kg',
        'prix_unitaire',
        'qualite',
        'pertes_kg',
        'notes',
    ];

    private const REQUIRED_COLUMNS = [
        'ligne_type',
        'telephone_producteur',
        'nom_parcelle',
        'culture',
        'campagne',
        'date',
    ];

    /**
     * Walk the file and return a line-by-line report.
     *
     * @return array{
     *     lines: array<int, array{line: int, status: string, reason: string|null}>,
     *     created: int,
     *     ignored: int,
     *     rejected: int,
     *     total: int,
     *     touched_cycles: array<int, int>,
     * }
     */
    public function import(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $this->emptyReport('Le fichier déposé n\'a pas pu être ouvert.');
        }

        try {
            $header = $this->readHeader($handle);
        } catch (Throwable $exception) {
            fclose($handle);

            return $this->emptyReport($exception->getMessage());
        }

        $lines = [];
        $touchedCycles = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, escape: '\\', separator: self::SEPARATOR)) !== false) {
            $lineNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $entry = $this->combine($header, $row);
            $result = $this->importLine($entry);

            $lines[] = [
                'line' => $lineNumber,
                'status' => $result['status'],
                'reason' => $result['reason'],
            ];

            if ($result['cycle_id'] !== null) {
                $touchedCycles[$result['cycle_id']] = $result['cycle_id'];
            }
        }

        fclose($handle);

        return [
            'lines' => $lines,
            'created' => $this->countBy($lines, 'creee'),
            'ignored' => $this->countBy($lines, 'ignoree'),
            'rejected' => $this->countBy($lines, 'rejetee'),
            'total' => count($lines),
            // Le module 5 recalculera les indicateurs de ces cycles, une
            // seule fois chacun plutôt qu'à chaque ligne.
            'touched_cycles' => array_values($touchedCycles),
        ];
    }

    /**
     * @param  array<string, string>  $entry
     * @return array{status: string, reason: string|null, cycle_id: int|null}
     */
    private function importLine(array $entry): array
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (blank($entry[$column] ?? null)) {
                return $this->rejected(sprintf('La colonne « %s » est vide.', $column));
            }
        }

        $kind = $this->normalize($entry['ligne_type']);

        if (! in_array($kind, ['operation', 'recolte'], true)) {
            return $this->rejected('La colonne « ligne_type » doit valoir « operation » ou « recolte ».');
        }

        $cycle = $this->resolveCycle($entry);

        if (is_string($cycle)) {
            return $this->rejected($cycle);
        }

        $date = $this->parseDate($entry['date']);

        if ($date === null) {
            return $this->rejected('La date est illisible : attendu JJ/MM/AAAA.');
        }

        if (! $cycle->campaign->covers($date)) {
            return $this->rejected(sprintf(
                'La date sort des bornes de la campagne (%s au %s).',
                $cycle->campaign->start_date->format('d/m/Y'),
                $cycle->campaign->end_date->format('d/m/Y'),
            ));
        }

        return $kind === 'operation'
            ? $this->importOperation($entry, $cycle, $date)
            : $this->importHarvest($entry, $cycle, $date);
    }

    /**
     * @param  array<string, string>  $entry
     * @return array{status: string, reason: string|null, cycle_id: int|null}
     */
    private function importOperation(array $entry, CropCycle $cycle, Carbon $date): array
    {
        $type = $this->resolveEnum(OperationType::class, $entry['type_operation'] ?? null);

        if (! $type instanceof OperationType) {
            return $this->rejected('Le type d\'opération est inconnu.');
        }

        $inputType = null;

        if (filled($entry['type_intrant'] ?? null)) {
            $inputType = $this->resolveEnum(InputType::class, $entry['type_intrant']);

            if (! $inputType instanceof InputType) {
                return $this->rejected('La nature de l\'intrant est inconnue.');
            }
        }

        $unit = null;

        if (filled($entry['unite'] ?? null)) {
            $unit = $this->resolveEnum(QuantityUnit::class, $entry['unite']);

            if (! $unit instanceof QuantityUnit) {
                return $this->rejected('L\'unité de quantité est inconnue.');
            }
        }

        $payload = [
            'type' => $type->value,
            'input_type' => $inputType?->value,
            'date' => $date->toDateString(),
            'product' => $this->text($entry['produit'] ?? null, 80),
            'quantity' => $this->number($entry['quantite'] ?? null),
            'unit' => $unit?->value,
            'cost' => $this->number($entry['cout'] ?? null),
            'notes' => $this->text($entry['notes'] ?? null, 2000),
        ];

        // Les mêmes règles que l'écran de saisie, à commencer par
        // l'obligation d'un intrant sur les types qui en apportent un
        // (SPEC section 6.3) : une seule source de vérité.
        $validator = Validator::make(
            array_filter($payload, static fn ($value): bool => $value !== null),
            $this->operationRules($payload),
            $this->operationMessages(),
        );

        if ($validator->fails()) {
            return $this->rejected($validator->errors()->first());
        }

        // Clé naturelle : un second dépôt du même fichier corrigé ne doit
        // pas dupliquer ce qui est déjà enregistré (SPEC section 11.3).
        if ($this->alreadyRecorded(Operation::query(), $cycle->id, $payload)) {
            return $this->ignored($cycle->id);
        }

        DB::transaction(function () use ($cycle, $payload): void {
            $cycle->operations()->create($payload);
            $cycle->markInProgress();
        });

        return $this->created($cycle->id);
    }

    /**
     * @param  array<string, string>  $entry
     * @return array{status: string, reason: string|null, cycle_id: int|null}
     */
    private function importHarvest(array $entry, CropCycle $cycle, Carbon $date): array
    {
        $quantity = $this->number($entry['quantite_kg'] ?? null);

        if ($quantity === null) {
            return $this->rejected('La colonne « quantite_kg » est obligatoire sur une ligne de récolte.');
        }

        $quality = null;

        if (filled($entry['qualite'] ?? null)) {
            $quality = $this->resolveEnum(Quality::class, $entry['qualite']);

            if (! $quality instanceof Quality) {
                return $this->rejected('La qualité est inconnue.');
            }
        }

        $payload = [
            'date' => $date->toDateString(),
            'quantity_kg' => $quantity,
            'unit_price' => $this->number($entry['prix_unitaire'] ?? null),
            'quality' => $quality?->value,
            'post_harvest_losses_kg' => $this->number($entry['pertes_kg'] ?? null),
        ];

        if ($this->alreadyRecorded(Harvest::query(), $cycle->id, [
            'date' => $payload['date'],
            'quantity_kg' => $payload['quantity_kg'],
        ])) {
            return $this->ignored($cycle->id);
        }

        DB::transaction(function () use ($cycle, $payload): void {
            $cycle->harvests()->create($payload);
            $cycle->markInProgress();
        });

        return $this->created($cycle->id);
    }

    /**
     * Resolve the cycle a line refers to. The import never creates
     * cycles: an unknown one is a motivated rejection.
     *
     * @param  array<string, string>  $entry
     * @return CropCycle|string the cycle, or the reason it was not found
     */
    private function resolveCycle(array $entry): CropCycle|string
    {
        $phone = preg_replace('/[\s.\-()]+/', '', $entry['telephone_producteur']) ?? '';

        $farmer = Farmer::firstWhere('phone', $phone);

        if ($farmer === null) {
            return sprintf('Aucun producteur de votre exploitation ne porte le numéro %s.', $phone);
        }

        $plot = Plot::query()
            ->where('farmer_id', $farmer->id)
            ->where('name', $entry['nom_parcelle'])
            ->first();

        if ($plot === null) {
            return sprintf(
                'Le producteur %s n\'a aucune parcelle nommée « %s ».',
                $farmer->fullName(),
                $entry['nom_parcelle'],
            );
        }

        $crop = Crop::firstWhere('name', $entry['culture']);

        if ($crop === null) {
            return sprintf('La culture « %s » est absente du référentiel.', $entry['culture']);
        }

        $campaign = Campaign::firstWhere('label', $entry['campagne']);

        if ($campaign === null) {
            return sprintf('La campagne « %s » est inconnue de votre exploitation.', $entry['campagne']);
        }

        $cycle = CropCycle::query()
            ->where('plot_id', $plot->id)
            ->where('crop_id', $crop->id)
            ->where('campaign_id', $campaign->id)
            ->first();

        if ($cycle === null) {
            return sprintf(
                'Aucun cycle ouvert sur « %s » pour %s en %s : ouvrez-le avant d\'importer.',
                $plot->name,
                $crop->name,
                $campaign->label,
            );
        }

        return $cycle;
    }

    /**
     * Whether a row carrying this natural key is already recorded on the
     * cycle. A null value in the key must match a null column, which a
     * plain where() would never do.
     *
     * @template TModel of Operation|Harvest
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $naturalKey
     */
    private function alreadyRecorded(Builder $query, int $cycleId, array $naturalKey): bool
    {
        $query->where('crop_cycle_id', $cycleId);

        foreach ($naturalKey as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);

                continue;
            }

            $query->where($column, $value);
        }

        return $query->exists();
    }

    /**
     * @param  resource  $handle
     * @return array<int, string>
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle, escape: '\\', separator: self::SEPARATOR);

        if ($header === false || $header === [null]) {
            throw new \RuntimeException('Le fichier déposé est vide.');
        }

        $header = array_map(
            fn (?string $column): string => $this->normalize($this->stripBom((string) $column)),
            $header,
        );

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            throw new \RuntimeException(sprintf(
                'Colonnes manquantes dans l\'en-tête : %s.',
                implode(', ', $missing),
            ));
        }

        return $header;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function combine(array $header, array $row): array
    {
        $entry = [];

        foreach ($header as $index => $column) {
            $entry[$column] = trim((string) ($row[$index] ?? ''));
        }

        return $entry;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Accept both the stored value and the French label shown on screen:
     * a file built from a spreadsheet carries what the technician reads.
     *
     * @template TEnum of BackedEnum&HasLabel
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    private function resolveEnum(string $enum, ?string $value): ?BackedEnum
    {
        if (blank($value)) {
            return null;
        }

        $needle = $this->normalize($value);

        foreach ($enum::cases() as $case) {
            if ($this->normalize((string) $case->value) === $needle
                || $this->normalize($case->label()) === $needle) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Accept the French decimal comma as well as the dot.
     */
    private function number(?string $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        $clean = str_replace([' ', ' ', ','], ['', '', '.'], trim($value));

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    private function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * Accept JJ/MM/AAAA, the format of the screens, and AAAA-MM-JJ.
     */
    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            // Le reformatage écarte les dates que Carbon corrige en
            // silence, comme un 31/02 devenu 03/03.
            if ($date !== null && $date->format($format) === $value) {
                return $date->startOfDay();
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\x{FEFF}/u', '', $value) ?? $value;
    }

    /**
     * @return array{status: string, reason: string|null, cycle_id: int|null}
     */
    private function rejected(string $reason): array
    {
        return ['status' => 'rejetee', 'reason' => $reason, 'cycle_id' => null];
    }

    /**
     * @return array{status: string, reason: string|null, cycle_id: int|null}
     */
    private function ignored(int $cycleId): array
    {
        return ['status' => 'ignoree', 'reason' => 'Déjà enregistrée.', 'cycle_id' => $cycleId];
    }

    /**
     * @return array{status: string, reason: string|null, cycle_id: int|null}
     */
    private function created(int $cycleId): array
    {
        return ['status' => 'creee', 'reason' => null, 'cycle_id' => $cycleId];
    }

    /**
     * @param  array<int, array{line: int, status: string, reason: string|null}>  $lines
     */
    private function countBy(array $lines, string $status): int
    {
        return count(array_filter($lines, static fn (array $line): bool => $line['status'] === $status));
    }

    /**
     * @return array{
     *     lines: array<int, array{line: int, status: string, reason: string|null}>,
     *     created: int, ignored: int, rejected: int, total: int, touched_cycles: array<int, int>
     * }
     */
    private function emptyReport(string $reason): array
    {
        return [
            'lines' => [['line' => 1, 'status' => 'rejetee', 'reason' => $reason]],
            'created' => 0,
            'ignored' => 0,
            'rejected' => 1,
            'total' => 1,
            'touched_cycles' => [],
        ];
    }
}
