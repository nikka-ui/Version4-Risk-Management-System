@extends('layouts.executive')

@section('content')
  @php
    $trends = $trends ?? [];
    $max = max(1, ...array_map(fn ($m) => (int) ($m['count'] ?? 0), $trends ?: [['count' => 0]]));
  @endphp

  @if (!empty($flash))
    <div class="alert alert--ok" role="status">{{ $flash }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Trends</h1>
      <p class="sup-page-desc">Monthly volume of submitted risk reports over the last 12 months. High/Critical share is highlighted.</p>
    </div>
  </div>

  <section class="sup-card">
    <div class="sup-card__head"><h2>Report volume trend</h2></div>
    <div class="sup-card__body">
      <div class="exec-trend-chart" role="img" aria-label="Monthly risk report trends">
        <div class="exec-trend-chart__bars">
          @foreach ($trends as $m)
            @php
              $count = (int) ($m['count'] ?? 0);
              $hc = (int) ($m['highCritical'] ?? 0);
              $height = $count ? (int) round(($count / $max) * 100) : 0;
              $hcHeight = $count ? (int) round(($hc / $count) * $height) : 0;
              $otherHeight = max(0, $height - $hcHeight);
            @endphp
            <div class="exec-trend-bar" title="{{ $m['label'] ?? '' }}: {{ $count }} total, {{ $hc }} high/critical">
              <div class="exec-trend-bar__stack" style="height: {{ $height }}%">
                <span class="exec-trend-bar__segment exec-trend-bar__segment--hc" style="height: {{ $hcHeight }}%"></span>
                <span class="exec-trend-bar__segment exec-trend-bar__segment--other" style="height: {{ $otherHeight }}%"></span>
              </div>
              <span class="exec-trend-bar__label">{{ $m['label'] ?? '' }}</span>
              <span class="exec-trend-bar__value">{{ $count }}</span>
            </div>
          @endforeach
        </div>
        <div class="exec-trend-chart__legend">
          <span class="exec-trend-chart__legend-item exec-trend-chart__legend-item--hc">High / Critical</span>
          <span class="exec-trend-chart__legend-item exec-trend-chart__legend-item--other">Other levels</span>
        </div>
      </div>
    </div>
  </section>
@endsection
