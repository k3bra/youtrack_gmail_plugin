<?php

use App\Http\Controllers\TicketFromEmailController;
use App\Http\Middleware\ClientKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/from-email', TicketFromEmailController::class);

