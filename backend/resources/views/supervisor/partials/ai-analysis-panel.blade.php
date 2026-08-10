@php
  $ai = is_array($ticket['ai'] ?? null) ? $ticket['ai'] : [];
  $category = $ai['riskCategory'] ?? ($ticket['category'] ?? '—');
  $categoryLabel = $category !== '—' ? str_replace('_', ' ', ucfirst((string) $category)) : '—';
  $dept = $ticket['department'] ?? ($ai['responsibleDepartment'] ?? '—');
  $priority = $ticket['priority'] ?? ($ai['priority'] ?? null);
  $likelihood = $ai['likelihood'] ?? ($ticket['likelihood'] ?? '—');
  $impact = $ai['impact'] ?? ($ticket['impact'] ?? '—');
  $confidence = isset($ai['confidence']) ? round((float) $ai['confidence'] * 100).'%' : '—';
@endphp
<section class="enterprise-card enterprise-card--ai ai-panel">
  <div class="enterprise-section-head enterprise-section-head--tight">
    <h2>{{ ($preview ?? false) ? 'AI PREVIEW' : 'AI CLASSIFICATION & ROUTING' }}</h2>
    <div class="ai-badge"><span class="ai-badge__dot" aria-hidden="true"></span><span>{{ ($preview ?? false) ? 'Preview' : 'Post-submission analysis' }}</span></div>
  </div>
  <div class="ai-preview-grid">
    <div class="ai-summary">
      <div class="ai-summary-head"><strong>Incident summary</strong></div>
      <p>{{ $ai['summary'] ?? '—' }}</p>
      @if (!empty($ai['suggestedMitigation']))
        <div class="ai-mitigation-suggestion"><strong>Suggested initial mitigation</strong><p>{{ $ai['suggestedMitigation'] }}</p></div>
      @endif
    </div>
    <div class="ai-analysis">
      <div class="ai-analysis-card">
        <div class="ai-analysis-row"><span class="ai-analysis-label">Risk category</span><span class="ai-analysis-value">{{ $categoryLabel }}</span></div>
        <div class="ai-analysis-row"><span class="ai-analysis-label">Responsible department</span><span class="ai-analysis-value ai-dept-value">{{ $dept }}</span></div>
        <div class="ai-analysis-row"><span class="ai-analysis-label">Priority</span><span class="ai-analysis-value">{{ $priority ?: '—' }}</span></div>
        <div class="ai-analysis-row"><span class="ai-analysis-label">Confidence</span><span class="ai-analysis-value">{{ $confidence }}</span></div>
        <div class="ai-analysis-row"><span class="ai-analysis-label">Likelihood × Impact</span><span class="ai-analysis-value">{{ $likelihood }}/5 × {{ $impact }}/5</span></div>
      </div>
    </div>
  </div>
  @if ($preview ?? false)
    <p class="text-muted routing-note">Final department assignment is confirmed when you submit the ticket.</p>
  @endif
</section>
