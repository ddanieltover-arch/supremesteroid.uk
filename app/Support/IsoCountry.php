<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

class IsoCountry
{
    /**
     * Normalize a country identifier to an ISO 3166-1 alpha-2 code.
     *
     * Business logic must never depend on free-form country names.
     */
    public static function normalize(string $countryCode): string
    {
        $normalized = strtoupper(trim($countryCode));

        if (! preg_match('/^[A-Z]{2}$/', $normalized)) {
            throw new InvalidArgumentException(
                "Invalid ISO 3166-1 alpha-2 country code: '{$countryCode}'."
            );
        }

        return $normalized;
    }
}
