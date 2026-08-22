<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_event_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->string('zone_id', 50);
            $table->integer('track_id')->nullable();
            $table->string('previous_status', 30);
            $table->string('current_status', 30);
            $table->float('confidence')->nullable();
            $table->float('iou_score')->nullable();
            $table->timestamp('timestamp')->useCurrent();

            $table->foreign('zone_id')->references('zone_id')->on('workstation_zones')->onDelete('cascade');
            $table->index('timestamp');
            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_event_logs');
    }
};
