<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_salary')->default(false); // shows staff name + salary month fields
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 10, 2);
            $table->string('title');
            $table->string('paid_to')->nullable();        // vendor or staff member
            $table->string('salary_month', 7)->nullable(); // YYYY-MM for salary records
            $table->string('payment_method', 15)->default('cash');
            $table->string('reference_no')->nullable();
            $table->string('bill_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('expense_date');
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 10);      // sms, whatsapp
            $table->string('type', 20);         // fee_due, payment, admission, announcement
            $table->string('recipient', 20);
            $table->text('message');
            $table->string('status', 10)->default('queued'); // queued, sent, failed, logged
            $table->text('response')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
