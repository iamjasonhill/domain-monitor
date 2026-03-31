<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DetectedIssueSummaryService;
use Illuminate\Http\JsonResponse;

class DetectedIssueController extends Controller
{
    public function index(DetectedIssueSummaryService $service): JsonResponse
    {
        return response()->json($service->snapshot());
    }

    public function show(string $issueId, DetectedIssueSummaryService $service): JsonResponse
    {
        $issue = $service->find($issueId);

        if ($issue === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($issue);
    }
}
