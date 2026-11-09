<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\CheckinController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SponsorInquiryController;
use App\Http\Controllers\Admin\SponsorController;
use App\Http\Controllers\Student\DocumentController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Student\StudentController;
use Illuminate\Support\Facades\Route;

/* ---- Public ------------------------------------------------------------ */
Route::get('/', [PublicController::class, 'index'])->name('home');
Route::post('/sponsor-inquiry', [SponsorInquiryController::class, 'store'])->middleware('throttle:6,60')->name('sponsor.inquiry');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');

/* ---- Guests ------------------------------------------------------------ */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // SSC staff have their own door; students never see a staff form at /login.
    Route::get('/ssc', [LoginController::class, 'showStaff'])->name('ssc.login');
    Route::post('/ssc', [LoginController::class, 'storeStaff'])->middleware('throttle:20,1')->name('ssc.login.store');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

/* ---- Email confirmation (the emailed link works signed-in or not) ------- */
Route::get('/verify', [VerifyController::class, 'show'])->name('verify');
Route::post('/verify', [VerifyController::class, 'resend'])->middleware('auth')->name('verify.resend');

/* ---- Any signed-in user -------------------------------------------------- */
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/account/password', [AccountController::class, 'passwordForm'])->name('account.password');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    // A document is readable by its owner or by staff.
    Route::get('/documents/{document}/file', [DocumentFileController::class, 'show'])
        ->whereNumber('document')->name('documents.file');
});

/* ---- Students ----------------------------------------------------------- */
Route::middleware(['auth', 'student'])->group(function () {
    Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
    Route::get('/ticket', [StudentController::class, 'ticket'])->name('ticket');
    Route::get('/ticket/image', [StudentController::class, 'ticketImage'])->middleware('throttle:20,1')->name('ticket.image');
    Route::get('/tracking', [StudentController::class, 'tracking'])->name('tracking');
    Route::get('/profile', [StudentController::class, 'profile'])->name('profile');
    Route::post('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/change-request', [AccountController::class, 'requestChange'])->name('profile.change');
    Route::get('/waiver', [StudentController::class, 'waiver'])->name('waiver');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notice}/seen', [NotificationController::class, 'seen'])->whereNumber('notice')->name('notifications.seen');
    Route::post('/notifications/read',[NotificationController::class, 'readAll'])->name('notifications.read');
    Route::get('/notifications/{notice}', [NotificationController::class, 'open'])->whereNumber('notice')->name('notifications.open');

    Route::middleware('verified')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    });
});

/* ---- Staff -------------------------------------------------------------- */
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/students', [AdminController::class, 'registrations'])->name('admin.registrations');
    Route::post('/students/{user}/status', [AdminController::class, 'setStatus'])->whereNumber('user')->name('admin.students.status');
    Route::get('/documents', [AdminController::class, 'documents'])->name('admin.documents');
    Route::get('/documents/student/{user}', [AdminController::class, 'student'])->whereNumber('user')->name('admin.documents.student');
    Route::post('/documents', [AdminController::class, 'review'])->name('admin.documents.review');
    Route::post('/documents/bulk', [AdminController::class, 'bulkReview'])->name('admin.documents.bulk');
    Route::get('/qr', [CheckinController::class, 'qr'])->name('admin.qr');

    // SSC roster and logins
    Route::get('/sponsors', [SponsorController::class, 'index'])->name('admin.sponsors');
    Route::post('/sponsors', [SponsorController::class, 'store'])->name('admin.sponsors.store');
    Route::post('/sponsors/{sponsor}/toggle', [SponsorController::class, 'toggle'])->whereNumber('sponsor')->name('admin.sponsors.toggle');
    Route::post('/sponsors/{sponsor}/remove', [SponsorController::class, 'destroy'])->whereNumber('sponsor')->name('admin.sponsors.destroy');
    Route::post('/sponsors/inquiries/{inquiry}', [SponsorController::class, 'inquiry'])->whereNumber('inquiry')->name('admin.sponsors.inquiry');
    Route::get('/requests', [ChangeRequestController::class, 'index'])->name('admin.requests');
    Route::post('/requests/{changeRequest}', [ChangeRequestController::class, 'decide'])->whereNumber('changeRequest')->name('admin.requests.decide');
    Route::get('/team', [TeamController::class, 'index'])->name('admin.team');
    Route::post('/team/{member}/account', [TeamController::class, 'account'])->whereNumber('member')->name('admin.team.account');
    Route::post('/team/{member}/access', [TeamController::class, 'access'])->whereNumber('member')->name('admin.team.access');
});

// Gate check-in: SSC admins and gate-only scanner accounts.
Route::middleware(['auth', 'staff'])->prefix('admin')->group(function () {
    Route::get('/checkin', [CheckinController::class, 'index'])->name('admin.checkin');
    Route::post('/checkin', [CheckinController::class, 'store'])->name('admin.checkin.store');
    Route::post('/checkin/scan', [CheckinController::class, 'scan'])->middleware('throttle:120,1')->name('admin.checkin.scan');
});

Route::middleware(['auth', 'admin'])->prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/{report}/export', [ReportController::class, 'export'])->name('reports.export');
});
