<?php
/**
 * routes/web.php
 * 
 * Definición de todas las rutas del sistema.
 * 
 * Formato: $router->METHOD('uri', 'Controller@method', [Middleware])
 * 
 * Rutas protegidas llevan: [AuthMiddleware::class]
 * Rutas de admin llevan:   [AuthMiddleware::class, AdminMiddleware::class]
 */

// ============================================================
// AUTENTICACIÓN (públicas)
// ============================================================
$router->get('/auth/login',                   'AuthController@loginForm');
$router->get('/auth/google',                  'AuthController@googleRedirect');
$router->get('/auth/google/callback',         'AuthController@googleCallback');
$router->post('/auth/logout',                 'AuthController@logout');

// ============================================================
// DASHBOARD
// ============================================================
$router->get('/',                             'DashboardController@index',        [AuthMiddleware::class]);
$router->get('/dashboard',                    'DashboardController@index',        [AuthMiddleware::class]);
$router->get('/dashboard/stats',              'DashboardController@stats',        [AuthMiddleware::class]);

// ============================================================
// CATEGORÍAS
// ============================================================
$router->get('/categorias',                   'CategoriaController@index',        [AuthMiddleware::class]);
$router->post('/categorias',                  'CategoriaController@store',        [AuthMiddleware::class]);
$router->post('/categorias/{id}/editar',      'CategoriaController@update',       [AuthMiddleware::class]);
$router->post('/categorias/{id}/eliminar',    'CategoriaController@destroy',      [AuthMiddleware::class]);

// ============================================================
// PRODUCTOS
// ============================================================
$router->get('/productos',                    'ProductoController@index',         [AuthMiddleware::class]);
$router->get('/productos/{id}',               'ProductoController@show',          [AuthMiddleware::class]);
$router->post('/productos',                   'ProductoController@store',         [AuthMiddleware::class]);
$router->post('/productos/{id}/editar',       'ProductoController@update',        [AuthMiddleware::class]);
$router->post('/productos/{id}/eliminar',     'ProductoController@destroy',       [AuthMiddleware::class]);
$router->get('/productos/{id}/qr',            'ProductoController@generarQr',     [AuthMiddleware::class]);

// ============================================================
// INVENTARIO (Entradas y Salidas)
// ============================================================
$router->get('/inventario',                   'InventarioController@index',       [AuthMiddleware::class]);
$router->get('/inventario/entradas',          'InventarioController@entradas',    [AuthMiddleware::class]);
$router->post('/inventario/entradas',         'InventarioController@registrarEntrada', [AuthMiddleware::class]);
$router->get('/inventario/salidas',           'InventarioController@salidas',     [AuthMiddleware::class]);
$router->post('/inventario/salidas',          'InventarioController@registrarSalida',  [AuthMiddleware::class]);

// ============================================================
// PEDIDOS
// ============================================================
$router->get('/pedidos',                      'PedidoController@index',           [AuthMiddleware::class]);
$router->get('/pedidos/nuevo',                'PedidoController@create',          [AuthMiddleware::class]);
$router->post('/pedidos',                     'PedidoController@store',           [AuthMiddleware::class]);
$router->get('/pedidos/{id}',                 'PedidoController@show',            [AuthMiddleware::class]);
$router->post('/pedidos/{id}/entregar',       'PedidoController@entregar',        [AuthMiddleware::class]);
$router->post('/pedidos/{id}/cancelar',       'PedidoController@cancelar',        [AuthMiddleware::class]);

// ============================================================
// REQUISICIONES
// ============================================================
$router->get('/requisiciones',                'RequisicionController@index',      [AuthMiddleware::class]);
$router->get('/requisiciones/nueva',          'RequisicionController@create',     [AuthMiddleware::class]);
$router->post('/requisiciones',               'RequisicionController@store',      [AuthMiddleware::class]);
$router->get('/requisiciones/{id}',           'RequisicionController@show',       [AuthMiddleware::class]);
$router->get('/requisiciones/{id}/pdf',       'RequisicionController@exportPdf',  [AuthMiddleware::class]);
$router->get('/requisiciones/{id}/excel',     'RequisicionController@exportExcel',[AuthMiddleware::class]);

// ============================================================
// VALES
// ============================================================
$router->get('/vales',                        'ValeController@index',             [AuthMiddleware::class]);
$router->get('/vales/resguardo/nuevo',        'ValeController@createResguardo',   [AuthMiddleware::class]);
$router->post('/vales/resguardo',             'ValeController@storeResguardo',    [AuthMiddleware::class]);
$router->get('/vales/salida/nuevo',           'ValeController@createSalida',      [AuthMiddleware::class]);
$router->post('/vales/salida',                'ValeController@storeSalida',       [AuthMiddleware::class]);
$router->get('/vales/{id}/pdf',               'ValeController@exportPdf',         [AuthMiddleware::class]);
$router->post('/vales/{id}/enviar',           'ValeController@enviar',            [AuthMiddleware::class]);
$router->post('/vales/{id}/cancelar',         'ValeController@cancelarVale',      [AuthMiddleware::class]);

// ============================================================
// REPORTES
// ============================================================
$router->get('/reportes',                     'ReporteController@index',          [AuthMiddleware::class]);
$router->get('/reportes/inventario',          'ReporteController@inventario',     [AuthMiddleware::class]);
$router->get('/reportes/movimientos',         'ReporteController@movimientos',    [AuthMiddleware::class]);
$router->get('/reportes/requisiciones',       'ReporteController@requisiciones',  [AuthMiddleware::class]);
$router->get('/reportes/auditoria',           'ReporteController@auditoria',      [AuthMiddleware::class]);
$router->post('/reportes/exportar',           'ReporteController@exportar',       [AuthMiddleware::class]);

// ============================================================
// USUARIOS (solo Admin)
// ============================================================
$router->get('/usuarios',                     'UsuarioController@index',          [AuthMiddleware::class]);
$router->post('/usuarios/{id}/rol',           'UsuarioController@updateRol',      [AuthMiddleware::class]);
$router->post('/usuarios/{id}/estado',        'UsuarioController@toggleEstado',   [AuthMiddleware::class]);

// ============================================================
// CONFIGURACIÓN (solo Admin)
// ============================================================
$router->get('/configuracion',                'ConfiguracionController@index',    [AuthMiddleware::class]);
$router->post('/configuracion',               'ConfiguracionController@update',   [AuthMiddleware::class]);
