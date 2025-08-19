<!-- resources/views/activos/qr.blade.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Activo {{ $activo->id }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Bootstrap CSS desde CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card {
            max-width: 500px;
            margin: auto;
            border-radius: 12px;
            overflow: hidden;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            text-align: center;
            font-size: 1.2rem;
        }
        .list-group-item strong {
            color: #0d6efd;
        }
    </style>
</head>
<body>

    <div class="card shadow">
        <div class="card-header">
            📦 Detalles del Activo #{{ $activo->id }}
        </div>
        <div class="card-body">
            <h5 class="card-title">{{ $activo->numero_activo }}</h5>
            <p class="card-text">{{ $activo->descripcion }}</p>
        </div>
        <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>Categoría:</strong> {{ $activo->categoria }}</li>
            <li class="list-group-item"><strong>Subcategoría:</strong> {{ $activo->subcategoria }}</li>
            <li class="list-group-item"><strong>Ubicación:</strong> {{ $activo->ubicacion }}</li>
            <li class="list-group-item"><strong>Usuario Asignado:</strong> {{ $activo->usuario }}</li>
            <li class="list-group-item"><strong>Valor:</strong> ${{ number_format($activo->valor, 0, ',', '.') }}</li>
            <li class="list-group-item"><strong>Fecha fin garantía:</strong> {{ \Carbon\Carbon::parse($activo->fecha_fin_garantia)->format('d/m/Y') }}</li>
        </ul>
        <div class="card-body text-center">
            <a href="javascript:window.print()" class="btn btn-primary">Imprimir</a>
        </div>
    </div>

</body>
</html>
