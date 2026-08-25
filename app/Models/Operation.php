<?php

namespace App\Models;

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\Concerns\BelongsToExploitation;
use Database\Factories\OperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exploitation_id
 * @property int $crop_cycle_id
 * @property OperationType $type
 * @property InputType|null $input_type
 * @property Carbon $date
 * @property string|null $product
 * @property string|null $quantity
 * @property QuantityUnit|null $unit
 * @property string|null $cost
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'crop_cycle_id',
    'type',
    'input_type',
    'date',
    'product',
    'quantity',
    'unit',
    'cost',
    'notes',
])]
class Operation extends Model
{
    /** @use HasFactory<OperationFactory> */
    use BelongsToExploitation, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'input_type' => InputType::class,
            'date' => 'date',
            'quantity' => 'decimal:2',
            'unit' => QuantityUnit::class,
            'cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    /**
     * The quantity converted to kilograms, or null when the unit does
     * not convert to a mass (SPEC section 9.4). The analytics layer of
     * module 5 builds the doses per hectare on top of this.
     */
    public function quantityInKilograms(): ?float
    {
        $factor = $this->unit?->toKilograms();

        if ($factor === null || $this->quantity === null) {
            return null;
        }

        return (float) $this->quantity * $factor;
    }
}
