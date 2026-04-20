<?php

use App\Http\Controllers\Api\DashboardPriorityQueueController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\DetectedIssueController;
use App\Http\Controllers\Api\DetectedIssueVerificationController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\IntegrationMetaController;
use App\Http\Controllers\Api\RuntimeAnalyticsContextController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\WebPropertyAstroCutoverController;
use App\Http\Controllers\Api\WebPropertyController;
use App\Http\Controllers\Api\WebPropertyFleetContextRefreshController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['api-key'])->group(function () {
    Route::get('/meta/integrations', IntegrationMetaController::class);

    // Domains
    Route::get('/domains', [DomainController::class, 'index']);
    Route::get('/domains/{domain}', [DomainController::class, 'show']);
    Route::patch('/domains/{domain}', [DomainController::class, 'update']);
    Route::post('/domains/{domain}/tags/{tag}', [DomainController::class, 'addTag']);
    Route::delete('/domains/{domain}/tags/{tag}', [DomainController::class, 'removeTag']);

    // Web properties
    Route::get('/web-properties', [WebPropertyController::class, 'index']);
    Route::get('/web-properties-summary', [WebPropertyController::class, 'summary']);
    Route::get('/runtime/analytics-contexts', RuntimeAnalyticsContextController::class);
    Route::get('/web-properties/{slug}', [WebPropertyController::class, 'show']);
    Route::post('/web-properties/{slug}/astro-cutover', WebPropertyAstroCutoverController::class)
        ->middleware('fleet-control-api-key');
    Route::post('/web-properties/{slug}/refresh-fleet-context', WebPropertyFleetContextRefreshController::class)
        ->middleware('fleet-control-api-key');
    Route::get('/web-properties/{slug}/health-summary', [WebPropertyController::class, 'healthSummary']);
    Route::get('/dashboard/priority-queue', DashboardPriorityQueueController::class);
    Route::get('/issues', [DetectedIssueController::class, 'index']);
    Route::get('/issues/{issueId}', [DetectedIssueController::class, 'show']);
    Route::post('/issues/{issueId}/verification', [DetectedIssueVerificationController::class, 'store'])
        ->middleware('fleet-control-api-key');

    // Tags
    Route::get('/tags', [TagController::class, 'index']);
    Route::get('/tags/{tag}/domains', [TagController::class, 'domains']);

    // Deployments
    Route::post('/deployments', [DeploymentController::class, 'store']);
});
