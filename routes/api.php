<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('auth/status', [AuthController::class, 'status']);
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// 2FA verification (public - used during login flow)
Route::post('auth/2fa/verify', [TwoFactorController::class, 'verify']);

// Protected routes
Route::middleware('jwt')->group(function () {
    // Auth routes (available to all authenticated users)
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // 2FA status (available to all authenticated users)
    Route::get('auth/2fa/status', [TwoFactorController::class, 'status']);

    // 2FA configuration (not available to read-only users)
    Route::middleware('write')->group(function () {
        Route::post('auth/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('auth/2fa/disable', [TwoFactorController::class, 'disable']);
        Route::get('auth/2fa/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
        Route::post('auth/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });

    // Users: read for all, write for admin only
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });

    // Resources with write access control
    Route::middleware('write')->group(function () {
        Route::apiResource('currencies', CurrencyController::class);
        Route::post('currencies/{currency}/set-base', [CurrencyController::class, 'setBase']);
        Route::post('currencies/convert', [CurrencyController::class, 'convert']);

        Route::apiResource('accounts', AccountController::class);
        Route::get('accounts-balance-history', [AccountController::class, 'balanceHistory']);
        Route::get('accounts-balance-comparison', [AccountController::class, 'balanceComparison']);

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

        // Transaction Import
        Route::prefix('transactions/import')->group(function () {
            Route::post('parse', [TransactionImportController::class, 'parse']);
            Route::post('preview', [TransactionImportController::class, 'preview']);
            Route::post('/', [TransactionImportController::class, 'import']);
        });

        Route::apiResource('budgets', BudgetController::class);

        // Recurring Transactions
        Route::get('recurring-upcoming', [RecurringTransactionController::class, 'upcoming']);
        Route::apiResource('recurring', RecurringTransactionController::class);
        Route::post('recurring/{recurring}/skip', [RecurringTransactionController::class, 'skip']);

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

        // Settings (admin + read-write can modify)
        Route::get('settings', [SettingsController::class, 'index']);
        Route::patch('settings', [SettingsController::class, 'update']);

        // Automation Rules
        Route::get('automation-rules/triggers', [AutomationRuleController::class, 'triggers']);
        Route::apiResource('automation-rules', AutomationRuleController::class);
        Route::post('automation-rules/{automationRule}/toggle', [AutomationRuleController::class, 'toggle']);
        Route::post('automation-rules/{automationRule}/test', [AutomationRuleController::class, 'test']);
        Route::get('automation-rules/{automationRule}/logs', [AutomationRuleController::class, 'logs']);
        Route::post('automation-rules/reorder', [AutomationRuleController::class, 'reorder']);
    });
});

