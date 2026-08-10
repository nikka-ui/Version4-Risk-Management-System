@extends('layouts.supervisor')

@section('content')
  @php
    $ticket = $ticket ?? [];
    $attachments = $attachments ?? [];
    $ai = is_array($ticket['ai'] ?? null) ? $ticket['ai'] : [];
    $ref = $ticket['reference'] ?? '';
    $category = $ai['riskCategory'] ?? ($ticket['category'] ?? '—');
    $categoryLabel = $category !== '—' ? str_replace('_', ' ', ucfirst((string) $category)) : '—';
    $dept = $ticket['department'] ?? ($ai['responsibleDepartment'] ?? '—');
    $priority = $ticket['priority'] ?? ($ai['priority'] ?? null);
    $isRevise = (bool) ($isRevise ?? false);
    $revisionBlocked = (bool) ($revisionBlocked ?? false);
  @endphp
  @if (!empty($error))
    <div class="alert" role="alert">{{ urldecode((string) $error) }}</div>
  @endif
  @if ($revisionBlocked)
    <div class="rmo-feedback-alert revision-blocked-alert" role="alert">
      <div class="rmo-feedback-alert__body">
        <p class="rmo-feedback-alert__title">Revision required</p>
        <p class="rmo-feedback-alert__message">No changes were detected since this ticket was returned. Go back, update the report details or evidence, then return to submit.</p>
        <p class="rmo-feedback-alert__hint"><a href="/laravel/supervisor/tickets/{{ urlencode($ref) }}/edit">Edit returned report</a></p>
      </div>
    </div>
  @endif
  <div class="enterprise-module">
    <div class="enterprise-top">
      <div class="progress-steps">
        <div class="progress-step"><span class="progress-num">1</span><span class="progress-label">Risk information</span></div>
        <div class="progress-step progress-step--active"><span class="progress-num">2</span><span class="progress-label">AI preview</span></div>
      </div>
      <div class="enterprise-title">
        <h1>{{ $isRevise ? 'REVISE RISK REPORT' : 'NEW RISK REPORT' }}</h1>
        <p class="sup-page-desc">{{ $isRevise ? 'Review your updates. On resubmit, AI will re-analyze and route the ticket.' : 'Review the AI-generated summary, classification, and proposed routing before submitting.' }}</p>
      </div>
    </div>

    @include('supervisor.partials.ai-analysis-panel', ['ticket' => $ticket, 'preview' => true])

    <section class="enterprise-card">
      <div class="enterprise-section-head">
        <h2>EVIDENCE ATTACHMENTS</h2>
      </div>
      @if (count($attachments) > 0)
        <ul class="upload-preview upload-preview--saved">
          @foreach ($attachments as $e)
            <li class="upload-preview-item upload-preview-item--saved">
              <span class="upload-name"><a href="/supervisor/attachments/{{ urlencode($e['id']) }}" target="_blank" rel="noopener">{{ $e['name'] }}</a></span>
              <span class="upload-meta">{{ !empty($e['uploadedAt']) ? \Illuminate\Support\Carbon::parse($e['uploadedAt'])->format('Y-m-d H:i') : '' }}</span>
            </li>
          @endforeach
        </ul>
      @else
        <p class="text-muted">No attachments on file.</p>
      @endif
    </section>

    <section class="enterprise-card review-submission-section review-submission-section--pending" id="reviewSubmissionSection">
      <div class="enterprise-section-head">
        <h2>{{ $isRevise ? 'FINAL STEP: RESUBMIT TICKET' : 'REVIEW & SUBMISSION' }}</h2>
      </div>
      <form method="post" action="/supervisor/tickets/new/preview/{{ urlencode($ref) }}/submit" class="submit-report-form" id="submitForm" novalidate>
        <div class="review-confirm" id="reviewConfirmBox">
          <label class="confirm-check" id="confirmCheckLabel">
            <input type="checkbox" id="confirmBox" name="confirmBox" value="1">
            <span>I confirm that the information provided is accurate{{ $isRevise ? ' and ready to resubmit' : '' }}.</span>
          </label>
          <p class="review-confirm-hint" id="reviewConfirmHint">Required — check this box to enable {{ $isRevise ? 'Resubmit ticket' : 'Submit ticket' }}.</p>
          <div class="review-note text-muted">Ticket: <span class="mono">{{ $ref }}</span></div>
        </div>
        <div class="enterprise-actions enterprise-actions--split review-submission-actions">
          <div class="enterprise-actions__group">
            <a href="/laravel/supervisor/tickets/{{ urlencode($ref) }}/edit" class="btn-enterprise-outline">{{ $isRevise ? 'Back to edit' : 'Edit Draft' }}</a>
            @if (! $isRevise)
              <button type="submit" formaction="/supervisor/tickets/new/preview/{{ urlencode($ref) }}/save" formmethod="post" class="btn-enterprise-outline">Save Draft</button>
            @endif
          </div>
          <button type="button" class="btn-enterprise-primary btn-enterprise-submit btn-enterprise-primary--inactive" id="submitBtn" @if($revisionBlocked) disabled @endif>
            {{ $isRevise ? 'Resubmit ticket' : 'Submit ticket' }}
          </button>
          <button type="submit" id="submitBtnNative" class="visually-hidden" tabindex="-1" aria-hidden="true">Submit</button>
        </div>
      </form>
    </section>
  </div>

  @include('supervisor.partials.ticket-preview-scripts', [
    'isRevise' => $isRevise,
    'revisionBlocked' => $revisionBlocked,
  ])
@endsection
