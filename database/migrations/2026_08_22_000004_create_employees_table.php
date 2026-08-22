<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('photo_filename', 255)->nullable();
            $table->string('assigned_zone_id', 50)->nullable();
            $table->string('position', 100)->nullable();
            $table->timestamps();

            $table->foreign('assigned_zone_id')
                  ->references('zone_id')
                  ->on('workstation_zones')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
