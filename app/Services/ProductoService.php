<?php
/**
 * app/Services/ProductoService.php
 *
 * Lógica de negocio de Productos.
 *
 * Responsabilidades:
 *   - Validación de datos antes de guardar
 *   - Conversión piezas ↔ cajas (REGLA CRÍTICA)
 *   - Generación y formato de código único
 *   - Preparar datos para la vista
 */

class ProductoService
{
    private ProductoModel   $productoModel;
    private CategoriaModel  $categoriaModel;

    public function __construct()
    {
        $this->productoModel  = new ProductoModel();
        $this->categoriaModel = new CategoriaModel();
    }

    // ----------------------------------------------------------------
    // DATOS PARA VISTAS
    // ----------------------------------------------------------------

    public function getListaPaginada(array $filtros, int $pagina, int $porPagina): array
    {
        $resultado = $this->productoModel->getPaginados(
            $filtros['busqueda']    ?? '',
            (int) ($filtros['categoria_id'] ?? 0),
            $filtros['estado_stock'] ?? '',
            $pagina,
            $porPagina
        );

        $resultado['items'] = array_map(
            fn(array $p) => $this->formatearParaLista($p),
            $resultado['items']
        );

        return $resultado;
    }

    public function getDetalle(int $id): array|false
    {
        $producto = $this->productoModel->findById($id);
        if (!$producto) return false;
        return $this->formatearDetalle($producto);
    }

