<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Mantenimiento extends Model
{
    use Auditable;

    protected $table = 'maintenances';

    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioMantenimiento::class, 'maintenance_id');
    }

    public function dias(): HasManyThrough
    {
        return $this->hasManyThrough(DiaMantenimiento::class, HorarioMantenimiento::class, 'maintenance_id', 'schedule_id');
    }
}
