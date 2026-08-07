<?php

namespace App\Models\Scopes;

use Illuminate\Support\Facades\Auth;

/**
 * Holds the cooperative used by CooperativeScope to isolate tenants.
 *
 * Resolution order: an explicitly set value (background jobs, seeders,
 * tests) wins; otherwise the authenticated user's cooperative is used.
 * A null resolution (guest, super_admin, or nothing set) disables the
 * scope entirely — the super_admin exception required by SPEC section 7.2.
 */
final class CurrentCooperative
{
    private static ?int $id = null;

    private static bool $explicit = false;

    /**
     * Explicitly fix the current cooperative outside an HTTP context.
     */
    public static function set(?int $id): void
    {
        self::$id = $id;
        self::$explicit = true;
    }

    /**
     * Forget any explicitly fixed cooperative.
     */
    public static function clear(): void
    {
        self::$id = null;
        self::$explicit = false;
    }

    public static function id(): ?int
    {
        if (self::$explicit) {
            return self::$id;
        }

        return Auth::user()?->cooperative_id;
    }
}
