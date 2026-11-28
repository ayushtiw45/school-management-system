<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->unique(['school_id', 'key']);
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. 2026-27
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();
            $table->unique(['school_class_id', 'name']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('admission_no');
            $table->unique(['school_id', 'admission_no']);
            $table->date('admission_date');
            $table->string('admission_type', 10)->default('manual'); // manual, online
            $table->string('status', 15)->default('pending'); // pending, active, inactive, left
            $table->foreignId('academic_session_id')->constrained();
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('roll_no', 20)->nullable();

            // Personal details
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('gender', 10);
            $table->date('dob')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('category', 20)->nullable();
            $table->string('photo')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('pincode', 10)->nullable();

            // Parent / guardian
            $table->string('father_name')->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation', 50)->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->string('email')->nullable();

            // Previous school
            $table->string('previous_school')->nullable();
            $table->string('previous_class', 30)->nullable();
            $table->string('tc_number', 50)->nullable();

            // Fees
            $table->decimal('concession_percent', 5, 2)->default(0);

            // Leaving / transfer
            $table->date('leaving_date')->nullable();
            $table->string('leaving_reason')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['school_class_id', 'section_id', 'status']);
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency', 15)->default('monthly'); // one_time, monthly, quarterly, half_yearly, yearly
            $table->boolean('concession_applicable')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            $table->unique(['academic_session_id', 'school_class_id', 'fee_head_id'], 'fee_structure_unique');
        });

        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained();
            $table->foreignId('fee_head_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('fine', 10, 2)->default(0);
            $table->decimal('paid', 10, 2)->default(0);
            $table->string('status', 10)->default('unpaid'); // unpaid, partial, paid
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_no');
            $table->unique(['school_id', 'receipt_no']);
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 10, 2);
            $table->string('method', 15); // cash, upi, bank, cheque, online
            $table->string('reference_no')->nullable(); // UPI txn id / bank ref / cheque no
            $table->string('bank_name')->nullable();
            $table->date('cheque_date')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('payment_date');
        });

        Schema::create('payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_fee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('fine', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payment_items', 'payments', 'student_fees', 'fee_structures', 'fee_heads',
            'student_documents', 'students', 'sections', 'school_classes', 'academic_sessions', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
