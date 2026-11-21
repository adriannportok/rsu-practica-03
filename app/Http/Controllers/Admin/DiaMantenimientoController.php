<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\DiaMantenimiento;
use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DiaMantenimientoController extends Controller
{
    use RespondeAjax;

    public function index(Mantenimiento $mantenimiento, string $horario)
    {
        $horario = $this->horarioDe($mantenimiento, $horario)->load(['vehiculo', 'responsable']);

        if (! request()->ajax()) {
            return view('admin.mantenimientos.dias.index', ['mantenimiento' => $mantenimiento, 'horario' => $horario]);
        }

        return response()->json([
            'data' => $horario->dias()->get()->map(fn (DiaMantenimiento $d) => $this->fila($d)),
        ]);
    }

    public function edit(Mantenimiento $mantenimiento, string $horario, string $dia)
    {
        $horario = $this->horarioDe($mantenimiento, $horario);

        return view('admin.mantenimientos.dias.edit', [
            'mantenimiento' => $mantenimiento,
            'horario' => $horario,
            'dia' => $horario->dias()->findOrFail($dia),
        ]);
    }

    public function update(Request $request, Mantenimiento $mantenimiento, string $horario, string $dia)
    {
        $horario = $this->horarioDe($mantenimiento, $horario);
        $dia = $horario->dias()->findOrFail($dia);

        $datos = $request->validate([
            'observation' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'done' => ['nullable', 'boolean'],
        ], [
            'observation.max' => 'La observación no puede pasar de 500 caracteres.',
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'image.max' => 'La imagen no puede pesar más de 2 MB.',
            'image.uploaded' => 'No se pudo subir la imagen. Verifique que no pese más de 2 MB.',
        ]);

        $realizado = (bool) ($datos['done'] ?? false);

        if ($realizado && $dia->date->isFuture()) {
            throw ValidationException::withMessages(['done' => 'No se puede marcar como realizado un día que todavía no llega.']);
        }

        $estado = $realizado
            ? DiaMantenimiento::REALIZADO
            : ($dia->date->lt(today()) ? DiaMantenimiento::NO_REALIZADO : DiaMantenimiento::PENDIENTE);

        $quitarImagen = (bool) ($datos['remove_image'] ?? false) && ! $request->hasFile('image');
        $anterior = $dia->image_path;

        $this->conArchivoNuevo($request->file('image'), 'maintenance_days', function (?string $nueva) use ($dia, $datos, $estado, $quitarImagen) {
            $dia->update([
                'status' => $estado,
                'observation' => filled($datos['observation'] ?? null) ? trim($datos['observation']) : null,
                'image_path' => $nueva ?? ($quitarImagen ? null : $dia->image_path),
            ]);
        }, $anterior);

        if ($quitarImagen) {
            $this->borrarArchivo($anterior);
        }

        return $this->exito('Día de mantenimiento actualizado', 'admin.mantenimientos.horarios.dias.index', ['dia' => $this->fila($dia->fresh())]);
    }

    private function horarioDe(Mantenimiento $mantenimiento, string $id): HorarioMantenimiento
    {
        return $mantenimiento->horarios()->findOrFail($id);
    }

    private function fila(DiaMantenimiento $d): array
    {
        return [
            'id' => $d->id,
            'fecha' => $d->date->format('d/m/Y'),
            'observacion' => $d->observation,
            'imagen' => filled($d->image_path) ? $this->urlArchivo($d->image_path) : null,
            'estado' => $d->status,
            'estado_texto' => DiaMantenimiento::ESTADOS[$d->status] ?? $d->status,
        ];
    }
}
