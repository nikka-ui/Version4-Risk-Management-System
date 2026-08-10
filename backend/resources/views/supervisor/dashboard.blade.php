@extends('layouts.supervisor')

@section('content')
  @php
    $stats = $stats ?? [];
    $recent = $recent ?? [];
    $showDue = ((int) ($stats['overdue'] ?? 0) > 0) || collect($recent)->contains(fn ($t) => !empty($t['dueAt']));
    $colspan = $showDue ? 7 : 6;
  @endphp
  <div class="sup-page-head">
    <div>
      <h1>Dashboard</h1>
      <p class="sup-page-desc">Report organizational risks, track AI-routed tickets, and monitor status from submission through closure.</p>
    </div>
    <a href="/laravel/supervisor/tickets/new" class="sup-btn-primary">+ Create new ticket</a>
  </div>
  <div class="routing-flow-banner" role="note">
    <strong>Automatic routing:</strong> Submit your report → AI analyzes <strong>incident details</strong> (what / why / where / how) → Responsible department is assigned. Your reporting unit does not affect assignment.
  </div>
  <div class="sup-kpi-grid">
    <a class="sup-kpi" href="/laravel/supervisor/tickets"><span class="sup-kpi__value">{{ (int) ($stats['total'] ?? 0) }}</span><span class="sup-kpi__label">My tickets</span></a>
    <a class="sup-kpi" href="/laravel/supervisor/drafts"><span class="sup-kpi__value">{{ (int) ($stats['drafts'] ?? 0) }}</span><span class="sup-kpi__label">Draft reports</span></a>
    <a class="sup-kpi" href="/laravel/supervisor/submitted"><span class="sup-kpi__value">{{ (int) ($stats['submitted'] ?? 0) }}</span><span class="sup-kpi__label">Submitted reports</span></a>
    <a class="sup-kpi{{ ((int) ($stats['returned'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/supervisor/returned"><span class="sup-kpi__value">{{ (int) ($stats['returned'] ?? 0) }}</span><span class="sup-kpi__label">Returned reports</span></a>
    <a class="sup-kpi{{ ((int) ($stats['overdue'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/supervisor/overdue"><span class="sup-kpi__value">{{ (int) ($stats['overdue'] ?? 0) }}</span><span class="sup-kpi__label">Overdue</span></a>
    <a class="sup-kpi" href="/laravel/supervisor/tickets?filter=closed"><span class="sup-kpi__value">{{ (int) ($stats['closed'] ?? 0) }}</span><span class="sup-kpi__label">Closed</span></a>
    <a class="sup-kpi" href="/laravel/supervisor/accomplishments"><span class="sup-kpi__value">{{ (int) ($stats['accomplishments'] ?? 0) }}</span><span class="sup-kpi__label">Accomplishments</span></a>
  </div>
  <section class="sup-card sup-card--table">
    <div class="sup-card__head">
      <h2>Recent tickets</h2>
      <a href="/laravel/supervisor/tickets" class="sup-link">View all</a>
    </div>
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            @if ($showDue)<th>Due date</th>@endif
            <th>Files</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recent as $t)
            <tr>
              <td class="mono"><a href="/laravel/supervisor/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
              <td>{{ $t['title'] }}</td>
              <td>{{ $t['category'] }}</td>
              <td>{{ $t['statusLabel'] }}</td>
              @if ($showDue)
                <td class="nowrap">{{ $t['dueAt'] ? \Illuminate\Support\Carbon::parse($t['dueAt'])->format('Y-m-d') : '—' }}</td>
              @endif
              <td>{{ (int) $t['evidenceCount'] }}</td>
              <td class="nowrap">{{ $t['updatedAt'] ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $colspan }}" class="empty">No tickets yet. <a href="/laravel/supervisor/tickets/new">Create your first report</a>.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
