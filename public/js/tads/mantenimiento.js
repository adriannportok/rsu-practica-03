/**
 * Módulo de Mantenimiento de vehículos (Práctica 03)
 *
 *   - mantenimientos: lista de mantenimientos programados (nombre y rango de fechas) con CRUD en ventana emergente
 *   - horariosMantenimiento: horarios de un mantenimiento (vehículo, tipo, día, horas y responsable)
 *   - diasMantenimiento: días generados por un horario; cada uno se edita (observación, imagen y estado)
 *
 * Todo se envía y recibe con AJAX a través de Tads.Gestor, igual que el resto de módulos.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const plural = (n, singular, pluralTexto) => `${n} ${n === 1 ? singular : pluralTexto}`;
    const colorTipo = { preventivo: 'azul', limpieza: 'verde', reparacion: 'ambar' };
    const colorEstado = { pendiente: 'ambar', realizado: 'verde', no_realizado: 'rojo' };

    /* ------------------------------------------------------------------ mantenimientos */

    Tads.Modulos.mantenimientos = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'mantenimiento',
                icono: 'bi-wrench-adjustable',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[1, 'asc']],
                buscar: 'Buscar mantenimiento...',
                columnas: [
                    { data: 'nombre', render: (v, modo) => modo === 'display' ? `<strong>${Tads.escapar(v)}</strong>` : v },
                    { data: 'inicio', render: Tads.celdaFecha },
                    { data: 'fin', render: Tads.celdaFecha },
                    { data: 'horarios', render: (v, modo) => modo === 'display'
                        ? Tads.insignia(plural(v, 'horario', 'horarios'), v ? 'azul' : 'gris') : v },
                    { data: null, orderable: false, searchable: false, className: 'text-center', render: (fila) => Tads.acciones(
                        `<a class="btn btn-sm t-boton" href="${Tads.escapar(fila.url_horarios)}"><i class="bi bi-calendar3 me-1"></i>Ver horarios</a>`,
                        Tads.botonEditar(fila.id),
                        Tads.botonEliminar(
                            fila.id, fila.nombre,
                            fila.dias_con_estado ? `No se puede eliminar: ${plural(fila.dias_con_estado, 'día ya marcado', 'días ya marcados')} como realizados o no realizados` : '',
                            `Se eliminarán también ${plural(fila.horarios, 'horario', 'horarios')} y ${plural(fila.dias, 'día generado', 'días generados')}.`,
                        ),
                    ) },
                ],
            });
        },
    };

    /* ------------------------------------------------------------------ horarios */

    Tads.Modulos.horariosMantenimiento = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'horario',
                icono: 'bi-calendar3',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                buscar: 'Buscar horario...',
                columnas: [
                    { data: 'orden', render: (v, modo, fila) => modo === 'display' ? `<strong>${Tads.escapar(fila.dia)}</strong>` : (modo === 'filter' ? fila.dia : v) },
                    { data: 'vehiculo', render: (v, modo, fila) => modo === 'display'
                        ? `${Tads.escapar(v)}<small class="d-block text-muted">${Tads.escapar(fila.placa)}</small>` : `${v} ${fila.placa}` },
                    { data: 'responsable', render: (v) => Tads.escapar(v) },
                    { data: 'tipo_texto', render: (v, modo, fila) => modo === 'display' ? Tads.insignia(v, colorTipo[fila.tipo] ?? 'gris') : v },
                    { data: 'inicio' },
                    { data: 'fin' },
                    { data: null, orderable: false, searchable: false, className: 'text-center', render: (fila) => Tads.acciones(
                        `<a class="btn t-boton-icono t-info" href="${Tads.escapar(fila.url_dias)}" data-bs-toggle="tooltip" title="Ver días"><i class="bi bi-eye-fill"></i></a>`,
                        Tads.botonEditar(fila.id),
                        Tads.botonEliminar(
                            fila.id, `${fila.dia} ${fila.inicio} - ${fila.fin} · ${fila.vehiculo}`, '',
                            `Se eliminarán también ${plural(fila.dias, 'día generado', 'días generados')}${fila.dias_con_estado ? `, incluidos ${plural(fila.dias_con_estado, 'día ya marcado', 'días ya marcados')} como realizados o no realizados` : ''}.`,
                        ),
                    ) },
                ],
            });
        },
    };

    /* ------------------------------------------------------------------ días */

    Tads.Modulos.diasMantenimiento = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'día de mantenimiento',
                icono: 'bi-calendar-check',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                orden: [[0, 'asc']],
                porPagina: 15,
                buscar: 'Buscar día...',
                columnas: [
                    { data: 'fecha', render: Tads.celdaFecha },
                    { data: 'observacion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'imagen', orderable: false, searchable: false, render: (url) => url
                        ? `<a href="${Tads.escapar(url)}" target="_blank" rel="noopener"><img class="t-miniatura" src="${Tads.escapar(url)}" alt="Imagen del mantenimiento"></a>`
                        : '<span class="text-muted">—</span>' },
                    { data: 'estado_texto', render: (v, modo, fila) => modo === 'display' ? Tads.insignia(v, colorEstado[fila.estado] ?? 'gris') : v },
                    { data: null, orderable: false, searchable: false, className: 'text-center', render: (fila) => Tads.acciones(Tads.botonEditar(fila.id)) },
                ],
            });
        },
    };
})(window);
