<?php

use App\Http\Controllers\Api\AdminEventController;
use App\Http\Controllers\Api\AdminInvitationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\RsvpController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/auth/user', [AuthController::class, 'user'])->middleware(['auth:sanctum', 'admin.active']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'admin.active']);

Route::get('/website-event', [PublicEventController::class, 'website'])->middleware('throttle:60,1');

Route::get('/events/{slug}', [PublicEventController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1');
Route::post('/events/{slug}/rsvps', [RsvpController::class, 'public'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:5,1');
Route::get('/invitations/{token}', [PublicEventController::class, 'invitation'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:20,1');
Route::post('/invitations/{token}/rsvp', [RsvpController::class, 'private'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'admin.active', 'admin.legacy'])->prefix('admin')->group(function (): void {
    Route::get('/events', [AdminEventController::class, 'index']);
    Route::post('/events', [AdminEventController::class, 'store']);
    Route::get('/events/{event}', [AdminEventController::class, 'show'])->whereNumber('event');
    Route::put('/events/{event}', [AdminEventController::class, 'update'])->whereNumber('event');
    Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])->whereNumber('event');
    Route::post('/events/{event}/duplicate', [AdminEventController::class, 'duplicate'])->whereNumber('event');
    Route::get('/events/{event}/metrics', [AdminEventController::class, 'metrics'])->whereNumber('event');
    Route::get('/events/{event}/responses', [AdminEventController::class, 'responses'])->whereNumber('event');
    Route::get('/events/{event}/export', [AdminEventController::class, 'export'])->whereNumber('event');
    Route::post('/events/{event}/invitations', [AdminInvitationController::class, 'store'])->whereNumber('event');
    Route::get('/events/{event}/invitations', [AdminInvitationController::class, 'index'])->whereNumber('event');
    Route::post('/invitations/{invitation}/revoke', [AdminInvitationController::class, 'revoke'])->whereNumber('invitation');
    Route::put('/responses/{response}', [AdminEventController::class, 'updateResponse'])->whereNumber('response');
});
