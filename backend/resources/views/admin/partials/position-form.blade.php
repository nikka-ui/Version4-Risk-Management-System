@php
  $editPos = $editPos ?? null;
  $isEdit = is_array($editPos);
  $formAction = $isEdit
    ? '/admin/positions/'.rawurlencode($editPos['id']).'/edit'
    : '/admin/positions';
@endphp
<section class="sup-card sup-card--compact">
  <h2>{{ $isEdit ? 'Edit position' : 'Add position' }}</h2>
  <form method="post" action="{{ $formAction }}">
    <div class="field">
      <label for="name">Position Name</label>
      <input id="name" name="name" type="text" value="{{ $editPos['name'] ?? '' }}" required>
    </div>
    <div class="action-row">
      <button type="submit" class="sup-btn-primary">{{ $isEdit ? 'Save' : 'Add Position' }}</button>
      <a href="/laravel/admin/positions" class="sup-btn-outline">Cancel</a>
    </div>
  </form>
</section>
