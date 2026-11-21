<?php

namespace App\Services;

use App\Models\DiaMantenimiento;
use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del módulo de Mantenimiento (Práctica 03): fechas sin solapar, horarios sin solapar por vehículo y día,
 * y generación de los días concretos de cada horario dentro del rango del mantenimiento.
 */
class ServicioMantenimiento
{
    /** Un mantenimiento no puede durar más de un año (evita generar miles de días por un error al escribir la fecha). */
    public const MAXIMO_DIAS = 366;

    /**
     * Fechas (AAAA-MM-DD) de un día de la semana dentro de un rango, ambos extremos incluidos.
     * Ejemplo: lunes (1) entre el 01/12/2026 y el 31/12/2026 → 01, 08, 15, 22 y 29 de diciembre.
     *
     * @return list<string>
     */
    public static function fechasDe(Carbon|string $inicio, Carbon|string $fin, int $diaIso): array
    {
        $fecha = Carbon::parse($inicio)->startOfDay();
        $fin = Carbon::parse($fin)->startOfDay();
        $fecha->addDays(($diaIso - $fecha->dayOfWeekIso + 7) % 7);

        $fechas = [];

        for (; $fecha->lte($fin); $fecha->addWeek()) {
            $fechas[] = $fecha->toDateString();
        }

        return $fechas;
    }

    /** Otro mantenimiento que ocupa alguna fecha del rango (el rango se toma con ambos extremos incluidos). */
    public static function mantenimientoQueSeCruza(string $inicio, string $fin, ?int $ignorarId = null): ?Mantenimiento
    {
        return Mantenimiento::query()
            ->whereDate('start_date', '<=', $fin)
            ->whereDate('end_date', '>=', $inicio)
            ->when($ignorarId, fn ($consulta) => $consulta->whereKeyNot($ignorarId))
            ->orderBy('start_date')
            ->first();
    }

    /**
     * Horario del mismo vehículo, el mismo día de la semana y con horas que se cruzan.
     * Horas que solo se tocan (uno termina a las 11:00 y otro empieza a las 11:00) no se consideran cruce.
     */
    public static function horarioQueSeCruza(int $mantenimientoId, int $vehiculoId, int $diaIso, string $horaInicio, string $horaFin, ?int $ignorarId = null): ?HorarioMantenimiento
    {
        return HorarioMantenimiento::query()
            ->where('maintenance_id', $mantenimientoId)
            ->where('vehicle_id', $vehiculoId)
            ->where('weekday', $diaIso)
            ->where('time_start', '<', self::hora($horaFin))
            ->where('time_end', '>', self::hora($horaInicio))
            ->when($ignorarId, fn ($consulta) => $consulta->whereKeyNot($ignorarId))
            ->first();
    }

    /** «08:00» → «08:00:00» (así se comparan igual en MySQL y en las pruebas). */
    public static function hora(string $hora): string
    {
        return strlen($hora) === 5 ? $hora.':00' : $hora;
    }

    /** Crea los días que le falten al horario dentro del rango de su mantenimiento. */
    public static function generarDias(HorarioMantenimiento $horario): int
    {
        $mantenimiento = $horario->mantenimiento;
        $deseadas = self::fechasDe($mantenimiento->start_date, $mantenimiento->end_date, $horario->weekday);
        $existentes = $horario->dias()->pluck('date')->map(fn ($f) => Carbon::parse($f)->toDateString())->all();
        $ahora = now();

        $nuevas = collect($deseadas)
            ->diff($existentes)
            ->map(fn (string $fecha) => [
                'schedule_id' => $horario->id,
                'date' => $fecha,
                'status' => DiaMantenimiento::PENDIENTE,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])
            ->values()
            ->all();

        if ($nuevas) {
            DiaMantenimiento::insert($nuevas);
        }

        return count($nuevas);
    }

    /**
     * Ajusta los días de todos los horarios cuando cambia el rango del mantenimiento: borra los pendientes que quedan fuera
     * y crea los que faltan. Antes se debe haber llamado a exigirRangoEditable().
     */
    public static function sincronizar(Mantenimiento $mantenimiento): void
    {
        foreach ($mantenimiento->horarios()->get() as $horario) {
            $horario->setRelation('mantenimiento', $mantenimiento);

            DiaMantenimiento::where('schedule_id', $horario->id)
                ->where(fn ($consulta) => $consulta
                    ->whereDate('date', '<', $mantenimiento->start_date->toDateString())
                    ->orWhereDate('date', '>', $mantenimiento->end_date->toDateString()))
                ->delete();

            self::generarDias($horario);
        }
    }

    /** No se acorta el rango si dejaría fuera días que ya tienen estado, observación o imagen. */
    public static function exigirRangoEditable(Mantenimiento $mantenimiento, string $inicio, string $fin): void
    {
        $perdidos = DiaMantenimiento::query()
            ->whereIn('schedule_id', $mantenimiento->horarios()->select('id'))
            ->where(fn ($consulta) => $consulta->whereDate('date', '<', $inicio)->orWhereDate('date', '>', $fin))
            ->where(fn ($consulta) => $consulta
                ->where('status', '!=', DiaMantenimiento::PENDIENTE)
                ->orWhereNotNull('observation')
                ->orWhereNotNull('image_path'))
            ->count();

        if ($perdidos > 0) {
            throw ValidationException::withMessages([
                'start_date' => "No se pueden cambiar las fechas: {$perdidos} día(s) con estado, observación o imagen quedarían fuera del nuevo rango.",
            ]);
        }
    }

    /** Días ya marcados como realizados o no realizados. Sirven para impedir eliminaciones que borrarían ese historial. */
    public static function diasConEstado(Mantenimiento|HorarioMantenimiento $origen): int
    {
        $consulta = DiaMantenimiento::query()->where('status', '!=', DiaMantenimiento::PENDIENTE);

        return $origen instanceof Mantenimiento
            ? $consulta->whereIn('schedule_id', $origen->horarios()->select('id'))->count()
            : $consulta->where('schedule_id', $origen->id)->count();
    }

    /** Rutas de las imágenes de los días de un horario o mantenimiento (para borrar los archivos después de eliminar). */
    public static function imagenesDe(Mantenimiento|HorarioMantenimiento $origen): array
    {
        $consulta = DiaMantenimiento::query()->whereNotNull('image_path');

        return ($origen instanceof Mantenimiento
            ? $consulta->whereIn('schedule_id', $origen->horarios()->select('id'))
            : $consulta->where('schedule_id', $origen->id))
            ->pluck('image_path')->all();
    }
}
