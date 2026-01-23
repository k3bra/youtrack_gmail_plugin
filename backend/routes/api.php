<?php

use App\Http\Controllers\PmsDocumentAnalysisController;
use App\Http\Controllers\PmsDocumentController;
use App\Http\Controllers\PmsDocumentExampleController;
use App\Http\Controllers\PmsDocumentTicketController;
use App\Http\Controllers\TicketFromEmailController;
use App\Http\Controllers\YouTrackIssueController;
use App\Http\Middleware\ClientKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/pms-documents', [PmsDocumentController::class, 'index']);
Route::get('/pms-documents/{pmsDocument}', [PmsDocumentController::class, 'show']);
Route::post('/pms-documents', [PmsDocumentController::class, 'store']);
Route::patch('/pms-documents/{pmsDocument}', [PmsDocumentController::class, 'update']);
Route::post('/pms-documents/{pmsDocument}/analyze', [PmsDocumentAnalysisController::class, 'store']);
Route::post('/pms-documents/{pmsDocument}/example', [PmsDocumentExampleController::class, 'store']);
Route::post('/pms-documents/{pmsDocument}/ticket', [PmsDocumentTicketController::class, 'store']);
Route::get('/pms-documents/{pmsDocument}/tickets', [PmsDocumentTicketController::class, 'index']);

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/from-email', [TicketFromEmailController::class, 'store']);

Route::middleware([ClientKeyMiddleware::class])
    ->get('/youtrack/issues/{issueId}', [YouTrackIssueController::class, 'show']);

Route::middleware([ClientKeyMiddleware::class])
    ->patch('/youtrack/issues/{issueId}', [YouTrackIssueController::class, 'update']);
