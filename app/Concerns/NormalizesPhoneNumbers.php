<?php

namespace App\Concerns;

trait NormalizesPhoneNumbers
{
    /**
     * Strip the separators technicians naturally type in a phone number,
     * so that "90 11-22.33" and "90112233" identify the same account.
     */
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[\s.\-()]+/', '', $phone) ?? $phone;
    }
}
