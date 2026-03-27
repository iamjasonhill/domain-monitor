<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardIssueQueueService;
use Illuminate\Http\JsonResponse;

class DashboardPriorityQueueController extends Controller
{
    public function __invoke(DashboardIssueQueueService $service): JsonResponse
    {
        return response()->json($service->snapshot());
    }
}
