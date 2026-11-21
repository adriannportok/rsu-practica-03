<form method="POST" action="{{ route('admin.mantenimientos.horarios.dias.update', [$mantenimiento, $horario, $dia]) }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="t-alerta-form"></div>

    <div class="mb-3">
        <label for="diaFecha" class="form-label">Fecha</label>
        <input type="text" id="diaFecha" class="form-control" readonly value="{{ $dia->date->format('d/m/Y') }}">
    </div>

    <div class="mb-3">
        <label for="diaObservacion" class="form-label">Observación</label>
        <textarea name="observation" id="diaObservacion" class="form-control" rows="3" maxlength="500"
            placeholder="Ejemplo: Todo conforme">{{ old('observation', $dia->observation) }}</textarea>
    </div>

    <div class="mb-3">
        <label for="diaImagen" class="form-label">Imagen</label>
        @if ($dia->image_path)
            <div class="d-flex align-items-center gap-3 mb-2">
                <img class="t-miniatura" src="{{ asset(ltrim($dia->image_path, '/')) }}" alt="Imagen actual">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remove_image" id="diaQuitarImagen" value="1">
                    <label class="form-check-label" for="diaQuitarImagen">Quitar la imagen actual</label>
                </div>
            </div>
        @endif
        <input type="file" name="image" id="diaImagen" class="form-control" accept="image/png,image/jpeg,image/webp">
        <div class="form-text">JPG, PNG o WEBP de hasta 2 MB. Si sube una nueva, reemplaza a la actual.</div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="done" id="diaRealizado" value="1" @checked(old('done', $dia->status === 'realizado'))>
        <label class="form-check-label" for="diaRealizado">Mantenimiento realizado</label>
    </div>

    <div class="t-nota">
        <i class="bi bi-info-circle-fill text-primary me-1"></i><strong>Nota:</strong>
        si no marca «realizado», el día queda como <strong>No realizado</strong> cuando su fecha ya pasó, o como <strong>Pendiente</strong> si es hoy o una fecha futura.
        No se puede marcar como realizado un día que todavía no llega.
    </div>

    @include('admin.partials.pie-formulario', ['textoBoton' => 'Guardar cambios'])
</form>
