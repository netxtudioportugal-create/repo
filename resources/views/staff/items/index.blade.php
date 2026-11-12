@extends('layouts.app')

@section('title', 'Item review')
@section('appClass', 'wide')

@section('content')
    <h1>Item review</h1>
    <p class="muted">
        Every item moves draft → BCBA content review → fairness review → approved → released.
        Release is {{ config('examprep.release_gate_open') ? 'open' : 'closed until Gate 0 authorizes it' }}.
        Changing a master’s text creates a new revision and sends it back to draft. A higher master version turns the language records made from the older one stale.
    </p>

    <div class="filters">
        <a href="{{ route('staff.items.index') }}" @if (! $status) aria-current="true" @endif>All ({{ $counts->sum() }})</a>
        @foreach (\App\Enums\ItemStatus::cases() as $s)
            <a href="{{ route('staff.items.index', ['status' => $s->value]) }}" @if ($status === $s->value) aria-current="true" @endif>{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="scroll">
        <table class="grid">
            <thead><tr><th>Item</th><th>Domain</th><th>Task</th><th>Master version</th><th>Status</th><th>Language records</th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td><a href="{{ route('staff.items.show', $item) }}">{{ $item->code }}</a></td>
                    <td>{{ $item->domain->code }}. {{ $item->domain->name('en-US') }}</td>
                    <td>{{ $item->task?->code ?? 'not mapped' }}</td>
                    <td>v{{ $item->master_version }}</td>
                    <td><span class="pill {{ $item->status->value }}">{{ $item->status->label() }}</span></td>
                    <td>
                        @foreach ($item->currentTranslations()->reject(fn ($t) => $t->language_status === null) as $locale => $t)
                            <span class="pill {{ $t->language_status->value }}">{{ $locale }}: {{ str_replace('_', ' ', $t->language_status->value) }}</span>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No items with this status. Run <code>php artisan items:import</code> to load the bank.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
