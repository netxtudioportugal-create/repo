<div class="staffbar">
    <strong>Staff</strong>
    <a href="{{ route('staff.items.index') }}">Item review</a>
    @if (auth()->user()->isReadOnly())
        <span class="pill">Read-only</span>
    @else
        <a href="{{ route('staff.notes') }}">Build notes and response log</a>
        <a href="{{ route('home') }}">Candidate view (test)</a>
    @endif
    <form method="POST" action="{{ route('staff.logout') }}">
        @csrf
        <button type="submit">Sign out {{ auth()->user()->name }}</button>
    </form>
</div>
