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
        Schema::table('walking_logs', function (Blueprint $table) {
            $table->string('activity_type')->default('walking');
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->unsignedInteger('steps')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('walking_logs', function (Blueprint $table) {
            $table->dropColumn(['activity_type', 'minutes']);
        });
    }
};
