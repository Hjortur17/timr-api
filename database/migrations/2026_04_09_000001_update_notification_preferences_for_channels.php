<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns to notification_preferences
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('channel_push')->default(true)->after('enabled');
            $table->boolean('channel_email')->default(true)->after('channel_push');
            $table->boolean('channel_in_app')->default(true)->after('channel_email');
            $table->json('timing_value')->nullable()->after('channel_in_app');
        });

        // Backfill user_id from employees table
        DB::statement('
            UPDATE notification_preferences
            SET user_id = (
                SELECT user_id FROM employees WHERE employees.id = notification_preferences.employee_id
            )
        ');

        // Map old type values to new ones
        DB::table('notification_preferences')
            ->where('type', 'shift_changed')
            ->update(['type' => 'schedule_change_alert']);

        DB::table('notification_preferences')
            ->where('type', 'shift_reminder')
            ->update(['type' => 'shift_start_reminder']);

        // Copy enabled state to channel columns before dropping
        DB::statement('
            UPDATE notification_preferences
            SET channel_push = enabled,
                channel_email = enabled,
                channel_in_app = enabled
        ');

        // Remove rows with null user_id (orphaned employee records)
        DB::table('notification_preferences')->whereNull('user_id')->delete();

        // Drop old constraints and columns (foreign key must be dropped before unique index in MySQL)
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['employee_id', 'type']);
            $table->dropColumn(['employee_id', 'enabled']);
        });

        // Rename type to notification_type
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->renameColumn('type', 'notification_type');
        });

        // Make user_id non-nullable and add new unique constraint
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'notification_type']);
        });

        // Add notification settings to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notifications_paused')->default(false)->after('onboarding_step');
            $table->string('quiet_hours_start', 5)->nullable()->after('notifications_paused');
            $table->string('quiet_hours_end', 5)->nullable()->after('quiet_hours_start');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notifications_paused', 'quiet_hours_start', 'quiet_hours_end']);
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'notification_type']);
            $table->renameColumn('notification_type', 'type');
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true)->after('type');
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'channel_push', 'channel_email', 'channel_in_app', 'timing_value']);
        });

        // Map new type values back to old ones
        DB::table('notification_preferences')
            ->where('type', 'schedule_change_alert')
            ->update(['type' => 'shift_changed']);

        DB::table('notification_preferences')
            ->where('type', 'shift_start_reminder')
            ->update(['type' => 'shift_reminder']);

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->unique(['employee_id', 'type']);
        });
    }
};
