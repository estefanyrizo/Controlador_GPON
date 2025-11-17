<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('obtained_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->onDelete('cascade');
            $table->string('gpon_interface'); // Unique reference (e.g., 1/1/6:10)
            $table->string('customer_name');
            $table->text('raw_config_section')->nullable(); // Store the entire interface section for debugging
            $table->boolean('status')->default(false);
            $table->string('speed')->nullable();
            $table->timestamp('last_updated_at');
            $table->timestamps();
            
            // Index for efficient searches
            $table->index(['olt_id', 'gpon_interface']);
            $table->index('last_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obtained_customers');
    }
}; 