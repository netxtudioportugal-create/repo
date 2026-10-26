<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\ItemReviewController;
use App\Http\Controllers\Staff\NotesController;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', [OnboardingController::class, 'show'])->name('onboarding');
Route::post('/welcome', [OnboardingController::class, 'store'])->name('onboarding.store');
Route::post('/locale', LocaleController::class)->name('locale');

Route::middleware('acknowledged')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', [AboutController::class, 'show'])->name('about');
    Route::delete('/about/data', [AboutController::class, 'clear'])->name('about.clear');

    Route::middleware('throttle:practice')->prefix('practice')->name('practice.')->group(function () {
        Route::post('/domain/{domain}', [PracticeController::class, 'startDomain'])->name('domain');
        Route::post('/timed', [PracticeController::class, 'startTimed'])->name('timed');
        Route::get('/{session}/q/{position}', [PracticeController::class, 'question'])->whereNumber('position')->name('question');
        Route::post('/{session}/q/{position}', [PracticeController::class, 'answer'])->whereNumber('position')->name('answer');
        Route::post('/{session}/finish', [PracticeController::class, 'finish'])->name('finish');
        Route::get('/{session}/results', [PracticeController::class, 'results'])->name('results');
        Route::get('/{session}/review/{n?}', [PracticeController::class, 'review'])->whereNumber('n')->name('review');
    });
});

Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->middleware('guest')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    Route::middleware('staff')->group(function () {
        Route::get('/items', [ItemReviewController::class, 'index'])->name('items.index');
        Route::get('/items/{item}', [ItemReviewController::class, 'show'])->name('items.show');
        Route::post('/items/{item}/transition', [ItemReviewController::class, 'transition'])->name('items.transition');
        Route::get('/notes', NotesController::class)->name('notes');
    });
});
