<div class="staffbar">
    <strong>Staff</strong>
    <a href="{{ route('staff.items.index') }}">Item review</a>
    <a href="{{ route('staff.notes') }}">Build notes and response log</a>
    <a href="{{ route('home') }}">Candidate view (test)</a>
    <form method="POST" action="{{ route('staff.logout') }}">
        @csrf
        <button type="submit">Sign out {{ auth()->user()->name }}</button>
    </form>
</div>
