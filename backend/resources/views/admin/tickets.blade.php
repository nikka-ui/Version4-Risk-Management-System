@extends('layouts.admin')

@section('content')
  @php
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'ticket_deleted' => 'Ticket soft-deleted successfully.',
      'not_found' => 'Ticket not found.',
      default => null,
    };
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode($error) : null;
    $tickets = $tickets ?? [];
    $departments = $departments ?? [];
    $filters = $filters ?? ['q' => '', 'department' => '', 'level' => '', 'status' => '', 'deleted' => false];
    $statusOptions = $statusOptions ?? [];
    $levelOptions = [
      'low' => 'Low',
      'moderate' => 'Moderate',
      'high' => 'High',
      'critical' => 'Critical',
    ];
  @endphp

  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if ($errorMsg)
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Ticket Management</h1>
      <p class="sup-page-desc">View and search all risk tickets. Administrators may delete tickets (soft delete) but cannot approve or modify the risk workflow.</p>
    </div>
  </div>

  <form method="get" action="/laravel/admin/tickets" class="admin-filter-bar">
    <input type="search" name="q" placeholder="Search tickets…" value="{{ $filters['q'] ?? '' }}" aria-label="Search tickets">
    <select name="department" aria-label="Filter by department">
      <option value="">All departments</option>
      @foreach ($departments as $dept)
        <option value="{{ $dept['name'] }}" @selected(($filters['department'] ?? '') === $dept['name'])>{{ $dept['name'] }}</option>
      @endforeach
    </select>
    <select name="level" aria-label="Filter by risk level">
      <option value="">All risk levels</option>
      @foreach ($levelOptions as $id => $label)
        <option value="{{ $id }}" @selected(($filters['level'] ?? '') === $id)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="status" aria-label="Filter by status">
      <option value="">All statuses</option>
      @foreach ($statusOptions as $id => $label)
        <option value="{{ $id }}" @selected(($filters['status'] ?? '') === $id)>{{ $label }}</option>
      @endforeach
    </select>
    <label class="admin-check-label">
      <input type="checkbox" name="deleted" value="1" @checked(!empty($filters['deleted']))> Show deleted
    </label>
    <button type="submit" class="btn-outline">Filter</button>
  </form>

  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Title</th>
            <th>Department</th>
            <th>Risk Level</th>
            <th>Status</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tickets as $t)
            <tr @class(['row--muted' => !empty($t['deleted'])])>
              <td class="mono nowrap">
                <a href="/admin/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a>
              </td>
              <td class="sup-truncate">{{ $t['title'] }}</td>
              <td>{{ $t['department'] }}</td>
              <td>
                <span class="risk-badge risk-badge--{{ $t['riskLevel'] ?? 'low' }}">{{ $t['riskLevelLabel'] ?? '—' }}</span>
              </td>
              <td><span class="status">{{ $t['statusLabel'] }}</span></td>
              <td class="nowrap">
                {{ !empty($t['updatedAt']) ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}
              </td>
              <td class="col-actions">
                @if (!empty($t['deleted']))
                  <span class="text-muted">Deleted</span>
                @else
                  <div class="admin-action-cell">
                    <a href="/admin/tickets/{{ urlencode($t['reference']) }}"
                       class="admin-icon-btn admin-icon-btn--view" title="View" aria-label="View">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                    <button type="button" class="admin-icon-btn admin-icon-btn--delete admin-ticket-delete-btn"
                      title="Delete" aria-label="Delete"
                      data-ref="{{ $t['reference'] }}" data-title="{{ $t['title'] }}">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </button>
                  </div>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty">No tickets found</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <dialog id="ticketDeleteDialog" class="admin-dialog">
    <form method="post" id="ticketDeleteForm" class="admin-dialog__form">
      <h3>Delete ticket</h3>
      <p class="sup-muted-block">You are about to soft-delete <strong id="ticketDeleteRef"></strong>. This is recorded in the audit log. Provide a reason for deletion.</p>
      <div class="field">
        <label for="ticketDeleteReason">Reason for deletion</label>
        <textarea id="ticketDeleteReason" name="reason" rows="3" required></textarea>
      </div>
      <div class="action-row">
        <button type="submit" class="btn-danger">Delete ticket</button>
        <button type="button" class="sup-btn-outline" id="ticketDeleteCancel">Cancel</button>
      </div>
    </form>
  </dialog>

  <script>
    (function () {
      var dlg = document.getElementById('ticketDeleteDialog');
      var form = document.getElementById('ticketDeleteForm');
      var refEl = document.getElementById('ticketDeleteRef');
      var reason = document.getElementById('ticketDeleteReason');
      document.querySelectorAll('.admin-ticket-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var ref = btn.getAttribute('data-ref');
          form.action = '/admin/tickets/' + encodeURIComponent(ref) + '/delete';
          refEl.textContent = ref + ' — ' + btn.getAttribute('data-title');
          reason.value = '';
          if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { form.submit(); }
        });
      });
      var cancel = document.getElementById('ticketDeleteCancel');
      if (cancel) cancel.addEventListener('click', function () { dlg.close(); });
    })();
  </script>
@endsection
