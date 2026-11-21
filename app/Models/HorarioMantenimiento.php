<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un horario de mantenimiento: qué vehículo, qué tipo de trabajo, qué día de la semana, de qué hora a qué hora y quién responde. */
class HorarioMantenimiento extends Model
{
    use Auditable;

    protected $table = 'maintenance_schedules';

    public const PREVENTIVO = 'preventivo';

    public const LIMPIEZA = 'limpieza';

    public const REPARACION = 'reparacion';

    public const TIPOS = [
        self::PREVENTIVO => 'Preventivo',
        self::LIMPIEZA => 'Limpieza',
        self::REPARACION => 'Reparación',
    ];

    /** Número ISO del día (1 = lunes ... 7 = domingo) → nombre. */
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected $fillable = ['maintenance_id', 'vehicle_id', 'user_id', 'type', 'weekday', 'time_start', 'time_end'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class, 'maintenance_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehicle_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'user_id');
    }

    public function dias(): HasMany
    {
        return $this->hasMany(DiaMantenimiento::class, 'schedule_id')->orderBy('date');
    }

    public function getTipoTextoAttribute(): string
    {
        return self::TIPOS[$this->type] ?? ucfirst($this->type);
    }

    public function getDiaTextoAttribute(): string
    {
        return self::DIAS[$this->weekday] ?? '';
    }

    /** «08:00» (la base guarda «08:00:00»). */
    public function getHoraInicioAttribute(): string
    {
        return substr($this->time_start, 0, 5);
    }

    public function getHoraFinAttribute(): string
    {
        return substr($this->time_end, 0, 5);
    }
}
