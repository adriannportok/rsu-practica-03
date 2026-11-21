<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="mantNombre" class="form-label t-obligatorio">Nombre</label>
    <input type="text" name="name" id="mantNombre" class="form-control" maxlength="100" required
        placeholder="Ejemplo: MANT. FEBRERO 2027" value="{{ old('name', $mantenimiento->name ?? '') }}">
</div>

<div class="row g-3">
    <div class="col-sm-6">
        <label for="mantInicio" class="form-label t-obligatorio">Fecha de inicio</label>
        <input type="date" name="start_date" id="mantInicio" class="form-control" required
            value="{{ old('start_date', isset($mantenimiento) ? $mantenimiento->start_date->toDateString() : '') }}">
    </div>
    <div class="col-sm-6">
        <label for="mantFin" class="form-label t-obligatorio">Fecha de fin</label>
        <input type="date" name="end_date" id="mantFin" class="form-control" required
            value="{{ old('end_date', isset($mantenimiento) ? $mantenimiento->end_date->toDateString() : '') }}">
    </div>
</div>

<div class="t-nota mt-3"><i class="bi bi-info-circle-fill text-primary me-1"></i><strong>Nota:</strong> la fecha de fin no puede ser menor que la de inicio y las fechas no pueden coincidir con las de otro mantenimiento.</div>

@include('admin.partials.pie-formulario')
