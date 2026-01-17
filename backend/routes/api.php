<?php

use App\Http\Controllers\TicketFromEmailController;
use App\Http\Middleware\ClientKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([ClientKeyMiddleware::class])
    ->post('/tickets/from-email', TicketFromEmailController::class);
