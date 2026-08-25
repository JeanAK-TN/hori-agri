<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\BelongsToExploitation;
use Database\Factories\FarmerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exploitation_id
 * @property int|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property Gender|null $gender
 * @property int|null $birth_year
 * @property int|null $experience_years
 * @property string $village
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'first_name',
    'last_name',
    'gender',
    'birth_year',
    'experience_years',
    'village',
    'phone',
])]
class Farmer extends Model
{
    /** @use HasFactory<FarmerFactory> */
    use BelongsToExploitation, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_year' => 'integer',
            'experience_years' => 'integer',
        ];
    }

    /**
     * The account this producer uses to consult the application, when
     * they have one (SPEC section 6.2).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Plot, $this>
     */
    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /**
     * Free-text search on the fields a technician knows by heart.
     *
     * @param  Builder<Farmer>  $query
     * @return Builder<Farmer>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $pattern = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($pattern): void {
            $query->where('first_name', 'ilike', $pattern)
                ->orWhere('last_name', 'ilike', $pattern)
                ->orWhere('village', 'ilike', $pattern)
                ->orWhere('phone', 'ilike', $pattern);
        });
    }
}
