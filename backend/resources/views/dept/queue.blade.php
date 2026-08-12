@extends('layouts.dept')

@section('content')
  @php
    $tickets = $tickets ?? [];
    $showDueColumn = (bool) ($showDueColumn ?? false);
    $colspan = $showDueColumn ? 8 : 7;
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'not_found' => 'Ticket not found or not visible to your department.',
      default => null,
    };
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode($error) : null;
  @endphp

  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
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
            <th>Category</th>
            <th>Ownership</th>
            <th>Status</th>
            @if ($showDueColumn)<th>Target date</th>@endif
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tickets as $t)
            <tr class="{{ !empty($t['isOverdue']) ? 'ticket-row--overdue' : '' }}">
              <td class="mono nowrap"><a href="/laravel/dept/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
              <td>{{ $t['title'] }}</td>
              <td class="nowrap">{{ $t['submittedByName'] }}</td>
              <td class="nowrap">{{ $t['categoryLabel'] }}</td>
              <td><span class="pill pill--{{ $t['ownershipTone'] }}">{{ $t['ownershipLabel'] }}</span></td>
              <td>
                <span class="pill pill--{{ !empty($t['isOverdue']) ? 'bad' : 'info' }}">{{ $t['statusLabel'] }}</span>
              </td>
              @if ($showDueColumn)
                <td class="nowrap cell--overdue">
                  @if (!empty($t['dueAt']))
                    {{ \Illuminate\Support\Carbon::parse($t['dueAt'])->format('Y-m-d') }}
                    @if (!empty($t['isOverdue']))
                      <span class="pill pill--bad pill--overdue">Overdue</span>
                    @endif
                  @else
                    —
                  @endif
                </td>
              @endif
              <td class="nowrap">{{ !empty($t['updatedAt']) ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $colspan }}" class="empty">{{ $emptyMessage }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
