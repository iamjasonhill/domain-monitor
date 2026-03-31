<?php

use App\Http\Controllers\Api\DashboardPriorityQueueController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\DetectedIssueController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\WebPropertyController;
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
    // Domains
    Route::get('/domains', [DomainController::class, 'index']);
    Route::get('/domains/{domain}', [DomainController::class, 'show']);
    Route::patch('/domains/{domain}', [DomainController::class, 'update']);
    Route::post('/domains/{domain}/tags/{tag}', [DomainController::class, 'addTag']);
    Route::delete('/domains/{domain}/tags/{tag}', [DomainController::class, 'removeTag']);

    // Web properties
    Route::get('/web-properties', [WebPropertyController::class, 'index']);
    Route::get('/web-properties-summary', [WebPropertyController::class, 'summary']);
    Route::get('/web-properties/{slug}', [WebPropertyController::class, 'show']);
    Route::get('/web-properties/{slug}/health-summary', [WebPropertyController::class, 'healthSummary']);
    Route::get('/dashboard/priority-queue', DashboardPriorityQueueController::class);
    Route::get('/issues', [DetectedIssueController::class, 'index']);
    Route::get('/issues/{issueId}', [DetectedIssueController::class, 'show']);

    // Tags
    Route::get('/tags', [TagController::class, 'index']);
    Route::get('/tags/{tag}/domains', [TagController::class, 'domains']);

    // Deployments
    Route::post('/deployments', [DeploymentController::class, 'store']);
});
