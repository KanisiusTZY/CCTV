<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('cctv_source', 255)->default('h.mp4');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tambahkan room_id ke workstation_zones jika belum ada
        if (!Schema::hasColumn('workstation_zones', 'room_id')) {
            Schema::table('workstation_zones', function (Blueprint $table) {
                $table->unsignedBigInteger('room_id')->nullable()->after('zone_id');
                $table->foreign('room_id')->references('id')->on('rooms')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workstation_zones', 'room_id')) {
            Schema::table('workstation_zones', function (Blueprint $table) {
                $table->dropForeign(['room_id']);
                $table->dropColumn('room_id');
            });
        }
        Schema::dropIfExists('rooms');
    }
};
