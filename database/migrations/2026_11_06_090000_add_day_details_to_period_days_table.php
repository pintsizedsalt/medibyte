<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The period_days table was first created with only the basic columns.
     * This adds the day-log details (temperature, tests, medication, sex,
     * notes) and the one-row-per-day rule. Every step checks first, so it is
     * safe on a fresh database and on one that already ran the first version.
     */
    public function up(): void
    {
        Schema::table('period_days', function (Blueprint $table) {
            if (! Schema::hasColumn('period_days', 'bbt')) {
                $table->decimal('bbt', 4, 2)->nullable();
            }
            if (! Schema::hasColumn('period_days', 'lh_test')) {
                $table->string('lh_test')->nullable();
            }
            if (! Schema::hasColumn('period_days', 'pregnancy_test')) {
                $table->string('pregnancy_test')->nullable();
            }
            if (! Schema::hasColumn('period_days', 'medication')) {
                $table->string('medication')->nullable();
            }
            if (! Schema::hasColumn('period_days', 'sexual_activity')) {
                $table->boolean('sexual_activity')->nullable();
            }
            if (! Schema::hasColumn('period_days', 'protection')) {
                $table->boolean('protection')->nullable();
            }
            if (! Schema::hasColumn('period_days', 'notes')) {
                $table->string('notes')->nullable();
            }
        });

        if (! Schema::hasIndex('period_days', ['user_id', 'log_date'], 'unique')) {
            Schema::table('period_days', function (Blueprint $table) {
                $table->unique(['user_id', 'log_date']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('period_days', ['user_id', 'log_date'], 'unique')) {
            Schema::table('period_days', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'log_date']);
            });
        }

        $columns = array_filter(
            ['bbt', 'lh_test', 'pregnancy_test', 'medication', 'sexual_activity', 'protection', 'notes'],
            fn ($column) => Schema::hasColumn('period_days', $column)
        );

        if ($columns !== []) {
            Schema::table('period_days', function (Blueprint $table) use ($columns) {
                $table->dropColumn(array_values($columns));
            });
        }
    }
};
