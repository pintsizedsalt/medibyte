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
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('username')->nullable()->unique()->after('last_name');
        });

        // Give existing test/demo accounts a valid identity and unique username.
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $name = trim((string) ($user->name ?? ''));
            $parts = preg_split('/\s+/', $name !== '' ? $name : 'MediByte User');
            $first = $parts[0] ?? 'MediByte';
            $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'User';
            $base = strtolower(preg_replace('/[^A-Za-z0-9_]/', '', $first.$last) ?: 'user');

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $first,
                'last_name' => $last,
                'username' => substr($base, 0, 24).$user->id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['first_name', 'last_name', 'username']);
        });
    }
};
