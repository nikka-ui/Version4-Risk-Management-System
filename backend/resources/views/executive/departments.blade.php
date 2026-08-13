@extends('layouts.executive')

@section('content')
  @php $departments = $departments ?? []; @endphp

  @if (!empty($flash))
    <div class="alert alert--ok" role="status">{{ $flash }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Department Performance</h1>
      <p class="sup-page-desc">Risk report volume and outcomes by responsible department. View only — no transfer or closure actions.</p>
    </div>
  </div>

  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Department</th>
            <th>Total</th>
            <th>Open</th>
            <th>Closed</th>
            <th>High</th>
            <th>Critical</th>
            <th>Overdue</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($departments as $d)
            <tr>
              <td>{{ $d['name'] }}</td>
              <td class="mono">{{ (int) ($d['total'] ?? 0) }}</td>
              <td class="mono">{{ (int) ($d['open'] ?? 0) }}</td>
              <td class="mono">{{ (int) ($d['closed'] ?? 0) }}</td>
              <td class="mono">{{ (int) ($d['high'] ?? 0) }}</td>
              <td class="mono">{{ (int) ($d['critical'] ?? 0) }}</td>
              <td class="mono">{{ (int) ($d['overdue'] ?? 0) }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty">No department data yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
