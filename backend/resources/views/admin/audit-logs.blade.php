@extends('layouts.admin')

@section('content')
  @php
    $flashMsg = is_string($flash ?? null) ? (string) $flash : '';
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode((string) $error) : '';
    $filters = $filters ?? ['q' => '', 'date' => '', 'user' => '', 'action' => '', 'module' => ''];
    $options = $options ?? ['users' => [], 'actions' => [], 'modules' => []];

    $exportParams = array_filter([
      'q' => $filters['q'] ?? '',
      'date' => $filters['date'] ?? '',
      'user' => $filters['user'] ?? '',
      'action' => $filters['action'] ?? '',
      'module' => $filters['module'] ?? '',
    ], fn ($v) => $v !== '');
    $exportQuery = http_build_query($exportParams);
    $exportHref = '/admin/audit-logs/export?format=csv' . ($exportQuery ? '&' . $exportQuery : '');
  @endphp

  @if (!empty($flashMsg))
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if (!empty($errorMsg))
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Audit Logs</h1>
      <p class="sup-page-desc">Complete audit trail of administrator and system actions.</p>
    </div>
    <div class="admin-export-actions">
      <a href="{{ $exportHref }}" class="sup-btn-outline">Export CSV</a>
      <button type="button" class="sup-btn-outline" onclick="window.print()">Print Logs</button>
    </div>
  </div>

  <form method="get" action="/admin/audit-logs" class="admin-filter-bar">
    <input type="search" name="q" placeholder="Search logs…" value="{{ $filters['q'] ?? '' }}">
    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" aria-label="Filter by date">
    <select name="user" aria-label="Filter by user">
      <option value="">All users</option>
      @foreach (($options['users'] ?? []) as $u)
        <option value="{{ $u }}" @selected(($filters['user'] ?? '') === $u)>{{ $u }}</option>
      @endforeach
    </select>
    <input type="text" name="action" placeholder="Action" value="{{ $filters['action'] ?? '' }}" aria-label="Filter by action">
    <select name="module" aria-label="Filter by module">
      <option value="">All modules</option>
      @foreach (($options['modules'] ?? []) as $m)
        <option value="{{ $m }}" @selected(($filters['module'] ?? '') === $m)>{{ $m }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn-outline">Filter</button>
    <a href="/admin/audit-logs" class="btn-outline">Reset</a>
  </form>

  <section class="sup-card sup-card--table admin-printable">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>User</th>
            <th>Role</th>
            <th>Action</th>
            <th>Module</th>
            <th>Description</th>
            <th>IP</th>
            <th>Device</th>
            <th>Browser</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($logs as $l)
            <tr>
              <td class="nowrap">{{ !empty($l['at']) ? \Illuminate\Support\Carbon::parse($l['at'])->format('Y-m-d H:i') : '—' }}</td>
              <td>{{ $l['username'] ?? '—' }}</td>
              <td>{{ $l['roleLabel'] ?? $l['role'] ?? '—' }}</td>
              <td><span class="pill">{{ \App\Support\AuditActions::label($l['action'] ?? '') }}</span></td>
              <td>{{ $l['module'] ?? '—' }}</td>
              <td class="sup-truncate">{{ $l['description'] ?? '' }}</td>
              <td class="mono">{{ $l['ip'] ?? '—' }}</td>
              <td>{{ $l['device'] ?? '—' }}</td>
              <td>{{ $l['browser'] ?? '—' }}</td>
              <td>
                @php
                  $detailJson = htmlspecialchars(json_encode($l, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                @endphp
                <button type="button" class="btn-link admin-log-detail-btn" data-detail="{{ $detailJson }}">
                  View Details
                </button>
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="empty">No audit logs</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <dialog id="auditDetailDialog" class="admin-dialog">
    <div class="admin-dialog__body"></div>
    <form method="dialog">
      <button class="sup-btn-outline">Close</button>
    </form>
  </dialog>

  <script>
    document.querySelectorAll('.admin-log-detail-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var data = JSON.parse(btn.getAttribute('data-detail'));
        var dlg = document.getElementById('auditDetailDialog');
        dlg.querySelector('.admin-dialog__body').replaceChildren();
        var h3 = document.createElement('h3');
        h3.textContent = 'Audit log details';
        var dl = document.createElement('dl');
        dl.className = 'detail-dl';
        function addRow(label, value) {
          var dt = document.createElement('dt');
          dt.textContent = label;
          var dd = document.createElement('dd');
          dd.textContent = value || '';
          dl.appendChild(dt);
          dl.appendChild(dd);
        }
        addRow('ID', data.id);
        addRow('Time', data.at);
        addRow('User', data.username);
        addRow('Action', data.action);
        addRow('Description', data.description);
        dlg.querySelector('.admin-dialog__body').appendChild(h3);
        dlg.querySelector('.admin-dialog__body').appendChild(dl);
        dlg.showModal();
      });
    });
  </script>
@endsection

