<x-auth-shell>
  <h1 class="login-title">Forgot password</h1>
  <p class="login-sub">Enter your username. If an account exists, we will email a 6-digit code to the address on file.</p>
  @if (!empty($error))
    <div class="alert" role="alert">{{ $error }}</div>
  @endif
  <form method="post" action="/forgot-password" autocomplete="on" class="login-form">
    @csrf
    <div class="login-field">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" required autofocus
        autocapitalize="none" autocomplete="username" placeholder="Enter your username"
        value="{{ $username ?? '' }}">
    </div>
    <button type="submit" class="login-submit">Send code</button>
    <p class="login-forgot"><a href="/login">Back to sign in</a></p>
  </form>
</x-auth-shell>
