<?php

use App\Http\Controllers\Api\ActivosFijos\ActivosController;
use App\Http\Controllers\Api\ActivosFijos\BodegasController;
use App\Http\Controllers\Api\ActivosFijos\CategoriaActivosController;
use App\Http\Controllers\Api\ActivosFijos\KadexActivosController;
use App\Http\Controllers\Api\ActivosFijos\MantenimientoActivosController;
use App\Http\Controllers\Api\ActivosFijos\MisActivosController;
use App\Http\Controllers\Api\ActivosFijos\SubCategoriaActivosController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CargoController;
use App\Http\Controllers\Api\CargueMasivo\CarguesMasivosCotroller;
use App\Http\Controllers\Api\Clientes\ClientesController;
use App\Http\Controllers\Api\Compras\CargueComprasController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\EmpxUsuController;
use App\Http\Controllers\Api\GestionPerfilesController;
use App\Http\Controllers\Api\Proveedores\ProveedoresController;
use App\Http\Controllers\Api\Proyectos\GestionProyectosController;
use App\Http\Controllers\Api\Proyectos\ProcesosProyectoController;
use App\Http\Controllers\Api\Proyectos\ProyectosController;
use App\Http\Controllers\Api\Proyectos\TipoProyectosController;
use App\Http\Controllers\Api\Proyectos\VaidarProcesoController;
use App\Http\Controllers\Api\Proyectos\ValiProcPTController;
use App\Http\Controllers\Api\TalentoHumano\Asistencia\ControlAsistenciasController;
use App\Http\Controllers\Api\TalentoHumano\AsistenObras\AsistenciasObrasController;
use App\Http\Controllers\Api\TalentoHumano\FichaObra\FichaObraController;
use App\Http\Controllers\Api\TalentoHumano\Personal\PersonalController;
use App\Http\Controllers\Api\TalentoHumano\PersonalProyelco\PersonalProyelcoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UsersPerfilesController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\PerfilesModulosController;
use App\Http\Controllers\SubmenuController;
use App\Http\Middleware\CompanyDatabase;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\HorarioAdicionalesController;
use App\Http\Controllers\Auth\HorariosController;
use App\Http\Controllers\AuthMarcacionController;
use App\Http\Controllers\ContratistasController;
use App\Models\Proyectos;
use App\Models\ProyectosDetalle;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

//manejo de login de app movile
Route::get('Appupdate', [AuthMarcacionController::class, 'Appupdate']);
Route::post('loginMarcacion', [AuthMarcacionController::class, 'loginMarcacion']);
Route::post('loginMarcacionConfi', [AuthMarcacionController::class, 'loginMarcacionConfi']);

