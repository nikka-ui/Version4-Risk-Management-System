@extends('layouts.admin')

@section('content')
  @php
    $stats = $stats ?? [];
    $recentUsers = $recentUsers ?? [];
    $deletedTickets = $deletedTickets ?? [];
    $auditLogs = $auditLogs ?? [];
  @endphp
  <div class="sup-page-head">
    <div>
      <h1>Dashboard</h1>
      <p class="sup-page-desc">System administration overview for the AI-Assisted ISO 31000 Risk Management platform.</p>
    </div>
  </div>
  <div class="sup-kpi-grid sup-kpi-grid--stats">
    <a class="sup-kpi" href="/laravel/admin/users">
      <span class="sup-kpi__value">{{ (int) ($stats['totalUsers'] ?? 0) }}</span>
      <span class="sup-kpi__label">Total Users</span>
    </a>
    <a class="sup-kpi" href="/laravel/admin/users?filter=active">
      <span class="sup-kpi__value">{{ (int) ($stats['activeUsers'] ?? 0) }}</span>
      <span class="sup-kpi__label">Active Users</span>
    </a>
    <a class="sup-kpi" href="/laravel/admin/departments">
      <span class="sup-kpi__value">{{ (int) ($stats['departments'] ?? 0) }}</span>
      <span class="sup-kpi__label">Departments</span>
    </a>
    <a class="sup-kpi" href="/laravel/admin/tickets?status=open">
      <span class="sup-kpi__value">{{ (int) ($stats['openTickets'] ?? 0) }}</span>
      <span class="sup-kpi__label">Open Tickets</span>
    </a>
    <a class="sup-kpi" href="/laravel/admin/tickets?status=closed">
      <span class="sup-kpi__value">{{ (int) ($stats['closedTickets'] ?? 0) }}</span>
      <span class="sup-kpi__label">Closed Tickets</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['highRiskTickets'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/admin/tickets?level=high">
      <span class="sup-kpi__value">{{ (int) ($stats['highRiskTickets'] ?? 0) }}</span>
      <span class="sup-kpi__label">High Risk</span>
    </a>
    <a class="sup-kpi{{ ((int) ($stats['criticalRiskTickets'] ?? 0) > 0) ? ' sup-kpi--warn' : '' }}" href="/laravel/admin/tickets?level=critical">
      <span class="sup-kpi__value">{{ (int) ($stats['criticalRiskTickets'] ?? 0) }}</span>
      <span class="sup-kpi__label">Critical Risk</span>
    </a>
  </div>
  <div class="sup-quick-actions">
    <a href="/laravel/admin/users?action=add" class="sup-quick-actions__link">Add User</a>
    <a href="/laravel/admin/departments?action=add" class="sup-quick-actions__link">Add Department</a>
    <a href="/admin/audit-logs" class="sup-quick-actions__link">View Audit Logs</a>
    <a href="/laravel/admin/tickets" class="sup-quick-actions__link">Manage Tickets</a>
  </div>
  <div class="admin-dash-grid">
    <section class="sup-card sup-card--table">
      <div class="sup-card__head">
        <h2>Newly created users</h2>
        <a href="/laravel/admin/users" class="sup-link">View all</a>
      </div>
      <div class="table-wrap">
        <table class="data-table data-table--compact sup-table">
          <thead>
            <tr>
              <th>Employee ID</th>
              <th>Name</th>
              <th>Role</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($recentUsers as $u)
              <tr>
                <td class="mono">{{ $u['employeeId'] ?: '—' }}</td>
                <td>{{ $u['displayName'] }}</td>
                <td>{{ $u['roleLabel'] }}</td>
                <td class="nowrap">{{ !empty($u['createdAt']) ? \Illuminate\Support\Carbon::parse($u['createdAt'])->format('Y-m-d H:i') : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="empty">No recent users</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <section class="sup-card sup-card--table">
      <div class="sup-card__head">
        <h2>Recently deleted tickets</h2>
        <a href="/laravel/admin/tickets?deleted=1" class="sup-link">View all</a>
      </div>
      <div class="table-wrap">
        <table class="data-table data-table--compact sup-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Title</th>
              <th>Deleted by</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($deletedTickets as $d)
              <tr>
                <td class="mono">{{ $d['ticketRef'] }}</td>
                <td>{{ $d['title'] }}</td>
                <td>{{ $d['deletedBy'] }}</td>
                <td class="nowrap">{{ !empty($d['at']) ? \Illuminate\Support\Carbon::parse($d['at'])->format('Y-m-d H:i') : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="empty">No deleted tickets</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <section class="sup-card sup-card--table admin-dash-grid__full">
      <div class="sup-card__head">
        <h2>Latest audit log entries</h2>
        <a href="/admin/audit-logs" class="sup-link">View all</a>
      </div>
      <div class="table-wrap">
        <table class="data-table data-table--compact sup-table">
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>User</th>
              <th>Action</th>
              <th>Module</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($auditLogs as $l)
              <tr>
                <td class="nowrap">{{ !empty($l['at']) ? \Illuminate\Support\Carbon::parse($l['at'])->format('Y-m-d H:i') : '—' }}</td>
                <td>{{ $l['username'] ?? '—' }}</td>
                <td>{{ \App\Support\AuditActions::label($l['action'] ?? '') }}</td>
                <td>{{ $l['module'] ?? '—' }}</td>
                <td class="sup-truncate">{{ $l['description'] ?? '' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="empty">Audit log mirror pending — open <a href="/admin/audit-logs">Audit Logs</a> for full history.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <section class="sup-card sup-card--compact admin-permissions-note">
    <h2>Administrator permissions</h2>
    <p class="sup-muted-block">System administrators manage users, departments, tickets (view/delete only), audit logs, and settings. Administrators cannot approve risk reports, validate mitigation plans, or override RMO or Audit decisions.</p>
  </section>
@endsection
