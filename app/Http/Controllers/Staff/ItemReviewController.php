<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ItemStatus;
use App\Enums\LanguageStatus;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Item;
use App\Models\ItemTranslation;
use App\Models\PracticeSession;
use App\Models\SessionItem;
use App\Services\OptionOrder;
use App\Services\ItemWorkflow;
use App\Services\LanguageReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class ItemReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $items = Item::with('domain', 'task', 'translations:id,item_id,locale,version,language_status')
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

    /**
     * The candidate screen for one item, in any locale, before or after an
     * answer is checked. Rendered from unsaved models: nothing is written.
     */
    public function preview(Request $request, Item $item)
    {
        $locales = array_keys(config('examprep.locales'));
        $locale = in_array($request->query('locale'), $locales, true) ? $request->query('locale') : app()->getLocale();
        App::setLocale($locale);

        $item->load('domain', 'task', 'translations');
        $reveal = $request->boolean('reveal');

        $session = new PracticeSession(['mode' => 'domain', 'is_staff_test' => true]);
        $si = new SessionItem([
            'item_version' => $item->version,
            'position' => 1,
            'option_order' => OptionOrder::for('preview|'.$item->code),
        ]);
        $si->setRelation('item', $item);
        [$content, $fallback] = $item->contentFor($locale);

        return view('practice.question', [
            'mode' => 'preview',
            'session' => $session,
            'si' => $si,
            'item' => $item,
            'content' => $content,
            'fallback' => $fallback,
            'revealed' => $reveal,
            'key' => $reveal ? $item->getAttribute('key') : null,
            'index' => 1,
            'count' => 1,
            'staffView' => true,
            'previewLocale' => $locale,
        ]);
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

    /** A language reviewer's decision on the current record of one locale. */
    public function language(Request $request, Item $item, LanguageReview $review)
    {
        $data = $request->validate([
            'record' => ['required', 'integer'],
            'language_status' => ['required', Rule::in(LanguageStatus::settable())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = ItemTranslation::where('item_id', $item->id)->findOrFail($data['record']);
        $review->decide($record, $request->user(), LanguageStatus::from($data['language_status']), $data['notes'] ?? null);

        return redirect()->route('staff.items.show', $item)
            ->with('status', "{$item->code} {$record->locale} v{$record->version} is now: ".$record->language_status->label());
    }
}
