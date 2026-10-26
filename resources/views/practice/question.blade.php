@extends('layouts.app')

@section('title', __('app.qOf', ['i' => $index, 't' => $count]))

@php
    $labels = ['A', 'B', 'C', 'D'];
    $order = $si->displayOrder();
    $pick = $si->selected_option;
    $ok = $revealed && $pick === $key;
    $isLast = $index === $count;
    $locale = app()->getLocale();
    $contentLang = $fallback ? 'en' : $locale;
@endphp

@section('content')
    <div class="qtop">
        <span class="muted small">
            {{ $mode === 'review' ? __('app.reviewOf', ['i' => $index, 't' => $count]) : __('app.qOf', ['i' => $index, 't' => $count]) }}
        </span>
        @if ($mode === 'timed')
            <span class="timer" id="timer" data-ends-at="{{ $session->ends_at->toIso8601String() }}" aria-label="{{ __('app.timeLeft') }}">--:--</span>
        @elseif ($mode === 'domain')
            <a class="btn ghost" style="min-height:36px;padding:6px 14px" href="{{ route('home') }}">{{ __('app.exit') }}</a>
        @endif
    </div>
    <div class="progress" aria-hidden="true"><span style="width:{{ $index / $count * 100 }}%"></span></div>

    @if ($staffView)
        <p class="pending" role="note">{{ __('app.testView') }}</p>
    @endif

    <p class="muted small" style="margin-bottom:6px">{{ $item->domain->code }}. {{ $item->domain->name() }}</p>

    @if ($fallback)
        <p class="pending">{{ __('app.pending') }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'review' ? '#' : route('practice.answer', [$session, $si->position]) }}">
        @csrf
        <p class="stem" lang="{{ $contentLang }}">{{ $content->stem }}</p>

        <ul class="opts" lang="{{ $contentLang }}">
            @foreach ($order as $i => $canon)
                @php
                    $cls = '';
                    $tag = null;
                    if ($revealed) {
                        if ($canon === $key) { $cls = 'good'; $tag = __('app.keyed'); }
                        elseif ($canon === $pick) { $cls = 'bad'; $tag = __('app.yours'); }
                    }
                @endphp
                <li>
                    <label class="opt {{ $cls }} {{ $revealed ? 'locked' : '' }}">
                        <input type="radio" name="option" value="{{ $canon }}"
                               aria-label="{{ __('app.optLabel', ['l' => $labels[$i]]) }}: {{ $content->options[$canon] }}{{ $tag ? '. '.$tag : '' }}"
                               @checked($pick === $canon) @disabled($revealed)>
                        <span class="k" aria-hidden="true">{{ $labels[$i] }}</span>
                        <span>
                            {{ $content->options[$canon] }}
                            @if ($tag)<span class="tag">{{ $cls === 'good' ? '✓' : '✕' }} {{ $tag }}</span>@endif
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>

        @if ($revealed)
            <div class="feedback" role="status">
                <strong>{{ $mode === 'review' ? __('app.why') : ($ok ? __('app.correct') : __('app.incorrect')) }}</strong>
                @if ($mode === 'review' && ! $pick)
                    <p>{{ __('app.yourAnswerNone') }}</p>
                @endif
                <p lang="{{ $contentLang }}">
                    @if ($content->option_rationales)<strong>{{ __('app.whyKey') }}</strong>@endif{{ $content->rationale }}
                </p>
                @if (! $ok && $pick && ! empty($content->option_rationales[$pick]))
                    <p lang="{{ $contentLang }}"><strong>{{ __('app.whyYours') }}</strong>{{ $content->option_rationales[$pick] }}</p>
                @endif
                @if ($item->source_ref)
                    <p class="src">{{ __('app.source') }}: RBT Ethics Code (2.0), {{ $item->source_ref }}</p>
                @endif
            </div>
        @endif

        @if ($mode === 'domain')
            @if (! $revealed)
                <button class="btn block" name="action" value="check">{{ __('app.check') }}</button>
            @else
                <button class="btn block" name="action" value="next">{{ $isLast ? __('app.finish') : __('app.next') }}</button>
            @endif
        @elseif ($mode === 'timed')
            <div class="row">
                @if ($index > 1)
                    <button class="btn ghost" name="action" value="prev">{{ __('app.prev') }}</button>
                @endif
                <button class="btn" name="action" value="{{ $isLast ? 'finish' : 'next' }}">{{ $isLast ? __('app.finish') : __('app.next') }}</button>
            </div>
        @endif
    </form>

    @if ($mode === 'timed')
        <form method="POST" action="{{ route('practice.finish', $session) }}" id="finishForm" style="margin-top:14px">
            @csrf
            <button class="btn ghost block" type="submit">{{ __('app.finishNow') }}</button>
        </form>
    @elseif ($mode === 'review')
        @if ($isLast)
            <a class="btn block" href="{{ route('practice.results', $session) }}">{{ __('app.back') }}</a>
        @else
            <a class="btn block" href="{{ route('practice.review', [$session, $index + 1]) }}">{{ __('app.next') }}</a>
        @endif
    @endif

    <p class="meta">
        @if ($staffView && $item->task)
            {{ __('app.itemMeta2', ['id' => $item->code, 'v' => $si->item_version, 'task' => $item->task->code, 'label' => $item->task->label()]) }}
        @elseif ($staffView)
            {{ __('app.itemMeta', ['id' => $item->code]) }}
        @else
            {{ __('app.itemMetaPublic', ['id' => $item->code, 'v' => $si->item_version]) }}
        @endif
    </p>
@endsection
