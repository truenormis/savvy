<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;


Route::get('auth/status', [AuthController::class, 'status']);
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('jwt')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::apiResource('currencies', CurrencyController::class);
    Route::post('currencies/{currency}/set-base', [CurrencyController::class, 'setBase']);
    Route::post('currencies/convert', [CurrencyController::class, 'convert']);

    Route::apiResource('accounts', AccountController::class);
    Route::get('accounts-balance-history', [AccountController::class, 'balanceHistory']);

    Route::apiResource('categories', CategoryController::class);
    Route::get('categories/{category}/statistics', [CategoryController::class, 'statistics']);
    Route::get('categories-summary', [CategoryController::class, 'summary']);

    Route::apiResource('transactions', TransactionController::class);
    Route::post('transactions/{transaction}/duplicate', [TransactionController::class, 'duplicate']);
    Route::get('transactions-summary', [TransactionController::class, 'summary']);

    Route::apiResource('budgets', BudgetController::class);

    Route::apiResource('tags', TagController::class);

    Route::get('reports/options', [ReportController::class, 'options']);
    Route::post('reports/generate', [ReportController::class, 'generate']);
});

