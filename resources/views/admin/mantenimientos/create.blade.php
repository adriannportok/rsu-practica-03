<form method="POST" action="{{ route('admin.mantenimientos.store') }}" class="t-formulario">
    @csrf
    @include('admin.mantenimientos._form')
</form>
