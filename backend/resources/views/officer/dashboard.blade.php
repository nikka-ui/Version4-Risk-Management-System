@extends('layouts.officer')

@section('content')
  @php
    $stats = $stats ?? [];
    $departments = $departments ?? [];
    $matrix = $matrix ?? array_fill(0, 5, array_fill(0, 5, 0));
    $impactLabels = ['Negligible', 'Minor', 'Moderate', 'Major', 'Severe'];
    $likelihoodLabels = ['Almost certain', 'Likely', 'Possible', 'Unlikely', 'Rare'];
    $palette = ['#B7DBE1', '#FFEFAD', '#FFADC0', '#EEF6F8'];
    $matrixTier = function (int $likelihood, int $impact): string {
      $score = $likelihood * $impact;
      if ($score <= 4) return 'low';
      if ($score <= 9) return 'moderate';
      if ($score <= 15) return 'high';
      return 'critical';
    };
  @endphp

  <div class="sup-page-head">
    <div>
      <h1>Dashboard</h1>
      <p class="sup-page-desc">Welcome, Risk Management Officer — view organizational risks, monitor SLA compliance, and participate in ticket discussion threads. The RMO does not own or edit tickets.</p>
    </div>
    <a href="/laravel/officer/overdue" class="filter-pill filter-pill--head">Overdue <span class="filter-pill__count">{{ (int) ($stats['overdueMitigation'] ?? 0) }}</span></a>
  </div>

  <div class="sup-kpi-grid sup-kpi-grid--officer">
    <a class="sup-kpi sup-kpi--accent" href="/laravel/officer/tickets">
      <span class="sup-kpi__value">{{ (int) ($stats['total'] ?? 0) }}</span>
      <span class="sup-kpi__label">Risk register</span>
    </a>
    <a class="sup-kpi" href="/laravel/officer/monitoring">
      <span class="sup-kpi__value">{{ (int) ($stats['open'] ?? 0) }}</span>
      <span class="sup-kpi__label">Open risks</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['overdueMitigation'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/officer/overdue">
      <span class="sup-kpi__value">{{ (int) ($stats['overdueMitigation'] ?? 0) }}</span>
      <span class="sup-kpi__label">Overdue / SLA</span>
    </a>
    <a class="sup-kpi" href="/laravel/officer/action-plans">
      <span class="sup-kpi__value">{{ (int) ($stats['awaitingFinalValidation'] ?? 0) }}</span>
      <span class="sup-kpi__label">Action plans</span>
    </a>
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($stats['complianceOpen'] ?? 0) }}</span>
      <span class="sup-kpi__label">Compliance risks</span>
    </div>
  </div>

  <div class="ticket-filters officer-quick-actions" aria-label="Quick actions">
    <a href="/laravel/officer/tickets" class="filter-pill">Risk register <span class="filter-pill__count">{{ (int) ($stats['total'] ?? 0) }}</span></a>
    <a href="/laravel/officer/overdue" class="filter-pill">Overdue &amp; SLA <span class="filter-pill__count">{{ (int) ($stats['overdueMitigation'] ?? 0) }}</span></a>
    <a href="/laravel/officer/action-plans" class="filter-pill">Action plans <span class="filter-pill__count">{{ (int) ($stats['awaitingFinalValidation'] ?? 0) }}</span></a>
    <a href="/laravel/officer/monitoring" class="filter-pill">Active monitoring <span class="filter-pill__count">{{ (int) ($stats['inMitigation'] ?? 0) }}</span></a>
  </div>

  <div class="officer-dash-grid">
    <section class="sup-card">
      <div class="sup-card__head">
        <h2>Risks by department</h2>
        <a href="/laravel/officer/tickets" class="sup-link">View register</a>
      </div>
      <div class="sup-card__body">
        @if (count($departments) === 0)
          <p class="sup-empty">No risk reports submitted yet.</p>
        @else
          <div class="officer-dept-grid">
            @foreach ($departments as $i => $d)
              <div class="officer-dept-tile">
                <span class="officer-dept-tile__icon" style="--dept-color:{{ $palette[$i % count($palette)] }}">{{ mb_strtoupper(mb_substr(trim($d['name']), 0, 1)) }}</span>
                <div class="officer-dept-tile__meta">
                  <span class="officer-dept-tile__name">{{ $d['name'] }}</span>
                  <span class="officer-dept-tile__count">{{ $d['count'] }} report{{ $d['count'] === 1 ? '' : 's' }}</span>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </section>

    <section class="sup-card">
      <div class="sup-card__head">
        <h2>Organization risk matrix</h2>
      </div>
      <div class="sup-card__body">
        <div class="rm-matrix" role="img" aria-label="Risk incident matrix by likelihood and impact">
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
  </div>
@endsection
