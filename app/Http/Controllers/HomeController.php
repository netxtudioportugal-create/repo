<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Item;
use App\Models\SessionItem;
use App\Services\PracticeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $learner = $this->learner($request);
        $staff = $this->isStaff($request);

        $available = Item::availableTo($staff)->get(['id', 'domain_id']);

        // Distinct items this learner has answered, by domain.
        $answered = SessionItem::query()
            ->whereNotNull('selected_option')
            ->whereIn('item_id', $available->pluck('id'))
            ->whereHas('session', fn ($q) => $q->where('learner_id', $learner->id))
            ->distinct()->pluck('item_id');

        $domains = Domain::orderBy('sort')->get()->map(function (Domain $d) use ($available, $answered) {
            $ids = $available->where('domain_id', $d->id)->pluck('id');

            return [
                'domain' => $d,
                'total' => $ids->count(),
                'answered' => $ids->intersect($answered)->count(),
            ];
        });

        return view('home', [
            'domains' => $domains,
            'total' => $available->count(),
            'minutes' => PracticeService::timedMinutes($available->count()),
            'staff' => $staff,
        ]);
    }
}
