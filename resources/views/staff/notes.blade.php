@extends('layouts.app')

@section('title', 'Build notes')
@section('appClass', 'wide')

@section('content')
    <h1>Build notes</h1>
    <div class="card">
        <ul>
            <li>Status: staging build. Candidates see only released items, and release stays closed until Gate 0 (<code>EXAMPREP_RELEASE_GATE_OPEN</code>).</li>
            <li>Grading runs on the server (<code>App\Services\Grader</code>). The keyed option is a hidden model attribute and reaches the page only after an answer is checked (domain mode) or the set is finished (timed and review).</li>
            <li>Every item is stored with canonical labels A to D. Display order is shuffled per session and item, and saved as <code>option_order</code>, so a reload never reshuffles.</li>
            <li>Versioning: the import hashes each master’s text. A change creates a new content revision and snapshot and sends the item back to draft. Reviewers approve a specific revision.</li>
            <li>Imports are transactional: one invalid item rejects the whole file. <code>--dry-run</code> validates without writing.</li>
            <li>Row-level ownership: a practice set opens only with the learner cookie that created it; anyone else gets a 404.</li>
            <li>Timed sets are enforced on the server through <code>ends_at</code>. The countdown on the page is display only.</li>
            <li>Results show raw counts only. No adaptive sequencing, study recommendations, readiness or pass-likelihood language.</li>
            <li>Locale set, frozen at Gate 0: en-US, pt-BR, es-419, uk-UA, ru-UA (never ru-RU). The menu labels EN, PT, ES, УКР, РУС are display only.</li>
            <li>Masters and language records: the en-US master carries <code>master_version</code>. Each language record is item + locale + version and records the master version it was made from. When the master version rises, older language records turn stale on their own and candidates see English until an updated translation is imported.</li>
            <li>Language review: draft, in language review, approved, stale. Stale is set by the system only. An approved text is never changed in place, and an import never overwrites a status set by a language reviewer.</li>
            <li>Ethics Pilot (domain F) is imported one step at a time with <code>pilot:import --step=3</code> to <code>6</code>; <code>pilot:evidence</code> prints the figures to record after each step.</li>
            <li>Deferred until after Gate 1: candidate accounts, payments, adaptive suggestions, AI features, full-length 85-item practice exam.</li>
        </ul>
    </div>

    <div class="card">
        <h2>Response log</h2>
        <p class="small muted">{{ $total }} responses stored, {{ $candidateTotal }} from candidates (the rest are staff test sessions). Latest 25:</p>
        <div class="scroll">
            <table class="grid">
                <thead><tr><th>item_id</th><th>v</th><th>sel</th><th>correct</th><th>order</th><th>ms</th><th>learner</th><th>staff test</th><th>at</th></tr></thead>
                <tbody>
                @forelse ($responses as $r)
                    <tr>
                        <td>{{ $r->item->code }}</td><td>{{ $r->item_version }}</td><td>{{ $r->selected_option }}</td>
                        <td>{{ $r->is_correct ? 'true' : 'false' }}</td><td>{{ $r->option_order_key }}</td><td>{{ $r->response_time_ms }}</td>
                        <td><code>{{ substr($r->learner_id, 0, 8) }}…</code></td><td>{{ $r->is_staff_test ? 'yes' : 'no' }}</td>
                        <td>{{ $r->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">No responses recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
