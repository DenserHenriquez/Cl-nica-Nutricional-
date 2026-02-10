<?php
// Busqueda_avanzada.php
// Búsqueda rápida/avanzada de pacientes con filtros por Nombre o ID, resultados paginados y consultas optimizadas.
// Requisitos cubiertos:
// - Barra de búsqueda con filtro por nombre o ID
// - Optimización de consultas: índices recomendados, prepared statements, COUNT separado, SELECT columnas específicas
// - Paginación eficiente con límites y offsets, controles de navegación
// - Resultados organizados y paginados
// - Modo de prueba de rendimiento simulando dataset amplio (opcional)

require_once __DIR__ . '/db_connection.php';

// Configuración
$ITEMS_POR_PAGINA_DEFAULT = 10; // puedes ajustar por defecto
$ITEMS_POR_PAGINA_MAX = 100;    // para evitar consultas muy pesadas

// Sanitización y parámetros
$termino = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro  = isset($_GET['filtro']) ? $_GET['filtro'] : 'nombre'; // 'nombre' | 'dni'
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : $ITEMS_POR_PAGINA_DEFAULT;
$perPage = $perPage > 0 ? min($perPage, $ITEMS_POR_PAGINA_MAX) : $ITEMS_POR_PAGINA_DEFAULT;

// Modo demo rendimiento: permite emular alta paginación con más filas por página
$modoDemo = isset($_GET['demo']) && $_GET['demo'] === '1';
if ($modoDemo) {
    $perPage = min(50, $ITEMS_POR_PAGINA_MAX); // aumentar un poco para probar
}

// Construcción de consulta de búsqueda
// Asumimos una tabla de pacientes; intenta ajustar los nombres de columnas si difieren de tu esquema.
// Índices recomendados en BD:
// - CREATE INDEX idx_pacientes_nombre ON pacientes (nombre);
// - Si buscas por prefijo, considera un índice FULLTEXT para búsquedas más complejas o un índice sobre (nombre)
// - CREATE INDEX idx_pacientes_id ON pacientes (id);

$errores = [];
$resultados = [];
$totalRegistros = 0;
$totalPaginas = 0;
$offset = ($page - 1) * $perPage;

// Normalizar filtro
$filtro = ($filtro === 'dni') ? 'dni' : 'nombre';

// Generar WHERE y parámetros
$where = '1=1';
$params = [];
$types  = '';

if ($termino !== '') {
    if ($filtro === 'dni') {
        // Búsqueda por DNI (prefijo para usabilidad)
        $where .= ' AND p.dni LIKE ?';
        $params[] = $termino . '%';
        $types   .= 's';
    } else {
        // Búsqueda por nombre completo con LIKE (prefijo)
        $where .= ' AND p.nombre_completo LIKE ?';
        $params[] = $termino . '%';
        $types   .= 's';
    }
}

// Campos específicos a seleccionar para evitar SELECT *
$selectCampos = 'p.id_pacientes, p.nombre_completo, p.dni, p.fecha_nacimiento, p.edad, p.telefono, p.estado';

// 1) Consulta de conteo total (para paginación)
$sqlCount = "SELECT COUNT(*) AS total FROM pacientes p WHERE $where";
$stmtCount = $conexion->prepare($sqlCount);
if ($stmtCount === false) {
    $errores[] = 'Error preparando consulta COUNT: ' . $conexion->error;
} else {
    if ($types !== '') {
        $stmtCount->bind_param($types, ...$params);
    }
    if ($stmtCount->execute()) {
        $resCount = $stmtCount->get_result();
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRegistros = (int)($row['total'] ?? 0);
        }
    } else {
        $errores[] = 'Error ejecutando COUNT: ' . $stmtCount->error;
    }
    $stmtCount->close();
}

