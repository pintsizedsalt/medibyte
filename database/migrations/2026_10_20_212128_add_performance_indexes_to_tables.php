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
        // === MEDICATIONS TABLE INDEXES ===
        // Note: profiles indexes already exist from previous migrations
        Schema::table('medications', function (Blueprint $table) {
            // Composite index for active medication queries (critical for reminders)
            // Using a unique name since base name may conflict
            $table->index(['user_id', 'is_active', 'start_date', 'end_date'], 'idx_medications_user_active_start_end');
            // Index for end_date queries
            $table->index('end_date', 'idx_medications_end_date');
            // Index for start_date queries
            $table->index('start_date', 'idx_medications_start_date');
        });

        // === DAILY_LOGS TABLE INDEXES ===
        Schema::table('daily_logs', function (Blueprint $table) {
            // The existing unique (user_id, log_date) index already covers this
            // lookup pattern, so only the water query index is added here.
            $table->index('water_ml', 'idx_daily_logs_water_ml');
        });

        // === WEIGHT_LOGS TABLE INDEXES ===
        Schema::table('weight_logs', function (Blueprint $table) {
            // Composite index for latest weight queries. BMI is derived in the
            // model and is not stored, so it has no index.
            $table->index(['user_id', 'log_date'], 'idx_weight_logs_user_date');
        });

        // === SYMPTOM_LOGS TABLE INDEXES ===
        Schema::table('symptom_logs', function (Blueprint $table) {
            // Composite index for user + date queries
            $table->index(['user_id', 'log_date'], 'idx_symptom_logs_user_date');
        });

        // === MOOD_LOGS TABLE INDEXES ===
        Schema::table('mood_logs', function (Blueprint $table) {
            // Composite index for user + date queries
            $table->index(['user_id', 'log_date'], 'idx_mood_logs_user_date');
        });

        // === VITAL_SIGN_LOGS TABLE INDEXES ===
        Schema::table('vital_sign_logs', function (Blueprint $table) {
            // Composite index for user + date queries
            $table->index(['user_id', 'log_date'], 'idx_vital_sign_logs_user_date');
        });

        // === APPOINTMENTS TABLE INDEXES ===
        Schema::table('appointments', function (Blueprint $table) {
            // Index for scheduled appointments
            $table->index(['user_id', 'status'], 'idx_appointments_user_status');
            // Index for appointment_at queries
            $table->index('appointment_at', 'idx_appointments_appointment_at');
        });

        // === SLEEP_LOGS TABLE INDEXES ===
        Schema::table('sleep_logs', function (Blueprint $table) {
            // Composite index for user + date queries
            $table->index(['user_id', 'log_date'], 'idx_sleep_logs_user_date');
            // Index for bedtime/wake_time queries
            $table->index(['bedtime', 'wake_time'], 'idx_sleep_logs_bedtime_wake');
        });

        // === WALKING_LOGS TABLE INDEXES ===
        Schema::table('walking_logs', function (Blueprint $table) {
            // Composite index for user + date queries
            $table->index(['user_id', 'log_date'], 'idx_walking_logs_user_date');
            // Index for activity_type queries
            $table->index('activity_type', 'idx_walking_logs_activity_type');
        });

        // === MEAL_LOGS TABLE INDEXES ===
        Schema::table('meal_logs', function (Blueprint $table) {
            // Composite index for user + date queries
            $table->index(['user_id', 'log_date'], 'idx_meal_logs_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all added indexes in reverse order
        Schema::table('meal_logs', function (Blueprint $table) {
            $table->dropIndex('idx_meal_logs_user_date');
        });

        Schema::table('walking_logs', function (Blueprint $table) {
            $table->dropIndex('idx_walking_logs_user_date');
            $table->dropIndex('idx_walking_logs_activity_type');
        });

        Schema::table('sleep_logs', function (Blueprint $table) {
            $table->dropIndex('idx_sleep_logs_user_date');
            $table->dropIndex('idx_sleep_logs_bedtime_wake');
        });

        Schema::table('vital_sign_logs', function (Blueprint $table) {
            $table->dropIndex('idx_vital_sign_logs_user_date');
        });

        Schema::table('mood_logs', function (Blueprint $table) {
            $table->dropIndex('idx_mood_logs_user_date');
        });

        Schema::table('symptom_logs', function (Blueprint $table) {
            $table->dropIndex('idx_symptom_logs_user_date');
        });

        Schema::table('weight_logs', function (Blueprint $table) {
            $table->dropIndex('idx_weight_logs_user_date');
        });

        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropIndex('idx_daily_logs_water_ml');
        });

        Schema::table('medications', function (Blueprint $table) {
            $table->dropIndex('idx_medications_user_active_start_end');
            $table->dropIndex('idx_medications_end_date');
            $table->dropIndex('idx_medications_start_date');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_user_status');
            $table->dropIndex('idx_appointments_appointment_at');
        });
    }
};