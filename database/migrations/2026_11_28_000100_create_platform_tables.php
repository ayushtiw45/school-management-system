<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings of the platform owner (Razorpay keys, prices, contact) — not per school
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Razorpay Autopay subscription of a school
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('razorpay_subscription_id')->unique();
            $table->string('razorpay_plan_id');
            $table->unsignedInteger('monthly_amount');        // paise
            $table->unsignedInteger('trial_amount')->default(0); // paise, 0 = no trial
            $table->string('status', 15)->default('created');  // created, authenticated, active, pending, halted, cancelled, completed
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_end')->nullable();
            $table->unsignedInteger('paid_count')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('platform_settings');
    }
};
