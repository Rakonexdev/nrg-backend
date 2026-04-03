<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollectorAccountController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DeductionController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinancialController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\QidRenewalController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\TimesheetController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MobileCollectorController;
use Illuminate\Support\Facades\Route;

/* |-------------------------------------------------------------------------- | GGCS API Routes |-------------------------------------------------------------------------- */

// Auth (public)
Route::post('/login', [AuthController::class , 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class , 'logout']);
    Route::get('/user', [AuthController::class , 'user']);

    // Users (Super Admin)
    Route::middleware('role:Super Admin')->group(function () {
            Route::apiResource('users', UserController::class)->except('destroy');
            Route::patch('/users/{user}/status', [UserController::class , 'toggleStatus']);
            Route::get('/roles', [RolePermissionController::class , 'roles']);
            Route::get('/permissions', [RolePermissionController::class , 'permissions']);
            Route::put('/roles/{role}/permissions', [RolePermissionController::class , 'updateRolePermissions']);
            Route::get('/settings/menus', [RolePermissionController::class , 'getMenus']);
            Route::put('/settings/menus', [RolePermissionController::class , 'updateMenus']);
            Route::get('/settings', [SettingsController::class , 'index']);
            Route::put('/settings', [SettingsController::class , 'update']);
            Route::post('/settings/test-storage', [SettingsController::class , 'testStorage']);
        }
        );

        Route::middleware('role:Super Admin|Admin|Accountant|Client Manager')->group(function () {
            Route::apiResource('companies', CompanyController::class)->only(['index', 'store', 'show']);
            Route::apiResource('persons', PersonController::class)->except('destroy');
            Route::post('/persons/{person}/files', [PersonController::class , 'uploadFile']);
            Route::delete('/persons/{person}/files/{file}', [PersonController::class , 'deleteFile']);
            Route::apiResource('projects', ProjectController::class);
            Route::get('/quotations', [QuotationController::class , 'index']);
            Route::post('/quotations', [QuotationController::class , 'store']);
            Route::get('/quotations/{quotation}', [QuotationController::class , 'show']);
            Route::delete('/quotations/{quotation}', [QuotationController::class , 'destroy']);
            Route::post('/quotations/{quotation}/files', [QuotationController::class , 'uploadFile']);
            Route::delete('/quotations/{quotation}/files/{file}', [QuotationController::class , 'deleteFile']);
            Route::patch('/projects/{project}/status', [ProjectController::class , 'updateStatus']);
            Route::post('/projects/{project}/professions', [ProjectController::class , 'storeProfession']);
            Route::put('/projects/{project}/professions/{profession}', [ProjectController::class , 'updateProfession']);
            Route::delete('/projects/{project}/professions/{profession}', [ProjectController::class , 'destroyProfession']);
            Route::post('/projects/{project}/files', [ProjectController::class , 'uploadFile']);
            Route::delete('/projects/{project}/files/{file}', [ProjectController::class , 'deleteFile']);
            Route::get('/collector-assignments/collectors', [CollectorAccountController::class , 'collectors']);
            Route::post('/collector-assignments/collectors', [CollectorAccountController::class , 'storeCollector']);
            Route::get('/collector-assignments/accounts', [CollectorAccountController::class , 'index']);
            Route::post('/collector-assignments/accounts/{person}/assign', [CollectorAccountController::class , 'assign']);
            Route::patch('/collector-assignments/accounts/{person}/unassign', [CollectorAccountController::class , 'unassign']);
            Route::post('/uploads/quotation-copy', [UploadController::class , 'quotationCopy']);
        }
        );

        Route::middleware('role:Super Admin|Admin|Accountant|Collector')->group(function () {
            Route::get('/invoices', [InvoiceController::class , 'index']);
            Route::get('/invoices/{invoice}', [InvoiceController::class , 'show']);
            Route::apiResource('collections', CollectionController::class)->only(['index', 'store', 'show']);
            Route::get('/reports/dashboard-stats', [ReportController::class , 'dashboardStats']);
            Route::get('/reports/collections-feed', [ReportController::class , 'collectionsFeed']);
            Route::get('/reports/qid-renewals', [ReportController::class , 'qidRenewals']);
            Route::get('/reports/settlements', [ReportController::class , 'settlements']);
            Route::get('/collector/accounts', [CollectorAccountController::class , 'index']);
            Route::patch('/collector/accounts/{person}/identity', [CollectorAccountController::class , 'updateIdentity']);
            Route::get('/qid-renewals', [QidRenewalController::class , 'index']);
            Route::patch('/qid-renewals/{qidRenewal}', [QidRenewalController::class , 'update']);
            Route::get('/settlements', [SettlementController::class , 'index']);
            Route::get('/settlements/available-collections', [SettlementController::class , 'availableCollections']);
            Route::post('/settlements', [SettlementController::class , 'store']);
            Route::post('/uploads/id-photo', [UploadController::class , 'idPhoto']);
            Route::get('/collector/mobile/dashboard', [MobileCollectorController::class, 'dashboard']);
            Route::get('/collector/mobile/queue', [MobileCollectorController::class, 'queue']);
            Route::get('/collector/mobile/qids', [MobileCollectorController::class, 'qids']);
        }
        );

        Route::middleware('role:Super Admin|Admin|Accountant')->group(function () {
            Route::get('/timesheet-groups', [TimesheetController::class , 'groups']);
            Route::post('/timesheet-groups', [TimesheetController::class , 'storeGroup']);
            Route::apiResource('timesheets', TimesheetController::class);
            Route::post('/invoices', [InvoiceController::class , 'store']);
            Route::get('/deductions', [DeductionController::class , 'index']);
            Route::post('/deductions', [DeductionController::class , 'store']);
            Route::patch('/settlements/{settlement}/status', [SettlementController::class , 'updateStatus']);
            Route::patch('/collections/{collection}/verify', [CollectionController::class , 'verify']);
            Route::get('/expense-categories', [ExpenseController::class , 'categories']);
            Route::post('/expense-categories', [ExpenseController::class , 'storeCategory']);
            Route::put('/expense-categories/{category}', [ExpenseController::class , 'updateCategory']);
            Route::apiResource('expenses', ExpenseController::class)->only(['index', 'store', 'show']);
            Route::get('/financial-heads', [FinancialController::class , 'heads']);
            Route::post('/financial-heads', [FinancialController::class , 'storeHead']);
            Route::get('/financial-entries', [FinancialController::class , 'entries']);
            Route::post('/financial-entries', [FinancialController::class , 'storeEntry']);
            Route::get('/reports/outstanding-invoices', [ReportController::class , 'outstandingInvoices']);
            Route::get('/reports/collections-summary', [ReportController::class , 'collectionsSummary']);
            Route::get('/reports/deductions', [ReportController::class , 'deductions']);
            Route::get('/reports/finance-summary', [ReportController::class , 'financeSummary']);
            Route::get('/reports/expenses-by-category', [ReportController::class , 'expensesByCategory']);
            Route::get('/reports/export/{report}', [ReportController::class , 'export']);
            Route::get('/audit-logs', [ReportController::class , 'auditLogs']);
            Route::post('/uploads/lpo', [UploadController::class , 'lpo']);
            Route::post('/uploads/invoice-copy', [UploadController::class , 'invoiceCopy']);
            Route::post('/uploads/expense-document', [UploadController::class , 'expenseDocument']);
        }
        );    });
