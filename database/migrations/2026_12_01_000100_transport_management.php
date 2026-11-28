<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v1.3.0 — Transport management: vehicles, routes, drivers, fuel, maintenance, expenses, student transport. */
return new class extends Migration
{
    public function up(): void
    {
        $school = fn (Blueprint $t) => $t->foreignId('school_id')->constrained()->cascadeOnDelete();

        Schema::create('vehicles', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('bus_no', 30);                 // school's own number, e.g. Bus 5
            $t->string('registration_no', 30);
            $t->string('make', 60)->nullable();       // company
            $t->string('model', 60)->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->unsignedSmallInteger('seats')->default(0);
            $t->string('fuel_type', 10)->default('diesel'); // petrol, diesel, cng, ev
            $t->string('ownership', 10)->default('owned');  // owned, rented
            $t->string('owner_name', 100)->nullable();      // for rented vehicles
            $t->decimal('monthly_rent', 10, 2)->default(0);
            $t->decimal('monthly_emi', 10, 2)->default(0);
            $t->string('insurance_company', 100)->nullable();
            $t->string('insurance_policy_no', 60)->nullable();
            $t->date('insurance_expiry')->nullable();
            $t->decimal('insurance_amount', 10, 2)->default(0);
            $t->string('puc_no', 60)->nullable();
            $t->date('puc_expiry')->nullable();
            $t->string('permit_no', 60)->nullable();
            $t->date('permit_expiry')->nullable();
            $t->date('fitness_expiry')->nullable();
            $t->unsignedInteger('odometer')->default(0);
            $t->unsignedInteger('service_interval_km')->default(0);
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['school_id', 'registration_no']);
        });

        Schema::create('transport_staff', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('type', 10);                   // driver, conductor
            $t->string('name', 100);
            $t->string('employee_id', 30)->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('alt_phone', 20)->nullable();
            $t->string('address', 300)->nullable();
            $t->date('dob')->nullable();
            $t->date('joining_date')->nullable();
            $t->string('licence_no', 40)->nullable();
            $t->string('licence_type', 40)->nullable();
            $t->date('licence_expiry')->nullable();
            $t->string('aadhaar_no', 20)->nullable();
            $t->string('verification_status', 12)->default('pending'); // pending, verified, failed
            $t->date('verified_on')->nullable();
            $t->string('verification_note', 300)->nullable();
            $t->time('duty_start')->nullable();
            $t->time('duty_end')->nullable();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('monthly_salary', 10, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['school_id', 'employee_id']);
        });

        Schema::create('transport_routes', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('name', 100);
            $t->string('route_no', 20)->nullable();
            $t->string('shift', 12)->default('both'); // morning, afternoon, both
            $t->unsignedTinyInteger('trip_no')->default(1);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->decimal('distance_km', 7, 2)->default(0); // one way
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->foreignId('conductor_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->unsignedSmallInteger('capacity')->nullable(); // empty = vehicle seats
            $t->decimal('monthly_fee', 10, 2)->default(0);    // default fee when the stop has none
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->unsignedSmallInteger('sequence')->default(1);
            $t->time('pickup_time')->nullable();
            $t->time('drop_time')->nullable();
            $t->decimal('distance_km', 7, 2)->nullable(); // from school
            $t->decimal('monthly_fee', 10, 2)->nullable();
            $t->timestamps();
        });

        Schema::create('route_changes', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $t->date('from_date');
            $t->date('to_date');
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->foreignId('conductor_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->string('reason', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('students', function (Blueprint $t) {
            $t->foreignId('transport_route_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('route_stop_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('transport_fee', 10, 2)->nullable();
            $t->date('transport_from')->nullable();
        });

        Schema::table('student_fees', function (Blueprint $t) {
            $t->foreignId('transport_route_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('transport_trips', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->date('trip_date');
            $t->string('shift', 12)->default('morning');
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('transport_route_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->foreignId('conductor_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->unsignedInteger('start_km')->nullable();
            $t->unsignedInteger('end_km')->nullable();
            $t->unsignedSmallInteger('students_count')->nullable();
            $t->string('notes', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['school_id', 'trip_date']);
        });

        Schema::create('staff_attendances', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('transport_staff_id')->constrained('transport_staff')->cascadeOnDelete();
            $t->date('date');
            $t->string('status', 10); // present, absent, half_day, leave
            $t->time('in_time')->nullable();
            $t->time('out_time')->nullable();
            $t->string('note', 200)->nullable();
            $t->timestamps();
            $t->unique(['transport_staff_id', 'date']);
        });

        Schema::create('staff_leaves', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('transport_staff_id')->constrained('transport_staff')->cascadeOnDelete();
            $t->date('from_date');
            $t->date('to_date');
            $t->string('reason', 300)->nullable();
            $t->string('status', 10)->default('approved'); // pending, approved, rejected
            $t->foreignId('substitute_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('staff_salaries', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('transport_staff_id')->constrained('transport_staff')->cascadeOnDelete();
            $t->string('month', 7); // 2026-11
            $t->decimal('amount', 10, 2);
            $t->decimal('deduction', 10, 2)->default(0);
            $t->decimal('bonus', 10, 2)->default(0);
            $t->date('paid_on');
            $t->string('method', 20)->default('cash');
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->unique(['transport_staff_id', 'month']);
        });

        Schema::create('transport_vendors', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('name', 100);
            $t->string('type', 20)->default('mechanic'); // mechanic, workshop, parts, tyres, fuel, other
            $t->string('contact_person', 100)->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('address', 300)->nullable();
            $t->string('notes', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('fuel_entries', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->date('entry_date');
            $t->string('fuel_type', 10);
            $t->decimal('quantity', 8, 2);           // litres, kg (CNG) or kWh (EV)
            $t->decimal('rate', 8, 2);
            $t->decimal('amount', 10, 2);
            $t->unsignedInteger('odometer')->nullable();
            $t->boolean('full_tank')->default(true);
            $t->string('station', 100)->nullable();
            $t->foreignId('driver_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->string('bill_path')->nullable();
            $t->string('notes', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['school_id', 'entry_date']);
        });

        Schema::create('vehicle_maintenances', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->date('service_date');
            $t->string('type', 20); // service, engine, tyre, battery, repair, breakdown, other
            $t->string('title', 150);
            $t->foreignId('transport_vendor_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('transport_staff')->nullOnDelete();
            $t->unsignedInteger('odometer')->nullable();
            $t->decimal('parts_cost', 10, 2)->default(0);
            $t->decimal('labour_cost', 10, 2)->default(0);
            $t->decimal('total_cost', 10, 2)->default(0);
            $t->date('next_due_date')->nullable();
            $t->unsignedInteger('next_due_km')->nullable();
            $t->string('status', 12)->default('done'); // scheduled, open (breakdown), done
            $t->string('bill_path')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['school_id', 'service_date']);
        });

        Schema::create('transport_expenses', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->date('expense_date');
            $t->string('category', 20); // emi, rent, insurance, tax, permit, toll, cleaning, misc
            $t->string('title', 150);
            $t->decimal('amount', 10, 2);
            $t->string('method', 20)->default('cash');
            $t->string('bill_path')->nullable();
            $t->string('notes', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['school_id', 'expense_date']);
        });

        // photos and documents of vehicles and staff
        Schema::create('transport_files', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('owner_type', 20); // vehicle, staff
            $t->unsignedBigInteger('owner_id');
            $t->string('kind', 20)->default('photo'); // photo, document
            $t->string('title', 100)->nullable();
            $t->string('path');
            $t->timestamps();
            $t->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('student_fees', fn (Blueprint $t) => $t->dropConstrainedForeignId('transport_route_id'));
        Schema::table('students', function (Blueprint $t) {
            $t->dropConstrainedForeignId('route_stop_id');
            $t->dropConstrainedForeignId('transport_route_id');
            $t->dropColumn(['transport_fee', 'transport_from']);
        });
        foreach (['transport_files', 'transport_expenses', 'vehicle_maintenances', 'fuel_entries', 'transport_vendors', 'staff_salaries',
            'staff_leaves', 'staff_attendances', 'transport_trips', 'route_changes', 'route_stops', 'transport_routes', 'transport_staff', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
