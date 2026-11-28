<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Version 1.2.0: four roles, enquiries, unique student IDs, classes up to 12. Keeps all existing data. */
return new class extends Migration
{
    public function up(): void
    {
        // roles: "Admission Staff" is now "Receptionist"
        DB::table('users')->where('role', 'admission_staff')->update(['role' => 'receptionist']);

        // unique student ID per school (roll numbers stay per class)
        Schema::table('students', function (Blueprint $table) {
            $table->string('student_code', 30)->nullable()->after('admission_no');
            $table->unique(['school_id', 'student_code']);
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('child_name');
            $table->string('parent_name')->nullable();
            $table->string('phone', 20);
            $table->string('alt_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete(); // class wanted
            $table->date('dob')->nullable();
            $table->string('previous_school')->nullable();
            $table->string('source', 20)->default('walk_in'); // walk_in, phone, website, reference, social, other
            $table->text('notes')->nullable();
            $table->string('status', 15)->default('new');      // new, follow_up, converted, not_interested
            $table->date('next_follow_up')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete(); // after conversion
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'status', 'next_follow_up']);
        });

        Schema::create('enquiry_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->date('next_follow_up')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // every existing school: add Class 9–12 (sections A, B) after its last class
        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            $order = (int) DB::table('school_classes')->where('school_id', $schoolId)->max('sort_order');
            foreach (['Class 9', 'Class 10', 'Class 11', 'Class 12'] as $name) {
                if (DB::table('school_classes')->where('school_id', $schoolId)->where('name', $name)->exists()) {
                    continue;
                }
                $classId = DB::table('school_classes')->insertGetId([
                    'school_id' => $schoolId, 'name' => $name, 'sort_order' => ++$order, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (['A', 'B'] as $sec) {
                    DB::table('sections')->insert(['school_id' => $schoolId, 'school_class_id' => $classId, 'name' => $sec, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            // give admitted students their student ID, oldest first
            $seq = [];
            $students = DB::table('students')->where('school_id', $schoolId)->whereNull('student_code')
                ->whereIn('status', ['active', 'inactive', 'left'])->orderBy('admission_date')->orderBy('id')->get(['id', 'admission_date']);
            foreach ($students as $st) {
                $yy = substr((string) $st->admission_date, 2, 2) ?: date('y');
                $seq[$yy] = ($seq[$yy] ?? 0) + 1;
                DB::table('students')->where('id', $st->id)->update(['student_code' => 'STU'.$yy.'-'.str_pad((string) $seq[$yy], 4, '0', STR_PAD_LEFT)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_follow_ups');
        Schema::dropIfExists('enquiries');
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'student_code']);
            $table->dropColumn('student_code');
        });
        DB::table('users')->where('role', 'receptionist')->update(['role' => 'admission_staff']);
    }
};
