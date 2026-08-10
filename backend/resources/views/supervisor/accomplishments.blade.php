@extends('layouts.supervisor')

@section('content')
  @php
    $flashLabels = [
      'accomplishment_submitted' => 'Accomplishment report submitted. Your department head will review and close the ticket.',
    ];
    $flashKey = is_string($flash ?? null) ? $flash : '';
    $flashMsg = $flashLabels[$flashKey] ?? null;
  @endphp
  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  <div class="sup-page-head">
    <div>
      <h1>Accomplishment history</h1>
      <p class="sup-page-desc">Reports submitted after implementing approved mitigations.</p>
    </div>
  </div>
  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Ticket</th>
            <th>Title</th>
            <th>Summary</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($accomplishments as $a)
            <tr>
              <td class="mono nowrap"><a href="/laravel/supervisor/tickets/{{ urlencode($a['ticketRef']) }}">{{ $a['ticketRef'] }}</a></td>
              <td>{{ $a['ticketTitle'] }}</td>
              <td class="sup-truncate" title="{{ $a['summary'] }}">{{ \Illuminate\Support\Str::limit($a['summary'], 80) }}</td>
              <td class="nowrap">{{ !empty($a['submittedAt']) ? \Illuminate\Support\Carbon::parse($a['submittedAt'])->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="empty">No accomplishment reports yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
