<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History of software updates done from the owner panel
        Schema::create('update_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 10)->default('update'); // update, rollback
            $table->string('from_version', 30)->nullable();
            $table->string('to_version', 30)->nullable();
            $table->string('status', 10);                     // success, failed
            $table->text('message')->nullable();
            $table->string('code_backup')->nullable();        // file name in storage/app/backups
            $table->string('db_backup')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_logs');
    }
};
