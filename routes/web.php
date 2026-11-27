<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeeHeadController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlineAdmissionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Public online admission form
Route::get('/apply', [OnlineAdmissionController::class, 'create'])->name('apply');
Route::post('/apply', [OnlineAdmissionController::class, 'store'])->middleware('throttle:10,1')->name('apply.store');
Route::get('/apply/thanks/{admissionNo}', [OnlineAdmissionController::class, 'thanks'])->name('apply.thanks');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/account/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('password.update');

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
