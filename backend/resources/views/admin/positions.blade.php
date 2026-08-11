@extends('layouts.admin')

@section('content')
  @php
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'created' => 'Position created successfully.',
      'updated' => 'Record updated successfully.',
      'deleted' => 'Record deleted successfully.',
      'not_found' => 'Position not found.',
      default => null,
    };
    $errorMsg = is_string($error ?? null) && $error !== '' ? $error : null;
    $positions = $positions ?? [];
    $editPos = $editPos ?? null;
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
      <h1>Position Management</h1>
      <p class="sup-page-desc">Manage job title presets used as reference labels across the system.</p>
    </div>
    <a href="/laravel/admin/positions?action=add" class="sup-btn-primary">+ Add Position</a>
  </div>

  @if ($showForm)
    @include('admin.partials.position-form', ['editPos' => $editPos])
  @endif

  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Position</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($positions as $p)
            @php $posId = $p['id'] ?? ''; @endphp
            <tr>
              <td><strong>{{ $p['name'] }}</strong></td>
              <td class="col-actions">
                <div class="admin-action-cell">
                  <a href="/laravel/admin/positions/{{ urlencode($posId) }}/edit"
                     class="admin-icon-btn admin-icon-btn--edit" title="Edit" aria-label="Edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                  </a>
                  <form method="post" action="/admin/positions/{{ urlencode($posId) }}/delete" class="inline-form"
                        onsubmit="return confirm('Delete position {{ addslashes($p['name']) }}?');">
                    <button type="submit" class="admin-icon-btn admin-icon-btn--delete" title="Delete" aria-label="Delete">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="2" class="empty">No positions</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
