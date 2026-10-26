@extends('layouts.app')

@section('title', 'Staff sign in')

@section('content')
    <h1>Staff sign in</h1>
    <p class="muted">For content authors, reviewers and testers. Candidates do not need an account.</p>
    <form method="POST" action="{{ route('staff.login') }}" class="card">
        @csrf
        <label class="field"><span>Email</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
        <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
        <label class="check" style="margin-top:0"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in on this device</span></label>
        @inject('turnstile', 'App\Services\Turnstile')
        @if ($turnstile->enabled())
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <div class="cf-turnstile" data-sitekey="{{ $turnstile->siteKey() }}" style="margin-bottom:14px"></div>
        @endif
        <button class="btn block" type="submit">Sign in</button>
    </form>
@endsection
