<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int|null $exploitation_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string $password
 * @property Role $role
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['exploitation_id', 'name', 'phone', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Enforce the invariant of SPEC section 5: exploitation_id is null
     * if and only if the role is super_admin. The database cannot
     * express this rule simply, so the model guarantees it.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $isSuperAdmin = $user->role === Role::SuperAdmin;

            if ($isSuperAdmin !== ($user->exploitation_id === null)) {
                throw new LogicException(
                    'users.exploitation_id must be null if and only if the role is super_admin.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /**
     * @return BelongsTo<Exploitation, $this>
     */
    public function exploitation(): BelongsTo
    {
        return $this->belongsTo(Exploitation::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    /**
     * Accounts a given user is allowed to see. The users table is not
     * covered by ExploitationScope (Auth would recurse while resolving
     * the current user), so visibility lives in this local scope.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        return $query->where('exploitation_id', $viewer->exploitation_id);
    }
}
