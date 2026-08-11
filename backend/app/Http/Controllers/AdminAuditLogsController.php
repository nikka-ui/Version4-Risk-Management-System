<?php

namespace App\Http\Controllers;

use App\Services\AdminAuditLogsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogsController extends Controller
{
    public function __construct(
        private readonly AdminAuditLogsService $service,
    ) {}

    public function index(Request $request): View
    {
        $payload = $this->service->list(
            $request->query('q'),
            $request->query('date'),
            $request->query('user'),
            $request->query('action'),
            $request->query('module'),
        );

        return view('admin.audit-logs', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'audit',
            'title' => 'Audit Logs',
            'logs' => $payload['logs'],
            'options' => $payload['options'],
            'filters' => $payload['filters'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }
}

