@extends('layouts.dashboard')

@section('content')
  @php
    $displayName = $user['displayName'] ?? $user['username'] ?? 'User';
    $roleLabel = $user['roleLabel'] ?? 'Employee';
  @endphp
  <div class="page-head">
    <h1>Welcome, {{ $displayName }}</h1>
    <p class="page-desc">{{ $hint }}</p>
    <span class="role-badge">{{ $roleLabel }}</span>
    <p class="text-muted" style="margin-top:1.5rem;font-size:0.8125rem">
      Risk ticket modules will appear here for your role in upcoming releases.
    </p>
  </div>
@endsection
