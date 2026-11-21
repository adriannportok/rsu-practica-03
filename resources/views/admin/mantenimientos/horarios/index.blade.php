@extends('adminlte::page')

@section('title', $mantenimiento->name.' · Horarios')

@section('content')
    @include('admin.mantenimientos._migas', ['migas' => [
        ['Mantenimiento', route('admin.mantenimientos.index')],
        [$mantenimiento->name],
    ]])

    @include('admin.partials.cabecera', [
        'icono' => 'bi-calendar3',
        'titulo' => $mantenimiento->name.' / Horarios',
        'subtitulo' => 'Del '.$mantenimiento->start_date->format('d/m/Y').' al '.$mantenimiento->end_date->format('d/m/Y').'. Cada horario genera automáticamente sus días dentro de ese rango.',
        'boton' => 'Agregar horario',
    ])

    <div class="t-tarjeta"
         id="moduloHorarios"
         data-tads-modulo="horariosMantenimiento"
         data-permiso="maintenances"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.mantenimientos.horarios.index', $mantenimiento) }}"
         data-url-recurso="{{ url('admin/mantenimientos/'.$mantenimiento->id.'/horarios') }}"
         data-url-crear="{{ route('admin.mantenimientos.horarios.create', $mantenimiento) }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Vehículo</th>
                            <th>Responsable</th>
                            <th>Tipo</th>
                            <th>Hora de inicio</th>
                            <th>Hora de fin</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-md'])
@stop
