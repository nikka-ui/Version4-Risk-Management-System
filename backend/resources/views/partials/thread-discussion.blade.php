@php
  $comments = $threadComments ?? [];
  $tops = array_values(array_filter($comments, fn ($c) => empty($c['parentId'])));
  $childrenOf = function (string $parentId) use ($comments) {
    return array_values(array_filter($comments, fn ($c) => ($c['parentId'] ?? null) === $parentId));
  };
  $currentUsername = (string) ($user['username'] ?? '');
  $canPost = !empty($canPost);
  $reactionOptions = ['👍', '❤️', '🎉', '👀'];
  $title = $title ?? 'Discussion thread';
  $composeLabel = $composeLabel ?? 'Add comment';
  $composePlaceholder = $composePlaceholder ?? 'Write a comment…';
@endphp
<section class="sup-card">
  <h2>{{ $title }}</h2>
  @if (count($tops) === 0)
    <div class="reddit-thread reddit-thread--empty">
      <p class="reddit-empty">No comments yet. Start the discussion below.</p>
    </div>
  @else
    <div class="reddit-thread">
      @foreach ($tops as $c)
        @include('partials.thread-comment', [
          'c' => $c,
          'isReply' => false,
          'children' => $childrenOf($c['id']),
          'canPost' => $canPost,
          'currentUsername' => $currentUsername,
          'postAction' => $postAction,
          'editAction' => $editAction,
          'reactAction' => $reactAction,
          'reactionOptions' => $reactionOptions,
        ])
      @endforeach
    </div>
  @endif

  @if ($canPost)
    <form method="post" action="{{ $postAction }}" class="stack-form reddit-compose">
      @csrf
      <div class="field">
        <label for="{{ $composeId }}">{{ $composeLabel }}</label>
        <textarea id="{{ $composeId }}" name="comment" rows="3" required placeholder="{{ $composePlaceholder }}"></textarea>
      </div>
      <button type="submit" class="btn-primary btn-primary--auto">Post comment</button>
    </form>
  @endif
</section>
