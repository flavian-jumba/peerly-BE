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
        Schema::table('appointments', function (Blueprint $table) {
            // Add duration in minutes (default 60 minutes = 1 hour)
            $table->integer('duration_minutes')->default(60)->after('appointment_at');
            
            // Add created_by to track who created the appointment (admin, user, system)
            $table->string('created_by')->default('user')->after('notes');
            
            // Add indexes for faster conflict checking
            $table->index(['user_id', 'appointment_at']);
            $table->index(['therapist_id', 'appointment_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'appointment_at']);
            $table->dropIndex(['therapist_id', 'appointment_at']);
            $table->dropColumn(['duration_minutes', 'created_by']);
        });
    }
};
