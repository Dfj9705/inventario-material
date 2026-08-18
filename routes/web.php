<?php

use App\Http\Controllers\LoanSignatureController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->get(
        '/loans/{loan}/signature',
        LoanSignatureController::class
    )
    ->name('loans.signature');