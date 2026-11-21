<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->date('start_date');
            $tabla->date('end_date');
            $tabla->timestamps();

            $tabla->index(['start_date', 'end_date']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('maintenance_id')->constrained('maintenances')->cascadeOnDelete();
            $tabla->foreignId('vehicle_id')->constrained('vehicles');
            $tabla->foreignId('user_id')->constrained('users');
            $tabla->string('type', 12);
            $tabla->unsignedTinyInteger('weekday');
            $tabla->time('time_start');
            $tabla->time('time_end');
            $tabla->timestamps();

            $tabla->index(['maintenance_id', 'vehicle_id', 'weekday'], 'maint_sched_vehicle_day');
        });

        Schema::create('maintenance_days', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('schedule_id')->constrained('maintenance_schedules')->cascadeOnDelete();
            $tabla->date('date');
            $tabla->string('status', 12)->default('pendiente');
            $tabla->string('observation', 500)->nullable();
            $tabla->string('image_path')->nullable();
            $tabla->timestamps();

            $tabla->unique(['schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_days');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('maintenances');
    }
};
