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
        Version {{ $item->version }}.
        Domain {{ $item->domain->code }} ({{ $item->domain->name('en') }}).
        Task {{ $item->task ? $item->task->code.' – '.$item->task->label('en') : 'not mapped yet' }}.
        @if ($item->source_ref) Source: RBT Ethics Code (2.0), {{ $item->source_ref }}. @endif
        Keyed option: <span class="keyed">{{ $key }}</span>.
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
            @php $t = $item->translations->firstWhere('locale', $code); @endphp
            <section class="card" lang="{{ $meta['html'] }}">
                <h2>{{ $meta['name'] }} @if ($t)<span class="pill">{{ $t->review_status }}</span>@endif</h2>
                @if ($t)
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
