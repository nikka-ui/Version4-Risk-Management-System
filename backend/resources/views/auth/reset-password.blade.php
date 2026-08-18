<x-auth-shell>
  <h1 class="login-title">Reset password</h1>
  <p class="login-sub">Enter the 6-digit code we sent to the email on your account, then choose a new password.</p>
  @if (!empty($error))
    <div class="alert" role="alert">{{ $error }}</div>
  @endif
  <form method="post" action="/forgot-password/reset" autocomplete="on" class="login-form">
    @csrf
    <div class="login-field">
      <label for="otp">One-time code</label>
      <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
        autocomplete="one-time-code" placeholder="000000" class="login-otp-input">
    </div>
    <div class="login-field">
      <label for="password">New password</label>
      <div class="login-password-wrap">
        <input id="password" name="password" type="password" required minlength="6"
          autocomplete="new-password" placeholder="Enter a new password">
        <button type="button" class="login-password-toggle" aria-label="Show password" aria-controls="password" aria-pressed="false">
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
    <div class="login-field">
      <label for="password_confirmation">Confirm password</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6"
        autocomplete="new-password" placeholder="Re-enter new password">
    </div>
    <button type="submit" class="login-submit">Reset password</button>
  </form>
  <form method="post" action="/forgot-password" class="login-resend">
    @csrf
    <input type="hidden" name="username" value="{{ $username ?? '' }}">
    <button type="submit" class="login-resend__btn">Resend code</button>
  </form>
  <p class="login-forgot"><a href="/login">Back to sign in</a></p>
</x-auth-shell>
