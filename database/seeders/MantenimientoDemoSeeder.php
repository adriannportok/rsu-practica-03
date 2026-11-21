<?php

namespace Database\Seeders;

use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\Vehiculo;
use App\Services\ServicioMantenimiento;
use Illuminate\Database\Seeder;

class MantenimientoDemoSeeder extends Seeder
{
    /**
     * Dos mantenimientos de ejemplo con sus horarios (los días se generan solos) para que la pantalla no aparezca vacía.
     * Usa los vehículos y el personal que ya crean VehiculoSeeder y PersonalSeeder; lo que falte se omite.
     */
    public function run(): void
    {
        // nombre => [inicio, fin, horarios[código de vehículo, tipo, día ISO, hora inicio, hora fin, DNI del responsable]]
        $mantenimientos = [
            'MANT. DICIEMBRE 2026' => ['2026-12-01', '2026-12-31', [
                ['VEH-001', HorarioMantenimiento::LIMPIEZA, 1, '08:00', '11:00', '12345678'],
                ['VEH-002', HorarioMantenimiento::LIMPIEZA, 1, '11:00', '13:00', '70000008'],
                ['VEH-001', HorarioMantenimiento::PREVENTIVO, 3, '08:00', '10:00', '12345678'],
            ]],
            'MANT. ENERO 2027' => ['2027-01-01', '2027-01-31', [
                ['VEH-003', HorarioMantenimiento::REPARACION, 2, '09:00', '12:00', '70000008'],
                ['VEH-004', HorarioMantenimiento::PREVENTIVO, 5, '14:00', '16:00', '12345678'],
            ]],
        ];

        foreach ($mantenimientos as $nombre => [$inicio, $fin, $horarios]) {
            if (Mantenimiento::where('name', $nombre)->exists()
                || ServicioMantenimiento::mantenimientoQueSeCruza($inicio, $fin)) {
                continue;
            }

            $mantenimiento = Mantenimiento::create(['name' => $nombre, 'start_date' => $inicio, 'end_date' => $fin]);

            foreach ($horarios as [$codigo, $tipo, $dia, $horaInicio, $horaFin, $dni]) {
                $vehiculo = Vehiculo::firstWhere('code', $codigo);
                $responsable = Personal::firstWhere('dni', $dni);

                if (! $vehiculo || ! $responsable) {
                    continue;
                }

                $horario = $mantenimiento->horarios()->create([
                    'vehicle_id' => $vehiculo->id, 'user_id' => $responsable->id, 'type' => $tipo, 'weekday' => $dia,
                    'time_start' => ServicioMantenimiento::hora($horaInicio), 'time_end' => ServicioMantenimiento::hora($horaFin),
                ]);

                ServicioMantenimiento::generarDias($horario->setRelation('mantenimiento', $mantenimiento));
            }
        }
    }
}
