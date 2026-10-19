<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasion_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_project_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['event_project_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occasion_labels');
    }
};
