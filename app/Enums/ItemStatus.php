<?php

namespace App\Enums;

/**
 * Item release pipeline (Pilot v3.2, Section 0): BCBA content review and an
 * independent fairness review both come before anything reaches candidates.
 */
enum ItemStatus: string
{
    case Draft = 'draft';
    case BcbaReview = 'bcba_review';
    case FairnessReview = 'fairness_review';
    case Approved = 'approved';
    case Released = 'released';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::BcbaReview => 'In BCBA content review',
            self::FairnessReview => 'In fairness review',
            self::Approved => 'Approved, awaiting release',
            self::Released => 'Released to candidates',
            self::Retired => 'Retired',
        };
    }

    /** The single forward step and the staff role allowed to take it. */
    public function forward(): ?array
    {
        return match ($this) {
            self::Draft => [self::BcbaReview, null, 'Send to BCBA content review'],
            self::BcbaReview => [self::FairnessReview, 'bcba_reviewer', 'Approve content, send to fairness review'],
            self::FairnessReview => [self::Approved, 'fairness_reviewer', 'Approve fairness'],
            self::Approved => [self::Released, 'admin', 'Release to candidates'],
            self::Released => [self::Retired, 'admin', 'Retire item'],
            self::Retired => null,
        };
    }

    public function canReturnToDraft(): bool
    {
        return in_array($this, [self::BcbaReview, self::FairnessReview, self::Approved], true);
    }
}