    public function getCategorias(): array
    {
        return $this->categoriaModel->getParaSelect();
    }

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    /**
     * Valida y crea un producto.
     * Retorna ['ok' => true, 'id' => N] o ['ok' => false, 'errors' => [...]]
     */
    public function crear(array $input): array
    {
        $validado = $this->validar($input);
        if (!empty($validado['errors'])) {
            return ['ok' => false, 'errors' => $validado['errors']];
        }

        // Verificar código único
        if ($this->productoModel->findByCodigo($validado['codigo'])) {
            return ['ok' => false, 'errors' => [
                'codigo' => 'Ya existe un producto con ese código.',
            ]];
        }

        $validado['creado_por'] = $_SESSION['usuario_id'];

        try {
            $id = $this->productoModel->create($validado);
            AuditoriaService::log('productos', 'crear', $id,
                "Producto creado: {$validado['nombre']} ({$validado['codigo']})"
            );
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            Logger::error('PRODUCTOS', 'Error al crear producto: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'Error interno al guardar.']];
        }
    }

    /**
     * Valida y actualiza un producto.
     */
    public function actualizar(int $id, array $input): array
    {
        $producto = $this->productoModel->findById($id);
        if (!$producto) {
            return ['ok' => false, 'errors' => ['general' => 'Producto no encontrado.']];
        }

        $validado = $this->validar($input, $id);
        if (!empty($validado['errors'])) {
            return ['ok' => false, 'errors' => $validado['errors']];
        }

        if ($this->productoModel->findByCodigo($validado['codigo'], $id)) {
            return ['ok' => false, 'errors' => [
                'codigo' => 'Ya existe otro producto con ese código.',
            ]];
        }

        $this->productoModel->update($id, $validado);

        AuditoriaService::log('productos', 'editar', $id,
            "Producto editado: {$validado['nombre']}",
            $producto,
            $validado
        );

        return ['ok' => true];
    }

    /**
     * Elimina un producto si no tiene movimientos de stock.
     */
    public function eliminar(int $id): array
    {
        $producto = $this->productoModel->findById($id);
        if (!$producto) {
            return ['ok' => false, 'message' => 'Producto no encontrado.'];
        }

        if ($this->productoModel->tieneMov($id)) {
            return ['ok' => false, 'message' =>
                'No se puede eliminar: el producto tiene movimientos de inventario registrados. ' .
                'Desactívalo en su lugar.'
            ];
        }

        $this->productoModel->softDelete($id);

        AuditoriaService::log('productos', 'eliminar', $id,
            "Producto eliminado: {$producto['nombre']} ({$producto['codigo']})"
        );

        return ['ok' => true];
    }

    // ----------------------------------------------------------------
    // CONVERSIÓN PIEZAS ↔ CAJAS (REGLA CRÍTICA)
    // ----------------------------------------------------------------

    /**
     * Convierte una cantidad en PIEZAS BASE a presentación visual legible.
     *
     * Ejemplos:
     *   250 piezas, 50 x caja  → "4 cajas + 45 piezas"
     *   50  piezas, 50 x caja  → "1 caja"
     *   10  piezas, 1  x caja  → "10 piezas"
     *    0  piezas, 50 x caja  → "Sin stock"
     */
    public static function piezasATexto(
        int    $piezas,
        int    $unidadesPorCaja,
        string $unidadMedida = 'pieza'
    ): string {
        if ($piezas === 0) return 'Sin stock';
        if ($unidadesPorCaja <= 1) {
            return number_format($piezas) . ' ' . $unidadMedida . ($piezas > 1 ? 's' : '');
        }

        $cajas   = intdiv($piezas, $unidadesPorCaja);
        $resto   = $piezas % $unidadesPorCaja;
        $partes  = [];

        if ($cajas > 0) {
            $partes[] = $cajas . ' caja' . ($cajas > 1 ? 's' : '');
        }
        if ($resto > 0) {
            $partes[] = $resto . ' ' . $unidadMedida . ($resto > 1 ? 's' : '');
        }

        return implode(' + ', $partes);
    }

    /**
     * Convierte cajas + piezas sueltas a PIEZAS BASE.
     * Usado al registrar entradas desde el formulario.
     *
     * Ejemplo: 3 cajas + 5 piezas × 50/caja = 155 piezas
     */
    public static function cajasAPiezas(
        int $cajas,
        int $piezasSueltas,
        int $unidadesPorCaja
    ): int {
        return ($cajas * $unidadesPorCaja) + $piezasSueltas;
    }

    // ----------------------------------------------------------------
    // VALIDACIÓN
    // ----------------------------------------------------------------

    private function validar(array $input, ?int $excludeId = null): array
    {
        $errores = [];

        $codigo          = strtoupper(trim($input['codigo'] ?? ''));
        $nombre          = Security::sanitize($input['nombre'] ?? '');
        $descripcion     = Security::sanitize($input['descripcion'] ?? '');
        $categoriaId     = (int) ($input['categoria_id'] ?? 0);
        $unidadMedida    = Security::sanitize($input['unidad_medida'] ?? 'pieza');
        $presentacion    = Security::sanitize($input['presentacion'] ?? '');
        $unidadesPorCaja = max(1, (int) ($input['unidades_por_caja'] ?? 1));
        $precioUnitario  = (float) str_replace(',', '', $input['precio_unitario'] ?? '0');
        $stockMinimo     = max(0, (int) ($input['stock_minimo'] ?? 0));
        $activo          = (int) ($input['activo'] ?? 1);

        // Validaciones
        if (strlen($codigo) < 2)  $errores['codigo']  = 'El código es obligatorio (mín. 2 caracteres).';
        if (strlen($codigo) > 40) $errores['codigo']  = 'El código no puede superar 40 caracteres.';
        if (!preg_match('/^[A-Z0-9\-_\.]+$/i', $codigo)) {
            $errores['codigo'] = 'El código solo puede contener letras, números y guiones.';
        }
        if (strlen($nombre) < 2)  $errores['nombre']  = 'El nombre es obligatorio (mín. 2 caracteres).';
        if (strlen($nombre) > 200) $errores['nombre'] = 'El nombre no puede superar 200 caracteres.';
        if ($categoriaId <= 0)    $errores['categoria_id'] = 'Selecciona una categoría.';
        if ($precioUnitario < 0)  $errores['precio_unitario'] = 'El precio no puede ser negativo.';
        if ($unidadesPorCaja < 1) $errores['unidades_por_caja'] = 'El factor debe ser al menos 1.';

        return [
            'codigo'            => $codigo,
            'nombre'            => $nombre,
            'descripcion'       => $descripcion ?: null,
            'categoria_id'      => $categoriaId,
            'unidad_medida'     => $unidadMedida ?: 'pieza',
            'presentacion'      => $presentacion ?: null,
            'unidades_por_caja' => $unidadesPorCaja,
            'precio_unitario'   => $precioUnitario,
            'stock_minimo'      => $stockMinimo,
            'activo'            => $activo,
            'errors'            => $errores,
        ];
    }

    // ----------------------------------------------------------------
    // FORMATEO PARA VISTAS
    // ----------------------------------------------------------------

    private function formatearParaLista(array $p): array
    {
        return array_merge($p, [
            'stock_texto'  => self::piezasATexto(
                (int) $p['cantidad_piezas'],
                (int) $p['unidades_por_caja'],
                $p['unidad_medida']
            ),
            'precio_fmt'   => '$' . number_format((float) $p['precio_unitario'], 2),
            'badge_stock'  => match ($p['estado_stock']) {
                'sin_stock' => ['clase' => 'badge-danger',  'label' => 'Sin stock'],
                'critico'   => ['clase' => 'badge-danger',  'label' => 'Crítico'],
                'bajo'      => ['clase' => 'badge-warning', 'label' => 'Bajo'],
                default     => ['clase' => 'badge-success', 'label' => 'OK'],
            },
        ]);
    }

    private function formatearDetalle(array $p): array
    {
        return array_merge($this->formatearParaLista($p), [
            'stock_minimo_texto' => self::piezasATexto(
                (int) $p['stock_minimo'],
                (int) $p['unidades_por_caja'],
                $p['unidad_medida']
            ),
        ]);
    }
}
