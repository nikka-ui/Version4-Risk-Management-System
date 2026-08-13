@extends('layouts.president')

@section('content')
  @php
    $tickets = $tickets ?? [];
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'not_found' => 'Ticket not found.',
      default => is_string($flash ?? null) && $flash !== '' ? $flash : null,
    };
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode($error) : null;
  @endphp

  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if ($errorMsg)
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>{{ $title }}</h1>
      <p class="sup-page-desc">{{ $pageDesc }}</p>
    </div>
  </div>

  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Level</th>
            <th>Category</th>
            <th>Department</th>
            <th>Status</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tickets as $t)
            @php
              $level = $t['level'] ?? 'high';
              $isOverdue = (bool) ($t['isOverdue'] ?? false);
              $tone = $isOverdue ? 'bad' : 'ok';
            @endphp
            <tr{{ $level === 'critical' ? ' class="row--critical"' : '' }}>
              <td class="mono nowrap"><a href="/president/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
              <td class="sup-truncate">{{ $t['title'] }}</td>
              <td class="nowrap">
                <span class="risk-badge risk-badge--{{ $level }}">{{ $t['riskLevelLabel'] ?? ucfirst($level) }}</span>
              </td>
              <td class="nowrap">{{ $t['categoryLabel'] ?? $t['category'] }}</td>
              <td class="nowrap">{{ $t['department'] }}</td>
              <td>
                <span class="pill pill--{{ $tone }}">{{ $t['statusLabel'] }}</span>
              </td>
              <td class="nowrap">{{ !empty($t['updatedAt']) ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="empty">{{ $emptyMessage }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
