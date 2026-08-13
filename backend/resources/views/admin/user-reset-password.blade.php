@extends('layouts.admin')

@section('content')
  @php
    $targetUser = $targetUser ?? [];
    $errorMsg = is_string($error ?? null) && $error !== '' ? $error : null;
    $displayName = $targetUser['displayName'] ?? $targetUser['username'] ?? 'user';
    $username = $targetUser['username'] ?? '';
  @endphp

  @if ($errorMsg)
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>Reset Password</h1>
      <p class="sup-page-desc">Set a new password for {{ $displayName }} ({{ $username }}).</p>
    </div>
    <a href="/admin/users" class="sup-btn-outline">Back to users</a>
  </div>

  <section class="sup-card sup-card--compact">
    <form method="post" action="/admin/users/{{ urlencode($username) }}/reset-password">
      @csrf
      <div class="admin-form-grid">
        <div class="field">
          <label for="password">New Password</label>
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
      </div>
      <div class="action-row">
        <button type="submit" class="sup-btn-primary">Reset Password</button>
      </div>
    </form>
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
  </section>
@endsection
