<?php

namespace App\Http\Controllers;

use App\Services\ReportLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3 slice 9: report-log mirror APIs (Laravel-owned report_logs table).
 */
class ReportLogController extends Controller
{
    public function __construct(
        private readonly ReportLogService $reportLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 200);

        $items = collect($this->reportLogs->list($limit))
            ->map(fn ($r) => $r->toExpressArray())
            ->values();

        return response()->json([
            'reportLogs' => $items,
            'count' => $items->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $log = $this->reportLogs->append($request->all());

        return response()->json(['reportLog' => $log->toExpressArray()], 201);
    }
}
