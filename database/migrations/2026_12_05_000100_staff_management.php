<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** v1.4.0 — Staff & teachers: profiles, subjects, timetable, attendance, leave, payroll. */
return new class extends Migration
{
    public function up(): void
    {
        $school = fn (Blueprint $t) => $t->foreignId('school_id')->constrained()->cascadeOnDelete();

        Schema::create('employees', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('employee_id', 30)->nullable();
            $t->string('type', 15)->default('teaching'); // teaching, non_teaching
            $t->string('name', 100);
            $t->string('gender', 10)->nullable();
            $t->date('dob')->nullable();
            $t->string('blood_group', 5)->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('alt_phone', 20)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('address', 300)->nullable();
            $t->string('photo')->nullable();
            $t->string('designation', 60)->nullable();
            $t->string('department', 60)->nullable();
            $t->string('qualification', 150)->nullable();
            $t->string('specialization', 150)->nullable();
            $t->decimal('experience_years', 4, 1)->nullable(); // before joining
            $t->date('joining_date')->nullable();
            $t->string('employment_type', 15)->default('permanent'); // permanent, probation, contract, part_time, guest
            $t->date('contract_end')->nullable();
            $t->string('aadhaar_no', 20)->nullable();
            $t->string('pan_no', 15)->nullable();
            $t->string('bank_name', 100)->nullable();
            $t->string('bank_account', 30)->nullable();
            $t->string('bank_ifsc', 15)->nullable();
            $t->string('uan_no', 20)->nullable();   // PF
            $t->string('esi_no', 20)->nullable();
            $t->string('emergency_name', 100)->nullable();
            $t->string('emergency_phone', 20)->nullable();
            // salary structure (monthly)
            $t->decimal('basic', 10, 2)->default(0);
            $t->decimal('hra', 10, 2)->default(0);
            $t->decimal('da', 10, 2)->default(0);
            $t->decimal('conveyance', 10, 2)->default(0);
            $t->decimal('other_allowance', 10, 2)->default(0);
            $t->decimal('pf', 10, 2)->default(0);
            $t->decimal('esi', 10, 2)->default(0);
            $t->decimal('tds', 10, 2)->default(0);
            $t->decimal('other_deduction', 10, 2)->default(0);
            $t->string('status', 12)->default('active'); // active, resigned, terminated, retired
            $t->date('leaving_date')->nullable();
            $t->string('leaving_reason', 300)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['school_id', 'employee_id']);
        });

        Schema::create('employee_documents', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('title', 100);
            $t->string('path');
            $t->date('valid_till')->nullable();
            $t->timestamps();
        });

        Schema::create('subjects', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('name', 60);
            $t->string('code', 15)->nullable();
            $t->timestamps();
        });

        Schema::table('sections', function (Blueprint $t) {
            $t->foreignId('class_teacher_id')->nullable()->constrained('employees')->nullOnDelete();
        });

        Schema::create('teacher_subjects', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('periods_per_week')->nullable();
            $t->timestamps();
        });

        Schema::create('periods', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('name', 30);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->unsignedTinyInteger('sort_order')->default(1);
            $t->boolean('is_break')->default(false);
            $t->timestamps();
        });

        Schema::create('timetable_entries', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('section_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('day'); // 1 = Monday … 6 = Saturday
            $t->foreignId('period_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->string('room', 20)->nullable();
            $t->timestamps();
            $t->unique(['section_id', 'day', 'period_id']);
            $t->index(['employee_id', 'day', 'period_id']);
        });

        Schema::create('employee_attendances', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('status', 10); // present, absent, half_day, leave, late
            $t->time('in_time')->nullable();
            $t->time('out_time')->nullable();
            $t->string('note', 200)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'date']);
        });

        Schema::create('leave_types', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->string('name', 60);
            $t->string('code', 10);
            $t->decimal('days_per_year', 5, 1)->default(0);
            $t->boolean('is_paid')->default(true);
            $t->timestamps();
        });

        Schema::create('employee_leaves', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $t->date('from_date');
            $t->date('to_date');
            $t->boolean('half_day')->default(false);
            $t->decimal('days', 5, 1);
            $t->string('reason', 300)->nullable();
            $t->string('status', 10)->default('pending'); // pending, approved, rejected, cancelled
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('substitutions', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->date('date');
            $t->foreignId('period_id')->constrained()->cascadeOnDelete();
            $t->foreignId('section_id')->constrained()->cascadeOnDelete();
            $t->foreignId('absent_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->foreignId('substitute_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('note', 200)->nullable();
            $t->timestamps();
            $t->unique(['date', 'period_id', 'section_id']);
        });

        Schema::create('salary_advances', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->date('given_on');
            $t->decimal('amount', 10, 2);
            $t->decimal('monthly_deduction', 10, 2);
            $t->decimal('recovered', 10, 2)->default(0);
            $t->string('note', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $t) use ($school) {
            $t->id();
            $school($t);
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('month', 7);
            $t->decimal('working_days', 4, 1);
            $t->decimal('paid_days', 4, 1);
            $t->decimal('lop_days', 4, 1)->default(0);
            $t->decimal('basic', 10, 2)->default(0);
            $t->decimal('hra', 10, 2)->default(0);
            $t->decimal('da', 10, 2)->default(0);
            $t->decimal('conveyance', 10, 2)->default(0);
            $t->decimal('other_allowance', 10, 2)->default(0);
            $t->decimal('bonus', 10, 2)->default(0);
            $t->decimal('gross', 10, 2)->default(0);
            $t->decimal('pf', 10, 2)->default(0);
            $t->decimal('esi', 10, 2)->default(0);
            $t->decimal('tds', 10, 2)->default(0);
            $t->decimal('advance_deduction', 10, 2)->default(0);
            $t->decimal('lop_deduction', 10, 2)->default(0);
            $t->decimal('other_deduction', 10, 2)->default(0);
            $t->decimal('total_deduction', 10, 2)->default(0);
            $t->decimal('net', 10, 2)->default(0);
            $t->string('status', 10)->default('draft'); // draft, paid
            $t->date('paid_on')->nullable();
            $t->string('method', 20)->nullable();
            $t->string('reference_no', 100)->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'month']);
        });

        Schema::table('expenses', function (Blueprint $t) {
            $t->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
        });

        // default leave types and periods for every school
        foreach (DB::table('schools')->pluck('id') as $sid) {
            $this->seedDefaults((int) $sid);
        }
    }

    public static function seedDefaults(int $sid): void
    {
        $now = now();
        if (! DB::table('leave_types')->where('school_id', $sid)->exists()) {
            foreach ([['Casual Leave', 'CL', 12, true], ['Sick Leave', 'SL', 10, true], ['Earned Leave', 'EL', 15, true], ['Leave Without Pay', 'LWP', 0, false]] as [$n, $c, $d, $p]) {
                DB::table('leave_types')->insert(['school_id' => $sid, 'name' => $n, 'code' => $c, 'days_per_year' => $d, 'is_paid' => $p, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! DB::table('periods')->where('school_id', $sid)->exists()) {
            $rows = [['Period 1', '08:00', '08:40'], ['Period 2', '08:40', '09:20'], ['Period 3', '09:20', '10:00'], ['Period 4', '10:00', '10:40'],
                ['Lunch', '10:40', '11:10', true], ['Period 5', '11:10', '11:50'], ['Period 6', '11:50', '12:30'], ['Period 7', '12:30', '13:10'], ['Period 8', '13:10', '13:50']];
            foreach ($rows as $i => $r) {
                DB::table('periods')->insert(['school_id' => $sid, 'name' => $r[0], 'start_time' => $r[1], 'end_time' => $r[2], 'sort_order' => $i + 1,
                    'is_break' => $r[3] ?? false, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! DB::table('subjects')->where('school_id', $sid)->exists()) {
            foreach (['English', 'Hindi', 'Mathematics', 'Science', 'Social Science', 'Computer', 'Sanskrit', 'General Knowledge', 'Drawing', 'Physical Education',
                'Physics', 'Chemistry', 'Biology', 'Accountancy', 'Business Studies', 'Economics'] as $n) {
                DB::table('subjects')->insert(['school_id' => $sid, 'name' => $n, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('payroll_id'));
        Schema::table('sections', fn (Blueprint $t) => $t->dropConstrainedForeignId('class_teacher_id'));
        foreach (['payrolls', 'salary_advances', 'substitutions', 'employee_leaves', 'leave_types', 'employee_attendances', 'timetable_entries', 'periods',
            'teacher_subjects', 'subjects', 'employee_documents', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
