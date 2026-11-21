<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaMantenimiento extends Model
{
    use Auditable;

    protected $table = 'maintenance_days';

    public const PENDIENTE = 'pendiente';

    public const REALIZADO = 'realizado';

    public const NO_REALIZADO = 'no_realizado';

    public const ESTADOS = [
        self::PENDIENTE => 'Pendiente',
        self::REALIZADO => 'Realizado',
        self::NO_REALIZADO => 'No realizado',
    ];

    protected $fillable = ['schedule_id', 'date', 'status', 'observation', 'image_path'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(HorarioMantenimiento::class, 'schedule_id');
    }

    public function tieneRegistro(): bool
    {
        return $this->status !== self::PENDIENTE || filled($this->observation) || filled($this->image_path);
    }
}
