<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workstation_zones', function (Blueprint $table) {
            $table->string('zone_id', 50)->primary();
            $table->string('zone_name', 100);
            $table->integer('bbox_x1');
            $table->integer('bbox_y1');
            $table->integer('bbox_x2');
            $table->integer('bbox_y2');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workstation_zones');
    }
};
