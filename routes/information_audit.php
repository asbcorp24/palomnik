<?php

use App\Http\Controllers\Admin\ObjectInformationAuditController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/information-audit')
    ->name('admin.information-audit.')
    ->middleware(['auth', 'verified', 'admin'])
    ->group(function (): void {
        Route::get('/', [ObjectInformationAuditController::class, 'index'])->name('index');
        Route::put('/{object}/verify', [ObjectInformationAuditController::class, 'verify'])->name('verify');
    });
