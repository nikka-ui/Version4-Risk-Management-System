@extends('layouts.dept')

@section('content')
  @php
    $stats = $stats ?? [];
    $recent = $recent ?? [];
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'not_found' => 'Ticket not found or not visible to your department.',
      default => null,
    };
  @endphp

  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Dashboard</h1>
      <p class="sup-page-desc">Welcome, {{ $user['displayName'] ?? $user['username'] }} — you own risk tickets routed to {{ $user['department'] ?? 'your department' }}.</p>
    </div>
    <a href="/laravel/dept/inbox" class="filter-pill filter-pill--head">Ownership inbox <span class="filter-pill__count">{{ (int) ($stats['inbox'] ?? 0) }}</span></a>
  </div>

  <div class="sup-kpi-grid sup-kpi-grid--officer">
    <a class="sup-kpi sup-kpi--accent" href="/laravel/dept/tickets">
      <span class="sup-kpi__value">{{ (int) ($stats['total'] ?? 0) }}</span>
      <span class="sup-kpi__label">Department tickets</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['inbox'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/dept/inbox">
      <span class="sup-kpi__value">{{ (int) ($stats['inbox'] ?? 0) }}</span>
      <span class="sup-kpi__label">Awaiting acceptance</span>
    </a>
    <a class="sup-kpi" href="/laravel/dept/active">
      <span class="sup-kpi__value">{{ (int) ($stats['active'] ?? 0) }}</span>
      <span class="sup-kpi__label">In progress</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['returned'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/dept/returned">
      <span class="sup-kpi__value">{{ (int) ($stats['returned'] ?? 0) }}</span>
      <span class="sup-kpi__label">Returned by President</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['drafts'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/dept/drafts">
      <span class="sup-kpi__value">{{ (int) ($stats['drafts'] ?? 0) }}</span>
      <span class="sup-kpi__label">Action plan drafts</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['pendingClosure'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/dept/closure">
      <span class="sup-kpi__value">{{ (int) ($stats['pendingClosure'] ?? 0) }}</span>
      <span class="sup-kpi__label">Pending closure</span>
    </a>
    <a class="sup-kpi" href="/laravel/dept/tickets">
      <span class="sup-kpi__value">{{ (int) ($stats['awaitingPresident'] ?? 0) }}</span>
      <span class="sup-kpi__label">Awaiting President</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['overdue'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/dept/overdue">
      <span class="sup-kpi__value">{{ (int) ($stats['overdue'] ?? 0) }}</span>
      <span class="sup-kpi__label">Overdue</span>
    </a>
    <a class="sup-kpi" href="/laravel/dept/tickets">
      <span class="sup-kpi__value">{{ (int) ($stats['closed'] ?? 0) }}</span>
      <span class="sup-kpi__label">Closed</span>
    </a>
  </div>

  <section class="sup-card sup-card--table">
    <div class="sup-card__head">
      <h2>Recent department tickets</h2>
      <a href="/laravel/dept/tickets" class="sup-link">View all</a>
    </div>
    <div class="table-wrap">
      <table class="data-table data-table--compact tickets-table sup-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Reporter</th>
            <th>Category</th>
            <th>Ownership</th>
            <th>Status</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recent as $t)
            <tr class="{{ !empty($t['isOverdue']) ? 'ticket-row--overdue' : '' }}">
              <td class="mono nowrap"><a href="/laravel/dept/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
              <td>{{ $t['title'] }}</td>
              <td class="nowrap">{{ $t['submittedByName'] }}</td>
              <td class="nowrap">{{ $t['categoryLabel'] }}</td>
              <td><span class="pill pill--{{ $t['ownershipTone'] }}">{{ $t['ownershipLabel'] }}</span></td>
              <td>
                <span class="pill pill--{{ !empty($t['isOverdue']) ? 'bad' : 'info' }}">{{ $t['statusLabel'] }}</span>
              </td>
              <td class="nowrap">{{ $t['updatedAt'] ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="empty">No tickets have been routed to your department yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
