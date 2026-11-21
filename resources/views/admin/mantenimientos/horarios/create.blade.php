<form method="POST" action="{{ route('admin.mantenimientos.horarios.store', $mantenimiento) }}" class="t-formulario">
    @csrf
    @include('admin.mantenimientos.horarios._form')
</form>
