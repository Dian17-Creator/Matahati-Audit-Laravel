<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AuditCategoryController;
use App\Http\Controllers\AuditQuestionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StockCategoryController;
use App\Http\Controllers\StockDepartmentController;

Route::post('/login', [AuthController::class, 'login']);

//dashboard controller
Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

// Audit Category Api
Route::prefix('audit/categories')->group(function () {
    Route::get('/', [AuditCategoryController::class, 'index']);
    Route::get('/{id}', [AuditCategoryController::class, 'show']);
    Route::post('/', [AuditCategoryController::class, 'store']);

    Route::post('/{id}/update', [AuditCategoryController::class, 'update']);
    Route::post('/{id}/delete', [AuditCategoryController::class, 'destroy']);
});

//Audir Question Api
Route::get('/audit/categories/{categoryId}/questions', [AuditQuestionController::class, 'index']);
Route::post('/audit/questions', [AuditQuestionController::class, 'store']);
Route::post('/audit/questions/reorder', [AuditQuestionController::class, 'reorder']);
Route::post('/audit/questions/{id}', [AuditQuestionController::class, 'update']);
Route::post('/audit/questions/{id}/delete', [AuditQuestionController::class, 'destroy']);

// Audit Department Api
Route::get('/audit/departments', [\App\Http\Controllers\AuditDepartmentController::class, 'index']);
Route::get('/audit/departments/{id}/mapping', [\App\Http\Controllers\AuditDepartmentController::class, 'mapping']);
Route::post('/audit/departments/mapping', [\App\Http\Controllers\AuditDepartmentController::class, 'storeMapping']);

// Audit Reports / Execution API
Route::prefix('audits')->group(function () {
    Route::get('/', [\App\Http\Controllers\AuditReportController::class, 'index']);
    Route::get('/detail', [\App\Http\Controllers\AuditReportController::class, 'show']);
    Route::get('/{id}/export-pdf', [\App\Http\Controllers\AuditReportController::class, 'exportPdf']);
    Route::post('/create', [\App\Http\Controllers\AuditReportController::class, 'store']);
    Route::post('/update', [\App\Http\Controllers\AuditReportController::class, 'updateAnswers']);
    Route::post('/upload-photo', [\App\Http\Controllers\AuditReportController::class, 'uploadPhoto']);
    Route::post('/update-photo', [\App\Http\Controllers\AuditReportController::class, 'updatePhoto']);
    Route::post('/delete-photo', [\App\Http\Controllers\AuditReportController::class, 'deletePhoto']);
    Route::post('/submit', [\App\Http\Controllers\AuditReportController::class, 'submit']);
    Route::post('/delete', [\App\Http\Controllers\AuditReportController::class, 'destroy']);
});

//API STOCK OPNAME

// Stock Category & Item Api
Route::prefix('stock')->group(function () {
    Route::get('/categories', [StockCategoryController::class, 'index']);
    Route::post('/categories', [StockCategoryController::class, 'storeCategory']);
    Route::put('/categories/{id?}', [StockCategoryController::class, 'updateCategory']);
    Route::delete('/categories/{id?}', [StockCategoryController::class, 'destroyCategory']);
    Route::post('/items', [StockCategoryController::class, 'storeItem']);
    Route::delete('/items/{id?}', [StockCategoryController::class, 'destroyItem']);
    Route::post('/items/reorder', [StockCategoryController::class, 'reorderItems']);
});

// STOCK DEPARTMENT
Route::prefix('stock/departments')->group(function () {
    Route::get('/', [StockDepartmentController::class, 'index']);
    Route::get('/{id}/mapping', [StockDepartmentController::class, 'mapping']);
    Route::post('/mapping', [StockDepartmentController::class, 'storeMapping']);
});
