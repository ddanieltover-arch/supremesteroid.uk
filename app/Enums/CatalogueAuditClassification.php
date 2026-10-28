<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reference-site URL audit outcomes.
 *
 * These values are for the durable audit ledger only. They are not product
 * status or ProductClassification values and do not make a product purchasable.
 */
enum CatalogueAuditClassification: string
{
    case EXCLUDED_RESTRICTED = 'EXCLUDED_RESTRICTED';
    case COMPLIANCE_HOLD = 'COMPLIANCE_HOLD';
    case LAWFUL_CANDIDATE_REVIEW = 'LAWFUL_CANDIDATE_REVIEW';

    public function label(): string
    {
        return match ($this) {
            self::EXCLUDED_RESTRICTED => 'Excluded — restricted or prohibited for retail onboarding',
            self::COMPLIANCE_HOLD => 'Held — evidence insufficient or ambiguous',
            self::LAWFUL_CANDIDATE_REVIEW => 'Potential lawful consumer candidate — publication gates incomplete',
        };
    }
}
