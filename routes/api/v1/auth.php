<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthTokenController;
use App\Http\Controllers\Api\V1\Auth\UserController;
use App\Http\Controllers\Api\V1\Auth\UserPasswordController;
use App\Http\Controllers\Api\V1\Auth\UserSendEmailVerificationCodeController;
use App\Http\Controllers\Api\V1\Auth\UserSendPasswordResetCodeController;
use App\Http\Controllers\Api\V1\Auth\UserVerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::post('register', [UserController::class, 'store'])->name('auth.register');
Route::post('login', [AuthTokenController::class, 'store'])->name('auth.login');

Route::middleware('throttle:6,1')->group(function (): void {
    Route::post('forgot-password', UserSendPasswordResetCodeController::class)->name('password.forgot');
    Route::post('reset-password', [UserPasswordController::class, 'store'])->name('password.reset');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('email-verification-code', UserSendEmailVerificationCodeController::class)->name('email.verification-code');
        Route::post('verify-email', UserVerifyEmailController::class)->name('email.verify');
        Route::put('update-password', [UserPasswordController::class, 'update'])->name('password.update');
    });

    Route::apiSingleton('user', UserController::class)->destroyable()->only(['update', 'destroy']);

    Route::post('logout', [AuthTokenController::class, 'destroy'])->name('auth.logout');
});
