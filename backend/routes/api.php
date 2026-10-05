<?php

use App\Http\Controllers\PmsDocumentAnalysisController;
use App\Http\Controllers\PmsDocumentController;
use App\Http\Controllers\PmsDocumentExampleController;
use App\Http\Controllers\PmsDocumentTicketController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketFromEmailController;
use App\Http\Controllers\TicketTriageController;
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
    ->post('/tickets/preview', [TicketFromEmailController::class, 'preview']);

Route::middleware([ClientKeyMiddleware::class])
    ->get('/tickets/priorities', [TicketFromEmailController::class, 'priorities']);

Route::middleware([ClientKeyMiddleware::class])
    ->get('/tickets/sprint-options', [TicketFromEmailController::class, 'sprintOptions']);

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/{issueId}/attachments/chunks', [TicketAttachmentController::class, 'storeChunk'])
    ->where('issueId', '[A-Za-z][A-Za-z0-9_]*-[0-9]+');

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/{issueId}/attachments/finalize', [TicketAttachmentController::class, 'finalize'])
    ->where('issueId', '[A-Za-z][A-Za-z0-9_]*-[0-9]+');

Route::middleware([ClientKeyMiddleware::class])
    ->get('/youtrack/issues/{issueId}', [YouTrackIssueController::class, 'show']);

Route::middleware([ClientKeyMiddleware::class])
    ->patch('/youtrack/issues/{issueId}', [YouTrackIssueController::class, 'update']);

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/{issueId}/triage', [TicketTriageController::class, 'store'])
    ->where('issueId', '[A-Za-z][A-Za-z0-9_]*-[0-9]+');

Route::middleware([ClientKeyMiddleware::class])
    ->get('/tickets/{issueId}/triage', [TicketTriageController::class, 'show'])
    ->where('issueId', '[A-Za-z][A-Za-z0-9_]*-[0-9]+');

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/{issueId}/triage/accept', [TicketTriageController::class, 'accept'])
    ->where('issueId', '[A-Za-z][A-Za-z0-9_]*-[0-9]+');
