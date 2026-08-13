@extends('layouts.executive')

@section('content')
  @php
    $stats = $stats ?? [];
    $categories = $categories ?? [];
    $highCriticalTickets = $highCriticalTickets ?? [];
    $levelLabels = ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Extreme/Critical'];
  @endphp

  @if (!empty($flash))
    <div class="alert alert--ok" role="status">{{ $flash }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Reports</h1>
      <p class="sup-page-desc">Summary reports by risk level and category. High and Critical items are prioritized for executive oversight.</p>
    </div>
  </div>

  <div class="sup-detail-stack">
    <section class="sup-card sup-card--table">
      <div class="sup-card__head"><h2>By risk level</h2></div>
      <div class="table-wrap">
        <table class="data-table data-table--compact sup-table">
          <thead><tr><th>Level</th><th>Count</th><th></th></tr></thead>
          <tbody>
            @foreach ($levelLabels as $id => $label)
              <tr>
                <td><span class="risk-badge risk-badge--{{ $id }}">{{ $label }}</span></td>
                <td class="mono">{{ (int) ($stats['byLevel'][$id] ?? 0) }}</td>
                <td><a href="/executive/register?level={{ urlencode($id) }}" class="sup-link">View</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
    <section class="sup-card sup-card--table">
      <div class="sup-card__head"><h2>By category</h2></div>
      <div class="table-wrap">
        <table class="data-table data-table--compact sup-table">
          <thead><tr><th>Category</th><th>Count</th><th></th></tr></thead>
          <tbody>
            @foreach ($categories as $id => $label)
              <tr>
                <td>{{ $label }}</td>
                <td class="mono">{{ (int) ($stats['byCategory'][$id] ?? 0) }}</td>
                <td><a href="/executive/register?category={{ urlencode($id) }}" class="sup-link">View</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <section class="sup-card sup-card--table">
    <div class="sup-card__head">
      <h2>Recent High &amp; Critical reports</h2>
      <a href="/executive/register?level=high" class="sup-link">View all</a>
    </div>
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Reference</th><th>Title</th><th>Level</th><th>Category</th><th>Department</th><th>Status</th><th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($highCriticalTickets as $t)
            @include('executive.partials.ticket-row', ['t' => $t])
          @empty
            <tr><td colspan="7" class="empty">No high or critical risk reports.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
