<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportWorkflowStatus: string
{
    case DISCOVERED = 'DISCOVERED';
    case IMPORTED = 'IMPORTED';
    /** @deprecated Prefer IMPORTED — retained for existing staged rows */
    case STAGED = 'STAGED';
    case NORMALIZED = 'NORMALIZED';
    case DUPLICATE = 'DUPLICATE';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case CONTENT_REVIEW = 'CONTENT_REVIEW';
    /** @deprecated Prefer CONTENT_REVIEW */
    case CONTENT_REWRITE = 'CONTENT_REWRITE';
    case COMPLIANCE_REVIEW = 'COMPLIANCE_REVIEW';
    case PRICING_REVIEW = 'PRICING_REVIEW';
    case MEDIA_REVIEW = 'MEDIA_REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case PUBLISHED = 'PUBLISHED';

    public function label(): string
    {
        return match ($this) {
            self::DISCOVERED => 'Discovered',
            self::IMPORTED, self::STAGED => 'Imported',
            self::NORMALIZED => 'Normalized',
            self::DUPLICATE => 'Duplicate',
            self::REVIEW_REQUIRED => 'Needs review',
            self::CONTENT_REVIEW, self::CONTENT_REWRITE => 'Content review',
            self::COMPLIANCE_REVIEW => 'Compliance review',
            self::PRICING_REVIEW => 'Pricing review',
            self::MEDIA_REVIEW => 'Media review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PUBLISHED => 'Published',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::DUPLICATE, self::REJECTED, self::PUBLISHED], true);
    }
}
