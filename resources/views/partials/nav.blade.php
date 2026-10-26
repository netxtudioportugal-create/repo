{{-- $current: home | about --}}
<nav aria-label="Main">
    <a href="{{ route('home') }}" @if ($current === 'home') aria-current="page" @endif>{{ __('app.navHome') }}</a>
    <form method="POST" action="{{ route('practice.timed') }}">
        @csrf
        <button type="submit">{{ __('app.navTimed') }}</button>
    </form>
    <a href="{{ route('about') }}" @if ($current === 'about') aria-current="page" @endif>{{ __('app.navAbout') }}</a>
</nav>
