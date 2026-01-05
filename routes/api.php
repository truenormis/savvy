<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DebtController;
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

    // Debts
    Route::apiResource('debts', DebtController::class);
    Route::post('debts/{debt}/payment', [DebtController::class, 'payment']);
    Route::post('debts/{debt}/collect', [DebtController::class, 'collect']);
    Route::post('debts/{debt}/reopen', [DebtController::class, 'reopen']);
    Route::get('debts-summary', [DebtController::class, 'summary']);

    Route::apiResource('categories', CategoryController::class);
    Route::get('categories/{category}/statistics', [CategoryController::class, 'statistics']);
    Route::get('categories-summary', [CategoryController::class, 'summary']);

    Route::apiResource('transactions', TransactionController::class);
    Route::post('transactions/{transaction}/duplicate', [TransactionController::class, 'duplicate']);
    Route::get('transactions-summary', [TransactionController::class, 'summary']);

    Route::apiResource('budgets', BudgetController::class);

    Route::apiResource('tags', TagController::class);

    Route::get('reports/overview', [ReportController::class, 'overview']);
    Route::get('reports/money-flow', [ReportController::class, 'moneyFlow']);
    Route::get('reports/expense-pace', [ReportController::class, 'expensePace']);
    Route::get('reports/expenses-by-category', [ReportController::class, 'expensesByCategory']);
    Route::get('reports/cash-flow-over-time', [ReportController::class, 'cashFlowOverTime']);
    Route::get('reports/activity-heatmap', [ReportController::class, 'activityHeatmap']);

    // Transaction Reports (Expenses/Income tabs)
    Route::get('reports/transactions/summary', [ReportController::class, 'transactionSummary']);
    Route::get('reports/transactions/by-category', [ReportController::class, 'transactionsByCategory']);
    Route::get('reports/transactions/dynamics', [ReportController::class, 'transactionDynamics']);
    Route::get('reports/transactions/top', [ReportController::class, 'topTransactions']);

    // Net Worth
    Route::get('reports/net-worth', [ReportController::class, 'netWorth']);
    Route::get('reports/net-worth-history', [ReportController::class, 'netWorthHistory']);
});

