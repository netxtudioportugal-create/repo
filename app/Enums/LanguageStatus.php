<?php

namespace App\Enums;

/**
 * Review status of a language record (any locale other than en-US).
 *
 * draft: no reviewer has started.
 * in_language_review: a named reviewer has started, or a documented review is unconfirmed.
 * approved: the reviewer of record approved at this source_master_version.
 * stale: the English master moved on. Set by the system only, never by an import file or a person.
 */
enum LanguageStatus: string
{
    case Draft = 'draft';
    case InLanguageReview = 'in_language_review';
    case Approved = 'approved';
    case Stale = 'stale';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InLanguageReview => 'In language review',
            self::Approved => 'Approved',
            self::Stale => 'Stale (master changed)',
        };
    }

    /** Values an import file or a reviewer may set. */
    public static function settable(): array
    {
        return [self::Draft->value, self::InLanguageReview->value, self::Approved->value];
    }
}
