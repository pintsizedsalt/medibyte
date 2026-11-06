<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per day a person fills in from the cycle calendar: how heavy the
     * flow is, how much it hurts, and the wider picture — symptoms, mood, discharge,
     * temperature, tests, sex and notes. Kept apart from the period episodes so any
     * day can be logged without being part of a period.
     */
    public function up(): void
    {
        Schema::create('period_days', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->string('flow')->nullable();
            $table->unsignedTinyInteger('pain')->nullable();
            $table->json('symptoms')->nullable();
            $table->string('mood')->nullable();
            $table->string('discharge')->nullable();
            $table->decimal('bbt', 4, 2)->nullable();
            $table->string('lh_test')->nullable();
            $table->string('pregnancy_test')->nullable();
            $table->string('medication')->nullable();
            $table->boolean('sexual_activity')->nullable();
            $table->boolean('protection')->nullable();
            $table->string('notes')->nullable();

            $table->unique(['user_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('period_days');
    }
};
