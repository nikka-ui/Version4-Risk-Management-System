@props([
    'title' => 'ACCC Risk Management System',
])
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }}</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="/css/app.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
</head>
<body class="login-body">
  <div class="login-shell">
    <div class="login-card">
      <aside class="login-visual">
        <div class="login-visual__intro">
          <p class="login-visual__eyebrow">Identify. Assess. Mitigate.</p>
          <h2 class="login-visual__headline">ACCC Risk
Management
System</h2>
        </div>
        <div class="login-visual__art">
          <img src="/img/risk-illustration.png" alt="Risk management dashboard illustration" class="login-visual__img">
        </div>
      </aside>
      <main class="login-panel">
        <div class="login-form-wrap">
          {{ $slot }}
        </div>
        <footer class="login-foot">
          <span>&copy; {{ date('Y') }} ACCC. Authorized personnel only.</span>
        </footer>
      </main>
    </div>
  </div>
  <script>
    (function () {
      document.querySelectorAll('.login-password-wrap').forEach(function (wrap) {
        const input = wrap.querySelector('input[type="password"], input[type="text"]');
        const toggle = wrap.querySelector('.login-password-toggle');
        if (!input || !toggle) return;
        toggle.addEventListener('click', function () {
          const show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          toggle.classList.toggle('is-visible', show);
          toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
          toggle.setAttribute('aria-pressed', String(show));
        });
      });
    })();
  </script>
</body>
</html>
