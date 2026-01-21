<?php

use App\Http\Controllers\PmsDocumentController;
use App\Http\Controllers\TicketFromEmailController;
use App\Http\Middleware\ClientKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/pms-documents', [PmsDocumentController::class, 'index']);
Route::get('/pms-documents/{pmsDocument}', [PmsDocumentController::class, 'show']);
Route::post('/pms-documents', [PmsDocumentController::class, 'store']);
Route::post('/pms-documents/{pmsDocument}/analyze', [PmsDocumentController::class, 'analyze']);
Route::post('/pms-documents/{pmsDocument}/example', [PmsDocumentController::class, 'example']);
Route::post('/pms-documents/{pmsDocument}/ticket', [PmsDocumentController::class, 'ticket']);
Route::get('/pms-documents/{pmsDocument}/tickets', [PmsDocumentController::class, 'tickets']);

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/from-email', TicketFromEmailController::class);
