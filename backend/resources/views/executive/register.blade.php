@extends('layouts.executive')

@section('content')
  @php
    $tickets = $tickets ?? [];
    $filters = $filters ?? ['level' => '', 'category' => ''];
    $categories = $categories ?? [];
    $activeLevel = (string) ($filters['level'] ?? '');
    $activeCategory = (string) ($filters['category'] ?? '');
    $flashMsg = match (is_string($flash ?? null) ? $flash : '') {
      'not_found' => 'Ticket not found.',
      default => is_string($flash ?? null) && $flash !== '' ? $flash : null,
    };
    $errorMsg = is_string($error ?? null) && $error !== '' ? urldecode($error) : null;
    $levels = ['' => 'All levels', 'low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'];
  @endphp

  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if ($errorMsg)
    <div class="alert alert--error" role="alert">{{ $errorMsg }}</div>
  @endif

  <div class="sup-page-head">
    <div>
      <h1>{{ $title }}</h1>
      <p class="sup-page-desc">{{ $pageDesc }}</p>
    </div>
  </div>

  <div class="console-filter-group">
    <span class="console-filter-label">Risk level</span>
    <div class="ticket-filters console-quick-actions console-quick-actions--inline">
      @foreach ($levels as $id => $label)
        @php
          $href = $id === '' ? '/executive/register' : '/executive/register?level='.urlencode($id);
          $tone = $id === '' ? 'filter-pill--all' : 'filter-pill--level filter-pill--level-'.$id;
          $active = $activeLevel === $id ? ' active' : '';
        @endphp
        <a href="{{ $href }}" class="filter-pill {{ $tone }}{{ $active }}">
          @if ($id !== '')<span class="filter-pill__dot" aria-hidden="true"></span>@endif{{ $label }}
        </a>
      @endforeach
    </div>
  </div>
  <div class="console-filter-group">
    <span class="console-filter-label">Category</span>
    <div class="ticket-filters console-quick-actions console-quick-actions--inline">
      @php
        $allCatHref = $activeLevel !== '' ? '/executive/register?level='.urlencode($activeLevel) : '/executive/register';
      @endphp
      <a href="{{ $allCatHref }}" class="filter-pill{{ $activeCategory === '' ? ' active' : '' }}">All categories</a>
      @foreach ($categories as $id => $label)
        @php
          $href = '/executive/register?category='.urlencode($id).($activeLevel !== '' ? '&level='.urlencode($activeLevel) : '');
        @endphp
        <a href="{{ $href }}" class="filter-pill{{ $activeCategory === $id ? ' active' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>

  <section class="sup-card sup-card--table">
    <div class="table-wrap">
      <table class="data-table data-table--compact sup-table">
        <thead>
          <tr>
            <th>Reference</th><th>Title</th><th>Level</th><th>Category</th><th>Department</th><th>Status</th><th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tickets as $t)
            @include('executive.partials.ticket-row', ['t' => $t])
          @empty
            <tr><td colspan="7" class="empty">{{ $emptyMessage }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
