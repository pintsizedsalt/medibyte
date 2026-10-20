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
        Schema::create('mood_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mood');
            $table->unsignedTinyInteger('intensity');
            $table->boolean('cried')->default(false);
            $table->boolean('has_big_event')->default(false);
            $table->string('event_note')->nullable();
            $table->json('impacts')->nullable();
            $table->text('reflection')->nullable();
            $table->date('log_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mood_logs');
    }
};
