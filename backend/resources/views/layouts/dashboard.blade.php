<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} — RMS</title>
  <link rel="icon" type="image/png" href="/img/favicon.png">
  <link rel="apple-touch-icon" href="/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body class="app-shell">
  @php
    $displayName = $user['displayName'] ?? $user['username'] ?? 'User';
    $initial = mb_strtoupper(mb_substr(trim((string) $displayName), 0, 1));
  @endphp
  <header class="app-header">
    <a href="/dashboard" class="app-logo">RMS</a>
    <div class="app-user">
      <div class="profile">
        <span class="profile-avatar" aria-hidden="true">{{ $initial }}</span>
        <span class="profile-name">{{ $displayName }}</span>
      </div>
      <form class="inline" method="post" action="/logout">
        <button type="submit" class="btn-text">Sign out</button>
      </form>
    </div>
  </header>
  <div class="app-body">
    <aside class="app-sidebar">
      <nav class="app-nav" aria-label="Dashboard navigation">
        <a href="/dashboard" class="{{ ($activeNav ?? 'home') === 'home' ? 'active' : '' }}">Overview</a>
      </nav>
    </aside>
    <main class="app-main">
      @yield('content')
    </main>
  </div>
</body>
</html>
