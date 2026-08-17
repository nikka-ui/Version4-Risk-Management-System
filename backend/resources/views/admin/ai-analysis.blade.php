@extends('layouts.admin')

@section('content')
  @php
    $flashMsg = is_string($flash ?? null) ? (string) $flash : '';
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode((string) $error) : '';
    $filters = $filters ?? ['q' => '', 'source' => '', 'category' => '', 'ticket' => ''];
    $options = $options ?? ['sources' => [], 'categories' => []];
    $runs = $runs ?? [];
  @endphp

  @if (!empty($flashMsg))
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if (!empty($errorMsg))
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>AI Analysis History</h1>
      <p class="sup-page-desc">Classify runs stored in Postgres. Live ticket display still uses the latest <code>ticket.ai</code> payload.</p>
    </div>
  </div>

  <form method="get" action="/admin/ai-analysis" class="admin-filter-bar">
    <input type="search" name="q" placeholder="Search ticket, source, category…" value="{{ $filters['q'] ?? '' }}">
    <input type="text" name="ticket" placeholder="Ticket reference" value="{{ $filters['ticket'] ?? '' }}" aria-label="Filter by ticket">
    <select name="source" aria-label="Filter by source">
      <option value="">All sources</option>
      @foreach (($options['sources'] ?? []) as $s)
        <option value="{{ $s }}" @selected(($filters['source'] ?? '') === $s)>{{ $s }}</option>
      @endforeach
    </select>
    <select name="category" aria-label="Filter by category">
      <option value="">All categories</option>
      @foreach (($options['categories'] ?? []) as $c)
        <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ $c }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn-outline">Filter</button>
    <a href="/admin/ai-analysis" class="btn-outline">Reset</a>
  </form>

  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Ticket</th>
            <th>Source</th>
            <th>Category</th>
            <th>L / I</th>
            <th>Priority</th>
            <th>Department</th>
            <th>Confidence</th>
            <th>Summary</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($runs as $run)
            <tr>
              <td class="nowrap">{{ !empty($run['createdAt']) ? \Illuminate\Support\Carbon::parse($run['createdAt'])->format('Y-m-d H:i') : '—' }}</td>
              <td class="mono">
                @if (!empty($run['ticketReference']))
                  <a href="/admin/tickets/{{ $run['ticketReference'] }}">{{ $run['ticketReference'] }}</a>
                @else
                  —
                @endif
              </td>
              <td><span class="pill">{{ $run['source'] ?? '—' }}</span></td>
              <td>{{ $run['riskCategory'] ?? '—' }}</td>
              <td class="nowrap">{{ $run['likelihood'] ?? '—' }} / {{ $run['impact'] ?? '—' }}</td>
              <td>{{ $run['priority'] ?? '—' }}</td>
              <td>{{ $run['responsibleDepartment'] ?? '—' }}</td>
              <td>{{ isset($run['confidence']) ? number_format((float) $run['confidence'] * 100, 0).'%' : '—' }}</td>
              <td class="sup-truncate">{{ $run['summary'] ?? '' }}</td>
            </tr>
          @empty
            <tr><td colspan="9" class="empty">No AI analysis runs</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
