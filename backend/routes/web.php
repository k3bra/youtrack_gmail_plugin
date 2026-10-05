<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PmsDocumentDownloadController;
use App\Http\Controllers\PmsDocumentTicketIndexController;
use App\Http\Controllers\PluginTicketDestroyController;
use App\Http\Controllers\PluginTicketIndexController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/plugin-tickets');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/pms-documents/{pmsDocument}/download', [PmsDocumentDownloadController::class, 'show']);
    Route::view('/pms-documents/{pmsDocument?}', 'pms-documents');
    Route::get('/pms-document-tickets', PmsDocumentTicketIndexController::class);
});

Route::get('/plugin-tickets', PluginTicketIndexController::class);
Route::delete('/plugin-tickets/{ticketRequest}', PluginTicketDestroyController::class);

Route::get('/youtrack/issues/{issueId}', function (string $issueId) {
    return view('youtrack-issue', ['issueId' => $issueId]);
});

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});
