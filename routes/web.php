<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnquiryController;
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
use App\Http\Controllers\SchoolAssetController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\Super;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\Hr as H;
use App\Http\Controllers\Transport as T;
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

Route::view('/whats-new', 'site.whats-new')->name('whats-new');
Route::get('/logo/{school:slug}', [SchoolAssetController::class, 'logo'])->name('school.logo');

// Public online admission form of a school: /apply/{school-slug}
Route::get('/apply/{school:slug}', [OnlineAdmissionController::class, 'create'])->name('apply');
Route::post('/apply/{school:slug}', [OnlineAdmissionController::class, 'store'])->middleware('throttle:10,1')->name('apply.store');
Route::get('/apply/{school:slug}/form', [OnlineAdmissionController::class, 'blankForm'])->name('apply.form');
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

    Route::get('/school-signature', [SchoolAssetController::class, 'signature'])->name('school.signature');

    // Sections list for dropdowns (JSON)
    Route::get('/classes/{schoolClass}/sections.json', [SectionController::class, 'forClass'])->name('classes.sections.json');

    // Students: everyone can view
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');

    // Enquiries, admissions + student records (reception, principal, admin)
    Route::middleware('role:admin,receptionist,principal')->group(function () {
        Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('/enquiries/create', [EnquiryController::class, 'create'])->name('enquiries.create');
        Route::post('/enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');
        Route::get('/enquiries/report', [EnquiryController::class, 'report'])->name('enquiries.report');
        Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
        Route::put('/enquiries/{enquiry}', [EnquiryController::class, 'update'])->name('enquiries.update');
        Route::post('/enquiries/{enquiry}/follow-ups', [EnquiryController::class, 'followUp'])->name('enquiries.followup');

        Route::get('/admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('/admissions/create', [AdmissionController::class, 'create'])->name('admissions.create');
        Route::post('/admissions', [AdmissionController::class, 'store'])->name('admissions.store');
        Route::delete('/admissions/{student}', [AdmissionController::class, 'reject'])->name('admissions.reject');
        Route::get('/admissions/{student}/print', [AdmissionController::class, 'print'])->name('admissions.print');
        Route::get('/admission-form/blank', [AdmissionController::class, 'blankForm'])->name('admissions.blank');

        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::post('/students/{student}/documents', [StudentController::class, 'storeDocument'])->name('students.documents.store');
        Route::delete('/students/{student}/documents/{document}', [StudentController::class, 'destroyDocument'])->name('students.documents.destroy');
    });

    // Decisions: approve without fee, status, leaving, promotion (principal, admin)
    Route::middleware('role:admin,principal')->group(function () {
        Route::post('/admissions/{student}/confirm', [AdmissionController::class, 'confirm'])->name('admissions.confirm');
        Route::post('/students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
        Route::get('/students/{student}/leave', [StudentController::class, 'leaveForm'])->name('students.leave');
        Route::post('/students/{student}/leave', [StudentController::class, 'leave'])->name('students.leave.store');
        Route::get('/students-promote', [StudentController::class, 'promoteForm'])->name('students.promote');
        Route::post('/students-promote', [StudentController::class, 'promote'])->name('students.promote.store');
    });

    Route::get('/students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
    Route::get('/students/{student}/id-card', [StudentController::class, 'idCard'])->name('students.idcard');
    Route::get('/id-cards', [StudentController::class, 'idCards'])->name('students.idcards');
    Route::get('/students/{student}/documents/{document}', [StudentController::class, 'showDocument'])->name('students.documents.show');

    // Fees, payments, reports, expenses (accounts; principal can only view)
    Route::middleware('role:admin,accountant,principal:read')->group(function () {
        Route::resource('fee-heads', FeeHeadController::class)->except(['show']);
        Route::get('/fee-structure', [FeeStructureController::class, 'index'])->name('fee-structure.index');
        Route::post('/fee-structure', [FeeStructureController::class, 'store'])->name('fee-structure.store');
        Route::post('/fee-structure/generate', [FeeStructureController::class, 'generate'])->name('fee-structure.generate');

        Route::get('/pending-fees', [StudentFeeController::class, 'pending'])->name('fees.pending');
        Route::get('/students/{student}/fee-slip', [StudentFeeController::class, 'slip'])->name('students.feeslip');
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

    // Classes, sessions, announcements (principal, admin)
    Route::middleware('role:admin,principal')->group(function () {
        Route::resource('classes', SchoolClassController::class)->parameters(['classes' => 'schoolClass'])->except(['show', 'create', 'edit']);
        Route::post('/classes/{schoolClass}/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::post('/sections/bulk', [SectionController::class, 'bulk'])->name('sections.bulk');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::resource('sessions', AcademicSessionController::class)->except(['show', 'create', 'edit']);
        Route::post('/sessions/{session}/current', [AcademicSessionController::class, 'makeCurrent'])->name('sessions.current');
        Route::get('/announcements', [NotificationController::class, 'announceForm'])->name('notifications.announce');
        Route::post('/announcements', [NotificationController::class, 'announce'])->name('notifications.announce.send');
    });

    // Import & export (principal can download; only admin imports)
    Route::middleware('role:admin,principal:read')->group(function () {
        Route::get('/data', [DataController::class, 'index'])->name('data.index');
        Route::get('/data/export/{type}', [DataController::class, 'export'])->name('data.export');
        Route::get('/data/export-all', [DataController::class, 'exportAll'])->name('data.export.all');
        Route::get('/data/template/{type}', [DataController::class, 'template'])->name('data.template');
        Route::post('/data/import/{type}', [DataController::class, 'upload'])->middleware('throttle:20,1')->name('data.upload');
        Route::get('/data/preview/{token}', [DataController::class, 'preview'])->name('data.preview');
        Route::post('/data/preview/{token}', [DataController::class, 'confirm'])->name('data.confirm');
    });

    // Staff & teachers (accounts can only view; payroll: admin & accounts, principal views)
    Route::middleware('role:admin,principal,accountant:read')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/', H\DashboardController::class)->name('dashboard');
        Route::resource('employees', H\EmployeeController::class)->except(['destroy']);
        Route::post('/employees/{employee}/exit', [H\EmployeeController::class, 'exit'])->name('employees.exit');
        Route::get('/employees/{employee}/photo', [H\EmployeeController::class, 'photo'])->name('employees.photo');
        Route::post('/employees/{employee}/documents', [H\EmployeeController::class, 'storeDocument'])->name('documents.store');
        Route::get('/documents/{document}', [H\EmployeeController::class, 'showDocument'])->name('documents.show');
        Route::delete('/documents/{document}', [H\EmployeeController::class, 'destroyDocument'])->name('documents.destroy');
        Route::get('/id-cards', [H\EmployeeController::class, 'idCards'])->name('idcards');
        Route::get('/employees/{employee}/letter/{type}', [H\EmployeeController::class, 'letter'])->name('letter');

        Route::get('/academics', [H\AcademicController::class, 'index'])->name('academics');
        Route::post('/subjects', [H\AcademicController::class, 'storeSubject'])->name('subjects.store');
        Route::delete('/subjects/{subject}', [H\AcademicController::class, 'destroySubject'])->name('subjects.destroy');
        Route::post('/class-teachers', [H\AcademicController::class, 'classTeachers'])->name('classteachers');
        Route::post('/assignments', [H\AcademicController::class, 'storeAssignment'])->name('assignments.store');
        Route::delete('/assignments/{assignment}', [H\AcademicController::class, 'destroyAssignment'])->name('assignments.destroy');

        Route::get('/attendance', [H\AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [H\AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/attendance/month', [H\AttendanceController::class, 'monthly'])->name('attendance.monthly');
        Route::get('/leaves', [H\LeaveController::class, 'index'])->name('leaves.index');
        Route::post('/leaves', [H\LeaveController::class, 'store'])->name('leaves.store');
        Route::put('/leaves/{leave}', [H\LeaveController::class, 'decide'])->name('leaves.decide');
        Route::post('/leave-types', [H\LeaveController::class, 'types'])->name('leaves.types');

        Route::get('/timetable', [H\TimetableController::class, 'index'])->name('timetable.index');
        Route::post('/timetable', [H\TimetableController::class, 'save'])->name('timetable.save');
        Route::post('/timetable/copy', [H\TimetableController::class, 'copyDay'])->name('timetable.copy');
        Route::get('/timetable/teacher', [H\TimetableController::class, 'teacher'])->name('timetable.teacher');
        Route::post('/periods', [H\TimetableController::class, 'periods'])->name('periods.save');
        Route::get('/substitutions', [H\TimetableController::class, 'substitutions'])->name('substitutions.index');
        Route::post('/substitutions', [H\TimetableController::class, 'assignSubstitute'])->name('substitutions.store');

        Route::get('/reports', [H\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [H\ReportController::class, 'show'])->name('reports.show');
    });
    Route::middleware('role:admin,accountant,principal:read')->prefix('staff')->name('staff.')->group(function () {
        Route::put('/employees/{employee}/salary', [H\EmployeeController::class, 'salary'])->name('employees.salary');
        Route::get('/payroll', [H\PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll/generate', [H\PayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('/payroll/pay', [H\PayrollController::class, 'pay'])->name('payroll.pay');
        Route::get('/payroll-slips', [H\PayrollController::class, 'slips'])->name('payroll.slips');
        Route::get('/payroll/{payroll}/edit', [H\PayrollController::class, 'edit'])->name('payroll.edit');
        Route::put('/payroll/{payroll}', [H\PayrollController::class, 'update'])->name('payroll.update');
        Route::post('/payroll/{payroll}/refresh', [H\PayrollController::class, 'refresh'])->name('payroll.refresh');
        Route::delete('/payroll/{payroll}', [H\PayrollController::class, 'destroy'])->name('payroll.destroy');
        Route::get('/payroll/{payroll}/slip', [H\PayrollController::class, 'slip'])->name('payroll.slip');
        Route::get('/advances', [H\PayrollController::class, 'advances'])->name('advances.index');
        Route::post('/advances', [H\PayrollController::class, 'storeAdvance'])->name('advances.store');
        Route::delete('/advances/{advance}', [H\PayrollController::class, 'destroyAdvance'])->name('advances.destroy');
    });
    Route::middleware('role:admin')->post('/staff/payroll/{payroll}/unpay', [H\PayrollController::class, 'unpay'])->name('staff.payroll.unpay');

    // Transport (principal can only view; salaries: admin & accounts)
    Route::middleware('role:admin,transport,accountant,principal:read')->prefix('transport')->name('transport.')->group(function () {
        Route::get('/', T\DashboardController::class)->name('dashboard');
        Route::resource('vehicles', T\VehicleController::class)->except(['destroy']);
        Route::post('/vehicles/{vehicle}/toggle', [T\VehicleController::class, 'toggle'])->name('vehicles.toggle');
        Route::resource('staff', T\StaffController::class)->except(['destroy'])->parameters(['staff' => 'staff']);
        Route::post('/staff/{staff}/toggle', [T\StaffController::class, 'toggle'])->name('staff.toggle');
        Route::resource('routes', T\RouteController::class)->parameters(['routes' => 'route']);
        Route::post('/routes/{route}/stops', [T\RouteController::class, 'storeStop'])->name('stops.store');
        Route::post('/routes/{route}/sort-stops', [T\RouteController::class, 'sortStops'])->name('stops.sort');
        Route::put('/stops/{stop}', [T\RouteController::class, 'updateStop'])->name('stops.update');
        Route::delete('/stops/{stop}', [T\RouteController::class, 'destroyStop'])->name('stops.destroy');
        Route::post('/routes/{route}/changes', [T\RouteController::class, 'storeChange'])->name('changes.store');
        Route::delete('/changes/{change}', [T\RouteController::class, 'destroyChange'])->name('changes.destroy');
        Route::post('/routes/{route}/students', [T\RouteController::class, 'addStudents'])->name('routes.students');
        Route::get('/students/{student}', [T\StudentTransportController::class, 'edit'])->name('student.edit');
        Route::put('/students/{student}', [T\StudentTransportController::class, 'update'])->name('student.update');

        Route::get('/trips', [T\TripController::class, 'index'])->name('trips.index');
        Route::post('/trips', [T\TripController::class, 'store'])->name('trips.store');
        Route::put('/trips/{trip}', [T\TripController::class, 'update'])->name('trips.update');
        Route::delete('/trips/{trip}', [T\TripController::class, 'destroy'])->name('trips.destroy');
        Route::get('/attendance', [T\AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [T\AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/attendance/month', [T\AttendanceController::class, 'monthly'])->name('attendance.monthly');
        Route::get('/leaves', [T\AttendanceController::class, 'leaves'])->name('leaves.index');
        Route::post('/leaves', [T\AttendanceController::class, 'storeLeave'])->name('leaves.store');
        Route::put('/leaves/{leave}', [T\AttendanceController::class, 'updateLeave'])->name('leaves.update');
        Route::delete('/leaves/{leave}', [T\AttendanceController::class, 'destroyLeave'])->name('leaves.destroy');

        Route::resource('fuel', T\FuelController::class)->except(['show'])->parameters(['fuel' => 'fuel']);
        Route::get('/fuel/{fuel}/bill', [T\FuelController::class, 'bill'])->name('fuel.bill');
        Route::resource('maintenance', T\MaintenanceController::class)->except(['show'])->parameters(['maintenance' => 'maintenance']);
        Route::get('/maintenance/{maintenance}/bill', [T\MaintenanceController::class, 'bill'])->name('maintenance.bill');
        Route::get('/vendors', [T\VendorController::class, 'index'])->name('vendors.index');
        Route::post('/vendors', [T\VendorController::class, 'store'])->name('vendors.store');
        Route::put('/vendors/{vendor}', [T\VendorController::class, 'update'])->name('vendors.update');
        Route::delete('/vendors/{vendor}', [T\VendorController::class, 'destroy'])->name('vendors.destroy');
        Route::post('/expenses/recurring', [T\ExpenseController::class, 'recurring'])->name('expenses.recurring');
        Route::resource('expenses', T\ExpenseController::class)->except(['show']);
        Route::get('/expenses/{expense}/bill', [T\ExpenseController::class, 'bill'])->name('expenses.bill');
        Route::post('/files', [T\FileController::class, 'store'])->name('files.store');
        Route::get('/files/{file}', [T\FileController::class, 'show'])->name('files.show');
        Route::delete('/files/{file}', [T\FileController::class, 'destroy'])->name('files.destroy');

        Route::get('/reports', [T\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [T\ReportController::class, 'show'])->name('reports.show');
    });
    Route::middleware('role:admin,accountant,principal:read')->prefix('transport')->name('transport.')->group(function () {
        Route::get('/salaries', [T\SalaryController::class, 'index'])->name('salaries.index');
        Route::post('/salaries', [T\SalaryController::class, 'store'])->name('salaries.store');
        Route::get('/salaries/{salary}/slip', [T\SalaryController::class, 'slip'])->name('salaries.slip');
        Route::delete('/salaries/{salary}', [T\SalaryController::class, 'destroy'])->name('salaries.destroy');
    });

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        Route::post('/settings/logo', [SettingController::class, 'uploadLogo'])->name('settings.logo');
        Route::post('/settings/signature', [SettingController::class, 'uploadSignature'])->name('settings.signature');
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/notification-settings', [NotificationController::class, 'settings'])->name('notifications.settings');
        Route::put('/notification-settings', [NotificationController::class, 'saveSettings'])->name('notifications.settings.update');
    });
});
