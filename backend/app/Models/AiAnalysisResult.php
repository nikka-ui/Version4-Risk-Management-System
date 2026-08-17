<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 11 slice 2: historical AI classify run (risk_tickets.ai remains live display SoT).
 */
class AiAnalysisResult extends Model
{
    protected $table = 'ai_analysis_results';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_reference',
        'source',
        'risk_category',
        'likelihood',
        'impact',
        'severity',
        'confidence',
        'responsible_department',
        'priority',
        'input',
        'result',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'likelihood' => 'integer',
            'impact' => 'integer',
            'severity' => 'integer',
            'confidence' => 'float',
            'input' => 'array',
            'result' => 'array',
        ];
    }

    /**
     * Payload for admin Blade + ticket-scoped API.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        $result = is_array($this->result) ? $this->result : [];
        $summary = is_string($result['summary'] ?? null) ? $result['summary'] : null;

        return [
            'id' => $this->id,
            'ticketReference' => $this->ticket_reference,
            'source' => $this->source,
            'riskCategory' => $this->risk_category,
            'likelihood' => $this->likelihood,
            'impact' => $this->impact,
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            'responsibleDepartment' => $this->responsible_department,
            'priority' => $this->priority,
            'summary' => $summary,
            'input' => $this->input,
            'result' => $result,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
