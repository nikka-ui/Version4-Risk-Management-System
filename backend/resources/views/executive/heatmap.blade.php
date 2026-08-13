@extends('layouts.executive')

@section('content')
  @php
    $matrix = $matrix ?? array_fill(0, 5, array_fill(0, 5, 0));
    $impactLabels = ['Negligible', 'Minor', 'Moderate', 'Major', 'Severe'];
    $likelihoodLabels = ['Almost certain', 'Likely', 'Possible', 'Unlikely', 'Rare'];
    $matrixTier = function (int $likelihood, int $impact): string {
      $score = $likelihood * $impact;
      if ($score <= 4) return 'low';
      if ($score <= 9) return 'moderate';
      if ($score <= 15) return 'high';
      return 'critical';
    };
  @endphp

  @if (!empty($flash))
    <div class="alert alert--ok" role="status">{{ $flash }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Organization risk matrix</h1>
      <p class="sup-page-desc">Likelihood × impact matrix showing the concentration of reported risks across the organization.</p>
    </div>
  </div>

  <section class="sup-card">
    <div class="sup-card__head"><h2>Organization risk matrix</h2></div>
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
@endsection
