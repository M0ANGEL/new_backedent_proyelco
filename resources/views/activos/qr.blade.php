<!-- resources/views/activos/qr.blade.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Activo {{ $activo->id }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- Bootstrap CSS desde CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Íconos de Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #e9f0ff, #f8f9fa);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 30px;
        }
        .card {
            max-width: 550px;
            margin: auto;
            border-radius: 16px;
            overflow: hidden;
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            text-align: center;
            font-size: 1.3rem;
            font-weight: bold;
            padding: 15px;
        }
        .list-group-item {
            border: none;
            padding: 12px 16px;
            font-size: 0.95rem;
        }
        .list-group-item strong {
            color: #0d6efd;
        }
        .btn-modern {
            border-radius: 30px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-modern:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>

    <div class="card shadow-lg">
        <div class="card-header">
            <i class="bi bi-box-seam"></i> Activo #{{ $activo->numero_activo }}
        </div>
        <div class="card-body text-center">
            <p class="card-text text-muted">Descripcion: {{ $activo->descripcion }}</p>
        </div>
        <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong><i class="bi bi-tag"></i> Categoría:</strong> {{ $activo->categoria }}</li>
            <li class="list-group-item"><strong><i class="bi bi-diagram-3"></i> Subcategoría:</strong> {{ $activo->subcategoria }}</li>
            <li class="list-group-item"><strong><i class="bi bi-geo-alt"></i> Ubicación:</strong> {{ $activo->ubicacion }}</li>
            <li class="list-group-item"><strong><i class="bi bi-person"></i> Usuario Asignado:</strong> {{ $activo->usuario }}</li>
            <li class="list-group-item"><strong><i class="bi bi-cash-coin"></i> Valor:</strong> ${{ $activo->valor}}</li>
        </ul>
        <div class="card-body text-center">
            <a href="javascript:window.print()" class="btn btn-primary btn-modern">
                <i class="bi bi-printer"></i> Imprimir
            </a>
        </div>
    </div>

</body>
</html>
