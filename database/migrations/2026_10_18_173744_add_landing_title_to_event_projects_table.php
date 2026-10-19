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
        Schema::table('event_projects', function (Blueprint $table) {
            $table->string('landing_title', 160)->nullable();
            $table->string('landing_subtitle', 300)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_projects', function (Blueprint $table) {
            $table->dropColumn(['landing_title', 'landing_subtitle']);
        });
    }
};
