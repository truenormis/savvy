<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\IdentityProviderController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WebauthnController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('auth/status', [AuthController::class, 'status']);
Route::get('auth/me', [AuthController::class, 'me']);
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// 2FA verification (public - used during login flow)
Route::post('auth/2fa/verify', [TwoFactorController::class, 'verify']);

// Passkey (WebAuthn) login — public, usernameless / step-up
Route::prefix('auth/webauthn')->middleware('throttle:30,1')->group(function () {
    Route::post('login/options', [WebauthnController::class, 'loginOptions']);
    Route::post('login/verify', [WebauthnController::class, 'loginVerify']);
});

// SSO (public, browser-driven redirect flow)
Route::prefix('auth/sso')->middleware('throttle:30,1')->group(function () {
    Route::get('providers', [SsoController::class, 'providers'])->name('sso.providers');
    Route::post('exchange', [SsoController::class, 'exchange'])->name('sso.exchange');
    Route::get('{slug}/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('{slug}/callback', [SsoController::class, 'callback'])->name('sso.callback');
    Route::post('{slug}/acs', [SsoController::class, 'acs'])->name('sso.acs');
    Route::get('{slug}/metadata', [SsoController::class, 'metadata'])->name('sso.metadata');
});

// Signed, keyless multipart part upload (parallel, presigned-style direct to volume)
Route::put('uploads/{upload}/parts/{partNumber}', [UploadController::class, 'uploadPart'])
    ->whereNumber('partNumber')
    ->middleware('signed')
    ->name('uploads.part');

// Protected routes
Route::middleware(['session', 'csrf'])->group(function () {
    // Auth routes (available to all authenticated users)
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // 2FA status (available to all authenticated users)
    Route::get('auth/2fa/status', [TwoFactorController::class, 'status']);

    // Passkeys: list for all authenticated users
    Route::get('auth/webauthn/credentials', [WebauthnController::class, 'index']);

    // 2FA configuration (not available to read-only users)
    Route::middleware('write')->group(function () {
        Route::post('auth/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('auth/2fa/disable', [TwoFactorController::class, 'disable']);
        Route::get('auth/2fa/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
        Route::post('auth/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);

        // Passkey registration & management (not for read-only users)
        Route::post('auth/webauthn/register/options', [WebauthnController::class, 'registerOptions']);
        Route::post('auth/webauthn/register/verify', [WebauthnController::class, 'registerVerify']);
        Route::patch('auth/webauthn/credentials/{credential}', [WebauthnController::class, 'update']);
        Route::delete('auth/webauthn/credentials/{credential}', [WebauthnController::class, 'destroy']);
    });

    // Users: read for all, write for admin only
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        // SSO identity provider administration
        Route::get('auth/sso/presets', [IdentityProviderController::class, 'presets']);
        Route::post('identity-providers/{identity_provider}/test', [IdentityProviderController::class, 'test']);
        Route::apiResource('identity-providers', IdentityProviderController::class);
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

        // Universal S3-compatible multipart uploads (Uppy AwsS3 companion protocol)
        Route::prefix('s3/multipart')->group(function () {
            Route::post('/', [UploadController::class, 'create']);
            Route::get('{upload}', [UploadController::class, 'listParts']);
            Route::get('{upload}/{partNumber}', [UploadController::class, 'signPart'])->whereNumber('partNumber');
            Route::post('{upload}/complete', [UploadController::class, 'complete']);
            Route::delete('{upload}', [UploadController::class, 'abort']);
        });

        // Transaction Import (async pipeline backed by multipart uploads)
        Route::prefix('transactions/import')->group(function () {
            Route::post('parse', [TransactionImportController::class, 'parse']);
            Route::post('preview', [TransactionImportController::class, 'preview']);
            Route::post('execute', [TransactionImportController::class, 'execute']);
            Route::get('{import}', [TransactionImportController::class, 'show'])->whereUuid('import');
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

        // Monitoring (volume / storage telemetry)
        Route::prefix('monitoring')->group(function () {
            Route::get('storage', [MonitoringController::class, 'storage']);
            Route::get('resources', [MonitoringController::class, 'resources']);
        });

        // Settings (admin + read-write can modify)
        Route::get('settings', [SettingsController::class, 'index']);
        Route::patch('settings', [SettingsController::class, 'update']);

        // Backups
        Route::prefix('backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/', [BackupController::class, 'store']);
            Route::post('upload', [BackupController::class, 'upload']);
            Route::get('{backup}/download', [BackupController::class, 'download']);
            Route::post('{backup}/restore', [BackupController::class, 'restore']);
            Route::delete('{backup}', [BackupController::class, 'destroy']);
        });

        // Automation Rules
        Route::get('automation-rules/triggers', [AutomationRuleController::class, 'triggers']);
        Route::apiResource('automation-rules', AutomationRuleController::class);
        Route::post('automation-rules/{automationRule}/toggle', [AutomationRuleController::class, 'toggle']);
        Route::post('automation-rules/{automationRule}/test', [AutomationRuleController::class, 'test']);
        Route::get('automation-rules/{automationRule}/logs', [AutomationRuleController::class, 'logs']);
        Route::post('automation-rules/reorder', [AutomationRuleController::class, 'reorder']);
    });
});
