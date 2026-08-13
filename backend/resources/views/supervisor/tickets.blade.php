@extends('layouts.supervisor')

@section('content')
  @php
    $filter = $filter ?? '';
    $counts = $counts ?? [];
    $tickets = $tickets ?? [];
    $showDueColumn = (bool) ($showDueColumn ?? false);
    $colspan = 7 + ($showDueColumn ? 1 : 0);
  @endphp
  @php
    $flashLabels = [
      'draft_deleted' => 'Draft deleted.',
      'draft_saved' => 'Draft saved.',
      'not_found' => 'Ticket not found.',
    ];
    $flashKey = is_string($flash ?? null) ? $flash : '';
    $flashMsg = $flashLabels[$flashKey] ?? null;
  @endphp
  @if (!empty($error))
    <div class="alert" role="alert">{{ urldecode((string) $error) }}</div>
  @endif
  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  <div class="sup-page-head">
    <div>
      <h1>{{ $title }}</h1>
      <p class="sup-page-desc">{{ $pageDesc }}</p>
    </div>
    <a href="/supervisor/tickets/new" class="sup-btn-primary">+ Create new ticket</a>
  </div>
  <div class="ticket-filters console-quick-actions">
    <a href="/supervisor/tickets" class="filter-pill{{ $filter === '' ? ' active' : '' }}">All <span class="filter-pill__count">{{ (int) ($counts['all'] ?? 0) }}</span></a>
    <a href="/supervisor/tickets?filter=draft" class="filter-pill{{ $filter === 'draft' ? ' active' : '' }}">Drafts <span class="filter-pill__count">{{ (int) ($counts['draft'] ?? 0) }}</span></a>
    <a href="/supervisor/tickets?filter=returned" class="filter-pill{{ $filter === 'returned' ? ' active' : '' }}">Returned reports <span class="filter-pill__count">{{ (int) ($counts['returned'] ?? 0) }}</span></a>
    <a href="/supervisor/overdue" class="filter-pill filter-pill--warn{{ $filter === 'overdue' ? ' active' : '' }}">Overdue <span class="filter-pill__count">{{ (int) ($counts['overdue'] ?? 0) }}</span></a>
    <a href="/supervisor/tickets?filter=submitted" class="filter-pill{{ $filter === 'submitted' ? ' active' : '' }}">Submitted <span class="filter-pill__count">{{ (int) ($counts['submitted'] ?? 0) }}</span></a>
    <a href="/supervisor/tickets?filter=closed" class="filter-pill{{ $filter === 'closed' ? ' active' : '' }}">Closed <span class="filter-pill__count">{{ (int) ($counts['closed'] ?? 0) }}</span></a>
  </div>
  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table tickets-table tickets-table--crud">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            @if ($showDueColumn)<th>Due date</th>@endif
            <th>Files</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tickets as $t)
            <tr class="{{ !empty($t['isOverdue']) ? 'ticket-row--overdue' : '' }}">
              <td class="mono nowrap"><a href="/supervisor/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
              <td>{{ $t['title'] }}</td>
              <td class="nowrap">{{ $t['categoryLabel'] ?? $t['category'] }}</td>
              <td>{{ $t['statusLabel'] }}</td>
              @if ($showDueColumn)
                <td class="nowrap">{{ !empty($t['dueAt']) ? \Illuminate\Support\Carbon::parse($t['dueAt'])->format('Y-m-d') : '—' }}</td>
              @endif
              <td class="nowrap">{{ (int) ($t['evidenceCount'] ?? 0) }}</td>
              <td class="nowrap">{{ !empty($t['updatedAt']) ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</td>
              <td class="col-actions">
                @if (!empty($t['canDelete']))
                  <div class="ticket-actions">
                    <a href="/supervisor/tickets/{{ urlencode($t['reference']) }}/edit" class="btn-link">Edit</a>
                    <form method="post" action="/supervisor/tickets/{{ urlencode($t['reference']) }}/delete" class="inline-form"
                      onsubmit="return confirm('Delete draft {{ $t['reference'] }}? This cannot be undone.');">
                      @csrf
                      <button type="submit" class="btn-link btn-link--danger">Delete</button>
                    </form>
                  </div>
                @elseif (!empty($t['isRevision']))
                  <a href="/supervisor/tickets/{{ urlencode($t['reference']) }}/edit" class="btn-link">Revise</a>
                @else
                  <a href="/supervisor/tickets/{{ urlencode($t['reference']) }}" class="btn-link">View</a>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $colspan }}" class="empty">
                @if ($filter === 'overdue')
                  No overdue tickets.
                @else
                  No tickets yet. <a href="/supervisor/tickets/new">Create your first risk report</a>.
                @endif
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
