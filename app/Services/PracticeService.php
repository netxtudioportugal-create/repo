<?php

namespace App\Services;

use App\Enums\SessionMode;
use App\Models\Domain;
use App\Models\Item;
use App\Models\ItemResponse;
use App\Models\Learner;
use App\Models\PracticeSession;
use App\Models\SessionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PracticeService
{
    public function __construct(private Grader $grader) {}

    public function startDomain(Learner $learner, Domain $domain, bool $staff): PracticeSession
    {
        $items = Item::availableTo($staff)->where('domain_id', $domain->id)->orderBy('code')->get();

        return $this->start($learner, SessionMode::Domain, $items, $staff, $domain);
    }

    public function startTimed(Learner $learner, bool $staff): PracticeSession
    {
        $items = Item::availableTo($staff)
            ->join('domains', 'domains.id', '=', 'items.domain_id')
            ->orderBy('domains.sort')->orderBy('items.code')
            ->select('items.*')->get();

        return $this->start($learner, SessionMode::Timed, $items, $staff);
    }

    public static function timedMinutes(int $count): int
    {
        return $count * config('examprep.timed.minutes_per_item') + config('examprep.timed.extra_minutes');
    }

    /** Records the first time an item was displayed, for response_time_ms. */
    public function markShown(SessionItem $si): void
    {
        if ($si->shown_at === null) {
            $si->update(['shown_at' => now()]);
        }
    }

    /**
     * Domain mode: grade, reveal, log. The answer is final once checked.
     */
    public function check(SessionItem $si, string $option): SessionItem
    {
        $session = $si->session;
        $this->guardOpen($session);

        if ($si->checked_at !== null) {
            throw ValidationException::withMessages(['option' => 'This item has already been checked.']);
        }

        return DB::transaction(function () use ($si, $option, $session) {
            $si->fill([
                'selected_option' => $option,
                'is_correct' => $this->grader->grade($si->item, $option),
                'answered_at' => now(),
                'checked_at' => now(),
            ])->save();

            $this->log($session, $si);

            return $si;
        });
    }

    /**
     * Timed mode: save (or change) the selection. Graded now, shown only at the end.
     * Returns false when time ran out; the session is then finished.
     */
    public function save(SessionItem $si, string $option): bool
    {
        $session = $si->session;

        if ($session->isExpired()) {
            $this->finish($session);

            return false;
        }

        $this->guardOpen($session);

        $si->fill([
            'selected_option' => $option,
            'is_correct' => $this->grader->grade($si->item, $option),
            'answered_at' => now(),
        ])->save();

        return true;
    }

    /** Finishes a session. Timed answers are logged once, as submitted. */
    public function finish(PracticeSession $session): PracticeSession
    {
        if ($session->isFinished()) {
            return $session;
        }

        return DB::transaction(function () use ($session) {
            if ($session->mode === SessionMode::Timed) {
                $session->sessionItems()->whereNotNull('selected_option')->with('item')->get()
                    ->each(fn (SessionItem $si) => $this->log($session, $si));
            }

            $session->update(['finished_at' => now()]);

            return $session;
        });
    }

    /** Raw counts per domain. No readiness or pass-likelihood figures, by design. */
    public function summary(PracticeSession $session): array
    {
        $rows = $session->sessionItems()->with('item.domain')->get();

        return [
            'total' => $rows->count(),
            'correct' => $rows->where('is_correct', true)->count(),
            'byDomain' => $rows->groupBy(fn ($si) => $si->item->domain->code)
                ->map(fn (Collection $g) => [
                    'domain' => $g->first()->item->domain,
                    'correct' => $g->where('is_correct', true)->count(),
                    'total' => $g->count(),
                ])->sortKeys()->values(),
            'missed' => $rows->filter(fn ($si) => $si->is_correct !== true)->values(),
        ];
    }

    private function start(Learner $learner, SessionMode $mode, Collection $items, bool $staff, ?Domain $domain = null): PracticeSession
    {
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'No items are available.']);
        }

        return DB::transaction(function () use ($learner, $mode, $items, $staff, $domain) {
            $session = PracticeSession::create([
                'learner_id' => $learner->id,
                'mode' => $mode,
                'domain_id' => $domain?->id,
                'is_staff_test' => $staff,
                'started_at' => now(),
                'ends_at' => $mode === SessionMode::Timed ? now()->addMinutes(self::timedMinutes($items->count())) : null,
            ]);

            $items->values()->each(fn (Item $item, int $i) => $session->sessionItems()->create([
                'item_id' => $item->id,
                'item_version' => $item->version,
                'position' => $i + 1,
                'option_order' => OptionOrder::for($session->id.'|'.$item->code),
            ]));

            return $session;
        });
    }

    private function guardOpen(PracticeSession $session): void
    {
        if ($session->isFinished()) {
            throw ValidationException::withMessages(['session' => 'This practice set is finished.']);
        }
    }

    private function log(PracticeSession $session, SessionItem $si): void
    {
        $shown = $si->shown_at ?? $si->answered_at;

        ItemResponse::create([
            'learner_id' => $session->learner_id,
            'practice_session_id' => $session->id,
            'item_id' => $si->item_id,
            'item_version' => $si->item_version,
            'selected_option' => $si->selected_option,
            'is_correct' => $si->is_correct,
            'option_order_key' => $si->option_order,
            'response_time_ms' => max(1, (int) $shown->diffInMilliseconds($si->answered_at, true)),
            'is_staff_test' => $session->is_staff_test,
        ]);
    }
}
