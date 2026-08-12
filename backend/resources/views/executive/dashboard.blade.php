@extends('layouts.executive')

@section('content')
  @php
    $stats = $stats ?? [];
    $matrix = $matrix ?? array_fill(0, 5, array_fill(0, 5, 0));
    $departments = $departments ?? [];

    $impactLabels = ['Negligible', 'Minor', 'Moderate', 'Major', 'Severe'];
    $likelihoodLabels = ['Almost certain', 'Likely', 'Possible', 'Unlikely', 'Rare'];

    $matrixTier = function (int $likelihood, int $impact): string {
      $score = $likelihood * $impact;
      if ($score <= 4) return 'low';
      if ($score <= 9) return 'moderate';
      if ($score <= 15) return 'high';
      return 'critical';
    };

    $byCategory = $stats['byCategory'] ?? [];
  @endphp

  @if (!empty($flash))
    <div class="alert alert--ok" role="status">{{ $flash }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Dashboard</h1>
      <p class="sup-page-desc">View-only oversight of organization-wide risks.</p>
    </div>
    <a href="/executive/register" class="btn-primary btn-primary--auto">Open risk register</a>
  </div>

  <div class="sup-kpi-grid sup-kpi-grid--levels">
    @foreach (['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'] as $id => $label)
      @php $count = (int) ($stats['byLevel'][$id] ?? 0); @endphp
      <a href="/executive/register?level={{ urlencode($id) }}" class="sup-kpi sup-kpi--risk sup-kpi--risk-{{ $id }}">
        <span class="sup-kpi__value">{{ $count }}</span>
        <span class="sup-kpi__label">{{ $label }}</span>
      </a>
    @endforeach
  </div>

  <div class="sup-kpi-grid sup-kpi-grid--stats">
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($stats['total'] ?? 0) }}</span>
      <span class="sup-kpi__label">Total reports</span>
    </div>
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($stats['open'] ?? 0) }}</span>
      <span class="sup-kpi__label">Open</span>
    </div>
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($stats['closed'] ?? 0) }}</span>
      <span class="sup-kpi__label">Closed</span>
    </div>
  </div>

  <div class="ticket-filters officer-quick-actions" aria-label="Quick actions">
    <a href="/executive/heatmap" class="filter-pill">Heatmap <span class="filter-pill__count">{{ (int) ($stats['total'] ?? 0) }}</span></a>
    <a href="/executive/register" class="filter-pill">Risk register <span class="filter-pill__count">{{ (int) ($stats['total'] ?? 0) }}</span></a>
    <a href="/executive/reports" class="filter-pill">Reports <span class="filter-pill__count">{{ (int) ($stats['highCriticalCount'] ?? 0) }}</span></a>
    <a href="/executive/trends" class="filter-pill">Trends</a>
    <a href="/executive/statistics" class="filter-pill">Statistics <span class="filter-pill__count">{{ (int) ($stats['open'] ?? 0) }}</span></a>
    <a href="/executive/departments" class="filter-pill">Dept performance <span class="filter-pill__count">{{ count($departments) }}</span></a>
  </div>

  <div class="exec-dash-grid">
    <section class="sup-card">
      <div class="sup-card__head">
        <h2>Organization risk matrix</h2>
        <a href="/executive/heatmap" class="sup-link">Open heatmap</a>
      </div>
      <div class="sup-card__body">
        <div class="rm-matrix" role="img" aria-label="Risk heatmap by likelihood and impact">
          <div class="rm-matrix__axis rm-matrix__axis--x">Impact →</div>
          <div class="rm-matrix__axis rm-matrix__axis--y">Likelihood →</div>
          <div class="rm-matrix__grid">
            <div class="rm-matrix__corner"></div>
            @foreach ($impactLabels as $label)
              <div class="rm-matrix__col-head">{{ $label }}</div>
            @endforeach
            @foreach ($matrix as $rowIdx => $row)
              @php $likelihood = 5 - (int) $rowIdx; @endphp
              <div class="rm-matrix__row-head">{{ $likelihoodLabels[$rowIdx] }}</div>
              @foreach ($row as $colIdx => $count)
                @php
                  $impact = (int) $colIdx + 1;
                  $tier = $matrixTier($likelihood, $impact);
                @endphp
                <div class="rm-matrix__cell rm-matrix__cell--{{ $tier }}" title="Likelihood {{ $likelihood }} × Impact {{ $impact }}">
                  <span class="rm-matrix__count">{{ $count ? $count : '' }}</span>
                </div>
              @endforeach
            @endforeach
          </div>
          <div class="rm-matrix__legend">
            <span class="rm-matrix__legend-item rm-matrix__legend-item--low">Low</span>
            <span class="rm-matrix__legend-item rm-matrix__legend-item--moderate">Moderate</span>
            <span class="rm-matrix__legend-item rm-matrix__legend-item--high">High</span>
            <span class="rm-matrix__legend-item rm-matrix__legend-item--critical">Critical</span>
          </div>
        </div>
      </div>
    </section>

    <section class="sup-card sup-card--table">
      <div class="sup-card__head"><h2>Reports by category</h2></div>
      <div class="table-wrap">
        <table class="data-table data-table--compact sup-table">
          <thead>
            <tr><th>Category</th><th>Count</th><th></th></tr>
          </thead>
          <tbody>
            @foreach (['operational' => 'Operational', 'financial' => 'Financial', 'compliance' => 'Compliance', 'strategic' => 'Strategic', 'reputational' => 'Reputational', 'environmental' => 'Environmental Risk'] as $id => $label)
              <tr>
                <td>{{ $label }}</td>
                <td class="mono">{{ (int) ($byCategory[$id] ?? 0) }}</td>
                <td><a href="/executive/register?category={{ urlencode($id) }}" class="sup-link">View in register</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection

