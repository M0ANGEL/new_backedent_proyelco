<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        .card {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background-color: #f9f9f9;
            font-family: Arial, sans-serif;
        }
        .card h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
        }
        .card p {
            font-size: 16px;
            color: #34495e;
            margin: 8px 0;
        }
        .card strong {
            color: #2c3e50;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>{{ $detalles['titulo'] }}</h2>

        <p>{{ $detalles['mensaje'] }}</p>

        <p><strong>Cliente:</strong> {{ $detalles['proyecto']->emp_nombre }}</p>
        <p><strong>Encargado:</strong> {{ $detalles['proyecto']->nombreEncargado }}</p>
        <p><strong>Ingeniero:</strong> {{ $detalles['proyecto']->nombreIngeniero }}</p>

        <div class="footer">
            Este es un correo automático. Por favor, no responder.
        </div>
    </div>
</body>
</html>
