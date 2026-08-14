@php
  $reactions = is_array($c['reactions'] ?? null) ? $c['reactions'] : [];
  $canEdit = $canPost && ($c['authorUsername'] ?? '') === $currentUsername && ($c['kind'] ?? 'comment') === 'comment';
@endphp
<div class="reddit-comment{{ !empty($isReply) ? ' reddit-comment--reply' : '' }}" id="comment-{{ $c['id'] }}">
  <div class="reddit-comment__main">
    <header class="reddit-comment__header">
      <span class="reddit-author">{{ $c['authorName'] }}</span>
      @if (($c['roleLabel'] ?? '') !== '')
        <span class="reddit-role">{{ $c['roleLabel'] }}</span>
      @endif
      @if (!empty($c['at']))
        <span class="reddit-sep" aria-hidden="true">·</span>
        <time class="reddit-time">{{ \Illuminate\Support\Carbon::parse($c['at'])->format('Y-m-d H:i') }}</time>
      @endif
      @if (!empty($c['editedAt']))
        <span class="reddit-edited">(edited)</span>
      @endif
    </header>
    <div class="reddit-body">{{ $c['body'] }}</div>
    @if ($canPost)
      <div class="reddit-reaction-row">
        @if (count($reactions) > 0)
          <div class="reddit-reaction-summary" aria-label="Reactions">
            @foreach ($reactions as $emoji => $users)
              <form method="post" action="{{ $reactAction }}" class="inline-form reaction-form">
                @csrf
                <input type="hidden" name="commentId" value="{{ $c['id'] }}">
                <input type="hidden" name="reaction" value="{{ $emoji }}">
                <button type="submit" class="reaction-pill{{ in_array($currentUsername, $users, true) ? ' is-active' : '' }}" title="{{ count($users) }} reaction{{ count($users) === 1 ? '' : 's' }}">{{ $emoji }} <span class="reaction-pill__count">{{ count($users) }}</span></button>
              </form>
            @endforeach
          </div>
        @endif
        <details class="reddit-react-box">
          <summary class="reddit-action-btn">React</summary>
          <div class="reddit-reactions" role="group" aria-label="Add reaction">
            @foreach ($reactionOptions as $emoji)
              <form method="post" action="{{ $reactAction }}" class="inline-form reaction-form">
                @csrf
                <input type="hidden" name="commentId" value="{{ $c['id'] }}">
                <input type="hidden" name="reaction" value="{{ $emoji }}">
                <button type="submit" class="reddit-action-btn reddit-action-btn--react{{ in_array($currentUsername, $reactions[$emoji] ?? [], true) ? ' is-active' : '' }}" title="React with {{ $emoji }}">{{ $emoji }}</button>
              </form>
            @endforeach
          </div>
        </details>
      </div>
    @endif
    @if (empty($isReply) && $canPost)
      <details class="reddit-reply-box">
        <summary class="reddit-action-btn">Reply</summary>
        <form method="post" action="{{ $postAction }}" class="stack-form reddit-reply-form">
          @csrf
          <input type="hidden" name="parentId" value="{{ $c['id'] }}">
          <div class="field">
            <label class="visually-hidden" for="reply-{{ $c['id'] }}">Reply</label>
            <textarea id="reply-{{ $c['id'] }}" name="comment" rows="3" required placeholder="Write a reply…"></textarea>
          </div>
          <button type="submit" class="btn-outline btn-primary--auto">Reply</button>
        </form>
      </details>
    @endif
    @if ($canEdit)
      <details class="reddit-edit-box">
        <summary class="reddit-action-btn">Edit</summary>
        <form method="post" action="{{ $editAction }}" class="stack-form reddit-reply-form">
          @csrf
          <input type="hidden" name="commentId" value="{{ $c['id'] }}">
          <div class="field">
            <textarea name="comment" rows="3" required>{{ $c['body'] }}</textarea>
          </div>
          <button type="submit" class="btn-outline btn-primary--auto">Save edit</button>
        </form>
      </details>
    @endif
    @if (empty($isReply))
      @foreach ($children as $reply)
        @include('partials.thread-comment', [
          'c' => $reply,
          'isReply' => true,
          'children' => [],
          'canPost' => $canPost,
          'currentUsername' => $currentUsername,
          'postAction' => $postAction,
          'editAction' => $editAction,
          'reactAction' => $reactAction,
          'reactionOptions' => $reactionOptions,
        ])
      @endforeach
    @endif
  </div>
</div>
