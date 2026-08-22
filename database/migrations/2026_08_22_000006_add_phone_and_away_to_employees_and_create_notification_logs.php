<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('phone_number', 50)->nullable()->after('position');
            $table->integer('max_away_minutes')->default(15)->after('phone_number');
        });

        Schema::create('presence_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('zone_id', 50)->nullable();
            $table->string('phone_number', 50);
            $table->string('notification_type', 50)->default('AWAY_THRESHOLD');
            $table->text('message');
            $table->string('status', 30)->default('SENT');
            $table->integer('away_duration_minutes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_notification_logs');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'max_away_minutes']);
        });
    }
};
