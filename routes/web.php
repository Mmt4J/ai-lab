<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::prefix('labs')->name('labs.')->group(function () {
        Route::livewire('summarizer', 'pages::labs.summarizer')->name('summarizer');
        Route::livewire('tickets', 'pages::labs.tickets')->name('tickets');
        Route::livewire('product', 'pages::labs.product')->name('product');
        Route::livewire('faq', 'pages::labs.faq')->name('faq');
        Route::livewire('receipt', 'pages::labs.receipt')->name('receipt');
        Route::livewire('reviews', 'pages::labs.reviews')->name('reviews');
    });
});

require __DIR__.'/settings.php';
