@extends('layouts.app')

@section('title', 'Item review')
@section('appClass', 'wide')

@section('content')
    <h1>Item review</h1>
    <p class="muted">
        Every item moves draft → BCBA content review → fairness review → approved → released.
        Release is {{ config('examprep.release_gate_open') ? 'open' : 'closed until Gate 0 authorizes it' }}.
        Changing an item’s content in the bank creates a new version and sends it back to draft.
    </p>

    <div class="filters">
        <a href="{{ route('staff.items.index') }}" @if (! $status) aria-current="true" @endif>All ({{ $counts->sum() }})</a>
        @foreach (\App\Enums\ItemStatus::cases() as $s)
            <a href="{{ route('staff.items.index', ['status' => $s->value]) }}" @if ($status === $s->value) aria-current="true" @endif>{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="scroll">
        <table class="grid">
            <thead><tr><th>Item</th><th>Domain</th><th>Task</th><th>Version</th><th>Status</th><th>Locales</th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td><a href="{{ route('staff.items.show', $item) }}">{{ $item->code }}</a></td>
                    <td>{{ $item->domain->code }}. {{ $item->domain->name('en') }}</td>
                    <td>{{ $item->task?->code ?? 'not mapped' }}</td>
                    <td>v{{ $item->version }}</td>
                    <td><span class="pill {{ $item->status->value }}">{{ $item->status->label() }}</span></td>
                    <td>{{ $item->translations->pluck('locale')->join(', ') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No items with this status. Run <code>php artisan items:import</code> to load the bank.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
