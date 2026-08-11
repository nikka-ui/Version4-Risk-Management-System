@extends('layouts.admin')

@section('content')
  @php
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'created' => 'Department created successfully.',
      'updated' => 'Record updated successfully.',
      'deleted' => 'Record deleted successfully.',
      'not_found' => 'Department not found.',
      default => null,
    };
    $errorMsg = is_string($error ?? null) && $error !== '' ? $error : null;
    $departments = $departments ?? [];
    $editDept = $editDept ?? null;
    $showForm = (bool) ($showForm ?? false);
  @endphp

  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if ($errorMsg)
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Department Management</h1>
      <p class="sup-page-desc">Manage organizational departments used across the risk management system.</p>
    </div>
    <a href="/laravel/admin/departments?action=add" class="sup-btn-primary">+ Add Department</a>
  </div>

  @if ($showForm)
    @include('admin.partials.department-form', ['editDept' => $editDept])
  @endif

  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Code</th>
            <th>Description</th>
            <th>Head</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($departments as $d)
            @php
              $status = $d['status'] ?? 'active';
              $statusCls = match ($status) {
                'active' => 'active',
                'deleted' => 'deleted',
                default => 'inactive',
              };
              $statusLabel = match ($status) {
                'active' => 'Active',
                'deleted' => 'Deleted',
                default => 'Inactive',
              };
              $deptId = $d['id'] ?? '';
            @endphp
            <tr>
              <td><strong>{{ $d['name'] }}</strong></td>
              <td class="mono">{{ $d['code'] }}</td>
              <td class="sup-truncate">{{ $d['description'] !== '' ? $d['description'] : '—' }}</td>
              <td>{{ $d['head'] ?: '—' }}</td>
              <td>
                <span class="admin-status admin-status--{{ $statusCls }}">
                  <span class="admin-status__dot" aria-hidden="true"></span>{{ $statusLabel }}
                </span>
              </td>
              <td class="col-actions">
                <div class="admin-action-cell">
                  <a href="/laravel/admin/departments/{{ urlencode($deptId) }}/edit"
                     class="admin-icon-btn admin-icon-btn--edit" title="Edit" aria-label="Edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                  </a>
                  <form method="post" action="/admin/departments/{{ urlencode($deptId) }}/delete" class="inline-form"
                        onsubmit="return confirm('Delete department {{ addslashes($d['name']) }}?');">
                    <button type="submit" class="admin-icon-btn admin-icon-btn--delete" title="Delete" aria-label="Delete">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="empty">No departments</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
