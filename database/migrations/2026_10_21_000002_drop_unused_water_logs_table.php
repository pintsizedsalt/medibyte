<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Water is stored once, in daily_logs. The earlier water_logs table was
        // never used by the application after daily_logs was introduced.
        Schema::dropIfExists('water_logs');
    }

    public function down(): void
    {
        Schema::create('water_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_ml');
            $table->time('logged_time')->nullable();
            $table->string('note')->nullable();
            $table->date('log_date');
            $table->timestamps();
            $table->index(['user_id', 'log_date']);
        });
    }
};
