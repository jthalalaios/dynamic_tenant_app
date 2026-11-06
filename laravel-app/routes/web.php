<?php

use App\Http\Controllers\Dev\MailPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Local development only: preview the branded emails (see MailPreviewController)
if (app()->environment('local')) {
    Route::get('dev/mail/{template}/{theme}', MailPreviewController::class)
        ->where(['template' => 'verify-email|reset-password', 'theme' => '[a-z]+'])
        ->name('dev.mail.preview');
}
