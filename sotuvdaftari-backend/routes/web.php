<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'version' => '1.0.0',
]));

// Admin panel (sessiya orqali, faqat `is_admin` foydalanuvchilar)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('users', [UserController::class, 'index'])->name('users');
        Route::get('users/{id}', [UserController::class, 'show'])->name('users.show');
        Route::post('users/{id}/block', [UserController::class, 'block'])->name('users.block');
        Route::post('users/{id}/unblock', [UserController::class, 'unblock'])->name('users.unblock');
        Route::post('users/{id}/grant-plan', [UserController::class, 'grantPlan'])->name('users.grant');

        Route::get('plans', [PlanController::class, 'index'])->name('plans');
        Route::put('plans/{key}', [PlanController::class, 'update'])->name('plans.update');

        Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals');
        Route::post('withdrawals/{id}/paid', [WithdrawalController::class, 'paid'])->name('withdrawals.paid');
        Route::post('withdrawals/{id}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');

        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('announcements/{id}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });
});
