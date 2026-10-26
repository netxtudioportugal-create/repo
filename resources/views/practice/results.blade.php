@extends('layouts.app')

@section('title', __('app.resTitle'))

@section('content')
    <h1>{{ __('app.resTitle') }}</h1>
    <p class="score">{{ __('app.resScore', ['c' => $correct, 't' => $total]) }}</p>
    <div class="limits small">{{ __('app.resLimits') }}</div>

    @if ($byDomain->count() > 1)
        <h2>{{ __('app.byDomain') }}</h2>
        <table>
            @foreach ($byDomain as $row)
                <tr><td>{{ $row['domain']->code }}. {{ $row['domain']->name() }}</td><td>{{ $row['correct'] }} / {{ $row['total'] }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="row" style="margin-top:22px">
        @if ($missed->isNotEmpty())
            <a class="btn" href="{{ route('practice.review', [$session, 1]) }}">{{ __('app.reviewMissed') }}</a>
        @else
            <p class="muted">{{ __('app.noMissed') }}</p>
        @endif
        <a class="btn ghost" href="{{ route('home') }}">{{ __('app.backHome') }}</a>
    </div>
@endsection

@section('nav')
    @include('partials.nav', ['current' => 'home'])
@endsection
