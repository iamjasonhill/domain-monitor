<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordAstroCutoverRequest;
use App\Services\WebPropertyAstroCutoverRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class WebPropertyAstroCutoverController extends Controller
{
    public function __invoke(
        RecordAstroCutoverRequest $request,
        string $slug,
        WebPropertyAstroCutoverRecorder $recorder
    ): JsonResponse {
        try {
            $summary = $recorder->record(
                $slug,
                $request->date('astro_cutover_at'),
                $request->boolean('refresh_seo_baseline', true),
                $request->string('captured_by')->trim()->value() ?: null,
                $request->string('notes')->trim()->value() ?: null,
            );
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'error' => 'Web property not found.',
            ], 404);
        }

        return response()->json($summary, $summary['success'] ? 200 : 207);
    }
}
