@php
  $editDept = $editDept ?? null;
  $isEdit = is_array($editDept);
  $formAction = $isEdit
    ? '/admin/departments/'.rawurlencode($editDept['id']).'/edit'
    : '/admin/departments';
@endphp
<section class="sup-card sup-card--compact">
  <h2>{{ $isEdit ? 'Edit department' : 'Add department' }}</h2>
  <form method="post" action="{{ $formAction }}">
    @csrf
    <div class="admin-form-grid">
      <div class="field">
        <label for="name">Department Name</label>
        <input id="name" name="name" type="text" value="{{ $editDept['name'] ?? '' }}" required>
      </div>
      <div class="field">
        <label for="code">Department Code</label>
        <input id="code" name="code" type="text" value="{{ $editDept['code'] ?? '' }}" required>
      </div>
      <div class="field admin-form-grid__full">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="2">{{ $editDept['description'] ?? '' }}</textarea>
      </div>
      <div class="field">
        <label for="head">Department Head (optional)</label>
        <input id="head" name="head" type="text" value="{{ $editDept['head'] ?? '' }}">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="active" @selected(($editDept['status'] ?? 'active') !== 'inactive')>Active</option>
          <option value="inactive" @selected(($editDept['status'] ?? '') === 'inactive')>Inactive</option>
        </select>
      </div>
    </div>
    <div class="action-row">
      <button type="submit" class="sup-btn-primary">{{ $isEdit ? 'Save' : 'Add Department' }}</button>
      <a href="/admin/departments" class="sup-btn-outline">Cancel</a>
    </div>
  </form>
</section>
