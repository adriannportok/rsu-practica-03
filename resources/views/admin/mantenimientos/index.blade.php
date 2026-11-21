@extends('adminlte::page')

@section('title', 'Mantenimiento')

@section('content')
    @include('admin.mantenimientos._migas', ['migas' => [['Mantenimiento']]])

    @include('admin.partials.cabecera', [
        'icono' => 'bi-wrench-adjustable',
        'titulo' => 'Mantenimientos programados',
        'subtitulo' => 'Programe las actividades de mantenimiento de los vehículos. Cada mantenimiento tiene su propio rango de fechas y sus horarios.',
        'boton' => 'Nuevo mantenimiento',
    ])

    <div class="t-tarjeta"
         id="moduloMantenimientos"
         data-tads-modulo="mantenimientos"
         data-permiso="maintenances"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.mantenimientos.index') }}"
         data-url-recurso="{{ url('admin/mantenimientos') }}"
         data-url-crear="{{ route('admin.mantenimientos.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Fecha de inicio</th>
                            <th>Fecha de fin</th>
                            <th>Horarios</th>
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
