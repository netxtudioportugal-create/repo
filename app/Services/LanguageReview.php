<?php

namespace App\Services;

use App\Enums\LanguageStatus;
use App\Enums\StaffRole;
use App\Models\ItemTranslation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * A language reviewer's decision on the current language record of an item.
 * The decision is tied to the reviewer, and an import never overwrites it.
 */
class LanguageReview
{
    public function decide(ItemTranslation $record, User $by, LanguageStatus $to, ?string $notes = null): ItemTranslation
    {
        if (! $by->hasRole(StaffRole::LanguageReviewer->value)) {
            throw new AuthorizationException('Only a language reviewer can take this step.');
        }

        $item = $record->loadMissing('item.translations')->item;
        $current = $item->currentTranslations()->get($record->locale);

        if ($record->locale === config('examprep.fallback_locale')) {
            throw ValidationException::withMessages(['language_status' => 'The English master has no language review.']);
        }
        if ($current?->id !== $record->id) {
            throw ValidationException::withMessages(['language_status' => 'Only the current version of a language record can be reviewed.']);
        }
        if ($to === LanguageStatus::Stale) {
            throw ValidationException::withMessages(['language_status' => 'Stale is set by the system only.']);
        }
        if ($record->isStale() || $record->source_master_version !== $item->master_version) {
            throw ValidationException::withMessages(['language_status' => 'This record was made from an older English master. Import an updated translation first.']);
        }

        $approved = $to === LanguageStatus::Approved;

        $record->update([
            'language_status' => $to,
            'language_reviewer' => $by->name,
            'language_reviewed_on' => $approved ? now()->toDateString() : null,
            'language_notes' => $notes ?: $record->language_notes,
            'status_set_by' => $by->id,
        ]);

        return $record;
    }
}
