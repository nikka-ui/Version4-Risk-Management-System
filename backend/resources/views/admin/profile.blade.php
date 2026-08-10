@extends('layouts.admin')

@section('content')
  <div class="sup-page-head">
    <h1 class="sup-page-head__title">Profile</h1>
    <p class="sup-page-head__desc">Your administrator account details.</p>
  </div>
  <section class="sup-card">
    <dl class="detail-dl detail-dl--console">
      <dt>Full Name</dt><dd>{{ $user['displayName'] }}</dd>
      <dt>Username</dt><dd class="mono">{{ $user['username'] }}</dd>
      <dt>Email</dt><dd>{{ $user['email'] ?: ($user['username'].'@rms.local') }}</dd>
      <dt>Role</dt><dd>{{ $user['roleLabel'] }}</dd>
      <dt>Department</dt><dd>{{ $user['department'] ?: 'Administration' }}</dd>
      <dt>Position</dt><dd>{{ $user['position'] ?: 'System Administrator' }}</dd>
    </dl>
  </section>
@endsection
