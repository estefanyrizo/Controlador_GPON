<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->boolean('multiple_isp')->nullable()->default(true);
            $table->unsignedBigInteger('default_isp')->nullable();
        });

        Schema::create('isps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('user_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->foreignId('user_type_id')->constrained('user_types');
            $table->boolean('status')->default(true);
            $table->foreignId('isp_id')->nullable()->constrained('isps');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->ipAddress('ip_olt')->nullable();
            $table->string('description')->nullable();
            $table->string('port')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('must_login')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('isp_olt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isps')->cascadeOnDelete();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->string('relation_name')->nullable();
            $table->text('relation_notes')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['isp_id', 'olt_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->nullable()->constrained('isps')->nullOnDelete();
            $table->foreignId('olt_id')->nullable()->constrained('olts')->nullOnDelete();
            $table->string('gpon_interface')->nullable();
            $table->string('service_number')->nullable();
            $table->string('code_customer')->nullable();
            $table->string('customer_name')->nullable();
            $table->boolean('obtained_status')->nullable();
            $table->string('obtained_velocity')->nullable();
            $table->timestamps();
        });

        // activity_logs moved to a separate migration (optimized)
    }

    public function down(): void
    {
        Schema::dropIfExists('isp_olt');
        Schema::dropIfExists('olts');
        Schema::dropIfExists('command_actions');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_types');
        Schema::dropIfExists('isps');
        Schema::dropIfExists('configurations');
        Schema::dropIfExists('customers');
    }
};