<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} — RMS</title>
  <link rel="icon" type="image/png" href="/img/favicon.png">
  <link rel="stylesheet" href="/css/app.css">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="supervisor-shell officer-console">
  @php
    $displayName = $user['displayName'] ?? $user['username'] ?? 'User';
    $initial = mb_strtoupper(mb_substr(trim((string) $displayName), 0, 1));
    $positionLine = $user['position'] ?: 'Compliance Officer';
    $stats = $stats ?? [];
    $nav = [
      ['id' => 'validation', 'href' => '/compliance', 'label' => 'Validation queue', 'statKey' => 'pendingValidation'],
    ];
  @endphp
  <aside class="supervisor-sidebar">
    <div class="supervisor-sidebar__brand">
      <div class="supervisor-sidebar__titles">
        <span class="supervisor-sidebar__system">Risk Management</span>
        <span class="supervisor-sidebar__role">Compliance Officer</span>
      </div>
    </div>
    <p class="supervisor-sidebar__section">Menu</p>
    <nav class="supervisor-sidebar__nav" aria-label="Compliance navigation">
      @foreach ($nav as $item)
        @php $count = isset($item['statKey']) ? (int) ($stats[$item['statKey']] ?? 0) : 0; @endphp
        <a href="{{ $item['href'] }}"
           class="supervisor-sidebar__link{{ ($activeNav ?? '') === $item['id'] ? ' supervisor-sidebar__link--active' : '' }}">
          <span class="supervisor-sidebar__label">{{ $item['label'] }}</span>
          @if ($count > 0)
            <span class="supervisor-sidebar__badge">{{ $count }}</span>
          @endif
        </a>
      @endforeach
    </nav>
    <div class="supervisor-sidebar__user">
      <span class="supervisor-sidebar__avatar" aria-hidden="true">{{ $initial }}</span>
      <div class="supervisor-sidebar__user-meta">
        <span class="supervisor-sidebar__user-name">{{ $displayName }}</span>
        <span class="supervisor-sidebar__user-email">{{ $positionLine }}</span>
      </div>
    </div>
    <form class="supervisor-sidebar__logout" method="post" action="/logout">
      @csrf
      <button type="submit" class="supervisor-sidebar__signout">Sign out</button>
    </form>
  </aside>
  <div class="supervisor-content">
    <header class="console-topbar">
      <div class="console-topbar__title">{{ $title }}</div>
      <div class="console-topbar__actions">
        <span class="console-topbar__role-pill">Compliance Officer</span>
      </div>
    </header>
    <main class="supervisor-main">
      @yield('content')
    </main>
  </div>
</body>
</html>
