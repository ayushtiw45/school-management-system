<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v1.6.0 — coupon codes made by the owner for the school plan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('description', 200)->nullable();
            $t->string('discount_type', 10)->default('percent'); // percent, flat, none
            $t->decimal('discount_value', 10, 2)->default(0);
            $t->unsignedSmallInteger('extra_days')->default(0);   // free days added before the first monthly charge
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->boolean('new_schools_only')->default(false);
            $t->foreignId('school_id')->nullable()->constrained()->nullOnDelete(); // only this school may use it
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('monthly_before');  // paise
            $t->unsignedInteger('monthly_after');   // paise
            $t->unsignedSmallInteger('extra_days')->default(0);
            $t->timestamps();
            $t->unique(['coupon_id', 'school_id']);
        });

        Schema::table('subscriptions', function (Blueprint $t) {
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('list_amount')->nullable();   // monthly price before the coupon (paise)
            $t->unsignedSmallInteger('extra_days')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('coupon_id');
            $t->dropColumn(['list_amount', 'extra_days']);
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
