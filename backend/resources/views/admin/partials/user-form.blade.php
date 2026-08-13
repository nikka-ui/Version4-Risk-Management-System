@php
  $editUser = $editUser ?? null;
  $departments = $departments ?? [];
  $roles = $roles ?? [];
  $isEdit = is_array($editUser);
  $formAction = $isEdit
    ? '/admin/users/'.rawurlencode($editUser['username']).'/edit'
    : '/admin/users';
  $isPrimaryAdmin = $isEdit && ($editUser['username'] ?? '') === 'admin';
@endphp
<section class="sup-card sup-card--compact admin-form-card">
  <h2>{{ $isEdit ? 'Edit user: '.$editUser['displayName'] : 'Create user' }}</h2>
  <form method="post" action="{{ $formAction }}" class="admin-user-form">
    @csrf
    <div class="admin-form-grid">
      <div class="field">
        <label for="employeeId">Employee ID</label>
        <input id="employeeId" name="employeeId" type="text" placeholder="Auto: EMP-001"
               value="{{ $editUser['employeeId'] ?? '' }}">
        @unless ($isEdit)
          <span class="field-hint">Leave blank to assign the next ID (EMP-001, EMP-002, …).</span>
        @endunless
      </div>
      <div class="field">
        <label for="displayName">Full Name</label>
        <input id="displayName" name="displayName" type="text" value="{{ $editUser['displayName'] ?? '' }}" required>
      </div>
      <div class="field">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" value="{{ $editUser['email'] ?? '' }}" required>
      </div>
      @unless ($isEdit)
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required pattern="[a-zA-Z0-9._-]{3,32}" autocapitalize="none">
        </div>
      @endunless
      <div class="field">
        <label for="department">Department</label>
        <select id="department" name="department" required>
          <option value="">Select department</option>
          @foreach ($departments as $dept)
            <option value="{{ $dept['name'] }}" @selected(($editUser['department'] ?? '') === $dept['name'])>{{ $dept['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label for="position">Company Position</label>
        <input id="position" name="position" type="text" value="{{ $editUser['position'] ?? '' }}"
               placeholder="e.g. IT Manager, Senior Analyst" required>
      </div>
      <div class="field">
        <label for="role">User Role</label>
        <select id="role" name="role" required @disabled($isPrimaryAdmin)>
          @foreach ($roles as $role)
            <option value="{{ $role['id'] }}" @selected(($editUser['role'] ?? '') === $role['id'])>{{ $role['label'] }}</option>
          @endforeach
        </select>
        @if ($isPrimaryAdmin)
          <input type="hidden" name="role" value="admin">
        @endif
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" @disabled($isPrimaryAdmin)>
          <option value="active" @selected(($editUser['status'] ?? 'active') !== 'inactive')>Active</option>
          <option value="inactive" @selected(($editUser['status'] ?? '') === 'inactive')>Inactive</option>
        </select>
      </div>
      @unless ($isEdit)
        <div class="field">
          <label for="password">Password</label>
          <div class="login-password-wrap">
            <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password">
            <button type="button" class="login-password-toggle" data-password-toggle="password"
                    aria-label="Show password" aria-controls="password" aria-pressed="false">
              <svg class="login-password-toggle__icon login-password-toggle__icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="login-password-toggle__icon login-password-toggle__icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label for="confirmPassword">Confirm Password</label>
          <div class="login-password-wrap">
            <input id="confirmPassword" name="confirmPassword" type="password" required minlength="6" autocomplete="new-password">
            <button type="button" class="login-password-toggle" data-password-toggle="confirmPassword"
                    aria-label="Show confirm password" aria-controls="confirmPassword" aria-pressed="false">
              <svg class="login-password-toggle__icon login-password-toggle__icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="login-password-toggle__icon login-password-toggle__icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>
      @endunless
    </div>
    <div class="action-row">
      <button type="submit" class="sup-btn-primary">{{ $isEdit ? 'Save changes' : 'Add User' }}</button>
      <a href="/admin/users" class="sup-btn-outline">Cancel</a>
    </div>
  </form>
  @unless ($isEdit)
    <script>
      (function () {
        document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
          const input = document.getElementById(toggle.getAttribute('data-password-toggle'));
          if (!input) return;
          toggle.addEventListener('click', function () {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
          });
        });
      })();
    </script>
  @endunless
</section>
