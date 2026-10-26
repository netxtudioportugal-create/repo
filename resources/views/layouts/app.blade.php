@php
    $locales = config('examprep.locales');
    $current = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locales[$current]['html'] ?? $current }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title', __('app.appName'))</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&family=Noto+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body>
<div class="app @yield('appClass')">
    @auth
        @include('partials.staffbar')
    @endauth

    <header>
        <a class="brand" href="{{ route('home') }}" style="color:inherit;text-decoration:none">
            <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true">
                <path d="M3 12c5-5 9 5 14 0s9 5 14 0" fill="none" stroke="var(--teal)" stroke-width="3" stroke-linecap="round"/>
                <path d="M3 20c5-5 9 5 14 0s9 5 14 0" fill="none" stroke="var(--teal-mid)" stroke-width="3" stroke-linecap="round"/>
                <circle cx="28" cy="27" r="3.5" fill="var(--gold)"/>
            </svg>
            <div><b>{{ __('app.appName') }}</b><span>{{ __('app.sub') }}</span></div>
        </a>

        <details class="langwrap">
            <summary class="langbtn">{{ __('app.language') }}: {{ $locales[$current]['name'] }} <span aria-hidden="true">▾</span></summary>
            <ul class="langlist">
                @foreach ($locales as $code => $meta)
                    <li>
                        <form method="POST" action="{{ route('locale') }}">
                            @csrf
                            <button name="locale" value="{{ $code }}" lang="{{ $meta['html'] }}" @if ($code === $current) aria-current="true" @endif>
                                <b>{{ $meta['name'] }}</b>
                                @if ($code === 'ru-UA')<span>{{ __('app.ruSub', [], $code) }}</span>@endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </details>
    </header>

    <main id="main">
        @if (session('status'))
            <p class="flash" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="errors" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @yield('content')
    </main>

    @hasSection('nav')
        @yield('nav')
    @endif

    <footer>© {{ now()->year }} {{ config('examprep.publisher') }}</footer>
</div>
</body>
</html>
