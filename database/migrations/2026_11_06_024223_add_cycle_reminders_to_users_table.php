<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cycle reminders are their own feature, configured the same way as the
     * appointment and medication cards: a master switch, the notice that arrives a
     * chosen number of days before a predicted period, optional notices for the
     * fertile window and ovulation day, and a handful of wellness nudges (PMS,
     * late period, a daily symptom log, and a daily pill). `cycle_paused` lets a
     * person stop predictions entirely while pregnant or on hormonal birth control.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('cycle_reminders_enabled')->default(true)->after('medication_repeat_minutes');
            $table->unsignedTinyInteger('cycle_lead_days')->default(2)->after('cycle_reminders_enabled');
            $table->string('cycle_reminder_time')->default('08:00')->after('cycle_lead_days');
            $table->boolean('cycle_fertile_reminder')->default(false)->after('cycle_reminder_time');
            $table->boolean('cycle_ovulation_reminder')->default(false)->after('cycle_fertile_reminder');
            $table->boolean('cycle_pms_reminder')->default(false)->after('cycle_ovulation_reminder');
            $table->boolean('cycle_late_reminder')->default(true)->after('cycle_pms_reminder');
            $table->boolean('cycle_daily_log_reminder')->default(false)->after('cycle_late_reminder');
            $table->string('cycle_daily_log_time')->nullable()->after('cycle_daily_log_reminder');
            $table->boolean('cycle_pill_reminder')->default(false)->after('cycle_daily_log_time');
            $table->string('cycle_pill_time')->nullable()->after('cycle_pill_reminder');
            $table->boolean('cycle_discreet')->default(false)->after('cycle_pill_time');
            $table->boolean('cycle_paused')->default(false)->after('cycle_discreet');
            $table->string('cycle_pause_reason')->nullable()->after('cycle_paused');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cycle_reminders_enabled',
                'cycle_lead_days',
                'cycle_reminder_time',
                'cycle_fertile_reminder',
                'cycle_ovulation_reminder',
                'cycle_pms_reminder',
                'cycle_late_reminder',
                'cycle_daily_log_reminder',
                'cycle_daily_log_time',
                'cycle_pill_reminder',
                'cycle_pill_time',
                'cycle_discreet',
                'cycle_paused',
                'cycle_pause_reason',
            ]);
        });
    }
};
