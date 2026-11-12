@extends('layouts.app')

@section('title', $item->code)
@section('appClass', 'wide')

@php
    $step = $item->status->forward();
    $user = auth()->user();
    $locales = config('examprep.locales');
@endphp

@section('content')
    <p><a href="{{ route('staff.items.index') }}">All items</a></p>
    <h1>{{ $item->code }} <span class="pill {{ $item->status->value }}" style="vertical-align:middle">{{ $item->status->label() }}</span></h1>
    <p class="muted">
        Master version {{ $item->master_version }} (content revision {{ $item->version }}).
        Domain {{ $item->domain->code }} ({{ $item->domain->name('en-US') }}).
        Task {{ $item->task ? $item->task->code.' – '.$item->task->label('en-US') : 'not mapped yet' }}.
        @if ($item->source_ref) Source: {{ $item->authority_source ?? 'RBT Ethics Code (2.0)' }}, {{ $item->source_ref }}@if ($item->source_checked_on), checked {{ $item->source_checked_on->toDateString() }}@endif. @endif
        Keyed option: <span class="keyed">{{ $key }}</span>.
        @if ($item->target_misconception) Target misconception: {{ $item->target_misconception }}. @endif
    </p>
    <p class="muted small">
        @if ($item->key_concept) {{ $item->key_concept }}. @endif
        @if ($item->question_type) Type: {{ $item->question_type }}. @endif
        @if ($item->difficulty_author) Author difficulty: {{ $item->difficulty_author }}. @endif
        @if ($item->is_rule_dependent) Rule dependent; re-verify every {{ $item->cadence_months }} months. @endif
        IP clearance: {{ $item->ip_clearance }}.
        @if ($item->source_review_hold) Source review hold. @endif
    </p>

    <section class="card">
        <h2>Review decision</h2>
        @if ($step)
            <form method="POST" action="{{ route('staff.items.transition', $item) }}">
                @csrf
                <input type="hidden" name="version" value="{{ $item->version }}">
                <label class="field"><span>Note (required when sending back)</span><textarea name="note">{{ old('note') }}</textarea></label>
                <div class="row">
                    @if ($user->hasRole($step[1]))
                        <button class="btn" name="direction" value="forward">{{ $step[2] }} (v{{ $item->version }})</button>
                    @else
                        <p class="muted small">The next step needs the {{ str_replace('_', ' ', $step[1]) }} role.</p>
                    @endif
                    @if ($item->status->canReturnToDraft())
                        <button class="btn ghost" name="direction" value="draft">Send back to draft</button>
                    @endif
                </div>
            </form>
        @else
            <p class="muted">Retired items have no further step.</p>
        @endif
    </section>

    <div class="tr-grid">
        @foreach ($locales as $code => $meta)
            @php
                $records = $item->translations->where('locale', $code)->sortByDesc('version')->values();
                $t = $records->first();
                $isMaster = $code === config('examprep.fallback_locale');
            @endphp
            <section class="card" lang="{{ $meta['html'] }}">
                <h2>
                    {{ $meta['name'] }} <span class="muted small">{{ $code }}</span>
                    @if ($t && ! $isMaster)<span class="pill {{ $t->language_status->value }}">{{ $t->language_status->label() }}</span>@endif
                </h2>
                @if ($t)
                    @unless ($isMaster)
                        <p class="muted small" lang="en">
                            Version {{ $t->version }}, made from master version {{ $t->source_master_version }}.
                            @if ($t->language_reviewer) Reviewer: {{ $t->language_reviewer }}@if ($t->language_reviewed_on), {{ $t->language_reviewed_on->toDateString() }}@endif. @endif
                            @if ($t->isStale()) Candidates see English until an updated translation is imported. @endif
                            @if ($t->language_notes)<br>{{ $t->language_notes }}@endif
                        </p>
                    @endunless
                    <p>{{ $t->stem }}</p>
                    <ol type="A">
                        @foreach ($t->options as $label => $text)
                            <li @class(['keyed' => $label === $key])>{{ $text }}</li>
                        @endforeach
                    </ol>
                    <p class="small"><strong>Rationale.</strong> {{ $t->rationale }}</p>
                    @foreach ($t->option_rationales ?? [] as $label => $why)
                        <p class="small"><strong>{{ $label }}.</strong> {{ $why }}</p>
                    @endforeach

                    @if (! $isMaster && ! $t->isStale() && $user->hasRole('language_reviewer'))
                        <form method="POST" action="{{ route('staff.items.language', $item) }}" lang="en">
                            @csrf
                            <input type="hidden" name="record" value="{{ $t->id }}">
                            <label class="field"><span>Language review note</span><textarea name="notes"></textarea></label>
                            <div class="row">
                                @foreach (\App\Enums\LanguageStatus::settable() as $value)
                                    @if ($value !== $t->language_status->value)
                                        <button class="btn ghost" name="language_status" value="{{ $value }}">Set {{ str_replace('_', ' ', $value) }}</button>
                                    @endif
                                @endforeach
                            </div>
                        </form>
                    @endif

                    @if ($records->count() > 1)
                        <p class="muted small" lang="en">
                            Earlier versions:
                            @foreach ($records->slice(1) as $old)
                                v{{ $old->version }} ({{ $old->language_status?->label() }}, master version {{ $old->source_master_version }})@if (! $loop->last), @endif
                            @endforeach
                        </p>
                    @endif
                @else
                    <p class="muted">Translation pending. Candidates see English.</p>
                @endif
            </section>
        @endforeach
    </div>

    <div class="tr-grid" style="margin-top:14px">
        <section class="card">
            <h2>Status history</h2>
            <table class="grid">
                @foreach ($item->statusChanges as $c)
                    <tr>
                        <td>{{ $c->created_at?->format('Y-m-d H:i') }}</td>
                        <td>v{{ $c->item_version }}: {{ $c->from_status ?? 'new' }} → {{ $c->to_status }}</td>
                        <td>{{ $c->user?->name ?? 'import' }}@if ($c->note)<br><span class="muted">{{ $c->note }}</span>@endif</td>
                    </tr>
                @endforeach
            </table>
        </section>
        <section class="card">
            <h2>Versions</h2>
            <table class="grid">
                @foreach ($item->versions as $v)
                    <tr><td>v{{ $v->version }}</td><td>{{ $v->created_at?->format('Y-m-d H:i') }}</td><td><code>{{ substr($v->content_hash, 0, 12) }}</code></td></tr>
                @endforeach
            </table>
        </section>
    </div>
@endsection
