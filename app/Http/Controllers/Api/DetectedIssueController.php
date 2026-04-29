<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListDetectedIssuesRequest;
use App\Services\DetectedIssueSummaryService;
use Illuminate\Http\JsonResponse;

class DetectedIssueController extends Controller
{
    public function index(ListDetectedIssuesRequest $request, DetectedIssueSummaryService $service): JsonResponse
    {
        $request->validated();
        $fleetFocus = $request->has('fleet_focus')
            ? $request->boolean('fleet_focus')
            : null;

        return response()->json($service->snapshot(
            $fleetFocus
        ));
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
