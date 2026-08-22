<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_zone_summary', function (Blueprint $table) {
            $table->id('summary_id');
            $table->date('date');
            $table->string('zone_id', 50);
            $table->integer('total_working_seconds')->default(0);
            $table->integer('total_away_seconds')->default(0);
            $table->timestamp('last_updated')->useCurrent();

            $table->unique(['date', 'zone_id']);
            $table->foreign('zone_id')->references('zone_id')->on('workstation_zones')->onDelete('cascade');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_zone_summary');
    }
};
