<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sex')->nullable();
            $table->string('period_status')->nullable();
            $table->decimal('weight_goal', 5, 2)->nullable();
            $table->unsignedInteger('water_goal_ml')->default(2000);
            $table->decimal('sleep_goal_hours', 4, 2)->default(8);
            $table->unsignedInteger('walking_goal_steps')->default(8000);
            $table->unsignedInteger('calorie_goal')->nullable();
            $table->unsignedTinyInteger('cycle_length')->default(28);
            $table->boolean('reminders_enabled')->default(true);
            $table->timestamp('onboarding_completed_at')->nullable();
        });

        if (Schema::hasTable('profiles')) {
            foreach (DB::table('users')->orderBy('id')->get() as $user) {
                $profile = DB::table('profiles')->where('user_id', $user->id)->first();

                DB::table('users')->where('id', $user->id)->update([
                    'sex' => $profile->sex ?? null,
                    'period_status' => $profile->period_status ?? null,
                    'weight_goal' => $profile->weight_goal ?? null,
                    'water_goal_ml' => $profile->water_goal_ml ?? 2000,
                    'sleep_goal_hours' => $profile->sleep_goal_hours ?? 8,
                    'walking_goal_steps' => $profile->walking_goal_steps ?? 8000,
                    'calorie_goal' => $profile->calorie_goal ?? null,
                    'cycle_length' => $profile->cycle_length ?? 28,
                    'reminders_enabled' => $profile->reminders_enabled ?? true,
                    'onboarding_completed_at' => $profile->onboarding_completed_at ?? null,
                ]);
            }

            Schema::dropIfExists('profiles');
        }
    }

    public function down(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sex')->nullable();
            $table->string('period_status')->nullable();
            $table->decimal('weight_goal', 5, 2)->nullable();
            $table->unsignedInteger('water_goal_ml')->default(2000);
            $table->decimal('sleep_goal_hours', 4, 2)->default(8);
            $table->unsignedInteger('walking_goal_steps')->default(8000);
            $table->unsignedInteger('calorie_goal')->nullable();
            $table->unsignedTinyInteger('cycle_length')->default(28);
            $table->boolean('reminders_enabled')->default(true);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            DB::table('profiles')->insert([
                'user_id' => $user->id,
                'sex' => $user->sex,
                'period_status' => $user->period_status,
                'weight_goal' => $user->weight_goal,
                'water_goal_ml' => $user->water_goal_ml,
                'sleep_goal_hours' => $user->sleep_goal_hours,
                'walking_goal_steps' => $user->walking_goal_steps,
                'calorie_goal' => $user->calorie_goal,
                'cycle_length' => $user->cycle_length,
                'reminders_enabled' => $user->reminders_enabled,
                'onboarding_completed_at' => $user->onboarding_completed_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sex',
                'period_status',
                'weight_goal',
                'water_goal_ml',
                'sleep_goal_hours',
                'walking_goal_steps',
                'calorie_goal',
                'cycle_length',
                'reminders_enabled',
                'onboarding_completed_at',
            ]);
        });
    }
};
