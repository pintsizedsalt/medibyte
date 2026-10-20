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
            $table->timestamps();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('doctor_name');
            $table->string('specialty')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('office_address')->nullable();
            $table->string('hospital')->nullable();
            $table->dateTime('appointment_at');
            $table->string('reason')->nullable();
            $table->string('notes')->nullable();
            $table->string('status')->default('scheduled');
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
