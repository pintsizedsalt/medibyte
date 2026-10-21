<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BMI is derived from weight and height, so the stored column is not needed.
        if (Schema::hasColumn('weight_logs', 'bmi')) {
            Schema::table('weight_logs', function (Blueprint $table) {
                $table->dropColumn('bmi');
            });
        }

        if (! Schema::hasColumn('profiles', 'onboarding_completed_at')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('reminders_enabled');
            });

            // Existing accounts already have a profile, so don't force them back
            // through the new setup wizard after this migration runs.
            DB::table('profiles')->whereNull('onboarding_completed_at')->update([
                'onboarding_completed_at' => DB::raw('updated_at'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'onboarding_completed_at')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('onboarding_completed_at');
            });
        }

        if (! Schema::hasColumn('weight_logs', 'bmi')) {
            Schema::table('weight_logs', function (Blueprint $table) {
                $table->decimal('bmi', 5, 2)->nullable()->after('height_cm');
            });
        }
    }
};
