<?php

namespace App\Services;

use App\Models\DiaMantenimiento;
use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ServicioMantenimiento
{
    public const MAXIMO_DIAS = 366;

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

    public static function mantenimientoQueSeCruza(string $inicio, string $fin, ?int $ignorarId = null): ?Mantenimiento
    {
        return Mantenimiento::query()
            ->whereDate('start_date', '<=', $fin)
            ->whereDate('end_date', '>=', $inicio)
            ->when($ignorarId, fn ($consulta) => $consulta->whereKeyNot($ignorarId))
            ->orderBy('start_date')
            ->first();
    }

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

    public static function hora(string $hora): string
    {
        return strlen($hora) === 5 ? $hora.':00' : $hora;
    }

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

    public static function diasConEstado(Mantenimiento|HorarioMantenimiento $origen): int
    {
        $consulta = DiaMantenimiento::query()->where('status', '!=', DiaMantenimiento::PENDIENTE);

        return $origen instanceof Mantenimiento
            ? $consulta->whereIn('schedule_id', $origen->horarios()->select('id'))->count()
            : $consulta->where('schedule_id', $origen->id)->count();
    }

    public static function imagenesDe(Mantenimiento|HorarioMantenimiento $origen): array
    {
        $consulta = DiaMantenimiento::query()->whereNotNull('image_path');

        return ($origen instanceof Mantenimiento
            ? $consulta->whereIn('schedule_id', $origen->horarios()->select('id'))
            : $consulta->where('schedule_id', $origen->id))
            ->pluck('image_path')->all();
    }
}
