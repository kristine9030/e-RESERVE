<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
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
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Student\DocumentController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\SuperAdmin\ActivityController as SuperActivity;
use App\Http\Controllers\SuperAdmin\AuthController as SuperAuth;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperDashboard;
use App\Http\Controllers\SuperAdmin\PerformanceController as SuperPerformance;
use App\Http\Controllers\SuperAdmin\UserController as SuperUsers;
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
    Route::post('/switch/{mode}', [\App\Http\Controllers\Auth\ModeController::class, 'switch'])->name('mode.switch');

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

/* Signed, short-lived CSV link a staff member can paste into Google Sheets (=IMPORTDATA). */
Route::get('/feed/records.csv', [AdminController::class, 'recordsFeed'])->middleware('signed')->name('records.feed');

/* ---- Super admin (system owner) ------------------------------------------ */
Route::middleware('guest')->group(function () {
    Route::get('/superadmin/login', [SuperAuth::class, 'show'])->name('superadmin.login');
    Route::post('/superadmin/login', [SuperAuth::class, 'store'])->middleware('throttle:20,1')->name('superadmin.login.store');
});
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', SuperDashboard::class)->name('dashboard');
    Route::get('/performance', SuperPerformance::class)->name('performance');
    Route::get('/users', [SuperUsers::class, 'index'])->name('users');
    Route::post('/users', [SuperUsers::class, 'store'])->name('users.store');
    Route::post('/users/{user}/reset', [SuperUsers::class, 'reset'])->whereNumber('user')->name('users.reset');
    Route::post('/users/{user}/toggle', [SuperUsers::class, 'toggle'])->whereNumber('user')->name('users.toggle');
    Route::get('/activity-log', [SuperActivity::class, 'index'])->name('activity');
    Route::get('/activity-log/export', [SuperActivity::class, 'export'])->name('activity.export');
});

/* ---- Staff -------------------------------------------------------------- */
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/students', [AdminController::class, 'registrations'])->name('admin.registrations');
    Route::get('/students/export/{format}', [AdminController::class, 'recordsExport'])->whereIn('format', ['xlsx', 'csv', 'pdf'])->name('admin.records.export');
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
    Route::get('/settings', [SiteSettingsController::class, 'index'])->name('admin.settings');
    Route::post('/settings', [SiteSettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/settings/quotas', [SiteSettingsController::class, 'quotas'])->name('admin.settings.quotas');
    Route::post('/settings/quotas/move', [SiteSettingsController::class, 'transfer'])->name('admin.settings.transfer');
    Route::get('/team', [TeamController::class, 'index'])->name('admin.team');
    Route::post('/team/{member}/account', [TeamController::class, 'account'])->whereNumber('member')->name('admin.team.account');
    Route::post('/team/{member}/access', [TeamController::class, 'access'])->whereNumber('member')->name('admin.team.access');
});

// Gate check-in: SSC admins and gate-only scanner accounts.
Route::middleware(['auth', 'staff'])->prefix('admin')->group(function () {
    Route::get('/checkin', [CheckinController::class, 'index'])->name('admin.checkin');
    Route::post('/checkin', [CheckinController::class, 'store'])->name('admin.checkin.store');
    Route::post('/checkin/manual', [CheckinController::class, 'manual'])->middleware('throttle:120,1')->name('admin.checkin.manual');
    Route::post('/checkin/scan', [CheckinController::class, 'scan'])->middleware('throttle:120,1')->name('admin.checkin.scan');
});

// Google Sheets feed: no login, protected by the link's signature (see ReportController::live).
Route::get('/reports/{report}/live.csv', [ReportController::class, 'live'])
    ->middleware(['signed', 'throttle:60,1'])->name('reports.live');

Route::middleware(['auth', 'admin'])->prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/{report}/export', [ReportController::class, 'export'])->name('reports.export');
});