if ($totalRegistros > 0) {
    $totalPaginas = (int)ceil($totalRegistros / $perPage);
    if ($page > $totalPaginas) {
        $page = $totalPaginas;
        $offset = ($page - 1) * $perPage;
    }

    // 2) Consulta de resultados paginados
    $sql = "SELECT $selectCampos FROM pacientes p WHERE $where ORDER BY p.id_pacientes ASC LIMIT ? OFFSET ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        $errores[] = 'Error preparando consulta de resultados: ' . $conexion->error;
    } else {
        // Construir parámetros incluyendo LIMIT y OFFSET
        $typesWithLimit = $types . 'ii';
        $paramsWithLimit = $params;
        $paramsWithLimit[] = $perPage;
        $paramsWithLimit[] = $offset;

        $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($fila = $res->fetch_assoc()) {
                $resultados[] = $fila;
            }
        } else {
            $errores[] = 'Error ejecutando consulta de resultados: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Función utilitaria para construir querystring preservando parámetros
function qs(array $data): string {
    return http_build_query(array_merge([
        'q' => isset($_GET['q']) ? $_GET['q'] : '',
        'filtro' => isset($_GET['filtro']) ? $_GET['filtro'] : 'nombre',
        'perPage' => isset($_GET['perPage']) ? $_GET['perPage'] : 10,
    ], $data));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Búsqueda de Pacientes</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .btn-primary {
            background-color: #198754;
            border-color: #198754;
        }
        .btn-primary:hover {
            background-color: #146c43;
            border-color: #13653f;
        }
        .bg-primary {
            background-color: #198754 !important;
        }
        .form-label {
            font-weight: 600;
            color: #198754;
        }
        .alert {
            border-radius: 0.375rem;
        }
        .header-section {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            /* Reduced height: 60% smaller than original 2rem padding */
            padding: 0.8rem 0;
            margin-bottom: 1rem;
        }
        .header-section h1 {
            /* Slightly smaller than original but clear and readable */
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0.15rem 0 0.25rem;
        }
        .header-section p {
            font-size: 1.05rem;
            opacity: 0.95;
            margin: 0;
        }
        .medical-icon {
            font-size: 1.9rem;
            margin-bottom: 0.35rem;
            color: #ffffff;
        }
        .busqueda-container {
            max-width: 1100px;
            margin: 20px auto;
            background: #fff;
            padding: 16px;
            border-radius: 8px;
        }
        .filtros {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filtros input[type="text"] {
            flex: 1 1 320px;
            padding: 8px;
        }
        .filtros select, .filtros input[type="number"] {
            padding: 8px;
        }
        .tabla-resultados {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .tabla-resultados th, .tabla-resultados td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
        }
        .tabla-resultados th {
            background: #f3f4f6;
        }
        .paginacion {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .paginacion a, .paginacion span {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-decoration: none;
            color: #111827;
        }
        .paginacion .activo {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
        }
        .errores {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 6px;
            margin-top: 12px;
        }
        .resumen {
            color: #374151;
            margin-top: 8px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            background: #e5e7eb;
        }
        .badge.activo {
            background: #dcfce7;
            color: #166534;
        }
        .badge.inactivo {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-search"></i>
            </div>
            <h1>Búsqueda de Pacientes</h1>
            <p>Busca y filtra pacientes registrados en la clínica nutricional.</p>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errores as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-funnel me-2"></i>Filtros de Búsqueda</h5>
            </div>
            <div class="card-body">
                <form method="get" class="filtros" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="q" class="form-label">
                                <i class="bi bi-search me-1"></i>Término de búsqueda
                            </label>
                            <input id="q" type="text" name="q" class="form-control" placeholder="Buscar por nombre o DNI" value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>" />
                        </div>
                        <div class="col-md-2">
                            <label for="filtro" class="form-label">
                                <i class="bi bi-filter me-1"></i>Filtro
                            </label>
                            <select id="filtro" name="filtro" class="form-select">
                                <option value="nombre" <?= $filtro === 'nombre' ? 'selected' : '' ?>>Por nombre</option>
                                <option value="dni" <?= $filtro === 'dni' ? 'selected' : '' ?>>Por DNI</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="perPage" class="form-label">
                                <i class="bi bi-list me-1"></i>Por página
                            </label>
                            <input type="number" name="perPage" class="form-control" min="1" max="<?= (int)$ITEMS_POR_PAGINA_MAX ?>" value="<?= (int)$perPage ?>" />
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Buscar
                            </button>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check">
                                <input type="checkbox" name="demo" value="1" class="form-check-input" id="demo" <?= $modoDemo ? 'checked' : '' ?> onchange="this.form.submit()" />
                                <label class="form-check-label" for="demo">Demo rendimiento</label>
                            </div>
                        </div>
                    </div>
                </form>
                <script>
                    (function() {
                        const q = document.getElementById('q');
                        const filtro = document.getElementById('filtro');

                        function setRules() {
                            if (filtro.value === 'dni') {
                                // Solo números
                                q.setAttribute('inputmode', 'numeric');
                                q.setAttribute('pattern', '\\d+');
                                q.placeholder = 'Buscar por DNI (solo números)';
                            } else {
                                // Solo letras y espacios (incluye acentos y ñ)
                                q.setAttribute('inputmode', 'text');
                                q.setAttribute('pattern', '[A-Za-zÁÉÍÓÚáéíóúÑñ ]+');
                                q.placeholder = 'Buscar por nombre (solo letras)';
                            }
                        }

                        function keyFilter(e) {
                            if (filtro.value === 'dni') {
                                // Permitir dígitos, navegación, borrar
                                const allowed = /[0-9]/;
                                const controlKeys = ['Backspace','Delete','ArrowLeft','ArrowRight','Home','End','Tab'];
                                if (controlKeys.includes(e.key) || (e.ctrlKey || e.metaKey)) return;
                                if (!allowed.test(e.key)) e.preventDefault();
                            } else {
                                // Permitir letras, espacio y acentos comunes
                                const allowed = /[A-Za-zÁÉÍÓÚáéíóúÑñ ]/;
                                const controlKeys = ['Backspace','Delete','ArrowLeft','ArrowRight','Home','End','Tab'];
                                if (controlKeys.includes(e.key) || (e.ctrlKey || e.metaKey)) return;
                                if (!allowed.test(e.key)) e.preventDefault();
                            }
                        }

                        filtro.addEventListener('change', setRules);
                        q.addEventListener('keydown', keyFilter);
                        // Inicializar con el valor actual
                        setRules();
                    })();
                </script>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-table me-2"></i>Resultados de Búsqueda</h5>
            </div>
            <div class="card-body">
                <div class="resumen mb-3">
                    <strong><?= $totalRegistros ?> resultado(s). Página <?= (int)$page ?> de <?= max(1, (int)$totalPaginas) ?>.</strong>
                </div>

                <?php if (!empty($resultados)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped tabla-resultados enhance-table external-filter" data-external-filter="#q">
                            <thead class="table-light" style="color: #000000;">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre completo</th>
                                    <th>DNI</th>
                                    <th>Fecha Nacimiento</th>
                                    <th>Edad</th>
                                    <th>Teléfono</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $pac): ?>
                                    <tr>
                                        <td><?= (int)$pac['id_pacientes'] ?></td>
                                        <td><?= htmlspecialchars($pac['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($pac['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($pac['fecha_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($pac['edad'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($pac['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge <?= strtolower($pac['estado'] ?? '') ?>"><?= htmlspecialchars($pac['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($termino !== '' && empty($errores)): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>No se encontraron resultados para el término de búsqueda.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>Ingresa un término de búsqueda para comenzar.
                    </div>
                <?php endif; ?>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de resultados">
                        <ul class="pagination justify-content-center mt-4">
                            <?php
                            $prevPage = max(1, $page - 1);
                            $nextPage = min($totalPaginas, $page + 1);
                            $window = 3;
                            $start = max(1, $page - $window);
                            $end = min($totalPaginas, $page + $window);
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= qs(['page' => 1]) ?>" title="Primera">« Primera</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= qs(['page' => $prevPage]) ?>" title="Anterior">‹ Anterior</a>
                            </li>
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <?php if ($i === $page): ?>
                                        <span class="page-link"><?= $i ?></span>
                                    <?php else: ?>
                                        <a class="page-link" href="?<?= qs(['page' => $i]) ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= qs(['page' => $nextPage]) ?>" title="Siguiente">Siguiente ›</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?= qs(['page' => $totalPaginas]) ?>" title="Última">Última »</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="assets/js/script.js"></script>
</body>
</html>