<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reminders are configured per feature: how far ahead of an appointment or a
     * dose to speak up, whether to keep nudging until the notice is opened, and
     * whether that feature sends anything at all. The columns stay null until a
     * person chooses, so an account keeps the reminders it already had.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('appointment_reminders_enabled')->default(true)->after('reminders_enabled');
            $table->json('appointment_lead_minutes')->nullable()->after('appointment_reminders_enabled');
            $table->boolean('appointment_day_notice')->default(false)->after('appointment_lead_minutes');
            $table->unsignedSmallInteger('appointment_repeat_minutes')->nullable()->after('appointment_day_notice');
            $table->boolean('medication_reminders_enabled')->default(true)->after('appointment_repeat_minutes');
            $table->json('medication_lead_minutes')->nullable()->after('medication_reminders_enabled');
            $table->unsignedSmallInteger('medication_repeat_minutes')->nullable()->after('medication_lead_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'appointment_reminders_enabled',
                'appointment_lead_minutes',
                'appointment_day_notice',
                'appointment_repeat_minutes',
                'medication_reminders_enabled',
                'medication_lead_minutes',
                'medication_repeat_minutes',
            ]);
        });
    }
};
