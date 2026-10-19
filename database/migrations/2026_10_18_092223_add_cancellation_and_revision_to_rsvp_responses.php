<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsvp_responses', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->index(['event_project_id', 'cancelled_at', 'submitted_at'], 'rsvp_event_active_submitted_index');
        });
        Schema::table('admin_audit_events', function (Blueprint $table): void {
            $table->string('reason', 500)->nullable();
            $table->index(['subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_events', function (Blueprint $table): void {
            $table->dropIndex(['subject_id', 'created_at']);
            $table->dropColumn('reason');
        });
        Schema::table('rsvp_responses', function (Blueprint $table): void {
            $table->dropIndex('rsvp_event_active_submitted_index');
            $table->dropColumn(['cancelled_at', 'revision']);
        });
    }
};
