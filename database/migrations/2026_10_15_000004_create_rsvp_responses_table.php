<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsvp_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('public_response_key_hash', 64)->nullable();
            $table->enum('attendance_status', ['attending', 'declined']);
            $table->unsignedTinyInteger('party_size')->default(0);
            $table->json('additional_guest_names')->nullable();
            $table->text('dietary_or_accessibility_notes')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['event_project_id', 'invitation_id']);
            $table->unique(['event_project_id', 'public_response_key_hash']);
            $table->index(['event_project_id', 'attendance_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvp_responses');
    }
};
