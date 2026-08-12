@extends('layouts.dept')

@section('content')
  @php
    $tickets = $tickets ?? [];
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode($error) : null;
  @endphp

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
      <table class="data-table data-table--compact tickets-table sup-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Reporter</th>
            <th>Status</th>
            <th>Return reason</th>
            <th>Returned</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tickets as $t)
            <tr>
              <td class="mono nowrap"><a href="/laravel/dept/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
              <td>{{ $t['title'] }}</td>
              <td class="nowrap">{{ $t['submittedByName'] }}</td>
              <td><span class="pill pill--bad">Returned by President</span></td>
              <td>{{ $t['returnReason'] }}</td>
              <td class="nowrap">{{ !empty($t['returnedAt']) ? \Illuminate\Support\Carbon::parse($t['returnedAt'])->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="empty">{{ $emptyMessage }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
