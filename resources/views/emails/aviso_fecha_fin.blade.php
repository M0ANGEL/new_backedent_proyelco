<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>🚨 Alerta de Inactivida en Proyecto <span> {{ $proyecto->descripcion_proyecto }}</span> </title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f6f8;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background-color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        p {
            font-size: 15px;
            color: #34495e;
        }

        .proyecto-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .proyecto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .proyecto-card h3 {
            margin-top: 0;
            color: #2980b9;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .proyecto-card p {
            margin: 8px 0;
            font-size: 14px;
        }

        .avance {
            color: #27ae60;
            font-weight: bold;
        }

        .atraso {
            color: #c0392b;
            font-weight: bold;
        }

        .btn-container {
            text-align: center;
            margin-top: 20px;
        }

        .btn {
            background-color: #2980b9;
            color: #ffffff;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn:hover {
            background-color: #21618c;
            transform: scale(1.05);
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
    <div class="container">

        <h2>🚨 Alerta de Inactivida en Proyecto <span> <b> {{ $proyecto->descripcion_proyecto }}</b></span></h2>
        <div class="proyecto-card">
            <p>Se informa que hace 3 dias habiles no se realizan registros en el proyecto
                <span> <b> {{ $proyecto->descripcion_proyecto }}</b></span>
            </p>
        </div>


        <div class="btn-container">
            <a class="btn" href="https://front.proyelco.com/#/auth/login" target="_blank">VER MÁS DETALLE</a>
        </div>

        <div class="footer">
            Este es un correo automático. Por favor, no responder.
        </div>
    </div>
</body>

</html>
