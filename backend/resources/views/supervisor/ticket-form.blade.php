@extends('layouts.supervisor')

@section('content')
  @php
    $ticket = $ticket ?? null;
    $attachments = $attachments ?? [];
    $five = is_array($ticket['fiveW1H'] ?? null) ? $ticket['fiveW1H'] : [];
    $pageTitle = ($isRevise ?? false)
      ? (($isDeptReturn ?? false) ? 'REVISE RETURNED REPORT' : 'REVISE RISK REPORT')
      : (($isEdit ?? false) ? 'EDIT DRAFT REPORT' : 'NEW RISK REPORT');
    $pageDesc = ($isRevise ?? false)
      ? (($isDeptReturn ?? false)
        ? 'The responsible department returned this ticket. Update the details and evidence, then resubmit for AI routing.'
        : 'Your report was returned by the Risk Management Unit. Update the details and evidence, then resubmit.')
      : (($isEdit ?? false)
        ? 'Update your draft report. Only drafts can be edited or deleted before submission.'
        : 'Submit a structured incident report. AI assigns the handling department from the risk title and incident details — your profile department is not used.');
    $ref = ($isEdit ?? false) ? ($ticket['reference'] ?? $ticketRef) : $ticketRef;
    $flashLabels = [
      'preview_generated' => 'AI preview generated. Review the summary before submitting.',
      'draft_updated' => 'Draft updated.',
      'evidence_uploaded' => 'Evidence uploaded.',
    ];
    $flashKey = is_string($flash ?? null) ? $flash : '';
    $flashMsg = $flashLabels[$flashKey] ?? null;
    $initialSnapshot = [
      'title' => $ticket['title'] ?? '',
      'location' => $ticket['location'] ?? '',
      'what' => $five['what'] ?? '',
      'why' => $five['why'] ?? '',
      'where' => $five['where'] ?? '',
      'when' => $five['when'] ?? '',
      'who' => $five['who'] ?? '',
      'how' => $five['how'] ?? '',
    ];
  @endphp
  @if ($flashMsg)
    <div class="alert alert--ok" role="status">{{ $flashMsg }}</div>
  @endif
  @if (!empty($error))
    <div class="alert" role="alert">{{ urldecode((string) $error) }}</div>
  @endif
  <div class="enterprise-module">
    <div class="enterprise-top">
      <div class="progress-steps">
        <div class="progress-step progress-step--active"><span class="progress-num">1</span><span class="progress-label">Risk information</span></div>
        <div class="progress-step"><span class="progress-num">2</span><span class="progress-label">AI preview</span></div>
      </div>
      <div class="enterprise-title">
        <h1>{{ $pageTitle }}</h1>
        <p class="sup-page-desc">{{ $pageDesc }}</p>
        <p class="required-legend"><span class="req">*</span> Required field</p>
      </div>
    </div>

    @if (($isRevise ?? false) && ($ticket['status'] ?? '') === 'returned' && !empty($officerNotes))
      <section class="rmo-feedback-alert revision-feedback-alert" role="alert">
        <div class="rmo-feedback-alert__body">
          <p class="rmo-feedback-alert__title">RMO feedback</p>
          <p class="rmo-feedback-alert__message">{{ $officerNotes }}</p>
          <p class="rmo-feedback-alert__hint">Address the feedback below, then continue to the AI preview and resubmit.</p>
        </div>
      </section>
    @endif

    @if (($isDeptReturn ?? false) && !empty($ownership['rejectionReason']))
      <section class="rmo-feedback-alert revision-feedback-alert" role="alert">
        <div class="rmo-feedback-alert__body">
          <p class="rmo-feedback-alert__title">Returned by responsible department</p>
          <p class="rmo-feedback-alert__message">{{ $ownership['rejectionReason'] }}</p>
          <p class="rmo-feedback-alert__hint">Update your report details or evidence, then resubmit. AI will re-analyze and route the ticket again.</p>
        </div>
      </section>
    @endif

    @if (!empty($user['department']))
      <p class="reporting-unit-note" role="note">Reporting as <strong>{{ $user['department'] }}</strong> — this is recorded for audit only and does <strong>not</strong> affect AI department assignment.</p>
    @endif

    <form method="post" action="{{ $formAction }}" class="enterprise-form" id="riskForm" enctype="multipart/form-data" novalidate>
      @csrf
      <input type="hidden" name="referenceOverride" value="{{ $ref }}">

      <section class="enterprise-card">
        <div class="enterprise-section-head">
          <h2>RISK INFORMATION</h2>
          <div class="ticket-ref">
            <span class="ticket-ref__label">Auto-generated Ticket Number</span>
            <span class="ticket-ref__value">{{ $ref }}</span>
          </div>
        </div>
        <div class="enterprise-grid enterprise-grid--2">
          <div class="field field--required" data-required="title">
            <label for="title">Risk Title <span class="req">*</span></label>
            <input id="title" name="title" type="text" required class="enterprise-input" value="{{ $ticket['title'] ?? '' }}" placeholder="Short, specific risk title">
          </div>
          <div class="field field--required" data-required="location">
            <label for="location">Incident location <span class="req">*</span></label>
            <input id="location" name="location" type="text" required class="enterprise-input" value="{{ $ticket['location'] ?? '' }}" placeholder="Building / unit / site">
          </div>
        </div>
      </section>

      <section class="enterprise-card" id="incidentDetailsSection" data-required="incident">
        <div class="enterprise-section-head">
          <h2>INCIDENT DETAILS <span class="req">*</span></h2>
          <p class="section-hint">All fields are required. AI department assignment uses these fields plus the risk title.</p>
        </div>
        <div class="incident-grid">
          @foreach (['what' => 'What happened?', 'why' => 'Why did it happen?', 'where' => 'Where did it occur?', 'when' => 'When did it occur?', 'who' => 'Who was involved?', 'how' => 'How was it discovered?'] as $field => $label)
            <div class="field field--required" data-required="{{ $field }}">
              <label for="{{ $field }}">{{ $label }} <span class="req">*</span></label>
              <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ in_array($field, ['what','why']) ? 4 : 3 }}" required>{{ $five[$field] ?? '' }}</textarea>
            </div>
          @endforeach
        </div>
      </section>

      <section class="enterprise-card" id="evidenceSection" data-required="evidence">
        <div class="enterprise-section-head">
          <h2>EVIDENCE REQUIREMENTS <span class="req">*</span></h2>
          <p class="section-hint">Upload at least one supporting file (PDF, PNG, or JPG). This section is required.</p>
        </div>
        <div class="upload-zone" id="dropzone" role="button" tabindex="0">
          <p class="upload-title">Drag and drop files here</p>
          <p class="upload-sub">Accepted types: PDF, PNG, JPG (max 20MB)</p>
          <button type="button" class="btn-outline btn-upload" id="browseBtn">Browse files</button>
          <input id="fileInput" name="attachments" type="file" multiple accept=".pdf,.png,.jpg,.jpeg" style="display:none">
        </div>
        @if (count($attachments) > 0)
          <ul class="upload-preview upload-preview--saved">
            @foreach ($attachments as $e)
              <li class="upload-preview-item upload-preview-item--saved">
                <span class="upload-name"><a href="/supervisor/attachments/{{ urlencode($e['id']) }}" target="_blank" rel="noopener">{{ $e['name'] }}</a></span>
                <span class="upload-meta">{{ !empty($e['uploadedAt']) ? \Illuminate\Support\Carbon::parse($e['uploadedAt'])->format('Y-m-d H:i') : '' }}</span>
                <label class="attach-remove"><input type="checkbox" name="removeAttachmentIds" value="{{ $e['id'] }}"> Remove</label>
              </li>
            @endforeach
          </ul>
        @endif
        <div class="upload-pending-wrap" id="pendingUploads" hidden>
          <ul class="upload-preview upload-preview--pending" id="filePreview"></ul>
        </div>
        <div class="upload-message" id="uploadMessage" role="status"></div>
      </section>

      @if ($isRevise ?? false)
        <p class="revision-required-hint" id="revisionRequiredHint" role="status">Make at least one change to the report details or evidence before continuing.</p>
      @endif

      <div class="enterprise-actions enterprise-actions--split">
        @if ($isEdit ?? false)
          <a href="/supervisor/tickets" class="btn-enterprise-outline">Back to My Tickets</a>
        @endif
        <button type="submit" id="nextBtn" class="btn-enterprise-primary btn-enterprise-next" disabled>
          {{ ($isRevise ?? false) || ($isEdit ?? false) ? 'UPDATE & PREVIEW' : 'NEXT: SUMMARY PREVIEW' }}
        </button>
      </div>
    </form>
  </div>

  <div class="ai-loading-overlay" id="aiLoading" aria-hidden="true" style="display:none">
    <div class="ai-loading-card">
      <div class="ai-spinner" aria-hidden="true"></div>
      <div class="ai-loading-text">Generating AI summary…</div>
    </div>
  </div>

  @include('supervisor.partials.ticket-form-scripts', [
    'savedCount' => count($attachments),
    'isRevise' => (bool) ($isRevise ?? false),
    'initialSnapshot' => $initialSnapshot,
  ])
@endsection
