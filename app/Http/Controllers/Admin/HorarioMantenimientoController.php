<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\DiaMantenimiento;
use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\Vehiculo;
use App\Services\ServicioMantenimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HorarioMantenimientoController extends Controller
{
    use RespondeAjax;

    public function index(Mantenimiento $mantenimiento)
    {
        if (! request()->ajax()) {
            return view('admin.mantenimientos.horarios.index', ['mantenimiento' => $mantenimiento]);
        }

        $filas = $mantenimiento->horarios()
            ->with(['vehiculo', 'responsable'])
            ->withCount([
                'dias',
                'dias as dias_con_estado_count' => fn ($consulta) => $consulta->where('status', '!=', DiaMantenimiento::PENDIENTE),
            ])
            ->orderBy('weekday')
            ->orderBy('time_start')
            ->get()
            ->map(fn (HorarioMantenimiento $h) => $this->fila($h));

        return response()->json(['data' => $filas]);
    }

    public function create(Mantenimiento $mantenimiento)
    {
        return view('admin.mantenimientos.horarios.create', ['mantenimiento' => $mantenimiento] + $this->opciones());
    }

    public function store(Request $request, Mantenimiento $mantenimiento)
    {
        $datos = $this->validado($request, $mantenimiento);

        $horario = DB::transaction(function () use ($datos, $mantenimiento) {
            $mantenimiento = Mantenimiento::whereKey($mantenimiento->id)->lockForUpdate()->firstOrFail();
            $this->exigirSinCruce($mantenimiento, $datos);

            $horario = $mantenimiento->horarios()->create($datos);
            $horario->setRelation('mantenimiento', $mantenimiento);
            ServicioMantenimiento::generarDias($horario);

            return $horario;
        });

        return $this->exito('Horario registrado y días generados', 'admin.mantenimientos.horarios.index', [
            'horario' => $this->fila($this->conRelaciones($horario)),
        ]);
    }

    public function show(Mantenimiento $mantenimiento, string $horario)
    {
        abort(404);
    }

    public function edit(Mantenimiento $mantenimiento, string $horario)
    {
        return view('admin.mantenimientos.horarios.edit', [
            'mantenimiento' => $mantenimiento,
            'horario' => $this->deMantenimiento($mantenimiento, $horario),
        ] + $this->opciones($this->deMantenimiento($mantenimiento, $horario)));
    }

    public function update(Request $request, Mantenimiento $mantenimiento, string $horario)
    {
        $actual = $this->deMantenimiento($mantenimiento, $horario);
        $datos = $this->validado($request, $mantenimiento, $actual);

        $actualizado = DB::transaction(function () use ($datos, $mantenimiento, $actual) {
            $mantenimiento = Mantenimiento::whereKey($mantenimiento->id)->lockForUpdate()->firstOrFail();
            $horario = HorarioMantenimiento::whereKey($actual->id)->lockForUpdate()->firstOrFail();

            $this->exigirSinCruce($mantenimiento, $datos, $horario->id);

            $cambiaElDia = (int) $datos['weekday'] !== $horario->weekday;

            if ($cambiaElDia) {
                $conRegistro = $horario->dias()->get()->filter(fn (DiaMantenimiento $d) => $d->tieneRegistro())->count();

                if ($conRegistro > 0) {
                    throw ValidationException::withMessages([
                        'weekday' => "No se puede cambiar el día de la semana: {$conRegistro} día(s) ya tienen estado, observación o imagen. Elimine el horario y cree uno nuevo.",
                    ]);
                }

                $horario->dias()->delete();
            }

            $horario->update($datos);
            $horario->setRelation('mantenimiento', $mantenimiento);
            ServicioMantenimiento::generarDias($horario);

            return $horario->fresh();
        });

        return $this->exito('Horario actualizado', 'admin.mantenimientos.horarios.index', [
            'horario' => $this->fila($this->conRelaciones($actualizado)),
        ]);
    }

    public function destroy(Mantenimiento $mantenimiento, string $horario)
    {
        $horario = $this->deMantenimiento($mantenimiento, $horario);

        $imagenes = ServicioMantenimiento::imagenesDe($horario);
        $horario->delete();
        array_map($this->borrarArchivo(...), $imagenes);

        return $this->exito('Horario eliminado junto con sus días generados', 'admin.mantenimientos.horarios.index');
    }

    private function deMantenimiento(Mantenimiento $mantenimiento, string $id): HorarioMantenimiento
    {
        return $mantenimiento->horarios()->findOrFail($id);
    }

    private function conRelaciones(HorarioMantenimiento $horario): HorarioMantenimiento
    {
        return $horario->load(['vehiculo', 'responsable'])->loadCount([
            'dias',
            'dias as dias_con_estado_count' => fn ($consulta) => $consulta->where('status', '!=', DiaMantenimiento::PENDIENTE),
        ]);
    }

    private function opciones(?HorarioMantenimiento $actual = null): array
    {
        return [
            'vehiculos' => Vehiculo::query()
                ->where(fn ($c) => $c->where('status', true)->when($actual, fn ($q) => $q->orWhere('id', $actual->vehicle_id)))
                ->orderBy('name')->get(['id', 'name', 'plate']),
            'responsables' => Personal::query()->with('tipo')
                ->where(fn ($c) => $c->where('users.status', true)->when($actual, fn ($q) => $q->orWhere('users.id', $actual->user_id)))
                ->orderBy('last_name')->orderBy('name')->get(),
        ];
    }

    private function validado(Request $request, Mantenimiento $mantenimiento, ?HorarioMantenimiento $actual = null): array
    {
        $datos = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where(
                fn ($c) => $c->where(fn ($q) => $q->where('status', true)->when($actual, fn ($w) => $w->orWhere('id', $actual->vehicle_id)))
            )],
            'type' => ['required', Rule::in(array_keys(HorarioMantenimiento::TIPOS))],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(
                fn ($c) => $c->whereNotNull('usertype_id')->where(fn ($q) => $q->where('status', true)->when($actual, fn ($w) => $w->orWhere('id', $actual->user_id)))
            )],
        ], [
            'vehicle_id.required' => 'Seleccione el vehículo.',
            'vehicle_id.exists' => 'El vehículo no existe o está inactivo.',
            'type.required' => 'Seleccione el tipo de mantenimiento.',
            'type.in' => 'El tipo de mantenimiento no es válido.',
            'weekday.required' => 'Seleccione el día de la semana.',
            'weekday.between' => 'El día de la semana no es válido.',
            'time_start.required' => 'Ingrese la hora de inicio.',
            'time_start.date_format' => 'La hora de inicio no es válida.',
            'time_end.required' => 'Ingrese la hora de fin.',
            'time_end.date_format' => 'La hora de fin no es válida.',
            'time_end.after' => 'La hora de fin debe ser mayor que la hora de inicio.',
            'user_id.required' => 'Seleccione al responsable.',
            'user_id.exists' => 'El responsable no existe o está inactivo.',
        ]);

        $datos['weekday'] = (int) $datos['weekday'];
        $datos['time_start'] = ServicioMantenimiento::hora($datos['time_start']);
        $datos['time_end'] = ServicioMantenimiento::hora($datos['time_end']);

        if (ServicioMantenimiento::fechasDe($mantenimiento->start_date, $mantenimiento->end_date, $datos['weekday']) === []) {
            $dia = mb_strtolower(HorarioMantenimiento::DIAS[$datos['weekday']]);

            throw ValidationException::withMessages([
                'weekday' => "El {$dia} no ocurre entre el {$mantenimiento->start_date->format('d/m/Y')} y el {$mantenimiento->end_date->format('d/m/Y')}: no se generaría ningún día.",
            ]);
        }

        return $datos;
    }

    private function exigirSinCruce(Mantenimiento $mantenimiento, array $datos, ?int $ignorarId = null): void
    {
        $otro = ServicioMantenimiento::horarioQueSeCruza(
            $mantenimiento->id, (int) $datos['vehicle_id'], $datos['weekday'], $datos['time_start'], $datos['time_end'], $ignorarId,
        );

        if ($otro) {
            throw ValidationException::withMessages([
                'time_start' => "El vehículo ya tiene mantenimiento el {$otro->dia_texto} de {$otro->hora_inicio} a {$otro->hora_fin} ({$otro->tipo_texto}). Los horarios del mismo vehículo no pueden cruzarse.",
            ]);
        }
    }

    private function fila(HorarioMantenimiento $h): array
    {
        $formato = fn (string $hora) => Carbon::createFromFormat('H:i', substr($hora, 0, 5))->format('h:i a');
        [$hora, $minuto] = array_map('intval', explode(':', substr($h->time_start, 0, 5)));

        return [
            'id' => $h->id,
            'dia' => $h->dia_texto,
            'orden' => $h->weekday * 10000 + $hora * 60 + $minuto,
            'vehiculo' => $h->vehiculo->name,
            'placa' => $h->vehiculo->plate,
            'responsable' => $h->responsable->nombre_completo,
            'tipo' => $h->type,
            'tipo_texto' => $h->tipo_texto,
            'inicio' => $formato($h->time_start),
            'fin' => $formato($h->time_end),
            'dias' => (int) ($h->dias_count ?? 0),
            'dias_con_estado' => (int) ($h->dias_con_estado_count ?? 0),
            'url_dias' => route('admin.mantenimientos.horarios.dias.index', [$h->maintenance_id, $h->id]),
        ];
    }
}
