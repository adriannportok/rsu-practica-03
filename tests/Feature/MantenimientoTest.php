<?php

namespace Tests\Feature;

use App\Models\ColorVehiculo;
use App\Models\DiaMantenimiento;
use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\ServicioMantenimiento;
use Database\Seeders\TipoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MantenimientoTest extends TestCase
{
    use RefreshDatabase;

    private int $contador = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TipoPersonalSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('uploads/maintenance_days'));

        parent::tearDown();
    }

    private function ajax(string $metodo, string $url, array $datos = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->{$metodo}($url, $datos);
    }

    private function vehiculo(string $nombre = 'Camión A'): Vehiculo
    {
        $n = ++$this->contador;

        return Vehiculo::create([
            'name' => $nombre, 'code' => "VH-{$n}", 'plate' => 'ABC-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'year' => 2022,
            'brand_id' => Marca::firstOrCreate(['name' => 'Hino'])->id,
            'model_id' => Modelo::firstOrCreate(['name' => 'Dutro', 'code' => 'DUTRO', 'brand_id' => Marca::firstWhere('name', 'Hino')->id])->id,
            'type_id' => TipoVehiculo::firstOrCreate(['name' => 'Compactador'])->id,
            'color_id' => ColorVehiculo::firstOrCreate(['name' => 'Blanco', 'code' => '#FFFFFF'])->id,
            'status' => true,
        ]);
    }

    private function responsable(bool $activo = true): Personal
    {
        $n = 40000000 + ++$this->contador;

        return Personal::create([
            'dni' => (string) $n, 'usertype_id' => TipoPersonal::conductor()->id, 'name' => 'Juan', 'last_name' => "Pérez {$n}",
            'birthdate' => '1990-01-01', 'email' => "m{$n}@prueba.test", 'address' => 'Av. Principal 123',
            'password' => 'secret123', 'status' => $activo, 'license' => 'Q'.substr($n.'0', 0, 8),
        ]);
    }

    private function mantenimiento(string $inicio = '2026-12-01', string $fin = '2026-12-31', string $nombre = 'MANT. DICIEMBRE 2026'): Mantenimiento
    {
        return Mantenimiento::create(['name' => $nombre, 'start_date' => $inicio, 'end_date' => $fin]);
    }

    private function datosHorario(array $cambios = []): array
    {
        return $cambios + [
            'vehicle_id' => $this->vehiculo()->id, 'type' => 'preventivo', 'weekday' => 1,
            'time_start' => '08:00', 'time_end' => '11:00', 'user_id' => $this->responsable()->id,
        ];
    }

    private function crearHorario(Mantenimiento $m, array $cambios = []): HorarioMantenimiento
    {
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario($cambios))->assertOk();

        return $m->horarios()->latest('id')->first();
    }

    public function test_registra_y_lista_mantenimientos(): void
    {
        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'MANT. FEBRERO 2027', 'start_date' => '2027-02-01', 'end_date' => '2027-02-28'])
            ->assertOk()->assertJsonPath('mantenimiento.nombre', 'MANT. FEBRERO 2027')->assertJsonPath('mantenimiento.inicio', '01/02/2027');

        $this->ajax('get', route('admin.mantenimientos.index'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.fin', '28/02/2027')->assertJsonPath('data.0.horarios', 0);
    }

    public function test_la_fecha_de_fin_no_puede_ser_menor_que_la_de_inicio(): void
    {
        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'Malo', 'start_date' => '2027-02-10', 'end_date' => '2027-02-09'])
            ->assertStatus(422)->assertJsonValidationErrors('end_date');

        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'Un día', 'start_date' => '2027-02-10', 'end_date' => '2027-02-10'])->assertOk();
    }

    public function test_exige_nombre_fechas_y_nombre_unico(): void
    {
        $this->ajax('post', route('admin.mantenimientos.store'), [])->assertStatus(422)->assertJsonValidationErrors(['name', 'start_date', 'end_date']);

        $this->mantenimiento();
        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'MANT. DICIEMBRE 2026', 'start_date' => '2027-03-01', 'end_date' => '2027-03-31'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_los_mantenimientos_no_pueden_solaparse_en_fechas(): void
    {
        $this->mantenimiento('2026-12-01', '2026-12-31');

        foreach ([['2026-12-31', '2027-01-15'], ['2026-11-20', '2026-12-01'], ['2026-12-10', '2026-12-12'], ['2026-11-01', '2027-02-01']] as [$inicio, $fin]) {
            $this->ajax('post', route('admin.mantenimientos.store'), ['name' => "Cruce {$inicio}", 'start_date' => $inicio, 'end_date' => $fin])
                ->assertStatus(422)->assertJsonValidationErrors('start_date');
        }

        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'MANT. ENERO 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-01-31'])->assertOk();
    }

    public function test_un_mantenimiento_no_puede_durar_mas_de_un_anio(): void
    {
        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'Eterno', 'start_date' => '2027-01-01', 'end_date' => '2028-06-01'])
            ->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_edita_un_mantenimiento_sin_chocar_consigo_mismo(): void
    {
        $m = $this->mantenimiento();

        $this->ajax('put', route('admin.mantenimientos.update', $m->id), ['name' => 'MANT. DIC 2026', 'start_date' => '2026-12-05', 'end_date' => '2026-12-20'])
            ->assertOk()->assertJsonPath('mantenimiento.nombre', 'MANT. DIC 2026');

        $otro = $this->mantenimiento('2027-01-01', '2027-01-31', 'MANT. ENERO 2027');
        $this->ajax('put', route('admin.mantenimientos.update', $otro->id), ['name' => 'MANT. ENERO 2027', 'start_date' => '2026-12-15', 'end_date' => '2027-01-31'])
            ->assertStatus(422)->assertJsonValidationErrors('start_date');
    }

    public function test_al_cambiar_las_fechas_se_sincronizan_los_dias_de_los_horarios(): void
    {
        $m = $this->mantenimiento('2026-12-01', '2026-12-31');
        $h = $this->crearHorario($m);
        $this->assertSame(['2026-12-07', '2026-12-14', '2026-12-21', '2026-12-28'], $this->fechasDe($h));

        $this->ajax('put', route('admin.mantenimientos.update', $m->id), ['name' => $m->name, 'start_date' => '2026-12-01', 'end_date' => '2026-12-15'])->assertOk();
        $this->assertSame(['2026-12-07', '2026-12-14'], $this->fechasDe($h));

        $this->ajax('put', route('admin.mantenimientos.update', $m->id), ['name' => $m->name, 'start_date' => '2026-12-01', 'end_date' => '2027-01-10'])->assertOk();
        $this->assertSame(['2026-12-07', '2026-12-14', '2026-12-21', '2026-12-28', '2027-01-04'], $this->fechasDe($h));
    }

    public function test_no_acorta_el_rango_si_deja_fuera_un_dia_ya_registrado(): void
    {
        $m = $this->mantenimiento('2026-12-01', '2026-12-31');
        $h = $this->crearHorario($m);
        $h->dias()->whereDate('date', '2026-12-28')->update(['observation' => 'Se cambió el aceite']);

        $this->ajax('put', route('admin.mantenimientos.update', $m->id), ['name' => $m->name, 'start_date' => '2026-12-01', 'end_date' => '2026-12-15'])
            ->assertStatus(422)->assertJsonValidationErrors('start_date');
        $this->assertCount(4, $h->dias);
    }

    public function test_elimina_un_mantenimiento_con_sus_horarios_y_dias(): void
    {
        $m = $this->mantenimiento();
        $this->crearHorario($m);
        $this->assertSame(4, DiaMantenimiento::count());

        $this->ajax('delete', route('admin.mantenimientos.destroy', $m->id))->assertOk();

        $this->assertDatabaseCount('maintenances', 0);
        $this->assertDatabaseCount('maintenance_schedules', 0);
        $this->assertDatabaseCount('maintenance_days', 0);
    }

    public function test_no_elimina_un_mantenimiento_con_dias_ya_marcados(): void
    {
        $m = $this->mantenimiento();
        $h = $this->crearHorario($m);
        $h->dias()->first()->update(['status' => DiaMantenimiento::REALIZADO]);

        $this->ajax('delete', route('admin.mantenimientos.destroy', $m->id))->assertStatus(422);
        $this->assertDatabaseCount('maintenances', 1);
    }

    public function test_el_horario_genera_los_dias_de_la_semana_dentro_del_rango(): void
    {
        $m = $this->mantenimiento('2026-12-01', '2026-12-31');

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['weekday' => 1]))
            ->assertOk()->assertJsonPath('horario.dia', 'Lunes')->assertJsonPath('horario.dias', 4);

        $h = $m->horarios()->first();
        $this->assertSame(['2026-12-07', '2026-12-14', '2026-12-21', '2026-12-28'], $this->fechasDe($h));
        $this->assertSame(['pendiente'], $h->dias->pluck('status')->unique()->all());

        $martes = $this->crearHorario($m, ['weekday' => 2]);
        $this->assertSame(['2026-12-01', '2026-12-08', '2026-12-15', '2026-12-22', '2026-12-29'], $this->fechasDe($martes));
    }

    public function test_calcula_las_fechas_de_un_dia_de_la_semana(): void
    {
        $this->assertSame(['2026-12-01', '2026-12-08', '2026-12-15', '2026-12-22', '2026-12-29'], ServicioMantenimiento::fechasDe('2026-12-01', '2026-12-31', 2));
        $this->assertSame(['2026-12-06', '2026-12-13', '2026-12-20', '2026-12-27'], ServicioMantenimiento::fechasDe('2026-12-01', '2026-12-31', 7));
        $this->assertSame([], ServicioMantenimiento::fechasDe('2026-12-01', '2026-12-03', 1));
    }

    public function test_un_horario_exige_todos_sus_datos_y_horas_coherentes(): void
    {
        $m = $this->mantenimiento();

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), [])
            ->assertStatus(422)->assertJsonValidationErrors(['vehicle_id', 'type', 'weekday', 'time_start', 'time_end', 'user_id']);

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['time_start' => '10:00', 'time_end' => '09:00']))
            ->assertStatus(422)->assertJsonValidationErrors('time_end');
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['time_start' => '10:00', 'time_end' => '10:00']))
            ->assertStatus(422)->assertJsonValidationErrors('time_end');
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['type' => 'pintura']))
            ->assertStatus(422)->assertJsonValidationErrors('type');
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['weekday' => 8]))
            ->assertStatus(422)->assertJsonValidationErrors('weekday');

        $this->assertDatabaseCount('maintenance_schedules', 0);
    }

    public function test_solo_acepta_vehiculos_y_personal_activos_que_existan(): void
    {
        $m = $this->mantenimiento();
        $inactivo = $this->vehiculo('Inactivo');
        $inactivo->update(['status' => false]);

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => $inactivo->id]))
            ->assertStatus(422)->assertJsonValidationErrors('vehicle_id');
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('vehicle_id');
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['user_id' => $this->responsable(false)->id]))
            ->assertStatus(422)->assertJsonValidationErrors('user_id');

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['user_id' => User::factory()->create()->id]))
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_el_dia_de_la_semana_debe_ocurrir_dentro_del_rango(): void
    {
        $m = $this->mantenimiento('2026-12-01', '2026-12-03');

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['weekday' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors('weekday');
        $this->assertDatabaseCount('maintenance_schedules', 0);
    }

    public function test_los_horarios_del_mismo_vehiculo_no_se_solapan_en_dia_y_hora(): void
    {
        $m = $this->mantenimiento();
        $a = $this->vehiculo('Vehículo A');
        $b = $this->vehiculo('Vehículo B');
        $this->crearHorario($m, ['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '08:00', 'time_end' => '11:00']);

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00']))
            ->assertStatus(422)->assertJsonValidationErrors('time_start');
        foreach ([['07:00', '08:30'], ['10:30', '12:00'], ['06:00', '13:00'], ['08:00', '11:00']] as [$ini, $fin]) {
            $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => $ini, 'time_end' => $fin]))
                ->assertStatus(422);
        }

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => $b->id, 'weekday' => 1, 'time_start' => '08:00', 'time_end' => '11:00']))->assertOk();
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 3, 'time_start' => '08:00', 'time_end' => '11:00']))->assertOk();
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '11:00', 'time_end' => '13:00']))->assertOk();
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '06:00', 'time_end' => '08:00']))->assertOk();

        $this->assertDatabaseCount('maintenance_schedules', 5);
    }

    public function test_al_editar_un_horario_no_choca_consigo_mismo(): void
    {
        $m = $this->mantenimiento();
        $a = $this->vehiculo('Vehículo A');
        $h = $this->crearHorario($m, ['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '08:00', 'time_end' => '11:00']);
        $otro = $this->crearHorario($m, ['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '12:00', 'time_end' => '14:00']);

        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$m, $h]), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '08:30', 'time_end' => '10:30']))->assertOk();
        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$m, $h]), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 1, 'time_start' => '08:30', 'time_end' => '12:30']))
            ->assertStatus(422)->assertJsonValidationErrors('time_start');
        $this->assertSame('14:00', $otro->fresh()->hora_fin);
    }

    public function test_cambiar_el_dia_regenera_los_dias_solo_si_nadie_registro_nada(): void
    {
        $m = $this->mantenimiento();
        $a = $this->vehiculo();
        $h = $this->crearHorario($m, ['vehicle_id' => $a->id, 'weekday' => 1]);

        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$m, $h]), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 3]))->assertOk();
        $this->assertSame(['2026-12-02', '2026-12-09', '2026-12-16', '2026-12-23', '2026-12-30'], $this->fechasDe($h));

        $h->dias()->first()->update(['observation' => 'Revisado']);
        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$m, $h]), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 5]))
            ->assertStatus(422)->assertJsonValidationErrors('weekday');
        $this->assertSame(3, $h->fresh()->weekday);

        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$m, $h]), $this->datosHorario(['vehicle_id' => $a->id, 'weekday' => 3, 'type' => 'limpieza', 'time_end' => '12:00']))->assertOk();
        $this->assertSame('Revisado', $h->dias()->first()->observation);
    }

    public function test_eliminar_un_horario_elimina_sus_dias(): void
    {
        $m = $this->mantenimiento();
        $h = $this->crearHorario($m);
        $otro = $this->crearHorario($m, ['weekday' => 2]);

        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', [$m, $h]))->assertOk();

        $this->assertDatabaseMissing('maintenance_schedules', ['id' => $h->id]);
        $this->assertSame(0, DiaMantenimiento::where('schedule_id', $h->id)->count());
        $this->assertSame(5, DiaMantenimiento::where('schedule_id', $otro->id)->count());
    }

    public function test_elimina_un_horario_aunque_sus_dias_tengan_datos(): void
    {
        [$m, $h] = $this->conDiasDeHoy();
        $dia = $this->dia($h, today()->subDays(7)->toDateString());
        $this->actualizarDia($m, $h, $dia, ['done' => 1, 'observation' => 'Todo conforme', 'image' => UploadedFile::fake()->image('foto.jpg')])->assertOk();
        $ruta = public_path(ltrim($dia->fresh()->image_path, '/'));
        $this->assertFileExists($ruta);

        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', [$m, $h]))->assertOk();

        $this->assertDatabaseMissing('maintenance_schedules', ['id' => $h->id]);
        $this->assertSame(0, DiaMantenimiento::count());
        $this->assertFileDoesNotExist($ruta);
    }

    public function test_un_horario_no_se_puede_tocar_desde_otro_mantenimiento(): void
    {
        $m = $this->mantenimiento();
        $otro = $this->mantenimiento('2027-01-01', '2027-01-31', 'MANT. ENERO 2027');
        $h = $this->crearHorario($m);

        $this->ajax('get', route('admin.mantenimientos.horarios.edit', [$otro, $h]))->assertNotFound();
        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$otro, $h]), $this->datosHorario())->assertNotFound();
        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', [$otro, $h]))->assertNotFound();
        $this->ajax('get', route('admin.mantenimientos.horarios.dias.index', [$otro, $h]))->assertNotFound();
    }

    public function test_lista_los_horarios_con_sus_datos(): void
    {
        $m = $this->mantenimiento();
        $this->crearHorario($m, ['weekday' => 3, 'type' => 'limpieza', 'time_start' => '14:30', 'time_end' => '16:00']);
        $this->crearHorario($m, ['weekday' => 1, 'type' => 'reparacion']);

        $this->ajax('get', route('admin.mantenimientos.horarios.index', $m))
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.dia', 'Lunes')->assertJsonPath('data.0.tipo_texto', 'Reparación')->assertJsonPath('data.0.inicio', '08:00 am')
            ->assertJsonPath('data.1.dia', 'Miércoles')->assertJsonPath('data.1.fin', '04:00 pm');
    }

    private function conDiasDeHoy(): array
    {
        $m = $this->mantenimiento(today()->subDays(14)->toDateString(), today()->addDays(14)->toDateString(), 'MANT. ACTUAL');
        $h = $this->crearHorario($m, ['weekday' => today()->dayOfWeekIso]);

        return [$m, $h];
    }

    private function dia(HorarioMantenimiento $h, string $fecha): DiaMantenimiento
    {
        return $h->dias()->whereDate('date', $fecha)->firstOrFail();
    }

    private function actualizarDia(Mantenimiento $m, HorarioMantenimiento $h, DiaMantenimiento $d, array $datos)
    {
        return $this->ajax('put', route('admin.mantenimientos.horarios.dias.update', [$m, $h, $d]), $datos);
    }

    public function test_lista_los_dias_generados(): void
    {
        [$m, $h] = $this->conDiasDeHoy();

        $this->ajax('get', route('admin.mantenimientos.horarios.dias.index', [$m, $h]))
            ->assertOk()->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.fecha', today()->subDays(14)->format('d/m/Y'))
            ->assertJsonPath('data.0.estado', 'pendiente')->assertJsonPath('data.0.imagen', null);
    }

    public function test_marca_un_dia_como_realizado_con_observacion(): void
    {
        [$m, $h] = $this->conDiasDeHoy();
        $dia = $this->dia($h, today()->subDays(7)->toDateString());

        $this->actualizarDia($m, $h, $dia, ['observation' => '  Todo conforme  ', 'done' => 1])
            ->assertOk()->assertJsonPath('dia.estado', 'realizado')->assertJsonPath('dia.observacion', 'Todo conforme');

        $this->assertSame('realizado', $dia->fresh()->status);
    }

    public function test_sin_marcar_un_dia_pasado_queda_como_no_realizado_y_uno_futuro_como_pendiente(): void
    {
        [$m, $h] = $this->conDiasDeHoy();

        $this->actualizarDia($m, $h, $this->dia($h, today()->subDays(14)->toDateString()), ['observation' => 'No se realizó'])
            ->assertOk()->assertJsonPath('dia.estado', 'no_realizado');
        $this->actualizarDia($m, $h, $this->dia($h, today()->addDays(7)->toDateString()), ['observation' => 'Llevar repuestos'])
            ->assertOk()->assertJsonPath('dia.estado', 'pendiente');
        $this->actualizarDia($m, $h, $this->dia($h, today()->toDateString()), ['observation' => 'En curso'])
            ->assertOk()->assertJsonPath('dia.estado', 'pendiente');
    }

    public function test_no_se_puede_marcar_realizado_un_dia_futuro(): void
    {
        [$m, $h] = $this->conDiasDeHoy();
        $futuro = $this->dia($h, today()->addDays(14)->toDateString());

        $this->actualizarDia($m, $h, $futuro, ['done' => 1])->assertStatus(422)->assertJsonValidationErrors('done');
        $this->assertSame('pendiente', $futuro->fresh()->status);
    }

    public function test_sube_reemplaza_y_quita_la_imagen_del_dia(): void
    {
        [$m, $h] = $this->conDiasDeHoy();
        $dia = $this->dia($h, today()->subDays(7)->toDateString());

        $this->actualizarDia($m, $h, $dia, ['done' => 1, 'image' => UploadedFile::fake()->image('antes.jpg', 200, 150)])->assertOk();
        $primera = $dia->fresh()->image_path;
        $this->assertStringStartsWith('/uploads/maintenance_days/', $primera);
        $this->assertFileExists(public_path(ltrim($primera, '/')));

        $this->actualizarDia($m, $h, $dia, ['done' => 1, 'image' => UploadedFile::fake()->image('despues.png', 200, 150)])->assertOk();
        $segunda = $dia->fresh()->image_path;
        $this->assertNotSame($primera, $segunda);
        $this->assertFileDoesNotExist(public_path(ltrim($primera, '/')));
        $this->assertFileExists(public_path(ltrim($segunda, '/')));

        $this->actualizarDia($m, $h, $dia, ['done' => 1, 'observation' => 'Sigue la foto'])->assertOk();
        $this->assertSame($segunda, $dia->fresh()->image_path);

        $this->actualizarDia($m, $h, $dia, ['done' => 1, 'remove_image' => 1])->assertOk()->assertJsonPath('dia.imagen', null);
        $this->assertNull($dia->fresh()->image_path);
        $this->assertFileDoesNotExist(public_path(ltrim($segunda, '/')));
    }

    public function test_rechaza_archivos_que_no_son_imagenes_o_pesan_demasiado(): void
    {
        [$m, $h] = $this->conDiasDeHoy();
        $dia = $this->dia($h, today()->subDays(7)->toDateString());

        $this->actualizarDia($m, $h, $dia, ['image' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->actualizarDia($m, $h, $dia, ['image' => UploadedFile::fake()->create('script.php', 10, 'text/x-php')])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->actualizarDia($m, $h, $dia, ['image' => UploadedFile::fake()->image('enorme.jpg')->size(3000)])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->actualizarDia($m, $h, $dia, ['observation' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors('observation');

        $this->assertNull($dia->fresh()->image_path);
    }

    public function test_eliminar_un_horario_pendiente_borra_las_imagenes_de_sus_dias(): void
    {
        [$m, $h] = $this->conDiasDeHoy();
        $dia = $this->dia($h, today()->addDays(7)->toDateString());
        $this->actualizarDia($m, $h, $dia, ['image' => UploadedFile::fake()->image('futuro.jpg')])->assertOk();
        $ruta = public_path(ltrim($dia->fresh()->image_path, '/'));
        $this->assertFileExists($ruta);

        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', [$m, $h]))->assertOk();

        $this->assertFileDoesNotExist($ruta);
    }

    public function test_no_elimina_un_vehiculo_ni_una_persona_que_figura_en_un_horario(): void
    {
        $m = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $persona = $this->responsable();
        $this->crearHorario($m, ['vehicle_id' => $vehiculo->id, 'user_id' => $persona->id]);

        $this->ajax('delete', route('admin.vehiculos.destroy', $vehiculo->id))
            ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede eliminar: el vehículo está en 1 horario de mantenimiento. Márquelo como inactivo.');
        $this->ajax('delete', route('admin.personal.destroy', $persona->id))->assertStatus(422);

        $this->assertDatabaseHas('vehicles', ['id' => $vehiculo->id]);
        $this->assertDatabaseHas('users', ['id' => $persona->id]);
    }

    public function test_las_pantallas_del_modulo_cargan_y_el_menu_tiene_la_opcion(): void
    {
        $m = $this->mantenimiento();
        $h = $this->crearHorario($m);

        $this->flushHeaders();
        $this->get(route('admin.mantenimientos.index'))->assertOk()->assertSee('Mantenimientos programados')->assertSee('Nuevo mantenimiento')->assertSee('js/tads/mantenimiento.js', false);
        $this->get(route('admin.mantenimientos.horarios.index', $m))->assertOk()->assertSee('MANT. DICIEMBRE 2026 / Horarios')->assertSee('Agregar horario');
        $this->get(route('admin.mantenimientos.horarios.dias.index', [$m, $h]))->assertOk()->assertSee('Días programados');

        $this->ajax('get', route('admin.mantenimientos.create'))->assertOk()->assertSee('Fecha de inicio');
        $this->ajax('get', route('admin.mantenimientos.horarios.create', $m))->assertOk()->assertSee('Tipo de mantenimiento')->assertSee('Preventivo');
        $this->ajax('get', route('admin.mantenimientos.horarios.edit', [$m, $h]))->assertOk()->assertSee('Guardar horario');
        $this->ajax('get', route('admin.mantenimientos.horarios.dias.edit', [$m, $h, $h->dias()->first()]))->assertOk()->assertSee('Mantenimiento realizado');
    }

    public function test_cada_accion_exige_su_permiso_a_los_operadores(): void
    {
        $m = $this->mantenimiento();
        $h = $this->crearHorario($m);
        $operador = User::factory()->operador()->create();
        $operador->permisos()->create(['permission' => 'maintenances.view']);

        $this->actingAs($operador);

        $this->ajax('get', route('admin.mantenimientos.index'))->assertOk();
        $this->ajax('get', route('admin.mantenimientos.horarios.index', $m))->assertOk();
        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'X', 'start_date' => '2030-01-01', 'end_date' => '2030-01-02'])->assertForbidden();
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $m), $this->datosHorario())->assertForbidden();
        $this->ajax('put', route('admin.mantenimientos.horarios.dias.update', [$m, $h, $h->dias()->first()]), ['observation' => 'X'])->assertForbidden();
        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', [$m, $h]))->assertForbidden();
        $this->ajax('delete', route('admin.mantenimientos.destroy', $m->id))->assertForbidden();

        $sin = User::factory()->operador()->create();
        $this->actingAs($sin)->ajax('get', route('admin.mantenimientos.index'))->assertForbidden();
    }

    private function fechasDe(HorarioMantenimiento $h): array
    {
        return $h->dias()->get()->map(fn (DiaMantenimiento $d) => $d->date->toDateString())->all();
    }
}
