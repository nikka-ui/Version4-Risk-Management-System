@extends('layouts.compliance')

@section('content')
  <div class="sup-page-head">
    <div>
      <h1>Compliance validation</h1>
      <p class="sup-page-desc">Validate or return High/Critical accomplishment reports. You cannot approve, close, or modify ownership.</p>
    </div>
  </div>

  <section class="sup-card">
    <h2>Awaiting validation ({{ count($tickets) }})</h2>
    @if (count($tickets) === 0)
      <p class="sup-muted-block">No tickets currently in <code>under_audit</code>.</p>
    @else
      <table class="data-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Department</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($tickets as $row)
            <tr>
              <td class="mono">{{ $row['reference'] }}</td>
              <td>{{ $row['title'] }}</td>
              <td>{{ $row['department'] }}</td>
              <td><a href="/compliance/tickets/{{ urlencode($row['reference']) }}">Open</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </section>
@endsection
