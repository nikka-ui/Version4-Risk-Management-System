@extends('layouts.supervisor')

@section('content')
  @php
    $tickets = $tickets ?? [];
  @endphp
  <div class="sup-page-head">
    <div>
      <h1>Action required</h1>
      <p class="sup-page-desc">Tickets awaiting implementation, revision, or accomplishment submission.</p>
    </div>
  </div>
  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            <th>Score</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tickets as $t)
            @php
              $href = !empty($t['isRevision'])
                ? '/supervisor/tickets/'.urlencode($t['reference']).'/edit'
                : '/supervisor/tickets/'.urlencode($t['reference']);
            @endphp
            <tr>
              <td class="mono nowrap"><a href="{{ $href }}">{{ $t['reference'] }}</a></td>
              <td>{{ $t['title'] }}</td>
              <td class="nowrap">{{ $t['categoryLabel'] ?? $t['category'] }}</td>
              <td>{{ $t['statusLabel'] }}</td>
              <td class="nowrap mono">{{ (int) ($t['riskScore'] ?? 0) }}</td>
              <td class="nowrap">{{ !empty($t['updatedAt']) ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="empty">No tickets require action right now.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
