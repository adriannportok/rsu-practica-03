<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento;
use App\Services\ServicioMantenimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MantenimientoController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.mantenimientos.index');
        }

        $filas = Mantenimiento::query()
            ->withCount([
                'horarios',
                'dias',
                'dias as dias_con_estado_count' => fn ($consulta) => $consulta->where('maintenance_days.status', '!=', 'pendiente'),
            ])
            ->orderBy('start_date')
            ->get()
            ->map(fn (Mantenimiento $m) => $this->fila($m));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.mantenimientos.create');
    }

    public function store(Request $request)
    {
        $datos = $this->validado($request);

        $mantenimiento = DB::transaction(function () use ($datos) {
            Mantenimiento::lockForUpdate()->pluck('id');
            $this->exigirSinCruce($datos);

            return Mantenimiento::create($datos);
        });

        return $this->exito('Mantenimiento registrado', 'admin.mantenimientos.index', ['mantenimiento' => $this->fila($mantenimiento)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.mantenimientos.edit', ['mantenimiento' => Mantenimiento::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $datos = $this->validado($request, (int) $id);

        $mantenimiento = DB::transaction(function () use ($datos, $id) {
            Mantenimiento::lockForUpdate()->pluck('id');
            $mantenimiento = Mantenimiento::findOrFail($id);

            $this->exigirSinCruce($datos, $mantenimiento->id);

            $cambioElRango = $mantenimiento->start_date->toDateString() !== $datos['start_date']
                || $mantenimiento->end_date->toDateString() !== $datos['end_date'];

            if ($cambioElRango) {
                ServicioMantenimiento::exigirRangoEditable($mantenimiento, $datos['start_date'], $datos['end_date']);
            }

            $mantenimiento->update($datos);

            if ($cambioElRango) {
                ServicioMantenimiento::sincronizar($mantenimiento->fresh());
            }

            return $mantenimiento->fresh();
        });

        $mantenimiento->loadCount(['horarios', 'dias', 'dias as dias_con_estado_count' => fn ($c) => $c->where('maintenance_days.status', '!=', 'pendiente')]);

        return $this->exito('Mantenimiento actualizado', 'admin.mantenimientos.index', ['mantenimiento' => $this->fila($mantenimiento)]);
    }

    public function destroy(string $id)
    {
        $mantenimiento = Mantenimiento::findOrFail($id);

        if ($n = ServicioMantenimiento::diasConEstado($mantenimiento)) {
            return $this->rechazo("No se puede eliminar: tiene {$n} día(s) ya marcados como realizados o no realizados. Se conservan como historial.", 'admin.mantenimientos.index');
        }

        $imagenes = ServicioMantenimiento::imagenesDe($mantenimiento);
        $mantenimiento->delete();
        array_map($this->borrarArchivo(...), $imagenes);

        return $this->exito('Mantenimiento eliminado junto con sus horarios y días programados', 'admin.mantenimientos.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('maintenances', 'name')->ignore($ignorarId)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'name.required' => 'Ingrese el nombre del mantenimiento.',
            'name.max' => 'El nombre no puede pasar de 100 caracteres.',
            'name.unique' => 'Ya existe un mantenimiento con ese nombre.',
            'start_date.required' => 'Seleccione la fecha de inicio.',
            'start_date.date_format' => 'La fecha de inicio no es válida.',
            'end_date.required' => 'Seleccione la fecha de fin.',
            'end_date.date_format' => 'La fecha de fin no es válida.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser menor que la fecha de inicio.',
        ]);

        $dias = Carbon::parse($datos['start_date'])->diffInDays(Carbon::parse($datos['end_date'])) + 1;

        if ($dias > ServicioMantenimiento::MAXIMO_DIAS) {
            throw ValidationException::withMessages(['end_date' => 'Un mantenimiento no puede durar más de '.ServicioMantenimiento::MAXIMO_DIAS.' días.']);
        }

        return $datos;
    }

    private function exigirSinCruce(array $datos, ?int $ignorarId = null): void
    {
        $otro = ServicioMantenimiento::mantenimientoQueSeCruza($datos['start_date'], $datos['end_date'], $ignorarId);

        if ($otro) {
            throw ValidationException::withMessages([
                'start_date' => "Las fechas se solapan con «{$otro->name}» ({$otro->start_date->format('d/m/Y')} al {$otro->end_date->format('d/m/Y')}). Los mantenimientos no pueden coincidir en fechas.",
            ]);
        }
    }

    private function fila(Mantenimiento $m): array
    {
        return [
            'id' => $m->id,
            'nombre' => $m->name,
            'inicio' => $m->start_date->format('d/m/Y'),
            'fin' => $m->end_date->format('d/m/Y'),
            'horarios' => (int) ($m->horarios_count ?? $m->horarios()->count()),
            'dias' => (int) ($m->dias_count ?? 0),
            'dias_con_estado' => (int) ($m->dias_con_estado_count ?? 0),
            'url_horarios' => route('admin.mantenimientos.horarios.index', $m),
        ];
    }
}
