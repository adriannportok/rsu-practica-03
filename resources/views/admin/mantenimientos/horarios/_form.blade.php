@php
    use App\Models\HorarioMantenimiento;
    $h = $horario ?? null;
@endphp

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-12">
        <label for="horVehiculo" class="form-label t-obligatorio">Vehículo</label>
        <select name="vehicle_id" id="horVehiculo" class="form-select" required>
            <option value="">Seleccione un vehículo</option>
            @foreach ($vehiculos as $vehiculo)
                <option value="{{ $vehiculo->id }}" @selected((string) old('vehicle_id', $h?->vehicle_id) === (string) $vehiculo->id)>{{ $vehiculo->name }} ({{ $vehiculo->plate }})</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6">
        <label for="horTipo" class="form-label t-obligatorio">Tipo de mantenimiento</label>
        <select name="type" id="horTipo" class="form-select" required>
            <option value="">Seleccione el tipo</option>
            @foreach (HorarioMantenimiento::TIPOS as $clave => $texto)
                <option value="{{ $clave }}" @selected(old('type', $h?->type) === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6">
        <label for="horDia" class="form-label t-obligatorio">Día de la semana</label>
        <select name="weekday" id="horDia" class="form-select" required>
            <option value="">Seleccione el día</option>
            @foreach (HorarioMantenimiento::DIAS as $numero => $texto)
                <option value="{{ $numero }}" @selected((string) old('weekday', $h?->weekday) === (string) $numero)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6">
        <label for="horInicio" class="form-label t-obligatorio">Hora de inicio</label>
        <input type="time" name="time_start" id="horInicio" class="form-control" required value="{{ old('time_start', $h?->hora_inicio) }}">
    </div>

    <div class="col-sm-6">
        <label for="horFin" class="form-label t-obligatorio">Hora de fin</label>
        <input type="time" name="time_end" id="horFin" class="form-control" required value="{{ old('time_end', $h?->hora_fin) }}">
    </div>

    <div class="col-12">
        <label for="horResponsable" class="form-label t-obligatorio">Responsable</label>
        <select name="user_id" id="horResponsable" class="form-select" required>
            <option value="">Seleccione al responsable</option>
            @foreach ($responsables as $persona)
                <option value="{{ $persona->id }}" @selected((string) old('user_id', $h?->user_id) === (string) $persona->id)>{{ $persona->nombre_completo }}{{ $persona->tipo ? ' — '.$persona->tipo->name : '' }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="t-nota mt-3">
    <i class="bi bi-info-circle-fill text-primary me-1"></i><strong>Nota:</strong>
    al guardar se generan los días de esa semana entre el {{ $mantenimiento->start_date->format('d/m/Y') }} y el {{ $mantenimiento->end_date->format('d/m/Y') }}.
    Un mismo vehículo no puede tener dos horarios que se crucen el mismo día.
</div>

@include('admin.partials.pie-formulario', ['textoBoton' => 'Guardar horario'])
