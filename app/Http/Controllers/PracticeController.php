<?php

namespace App\Http\Controllers;

use App\Enums\SessionMode;
use App\Models\Domain;
use App\Models\PracticeSession;
use App\Models\SessionItem;
use App\Services\PracticeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PracticeController extends Controller
{
    public function __construct(private PracticeService $practice) {}

    public function startDomain(Request $request, Domain $domain)
    {
        $session = $this->practice->startDomain($this->learner($request), $domain, $this->isStaff($request));

        return redirect()->route('practice.question', [$session, 1]);
    }

    public function startTimed(Request $request)
    {
        $session = $this->practice->startTimed($this->learner($request), $this->isStaff($request));

        return redirect()->route('practice.question', [$session, 1]);
    }

    public function question(Request $request, PracticeSession $session, int $position)
    {
        $this->own($request, $session);

        if ($session->mode === SessionMode::Timed && ! $session->isFinished() && $session->isExpired()) {
            $this->practice->finish($session);

            return redirect()->route('practice.results', $session)->with('status', __('app.expired'));
        }

        if ($session->isFinished()) {
            return redirect()->route('practice.results', $session);
        }

        $si = $this->sessionItem($session, $position);
        $this->practice->markShown($si);

        $revealed = $session->mode === SessionMode::Domain && $si->checked_at !== null;

        return view('practice.question', $this->questionData($session, $si, $revealed) + [
            'mode' => $session->mode->value,
        ]);
    }

    public function answer(Request $request, PracticeSession $session, int $position)
    {
        $this->own($request, $session);
        $si = $this->sessionItem($session, $position);
        $count = $session->sessionItems()->count();

        $data = $request->validate([
            'action' => ['required', Rule::in(['check', 'next', 'prev', 'finish'])],
            'option' => [Rule::requiredIf($request->input('action') === 'check'), 'nullable', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        if ($session->mode === SessionMode::Domain) {
            if ($data['action'] === 'check') {
                $this->practice->check($si, $data['option']);

                return redirect()->route('practice.question', [$session, $position]);
            }

            if ($position < $count) {
                return redirect()->route('practice.question', [$session, $position + 1]);
            }

            $this->practice->finish($session);

            return redirect()->route('practice.results', $session);
        }

        // Timed mode: every navigation also saves the current selection.
        if (! empty($data['option']) && ! $this->practice->save($si, $data['option'])) {
            return redirect()->route('practice.results', $session)->with('status', __('app.expired'));
        }

        return match ($data['action']) {
            'prev' => redirect()->route('practice.question', [$session, max(1, $position - 1)]),
            'next' => $position < $count
                ? redirect()->route('practice.question', [$session, $position + 1])
                : $this->finishAndShow($session),
            default => $this->finishAndShow($session),
        };
    }

    public function finish(Request $request, PracticeSession $session)
    {
        $this->own($request, $session);

        return $this->finishAndShow($session);
    }

    public function results(Request $request, PracticeSession $session)
    {
        $this->own($request, $session);

        if (! $session->isFinished()) {
            return redirect()->route('practice.question', [$session, 1]);
        }

        return view('practice.results', ['session' => $session] + $this->practice->summary($session));
    }

    /** Walk through missed items with keys and rationales. Only after the set is finished. */
    public function review(Request $request, PracticeSession $session, int $n = 1)
    {
        $this->own($request, $session);
        abort_unless($session->isFinished(), 404);

        $missed = $this->practice->summary($session)['missed'];
        abort_if($missed->isEmpty() || $n < 1 || $n > $missed->count(), 404);

        $si = $missed[$n - 1]->load('item.domain', 'item.task', 'item.translations');

        return view('practice.question', $this->questionData($session, $si, true, $n, $missed->count()) + [
            'mode' => 'review',
        ]);
    }

    private function finishAndShow(PracticeSession $session)
    {
        $this->practice->finish($session);

        return redirect()->route('practice.results', $session);
    }

    /** Row-level ownership: a session is visible only through its own learner cookie. */
    private function own(Request $request, PracticeSession $session): void
    {
        abort_unless($session->learner_id === $this->learner($request)->id, 404);
    }

    private function sessionItem(PracticeSession $session, int $position): SessionItem
    {
        return $session->sessionItems()
            ->where('position', $position)
            ->with('item.domain', 'item.task', 'item.translations')
            ->firstOrFail();
    }

    private function questionData(PracticeSession $session, SessionItem $si, bool $revealed, ?int $index = null, ?int $count = null): array
    {
        [$content, $fallback] = $si->item->contentFor(app()->getLocale());

        return [
            'session' => $session,
            'si' => $si,
            'item' => $si->item,
            'content' => $content,
            'fallback' => $fallback,
            'revealed' => $revealed,
            // The key is passed to the view only once feedback is due.
            'key' => $revealed ? $si->item->getAttribute('key') : null,
            'index' => $index ?? $si->position,
            'count' => $count ?? $session->sessionItems()->count(),
            'staffView' => $session->is_staff_test,
        ];
    }
}
