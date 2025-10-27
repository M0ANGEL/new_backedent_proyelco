<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status del Servidor</title>
    <!-- CDN de TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-md text-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">🧩 Estado del Servidor</h1>

        <div class="space-y-4">
            <div class="p-4 rounded-lg border border-green-300 bg-green-50">
                <p class="text-green-700 font-semibold">✅ Servidor Laravel en funcionamiento</p>
            </div>

            <div class="p-4 rounded-lg border 
                @if(isset($dbStatus) && str_contains($dbStatus, 'Error')) border-red-300 bg-red-50 text-red-700
                @else border-green-300 bg-green-50 text-green-700 @endif">
                <p class="font-semibold">
                    {{ $dbStatus ?? 'Conectando a la base de datos...' }}
                </p>
            </div>
        </div>

        <p class="text-gray-500 mt-6 text-sm">
            Última actualización: {{ now()->format('Y-m-d H:i:s') }}
        </p>
    </div>

</body>
</html>
