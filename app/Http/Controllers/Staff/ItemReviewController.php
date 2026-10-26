<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Item;
use App\Services\ItemWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $items = Item::with('domain', 'task', 'translations:id,item_id,locale,review_status')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->join('domains', 'domains.id', '=', 'items.domain_id')
            ->orderBy('domains.sort')->orderBy('items.code')
            ->select('items.*')->get();

        $counts = Item::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('staff.items.index', [
            'items' => $items,
            'counts' => $counts,
            'status' => $status,
            'domains' => Domain::orderBy('sort')->get(),
        ]);
    }

    public function show(Item $item)
    {
        $item->load('domain', 'task', 'translations', 'versions', 'statusChanges.user');

        return view('staff.items.show', ['item' => $item, 'key' => $item->getAttribute('key')]);
    }

    public function transition(Request $request, Item $item, ItemWorkflow $workflow)
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['forward', 'draft'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'integer'],
        ]);

        // Reviewers sign off on the exact version they read.
        if ((int) $data['version'] !== $item->version) {
            return back()->withErrors(['status' => 'This item changed while you were reviewing it. Read the new version first.']);
        }

        $data['direction'] === 'forward'
            ? $workflow->advance($item, $request->user(), $data['note'] ?? null)
            : $workflow->returnToDraft($item, $request->user(), $data['note'] ?? '');

        return redirect()->route('staff.items.show', $item)
            ->with('status', "{$item->code} is now: ".$item->fresh()->status->label());
    }
}
