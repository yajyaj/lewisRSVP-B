<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('internal_name');
            $table->string('public_slug')->unique();
            $table->string('combined_title');
            $table->text('welcome_message')->nullable();
            $table->date('event_date');
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->timestamp('rsvp_deadline')->nullable();
            $table->string('cover_image_url', 2048)->nullable();
            $table->json('gallery_urls')->nullable();
            $table->string('theme', 20)->default('sage');
            $table->enum('access_mode', ['public', 'private'])->default('public');
            $table->boolean('allow_plus_ones')->default(true);
            $table->unsignedTinyInteger('max_party_size')->default(5);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['status', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_projects');
    }
};
