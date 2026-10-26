@extends('layouts.app')

@section('title', __('app.homeTitle'))

@section('content')
    @if ($staff)
        <p class="pending" role="note">{{ __('app.testView') }}</p>
    @endif

    <h1>{{ __('app.homeTitle') }}</h1>
    <p class="muted">{{ __('app.homeSub') }}</p>

    @if ($total === 0)
        <div class="empty">
            <h2 style="margin-top:0">{{ __('app.emptyTitle') }}</h2>
            <p class="muted">{{ __('app.emptyBody') }}</p>
        </div>
    @else
        <ul class="river">
            @foreach ($domains as $row)
                @continue($row['total'] === 0)
                <li>
                    <form method="POST" action="{{ route('practice.domain', $row['domain']) }}">
                        @csrf
                        <button class="dom" type="submit">
                            <span class="letter">{{ $row['domain']->code }}</span>
                            <span>
                                <span class="name">{{ $row['domain']->name() }}</span>
                                <span class="count">{{ __('app.answered', ['n' => $row['answered'], 't' => $row['total']]) }}</span>
                            </span>
                            <span class="meter" aria-hidden="true"><i style="width:{{ round($row['answered'] / max(1, $row['total']) * 100) }}%"></i></span>
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>

        <section class="panel">
            <h2>{{ __('app.timedTitle') }}</h2>
            <p>{{ __('app.timedBody', ['t' => $total, 'm' => $minutes]) }}</p>
            <form method="POST" action="{{ route('practice.timed') }}">
                @csrf
                <button class="btn" type="submit">{{ __('app.timedStart') }}</button>
            </form>
        </section>
    @endif
@endsection

@section('nav')
    @include('partials.nav', ['current' => 'home'])
@endsection
