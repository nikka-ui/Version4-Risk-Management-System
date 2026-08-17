<?php

namespace App\Http\Controllers;

use App\Services\AdminAiAnalysisService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 11 slice 3: System Administrator AI classify history (Blade GET).
 */
class AdminAiAnalysisController extends Controller
{
    public function __construct(
        private readonly AdminAiAnalysisService $service,
    ) {}

    public function index(Request $request): View
    {
        $payload = $this->service->list(
            $request->query('q'),
            $request->query('source'),
            $request->query('category'),
            $request->query('ticket'),
        );

        return view('admin.ai-analysis', [
            'user' => $request->user()->toIdentityArray(),
            'activeNav' => 'ai',
            'title' => 'AI Analysis History',
            'runs' => $payload['runs'],
            'options' => $payload['options'],
            'filters' => $payload['filters'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }
}
