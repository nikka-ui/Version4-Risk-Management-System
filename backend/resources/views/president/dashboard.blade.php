@extends('layouts.president')

@section('content')
  @php
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'notifications_read' => 'All notifications marked as read.',
      default => is_string($flash ?? null) && $flash !== '' ? $flash : null,
    };
  @endphp

  @if (!empty($flashMsg))
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>President dashboard</h1>
      <p class="sup-page-desc">Final approving authority for High and Critical risks — review and approve department action plans, or return them for revision.</p>
    </div>
    @if ($pendingCount > 0)
      <a href="/president/pending" class="btn-primary btn-primary--auto">Review pending decisions</a>
    @endif
  </div>

  <div class="sup-kpi-grid sup-kpi-grid--levels">
    @foreach (['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'] as $id => $label)
      @php $count = (int) ($org['byLevel'][$id] ?? 0); @endphp
      <a href="/executive/register?level={{ urlencode($id) }}" class="sup-kpi sup-kpi--risk sup-kpi--risk-{{ $id }}">
        <span class="sup-kpi__value">{{ $count }}</span>
        <span class="sup-kpi__label">{{ $label }}</span>
      </a>
    @endforeach
  </div>

  <div class="sup-kpi-grid sup-kpi-grid--stats">
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($stats['total'] ?? 0) }}</span>
      <span class="sup-kpi__label">Total reports (High/Critical)</span>
    </div>
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($stats['open'] ?? 0) }}</span>
      <span class="sup-kpi__label">Open</span>
    </div>
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($stats['closed'] ?? 0) }}</span>
      <span class="sup-kpi__label">Closed</span>
    </div>
    <div class="sup-kpi">
      <span class="sup-kpi__value">{{ (int) ($pendingCount ?? 0) }}</span>
      <span class="sup-kpi__label">Pending decisions</span>
    </div>
  </div>

  <div class="ticket-filters officer-quick-actions" aria-label="Quick actions">
    <a href="/president/pending" class="filter-pill">Pending decisions <span class="filter-pill__count">{{ $pendingCount }}</span></a>
    <a href="/president/high" class="filter-pill">High risks <span class="filter-pill__count">{{ (int) ($stats['highCount'] ?? 0) }}</span></a>
    <a href="/president/critical" class="filter-pill">Critical risks <span class="filter-pill__count">{{ (int) ($stats['criticalCount'] ?? 0) }}</span></a>
    <a href="/president/trends" class="filter-pill">Trends</a>
  </div>

  <div class="exec-dash-grid">
    <section class="sup-card">
      <div class="sup-card__head">
        <h2>Organization risk matrix</h2>
      </div>
      <div class="sup-card__body">
        {{-- Reuse existing heatmap markup used by the Executive console --}}
        <div class="rm-matrix" role="img" aria-label="Risk heatmap by likelihood and impact">
          <div class="rm-matrix__axis rm-matrix__axis--x">Impact →</div>
          <div class="rm-matrix__axis rm-matrix__axis--y">Likelihood →</div>
          <div class="rm-matrix__grid">
            <div class="rm-matrix__corner"></div>
            @php
              $impactLabels = ['Negligible', 'Minor', 'Moderate', 'Major', 'Severe'];
              $likelihoodLabels = ['Almost certain', 'Likely', 'Possible', 'Unlikely', 'Rare'];
            @endphp
            @foreach ($impactLabels as $label)
              <div class="rm-matrix__col-head">{{ $label }}</div>
            @endforeach
            @foreach ($matrix as $rowIdx => $row)
              @php $likelihood = 5 - (int) $rowIdx; @endphp
              <div class="rm-matrix__row-head">{{ $likelihoodLabels[$rowIdx] ?? '' }}</div>
              @foreach ($row as $colIdx => $count)
                @php $impact = (int) $colIdx + 1; @endphp
                @php
                  $score = (int) $likelihood * (int) $impact;
                  $tier = $score <= 4 ? 'low' : ($score <= 9 ? 'moderate' : ($score <= 15 ? 'high' : 'critical'));
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

    @if ($pendingCount > 0)
      <section class="sup-card sup-card--table">
        <div class="sup-card__head">
          <h2>Pending decisions</h2>
        </div>
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
              @foreach ($pendingTickets as $t)
                @php
                  $level = $t['level'] ?? 'high';
                  $isOverdue = (bool) ($t['isOverdue'] ?? false);
                  $tone = $isOverdue ? 'bad' : 'ok';
                @endphp
                <tr{{ $level === 'critical' ? ' class="row--critical"' : '' }}>
                  <td class="mono nowrap"><a href="/president/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
                  <td class="sup-truncate">{{ $t['title'] }}</td>
                  <td class="nowrap">
                    <span class="risk-badge risk-badge--{{ $level }}">{{ $level === 'critical' ? 'Critical' : 'High' }}</span>
                  </td>
                  <td class="nowrap">{{ $t['category'] }}</td>
                  <td class="nowrap">{{ $t['department'] }}</td>
                  <td>
                    <span class="pill pill--{{ $tone }}">{{ $t['statusLabel'] }}</span>
                  </td>
                  <td class="nowrap">{{ $t['updatedAt'] ? $t['updatedAt'] : '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @else
      <section class="sup-card sup-card--critical-empty">
        <div class="sup-card__head"><h2>Pending decisions</h2></div>
        <div class="sup-card__body">
          <p class="sup-muted-block">No High or Critical risk tickets are awaiting your decision.</p>
        </div>
      </section>
    @endif
  </div>
@endsection

