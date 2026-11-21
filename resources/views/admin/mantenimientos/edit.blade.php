<form method="POST" action="{{ route('admin.mantenimientos.update', $mantenimiento->id) }}" class="t-formulario">
    @csrf
    @method('PUT')
    @include('admin.mantenimientos._form')
</form>
