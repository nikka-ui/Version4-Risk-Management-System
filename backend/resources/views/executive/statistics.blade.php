@extends('layouts.executive')

@section('content')
  @php
    $stats = $stats ?? [];
    $byStatus = $byStatus ?? [];
    arsort($byStatus);
  @endphp

  @if (!empty($flash))
    <div class="alert alert--ok" role="status">{{ $flash }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Statistics</h1>
      <p class="sup-page-desc">Organization-wide risk statistics. This view is read-only.</p>
    </div>
  </div>

  <div class="sup-kpi-grid sup-kpi-grid--levels">
    @foreach (['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'] as $id => $label)
      <a href="/executive/register?level={{ urlencode($id) }}" class="sup-kpi sup-kpi--risk sup-kpi--risk-{{ $id }}">
        <span class="sup-kpi__value">{{ (int) ($stats['byLevel'][$id] ?? 0) }}</span>
        <span class="sup-kpi__label">{{ $label }}</span>
      </a>
    @endforeach
  </div>
  <div class="sup-kpi-grid sup-kpi-grid--stats">
    <div class="sup-kpi"><span class="sup-kpi__value">{{ (int) ($stats['total'] ?? 0) }}</span><span class="sup-kpi__label">Total reports</span></div>
    <div class="sup-kpi"><span class="sup-kpi__value">{{ (int) ($stats['open'] ?? 0) }}</span><span class="sup-kpi__label">Open</span></div>
    <div class="sup-kpi"><span class="sup-kpi__value">{{ (int) ($stats['closed'] ?? 0) }}</span><span class="sup-kpi__label">Closed</span></div>
    <div class="sup-kpi"><span class="sup-kpi__value">{{ (int) ($stats['highCriticalCount'] ?? 0) }}</span><span class="sup-kpi__label">High / Critical</span></div>
  </div>

  <section class="sup-card sup-card--table">
    <div class="sup-card__head"><h2>By workflow status</h2></div>
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead><tr><th>Status</th><th>Count</th></tr></thead>
        <tbody>
          @forelse ($byStatus as $status => $count)
            <tr>
              <td><span class="pill pill--ok">{{ str_replace('_', ' ', ucfirst((string) $status)) }}</span></td>
              <td class="mono">{{ (int) $count }}</td>
            </tr>
          @empty
            <tr><td colspan="2" class="empty">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
