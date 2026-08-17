@extends('layouts.admin')

@section('content')
  @php
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'not_found' => 'Ticket not found.',
      'reclassified' => 'AI classify re-run completed. Live ticket AI fields updated; workflow status unchanged.',
      default => null,
    };
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode($error) : null;
    $t = $ticket ?? [];
  @endphp

  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if ($errorMsg)
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Ticket Details</h1>
      <p class="sup-page-desc">{{ $t['reference'] ?? '' }} · {{ $t['statusLabel'] ?? '' }} · Read-only view</p>
    </div>
    <a href="/admin/tickets" class="sup-btn-outline">Back to tickets</a>
  </div>

  <section class="sup-card">
    <dl class="detail-dl detail-dl--console">
      <dt>Reference</dt><dd class="mono">{{ $t['reference'] ?? '—' }}</dd>
      <dt>Department</dt><dd>{{ $t['department'] ?? '—' }}</dd>
      <dt>Submitted by</dt><dd>{{ $t['submittedByName'] ?? '—' }}</dd>
      <dt>Risk level</dt>
      <dd>
        <span class="risk-badge risk-badge--{{ $t['riskLevel'] ?? 'low' }}">
          {{ $t['riskLevelLabel'] ?? '—' }}
        </span>
      </dd>
      <dt>Status</dt><dd>{{ $t['statusLabel'] ?? '—' }}</dd>
      <dt>Updated</dt><dd>{{ !empty($t['updatedAt']) ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</dd>
      @if (!empty($t['deleted']))
        <dt>Deletion reason</dt>
        <dd>{{ $t['deletionReason'] ?? '—' }}</dd>
      @endif
    </dl>

    <p class="sup-muted-block admin-readonly-note">
      This is a read-only view. Administrators cannot approve, reject, or modify the risk workflow.
    </p>
  </section>

  <section class="sup-card sup-card--table">
    <div class="sup-card__head">
      <h2>AI classify history</h2>
      <div class="sup-card__actions">
        @if (empty($t['deleted']))
          <form method="post" action="/admin/tickets/{{ urlencode((string) ($t['reference'] ?? '')) }}/reclassify" class="inline-form">
            @csrf
            <button type="submit" class="sup-btn-outline">Re-run AI classify</button>
          </form>
        @endif
        <a href="/admin/ai-analysis?ticket={{ urlencode((string) ($t['reference'] ?? '')) }}" class="sup-link">View all</a>
      </div>
    </div>
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Source</th>
            <th>Category</th>
            <th>L / I</th>
            <th>Priority</th>
          </tr>
        </thead>
        <tbody>
          @forelse (($aiRuns ?? []) as $run)
            <tr>
              <td class="nowrap">{{ !empty($run['createdAt']) ? \Illuminate\Support\Carbon::parse($run['createdAt'])->format('Y-m-d H:i') : '—' }}</td>
              <td>{{ $run['source'] ?? '—' }}</td>
              <td>{{ $run['riskCategory'] ?? '—' }}</td>
              <td class="nowrap">{{ $run['likelihood'] ?? '—' }} / {{ $run['impact'] ?? '—' }}</td>
              <td>{{ $run['priority'] ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty">No stored AI runs for this ticket</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection

