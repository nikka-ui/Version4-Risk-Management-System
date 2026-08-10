@extends('layouts.supervisor')

@section('content')
  <div class="sup-page-head">
    <div>
      <h1>Profile</h1>
      <p class="sup-page-desc">Your Ticket Reporter account details.</p>
    </div>
  </div>
  <section class="sup-card">
    <dl class="detail-dl detail-dl--profile">
      <dt>Display name</dt><dd>{{ $user['displayName'] ?: '—' }}</dd>
      <dt>Username</dt><dd class="mono">{{ $user['username'] }}</dd>
      <dt>Email</dt><dd>{{ $user['email'] ?: '—' }}</dd>
      <dt>Employee ID</dt><dd>{{ $user['employeeId'] ?: '—' }}</dd>
      <dt>Department</dt><dd>{{ $user['department'] ?: '—' }}</dd>
      <dt>Position</dt><dd>{{ $user['position'] ?: '—' }}</dd>
      <dt>Role</dt><dd>{{ $user['roleLabel'] ?: 'Ticket Reporter' }}</dd>
    </dl>
    <p class="text-muted section-hint">Contact your system administrator to update profile details or reset your password.</p>
  </section>
@endsection
