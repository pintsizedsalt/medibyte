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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cycle_reminders_enabled')) {
                $table->boolean('cycle_reminders_enabled')->default(true);
            }
            if (! Schema::hasColumn('users', 'cycle_lead_days')) {
                $table->integer('cycle_lead_days')->default(2);
            }
            if (! Schema::hasColumn('users', 'cycle_reminder_time')) {
                $table->string('cycle_reminder_time')->default('08:00');
            }
            if (! Schema::hasColumn('users', 'cycle_fertile_reminder')) {
                $table->boolean('cycle_fertile_reminder')->default(false);
            }
            if (! Schema::hasColumn('users', 'cycle_ovulation_reminder')) {
                $table->boolean('cycle_ovulation_reminder')->default(false);
            }
            if (! Schema::hasColumn('users', 'cycle_pms_reminder')) {
                $table->boolean('cycle_pms_reminder')->default(false);
            }
            if (! Schema::hasColumn('users', 'cycle_late_reminder')) {
                $table->boolean('cycle_late_reminder')->default(true);
            }
            if (! Schema::hasColumn('users', 'cycle_daily_log_reminder')) {
                $table->boolean('cycle_daily_log_reminder')->default(false);
            }
            if (! Schema::hasColumn('users', 'cycle_pill_reminder')) {
                $table->boolean('cycle_pill_reminder')->default(false);
            }
            if (! Schema::hasColumn('users', 'cycle_pill_time')) {
                $table->string('cycle_pill_time')->default('');
            }
            if (! Schema::hasColumn('users', 'cycle_discreet')) {
                $table->boolean('cycle_discreet')->default(false);
            }
            if (! Schema::hasColumn('users', 'cycle_paused')) {
                $table->boolean('cycle_paused')->default(false);
            }
            if (! Schema::hasColumn('users', 'cycle_pause_reason')) {
                $table->string('cycle_pause_reason')->default('other');
            }
            if (! Schema::hasColumn('users', 'cycle_daily_log_time')) {
                $table->string('cycle_daily_log_time')->default('08:00');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cycle_reminders_enabled', 'cycle_lead_days', 'cycle_reminder_time', 'cycle_fertile_reminder', 'cycle_ovulation_reminder', 'cycle_pms_reminder', 'cycle_late_reminder', 'cycle_daily_log_reminder', 'cycle_pill_reminder', 'cycle_pill_time', 'cycle_discreet', 'cycle_paused', 'cycle_pause_reason', 'cycle_daily_log_time']);
        });
    }
};
