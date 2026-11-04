<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_logs', function (Blueprint $table) {
            $table->time('meal_time')->nullable()->after('meal_type');
            $table->unsignedInteger('calories')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('meal_logs', function (Blueprint $table) {
            $table->dropColumn('meal_time');
        });
    }
};
