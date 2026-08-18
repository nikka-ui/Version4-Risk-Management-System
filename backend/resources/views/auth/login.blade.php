<x-auth-shell>
  <h1 class="login-title">Sign In</h1>
  <p class="login-sub">Use your assigned credentials to continue.</p>
  @if (!empty($success))
    <div class="alert alert--success" role="status">{{ $success }}</div>
  @endif
  @if (!empty($error))
    <div class="alert" role="alert">{{ $error }}</div>
  @endif
  <form method="post" action="/login" autocomplete="on" class="login-form">
    @csrf
    @if (!empty($next))
      <input type="hidden" name="next" value="{{ $next }}">
    @endif
    <div class="login-field">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" required autofocus
        autocapitalize="none" autocomplete="username" placeholder="Enter your username"
        value="{{ $username ?? '' }}">
    </div>
    <div class="login-field">
      <label for="password">Password</label>
      <div class="login-password-wrap">
        <input id="password" name="password" type="password" required
          autocomplete="current-password" placeholder="Enter your password">
        <button type="button" class="login-password-toggle" id="password-toggle"
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
    <button type="submit" class="login-submit">Sign In</button>
    <p class="login-forgot"><a href="/forgot-password">Forgot password?</a></p>
  </form>
</x-auth-shell>
