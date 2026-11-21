<form method="POST" action="{{ route('admin.mantenimientos.horarios.update', [$mantenimiento, $horario]) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.mantenimientos.horarios._form')
</form>
