<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventSettingsController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [SessionController::class, 'create'])->name('login');
        Route::post('login', [SessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware(['auth', 'admin.active', 'can:access-admin'])->group(function (): void {
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('reservations/{reservation}/restore', [ReservationController::class, 'restore'])->name('reservations.restore');
        Route::resource('reservations', ReservationController::class);

        Route::get('event-settings', [EventSettingsController::class, 'index'])->name('event-settings');
        Route::get('event-settings/create', [EventSettingsController::class, 'create'])->name('event-settings.create');
        Route::post('event-settings', [EventSettingsController::class, 'store'])->name('event-settings.store');
        Route::get('event-settings/{event}/edit', [EventSettingsController::class, 'edit'])->name('event-settings.edit');
        Route::put('event-settings/{event}', [EventSettingsController::class, 'update'])->name('event-settings.update');
        Route::delete('event-settings/{event}', [EventSettingsController::class, 'destroy'])->name('event-settings.destroy');
        Route::get('profile', [ProfileController::class, 'index'])->name('profile');
        Route::put('profile', [ProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
        Route::get('users/{user}/password', [UserController::class, 'passwordForm'])->name('users.password.edit');
        Route::put('users/{user}/password', [UserController::class, 'password'])->middleware('throttle:5,1')->name('users.password.update');
        Route::resource('users', UserController::class);
        Route::resource('guests', GuestController::class)->only(['index', 'show', 'edit', 'update']);
    });
});
