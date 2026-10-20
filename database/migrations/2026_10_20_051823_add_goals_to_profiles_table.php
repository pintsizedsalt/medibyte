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
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedInteger('water_goal_ml')->default(2000);
            $table->decimal('sleep_goal_hours', 4, 2)->default(8);
            $table->unsignedInteger('walking_goal_minutes')->default(30);
            $table->unsignedInteger('calorie_goal')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['water_goal_ml', 'sleep_goal_hours', 'walking_goal_minutes', 'calorie_goal']);
        });
    }
};
