<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeeHeadController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlineAdmissionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\Super;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---------------- Public website ----------------
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/lang/{locale}', [HomeController::class, 'locale'])->name('locale');
Route::view('/privacy', 'site.privacy')->name('privacy');
Route::view('/terms', 'site.terms')->name('terms');
Route::view('/refund-policy', 'site.refund')->name('refund');
Route::view('/contact', 'site.contact')->name('contact');
Route::get('/install/finish', [InstallController::class, 'finish'])->name('install.finish');
Route::post('/razorpay/webhook', [BillingController::class, 'webhook'])->name('razorpay.webhook');

// Public online admission form of a school: /apply/{school-slug}
Route::get('/apply/{school:slug}', [OnlineAdmissionController::class, 'create'])->name('apply');
Route::post('/apply/{school:slug}', [OnlineAdmissionController::class, 'store'])->middleware('throttle:10,1')->name('apply.store');
Route::get('/apply/{school:slug}/thanks/{admissionNo}', [OnlineAdmissionController::class, 'thanks'])->name('apply.thanks');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.store');
});

Route::middleware(['auth', 'role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('password.update');
});

// ---------------- Platform owner ----------------
Route::middleware(['auth', 'super'])->prefix('super')->name('super.')->group(function () {
    Route::get('/', Super\DashboardController::class)->name('dashboard');
    Route::get('/schools', [Super\SchoolController::class, 'index'])->name('schools');
    Route::get('/schools/{school}', [Super\SchoolController::class, 'show'])->name('schools.show');
    Route::post('/schools/{school}/extend', [Super\SchoolController::class, 'extend'])->name('schools.extend');
    Route::post('/schools/{school}/block', [Super\SchoolController::class, 'toggleBlock'])->name('schools.block');
    Route::get('/subscriptions', [Super\SubscriptionController::class, 'index'])->name('subscriptions');
    Route::get('/settings', [Super\SettingController::class, 'edit'])->name('settings');
    Route::put('/settings', [Super\SettingController::class, 'update'])->name('settings.update');
    Route::get('/update', [Super\UpdateController::class, 'index'])->name('update');
    Route::post('/update/upload', [Super\UpdateController::class, 'upload'])->middleware('throttle:10,1')->name('update.upload');
    Route::post('/update/apply', [Super\UpdateController::class, 'apply'])->name('update.apply');
    Route::post('/update/cancel', [Super\UpdateController::class, 'cancel'])->name('update.cancel');
    Route::post('/update/{log}/rollback', [Super\UpdateController::class, 'rollback'])->name('update.rollback');
    Route::get('/backups/{file}', [Super\UpdateController::class, 'download'])->where('file', '[A-Za-z0-9._-]+')->name('backups.download');
});

// ---------------- School software (locked until the ₹1 trial is paid) ----------------
Route::middleware(['auth', 'role', 'school'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/billing', [BillingController::class, 'show'])->name('billing');
    Route::post('/billing/start', [BillingController::class, 'start'])->middleware('throttle:10,1')->name('billing.start');
    Route::post('/billing/verify', [BillingController::class, 'verify'])->name('billing.verify');
    Route::post('/billing/{subscription}/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

    // Sections list for dropdowns (JSON)
    Route::get('/classes/{schoolClass}/sections.json', [SectionController::class, 'forClass'])->name('classes.sections.json');

    // Students: everyone can view
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');

    // Admission + student records
    Route::middleware('role:admin,admission_staff')->group(function () {
        Route::get('/admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('/admissions/create', [AdmissionController::class, 'create'])->name('admissions.create');
        Route::post('/admissions', [AdmissionController::class, 'store'])->name('admissions.store');
        Route::post('/admissions/{student}/confirm', [AdmissionController::class, 'confirm'])->name('admissions.confirm');
        Route::delete('/admissions/{student}', [AdmissionController::class, 'reject'])->name('admissions.reject');
        Route::get('/admissions/{student}/print', [AdmissionController::class, 'print'])->name('admissions.print');

        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::post('/students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
        Route::post('/students/{student}/documents', [StudentController::class, 'storeDocument'])->name('students.documents.store');
        Route::delete('/students/{student}/documents/{document}', [StudentController::class, 'destroyDocument'])->name('students.documents.destroy');
        Route::get('/students/{student}/leave', [StudentController::class, 'leaveForm'])->name('students.leave');
        Route::post('/students/{student}/leave', [StudentController::class, 'leave'])->name('students.leave.store');
        Route::get('/students-promote', [StudentController::class, 'promoteForm'])->name('students.promote');
        Route::post('/students-promote', [StudentController::class, 'promote'])->name('students.promote.store');
    });

    Route::get('/students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
    Route::get('/students/{student}/id-card', [StudentController::class, 'idCard'])->name('students.idcard');
    Route::get('/id-cards', [StudentController::class, 'idCards'])->name('students.idcards');
    Route::get('/students/{student}/documents/{document}', [StudentController::class, 'showDocument'])->name('students.documents.show');

    // Fees + payments
    Route::middleware('role:admin,accountant')->group(function () {
        Route::resource('fee-heads', FeeHeadController::class)->except(['show']);
        Route::get('/fee-structure', [FeeStructureController::class, 'index'])->name('fee-structure.index');
        Route::post('/fee-structure', [FeeStructureController::class, 'store'])->name('fee-structure.store');
        Route::post('/fee-structure/generate', [FeeStructureController::class, 'generate'])->name('fee-structure.generate');

        Route::get('/pending-fees', [StudentFeeController::class, 'pending'])->name('fees.pending');
        Route::post('/students/{student}/fees', [StudentFeeController::class, 'store'])->name('students.fees.store');
        Route::post('/students/{student}/fees/generate', [StudentFeeController::class, 'generate'])->name('students.fees.generate');
        Route::put('/student-fees/{studentFee}', [StudentFeeController::class, 'update'])->name('student-fees.update');
        Route::delete('/student-fees/{studentFee}', [StudentFeeController::class, 'destroy'])->name('student-fees.destroy');

        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/collect-fee', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/students/{student}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');

        // Expenses
        Route::get('/expenses/{expense}/bill', [ExpenseController::class, 'bill'])->name('expenses.bill');
        Route::resource('expenses', ExpenseController::class)->except(['show']);
        Route::resource('expense-categories', ExpenseCategoryController::class)->only(['index', 'store', 'update', 'destroy']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{log}/retry', [NotificationController::class, 'retry'])->name('notifications.retry');
        Route::post('/notifications/remind', [NotificationController::class, 'remind'])->name('notifications.remind');
    });

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        Route::resource('classes', SchoolClassController::class)->parameters(['classes' => 'schoolClass'])->except(['show', 'create', 'edit']);
        Route::post('/classes/{schoolClass}/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::resource('sessions', AcademicSessionController::class)->except(['show', 'create', 'edit']);
        Route::post('/sessions/{session}/current', [AcademicSessionController::class, 'makeCurrent'])->name('sessions.current');
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/announcements', [NotificationController::class, 'announceForm'])->name('notifications.announce');
        Route::post('/announcements', [NotificationController::class, 'announce'])->name('notifications.announce.send');
        Route::get('/notification-settings', [NotificationController::class, 'settings'])->name('notifications.settings');
        Route::put('/notification-settings', [NotificationController::class, 'saveSettings'])->name('notifications.settings.update');
    });
});