Route::post('login', [AuthController::class, 'login']);
Route::post('clear-sessions', [AuthController::class, 'clearSessions']);
Route::middleware([CompanyDatabase::class])->group(function () {
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('logout', [AuthController::class, 'logout']);
});

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::apiResource('perfil', GestionPerfilesController::class);
    Route::apiResource('modulo', ModulosController::class);
    Route::apiResource('menu', MenuController::class);
    Route::apiResource('users-perfiles', UsersPerfilesController::class);
    Route::apiResource('perfiles-modulos', PerfilesModulosController::class);
    Route::apiResource('submenu', SubmenuController::class);


    // Rutas Usuarios
    Route::post('register', [UserController::class, 'register']);
    Route::post('removerItem', [UserController::class, 'removerItem']);
    Route::get('usuarios', [UserController::class, 'index']);
    Route::get('user-profile', [AuthController::class, 'userProfile']);
    Route::get('user-documentos', [AuthController::class, 'userDocumentos']);
    Route::get('user-bodegas', [AuthController::class, 'userBodegas']);
    Route::post('validar-documento', [AuthController::class, 'validarDocumento']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
    Route::put('/usuarios/{id}', [UserController::class, 'update']);
    Route::get('/usuarios/username/{username}', [UserController::class, 'usernameUnique']);
    Route::get('/usuarios/{id}', [UserController::class, 'show']);
    Route::post('/usuarios/perfiles/imagen', [UserController::class, 'cambioImagen']);
    Route::put('/usuarios/{id}/contrasena', [UserController::class, 'cambioContrasena']);
    Route::post('users-by-rol', [UserController::class, 'getListUsersByRoles']);
    // Rutas Reportes Usuarios
    // Route::get('usuarios/reporte/all', [UsuariosExcelController::class, 'exportarUsuarios']);
    // Rutas Reportes permisos usuarios documentos
    // Route::get('usuarios/permisos/documentos', [UsuariosExcelController::class, 'exportarUsuarioPrivilegioDocumentos']);
    // Route::get('perfiles/modulos', [UsuariosExcelController::class, 'exportarUsuarioModulos']);


    // Rutas Cargos
    Route::apiResource('cargos', CargoController::class);

    // Rutas Usuarios Cargos
    Route::apiResource('cargos/usuarios', CargoController::class);

    // Rutas Empresas
    Route::apiResource('empresas', EmpresaController::class);

    // Rutas Usuario/Empresas
    Route::get('usuarios/empresas', [EmpxUsuController::class, 'index']);
    Route::delete('/usuarios/empresas/{id}', [EmpxUsuController::class, 'destroy']);

    //horarios
    Route::apiResource('crear-horarios', HorariosController::class);
    Route::get('perfiles-horarios', [HorariosController::class, 'getHorarios']);
    Route::post('crear-perfiles-horarios', [HorariosController::class, 'crearPerfil']);

    //horarios adicionales
    Route::apiResource('horario-adicionales', HorarioAdicionalesController::class);

    //clientes de proyectos
    Route::apiResource('admin-clientes', ClientesController::class);

    //tipos de proyecto
    Route::apiResource('tipo-proyectos', TipoProyectosController::class);

    //procesos de proyectos
    Route::apiResource('procesos-proyectos', ProcesosProyectoController::class);

    //validaciones por proceos
    Route::apiResource('validacion-procesos-proyectos', ValiProcPTController::class);

    //crear proyecto y administracion del mismo
    Route::apiResource('administracion-proyectos', ProyectosController::class);
    //consulta del detalle del proyecto administrador
    Route::get('administracion-proyectos-detalle/{id}', [ProyectosController::class, 'indexProgreso']);
    Route::get('usuarios-proyectos', [ProyectosController::class, 'usuariosProyectos']);
    Route::get('ingenieros-proyectos', [ProyectosController::class, 'ingenierosProyectos']);
    Route::get('usuario-correos', [ProyectosController::class, 'usuariosCorreos']);

    //gestion de encargado del proyecto
    Route::apiResource('gestion-proyectos', GestionProyectosController::class);
    Route::get('gestion-proyectos-detalle/{id}', [GestionProyectosController::class, 'indexProgreso']); //**--**//
    Route::post('gestion-iniciar-torre', [GestionProyectosController::class, 'IniciarTorre']);
    Route::get('info-proyecto/{id}', [GestionProyectosController::class, 'infoProyecto']); //**--**//
    Route::get('gestion-confirmar-apartamento/{id}', [GestionProyectosController::class, 'confirmarAptNuevaLogica']); //se cambia temporal para probar nueva logica este confirmarApt por confirmarAptNuevaLogica
    Route::post('gestion-confirmar-validar', [VaidarProcesoController::class, 'validarProcesoNuevaLogica']);
    Route::get('InformeDetalladoProyectos/{id}', [GestionProyectosController::class, 'InformeDetalladoProyectos']);
    Route::post('CambioEstadosApt-anulacion', [GestionProyectosController::class, 'CambioEstadosApt']);
    Route::get('gestion-proyectos-encargados', [GestionProyectosController::class, 'indexEncargadoObra']);

    //cart dashboard
    Route::get('info-dashboard-card', [ProyectosController::class, 'infoCard']);

    // activacion de pisos por dias en procesos  
    // Route::post('activacionXdia', [GestionProyectosController::class, 'activacionXDia']);


    // cargue de archivo plano para papeleria
    Route::post('papeleria/archivo-plano', [CargueComprasController::class, 'cargarPlanoPapeleria']);
    Route::post('envio-cotizacion', [CargueComprasController::class, 'EnvioCotizacion']);
    Route::get('plantilla-papelera-descarga', [CargueComprasController::class, 'plantilla']);
    Route::get('papeleria', [CargueComprasController::class, 'index']); //llmado de los datos de cotizacion 

    // proveedores de cotizacion
    Route::apiResource('proveedores', ProveedoresController::class);

    // personal empresa y asistencias
    Route::apiResource('personal', PersonalController::class);
    Route::apiResource('asistencias', AsistenciasObrasController::class);
    Route::get('proyectos-activos', [AsistenciasObrasController::class, 'proyectosActivos']);
    Route::get('empleados', [AsistenciasObrasController::class, 'empleados']);
    Route::get('asistencias-confirmar', [AsistenciasObrasController::class, 'UsuarioConfirmarAsistencia']);
    Route::post('asistencias-confirmar-empleado', [AsistenciasObrasController::class, 'confirmarAsistencias']);
    Route::post('no-asistencias-empleado', [AsistenciasObrasController::class, 'confirmarNoAsistencias']);
    Route::post('cambio-Proyecto-Asistencia', [AsistenciasObrasController::class, 'cambioProyectoAsistencia']);

    //cargue masivos
    Route::post('cargueEmpleados', [CarguesMasivosCotroller::class, 'cargueEmpleados']);
    Route::post('cargueUpdateProyecto', [CarguesMasivosCotroller::class, 'cargueUpdateProyecto']);


    //fin cargue masivos

    //anulacion de apt informativo
    Route::post('proyectos-simular-anulacion', [GestionProyectosController::class, 'simularAnulacion']);

    // dashboar
    // esta ruta me envia todo mis proyectos en los que yo estoy
    Route::get('dashboards/indexMisProyectos', [DashboardController::class, 'indexMisProyectos']);
    Route::get('dashboards/proyectosDetalles/{id}', [DashboardController::class, 'dashboardsProyectos']);
    Route::post('dashboards/infoApt', [DashboardController::class, 'infoApt']);

    //descargas en excel
    Route::get('informe-proyecto-excel/{id}', [GestionProyectosController::class, 'ExportInformeExcelProyecto']);

    //activos fijos
    Route::apiResource('bodega-areas', BodegasController::class);
    Route::get('bodega-areas-obras', [BodegasController::class, 'obras']);
    Route::apiResource('categorias-activos', CategoriaActivosController::class);
    Route::apiResource('subcategorias-activos', SubCategoriaActivosController::class);
    Route::get('categoria-subcategoria-activos/{id}', [SubCategoriaActivosController::class, 'SubcategoriaFiltrado']);

    Route::apiResource('administar-activos', ActivosController::class);
    Route::get('administar-activosBaja', [ActivosController::class, 'indexActivosBaja']);
    Route::get('usuariosAsignacion', [ActivosController::class, 'usuariosAsignacion']);

    Route::get('mis-activos-pendientes', [MisActivosController::class, 'index']);
    Route::get('mis-activos', [MisActivosController::class, 'misActivos']);

    //administar activos
    Route::apiResource('administar-kardex-activos', KadexActivosController::class);
    Route::get('administar-activos-all', [KadexActivosController::class, 'index']);
    Route::get('administar-activos-pendientes-all', [KadexActivosController::class, 'activosPendientes']);


    Route::get('administar-mis-activos', [KadexActivosController::class, 'misActivos']);
    Route::get('activo-pendientes', [KadexActivosController::class, 'activosSinConfirmar']);
    Route::get('activo-aceptarActivo/{id}', [KadexActivosController::class, 'aceptarActivo']);
    Route::post('activo-rechazarActivo', [KadexActivosController::class, 'rechazarActivo']);
    Route::get('activo-informacion/{id}', [KadexActivosController::class, 'infoActivo']);

    //solicitar activo
    Route::get('solicitar-activos', [KadexActivosController::class, 'solicitarActivos']);
    Route::post('envio-solicitud-activo', [KadexActivosController::class, 'envioSolicitudActivo']);
    Route::post('liberar-activos', [KadexActivosController::class, 'liberarActivos']);



    //kardex historico
    Route::get('activos-historico', [KadexActivosController::class, 'historico']);

    //nomenclaturas de proyes
    Route::get('nomenclaturas/{id}', [ProyectosController::class, 'nomenclaturas']);
    Route::post('ActualizarNomenclaturas', [ProyectosController::class, 'ActualizarNomenclaturas']);

    //mantenimiento activos
    Route::apiResource('administra-mantenimiento-activos', MantenimientoActivosController::class);
    Route::get('activosBodegaPrincipal', [MantenimientoActivosController::class, 'activosBodegaPrincipal']);

    //notificacions de obras isn movimientos mas de un dia
    Route::get('obras-sin-movimientos', [ProyectosController::class, 'obrasSinMovimientos']);
    Route::get('obras-sin-movimientos-ing', [ProyectosController::class, 'obrasSinMovimientosIng']);




    //talento humano
    Route::apiResource('administar-th', PersonalProyelcoController::class);
    Route::get('paises-th', [PersonalProyelcoController::class, 'paises']);
    Route::get('ciudades-th/{id}', [PersonalProyelcoController::class, 'ciudades']);
    Route::get('cargos-th', [PersonalProyelcoController::class, 'cargos']);


    //telefonos app mobile
    Route::post('validarTelefono', [AuthMarcacionController::class, 'validarTelefono']);
    Route::post('/registrar-telefono', [AuthMarcacionController::class, 'registrarTelefono']);
    Route::post('/registrar-sede', [AuthMarcacionController::class, 'registarUbicacionObra']);
    Route::get('/obras-app', [AuthMarcacionController::class, 'obrasApp']);

    //marcacion
    Route::post('consultar-cedula',[ControlAsistenciasController::class, 'consultarUsuario']);
    Route::post('registrar-asistencia',[ControlAsistenciasController::class, 'registrarMarcacion']);

    //contratista
    Route::apiResource('administar-contratistas',ContratistasController::class);
    Route::get('contratistas',[ContratistasController::class,'ContratistasActivos']);

    //personal no proyelco
    Route::apiResource('personal-no-proyelco',PersonalController::class);
    Route::get('empleados/{cedula}',[PersonalController::class,'usuarioCedulaFicha']);


    //Crear ficha
    Route::apiResource('ficha-obra',FichaObraController::class);
});
