@extends('layouts.supervisor')

@section('content')
  @php
    $flashLabels = [
      'notifications_read' => 'All notifications marked as read.',
      'not_found' => 'Notification not found.',
    ];
    $flashKey = is_string($flash ?? null) ? $flash : '';
    $flashMsg = $flashLabels[$flashKey] ?? null;
  @endphp
  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  <div class="sup-page-head">
    <div>
      <h1>Notifications</h1>
      <p class="sup-page-desc">Ticket status updates, routing confirmations, and return notices.</p>
    </div>
    @if (($unread ?? 0) > 0)
      <form method="post" action="/supervisor/notifications/read-all">
        @csrf
        <button type="submit" class="btn-outline">Mark all read</button>
      </form>
    @endif
  </div>
  <section class="sup-card">
    <ul class="notif-page-list">
      @forelse ($notifications as $n)
        <li class="notif-page-item{{ empty($n['read']) ? ' notif-page-item--unread' : '' }}">
          <a href="/supervisor/notifications/open/{{ urlencode($n['id']) }}" class="notif-page-item__link">
            <span class="notif-page-item__title">{{ $n['title'] ?: 'Notification' }}</span>
            <span class="notif-page-item__message">{{ $n['message'] ?? '' }}</span>
            <span class="notif-page-item__time">{{ !empty($n['at']) ? \Illuminate\Support\Carbon::parse($n['at'])->format('Y-m-d H:i') : '' }}</span>
          </a>
        </li>
      @empty
        <li class="notif-page-item notif-page-item--empty">No notifications yet. Status updates on your tickets will appear here.</li>
      @endforelse
    </ul>
  </section>
@endsection
