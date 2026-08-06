<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RiskTicket extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'reference',
        'title',
        'description',
        'location',
        'status',
        'category',
        'priority',
        'department',
        'reporter_department',
        'likelihood',
        'impact',
        'risk_score',
        'submitted_by',
        'submitted_by_name',
        'mitigation_approach',
        'evidence_count',
        'accomplishment_external_id',
        'source_created_at',
        'source_updated_at',
        'submitted_at',
        'routed_at',
        'mitigation_due_at',
        'deleted',
        'deleted_at',
        'deleted_by',
        'deleted_by_name',
        'deletion_reason',
        'five_w1h',
        'ai',
        'ownership',
        'action_plan',
        'personnel',
        'progress_updates',
        'reassignments',
        'audit_trail',
        'thread_comments',
        'private_comments',
        'executive_comments',
        'mitigation_plan_history',
        'reopen_history',
        'president_plan_decision',
        'president_final_decision',
        'president_decision',
        'closure',
        'final_resolution',
        'rmu_recommendations',
        'escalations',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'likelihood' => 'integer',
            'impact' => 'integer',
            'risk_score' => 'integer',
            'evidence_count' => 'integer',
            'deleted' => 'boolean',
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'routed_at' => 'datetime',
            'mitigation_due_at' => 'datetime',
            'deleted_at' => 'datetime',
            'five_w1h' => 'array',
            'ai' => 'array',
            'ownership' => 'array',
            'action_plan' => 'array',
            'personnel' => 'array',
            'progress_updates' => 'array',
            'reassignments' => 'array',
            'audit_trail' => 'array',
            'thread_comments' => 'array',
            'private_comments' => 'array',
            'executive_comments' => 'array',
            'mitigation_plan_history' => 'array',
            'reopen_history' => 'array',
            'president_plan_decision' => 'array',
            'president_final_decision' => 'array',
            'president_decision' => 'array',
            'closure' => 'array',
            'final_resolution' => 'array',
            'rmu_recommendations' => 'array',
            'escalations' => 'array',
            'payload' => 'array',
        ];
    }

    public function accomplishment(): HasOne
    {
        return $this->hasOne(Accomplishment::class, 'ticket_ref', 'reference');
    }

    /**
     * Full Express-shaped ticket payload for API detail responses.
     *
     * @return array<string, mixed>
     */
    public function toExpressArray(): array
    {
        $base = [
            'id' => $this->external_id,
            'reference' => $this->reference,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'location' => $this->location ?? '',
            'status' => $this->status,
            'category' => $this->category,
            'priority' => $this->priority,
            'department' => $this->department,
            'reporterDepartment' => $this->reporter_department,
            'likelihood' => $this->likelihood,
            'impact' => $this->impact,
            'riskScore' => $this->risk_score,
            'submittedBy' => $this->submitted_by,
            'submittedByName' => $this->submitted_by_name,
            'mitigationApproach' => $this->mitigation_approach,
            'evidenceCount' => $this->evidence_count,
            'accomplishmentId' => $this->accomplishment_external_id,
            'createdAt' => optional($this->source_created_at)?->toIso8601String(),
            'updatedAt' => optional($this->source_updated_at)?->toIso8601String(),
            'submittedAt' => optional($this->submitted_at)?->toIso8601String(),
            'routedAt' => optional($this->routed_at)?->toIso8601String(),
            'mitigationDueAt' => optional($this->mitigation_due_at)?->toIso8601String(),
            'deleted' => $this->deleted,
            'deletedAt' => optional($this->deleted_at)?->toIso8601String(),
            'deletedBy' => $this->deleted_by,
            'deletedByName' => $this->deleted_by_name,
            'deletionReason' => $this->deletion_reason,
            'fiveW1H' => $this->five_w1h,
            'ai' => $this->ai,
            'ownership' => $this->ownership,
            'actionPlan' => $this->action_plan,
            'personnel' => $this->personnel ?? [],
            'progressUpdates' => $this->progress_updates ?? [],
            'reassignments' => $this->reassignments ?? [],
            'auditTrail' => $this->audit_trail ?? [],
            'threadComments' => $this->thread_comments ?? [],
            'privateComments' => $this->private_comments ?? [],
            'executiveComments' => $this->executive_comments ?? [],
            'mitigationPlanHistory' => $this->mitigation_plan_history ?? [],
            'reopenHistory' => $this->reopen_history ?? [],
            'presidentPlanDecision' => $this->president_plan_decision,
            'presidentFinalDecision' => $this->president_final_decision,
            'presidentDecision' => $this->president_decision,
            'closure' => $this->closure,
            'finalResolution' => $this->final_resolution,
            'rmuRecommendations' => $this->rmu_recommendations ?? [],
            'escalations' => $this->escalations ?? [],
        ];

        $payload = is_array($this->payload) ? $this->payload : [];

        return array_merge($payload, $base);
    }

    /**
     * Compact list DTO (similar to Express publicTicket scalars).
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        $ownership = is_array($this->ownership) ? $this->ownership : [];
        $ai = is_array($this->ai) ? $this->ai : [];
        $riskLevel = is_array($ai['riskLevel'] ?? null) ? $ai['riskLevel'] : null;

        return [
            'id' => $this->external_id,
            'reference' => $this->reference,
            'title' => $this->title ?? '',
            'status' => $this->status,
            'category' => $this->category,
            'priority' => $this->priority,
            'department' => $this->department,
            'reporterDepartment' => $this->reporter_department,
            'likelihood' => $this->likelihood,
            'impact' => $this->impact,
            'riskScore' => $this->risk_score,
            'riskLevel' => $riskLevel,
            'submittedBy' => $this->submitted_by,
            'submittedByName' => $this->submitted_by_name,
            'evidenceCount' => $this->evidence_count,
            'ownershipState' => $ownership['state'] ?? null,
            'ownerName' => $ownership['ownerName'] ?? null,
            'createdAt' => optional($this->source_created_at)?->toIso8601String(),
            'updatedAt' => optional($this->source_updated_at)?->toIso8601String(),
            'submittedAt' => optional($this->submitted_at)?->toIso8601String(),
            'deleted' => $this->deleted,
        ];
    }
}
