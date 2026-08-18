<?php

namespace App\Http\Controllers;

use App\Services\AdminAuditLogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminAuditLogsApiController extends Controller
{
    public function __construct(
        private readonly AdminAuditLogsService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payload = $this->service->list(
            $request->query('q'),
            $request->query('date'),
            $request->query('user'),
            $request->query('action'),
            $request->query('module'),
        );

        return response()->json($payload);
    }

    public function export(Request $request): Response
    {
        $payload = $this->service->list(
            $request->query('q'),
            $request->query('date'),
            $request->query('user'),
            $request->query('action'),
            $request->query('module'),
            1000,
        );

        return response($this->service->toCsv($payload['logs']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-logs.csv"',
        ]);
    }
}
