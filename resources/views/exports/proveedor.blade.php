<table>
    <thead>
        <tr>
            <th>codigo_insumo</th>
            <th>insumo_descripcion</th>
            <th>unidad</th>
            <th>mat_requerido</th>
            <th>agrupacion_descripcion</th>
            <th>valor</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datos as $item)
            <tr>
                <td>{{ $item->codigo_insumo }}</td>
                <td>{{ $item->insumo_descripcion }}</td>
                <td>{{ $item->unidad }}</td>
                <td>{{ $item->mat_requerido }}</td>
                <td>{{ $item->agrupacion_descripcion }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
