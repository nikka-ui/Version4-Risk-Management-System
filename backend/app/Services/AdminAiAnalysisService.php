<?php

namespace App\Services;

use App\Models\AiAnalysisResult;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 11 slice 3: admin AI classify history list from Postgres.
 */
class AdminAiAnalysisService
{
    /**
     * @return array{
     *   runs: list<array<string, mixed>>,
     *   options: array{sources: list<string>, categories: list<string>},
     *   filters: array<string, string>,
     * }
     */
    public function list(
        ?string $q,
        ?string $source,
        ?string $category,
        ?string $ticket,
        int $limit = 200,
    ): array {
        $filters = [
            'q' => $q ?? '',
            'source' => $source ?? '',
            'category' => $category ?? '',
            'ticket' => $ticket ?? '',
        ];

        $query = AiAnalysisResult::query()->orderByDesc('id');
        $this->applyFilters($query, $filters);

        $runs = $query
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (AiAnalysisResult $row) => $row->toListArray())
            ->all();

        return [
            'runs' => $runs,
            'options' => $this->options(),
            'filters' => $filters,
        ];
    }

    /**
     * @param  Builder<AiAnalysisResult>  $query
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $needle = mb_strtolower(trim($filters['q']));
        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->whereRaw('LOWER(COALESCE(ticket_reference, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(source, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(risk_category, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(responsible_department, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(priority, \'\')) LIKE ?', [$like]);
            });
        }

        if ($filters['source'] !== '') {
            $query->where('source', $filters['source']);
        }

        if ($filters['category'] !== '') {
            $query->where('risk_category', $filters['category']);
        }

        if ($filters['ticket'] !== '') {
            $query->where('ticket_reference', $filters['ticket']);
        }
    }

    /**
     * @return array{sources: list<string>, categories: list<string>}
     */
    private function options(): array
    {
        $sources = AiAnalysisResult::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->map(fn ($v) => (string) $v)
            ->all();

        $categories = AiAnalysisResult::query()
            ->whereNotNull('risk_category')
            ->where('risk_category', '!=', '')
            ->distinct()
            ->orderBy('risk_category')
            ->pluck('risk_category')
            ->map(fn ($v) => (string) $v)
            ->all();

        return [
            'sources' => $sources,
            'categories' => $categories,
        ];
    }
}
