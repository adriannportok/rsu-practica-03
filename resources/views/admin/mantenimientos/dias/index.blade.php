@extends('adminlte::page')

@section('title', $mantenimiento->name.' · Días')

@section('content')
    @include('admin.mantenimientos._migas', ['migas' => [
        ['Mantenimiento', route('admin.mantenimientos.index')],
        [$mantenimiento->name, route('admin.mantenimientos.horarios.index', $mantenimiento)],
        [$horario->dia_texto],
        [$horario->vehiculo->name],
    ]])

    @include('admin.partials.cabecera', [
        'icono' => 'bi-calendar-check',
        'titulo' => $mantenimiento->name.' – Días programados',
        'subtitulo' => $horario->tipo_texto.' · '.$horario->dia_texto.' de '.$horario->hora_inicio.' a '.$horario->hora_fin.' · '.$horario->vehiculo->name.' ('.$horario->vehiculo->plate.') · Responsable: '.$horario->responsable->nombre_completo,
    ])

    <div class="t-tarjeta"
         id="moduloDias"
         data-tads-modulo="diasMantenimiento"
         data-permiso="maintenances"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.mantenimientos.horarios.dias.index', [$mantenimiento, $horario]) }}"
         data-url-recurso="{{ url('admin/mantenimientos/'.$mantenimiento->id.'/horarios/'.$horario->id.'/dias') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Observación</th>
                            <th>Imagen</th>
                            <th>Estado</th>
                            <th class="text-center">Editar</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-md'])
@stop
