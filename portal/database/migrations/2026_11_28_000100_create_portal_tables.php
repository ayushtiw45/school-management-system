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
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('installation_id', 30)->index();
            $table->string('school_name');
            $table->string('plan', 20);           // 1y, 2y, 3y, life, custom
            $table->date('starts_on');
            $table->date('expires_on')->nullable(); // null = lifetime
            $table->text('license_key');
            $table->string('source', 10);         // online, manual
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('installation_id', 30);
            $table->string('plan', 20);
            $table->unsignedInteger('amount');     // in paise
            $table->string('currency', 3)->default('INR');
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('status', 10)->default('created'); // created, paid, failed
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('settings');
    }
};
