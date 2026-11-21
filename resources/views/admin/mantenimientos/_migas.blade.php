{{-- Ruta de navegación del módulo: Gestión de vehículos / Mantenimiento / <mantenimiento> / <horario>. --}}
<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item">Gestión de vehículos</li>
        @foreach ($migas as $miga)
            @if (! $loop->last && isset($miga[1]))
                <li class="breadcrumb-item"><a href="{{ $miga[1] }}">{{ $miga[0] }}</a></li>
            @else
                <li class="breadcrumb-item active" aria-current="page">{{ $miga[0] }}</li>
            @endif
        @endforeach
    </ol>
</nav>
