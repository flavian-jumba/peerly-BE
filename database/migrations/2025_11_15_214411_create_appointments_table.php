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
        Schema::create('appointments', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                    $table->foreignId('therapist_id')->constrained('therapists')->onDelete('cascade');
                    $table->datetime('appointment_at');
                    $table->string('status')->default('pending');
                    $table->text('notes')->nullable();
                    $table->timestamps();
                });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
