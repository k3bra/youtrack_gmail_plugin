<?php

use App\Http\Controllers\PmsDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pms-documents/{pmsDocument}/download', [PmsDocumentController::class, 'download']);
Route::view('/pms-documents/{pmsDocument?}', 'pms-documents');

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});
