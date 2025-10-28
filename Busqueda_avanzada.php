<?php
<<<<<<< Updated upstream
// Busqueda_avanzada.php
// Búsqueda rápida/avanzada de pacientes con filtros por Nombre o ID, resultados paginados y consultas optimizadas.
// Requisitos cubiertos:
// - Barra de búsqueda con filtro por nombre o ID
// - Optimización de consultas: índices recomendados, prepared statements, COUNT separado, SELECT columnas específicas
// - Paginación eficiente con límites y offsets, controles de navegación
// - Resultados organizados y paginados
// - Modo de prueba de rendimiento simulando dataset amplio (opcional)
=======

>>>>>>> Stashed changes

require_once __DIR__ . '/db_connection.php';

// Configuración
$ITEMS_POR_PAGINA_DEFAULT = 10; // puedes ajustar por defecto
$ITEMS_POR_PAGINA_MAX = 100;    // para evitar consultas muy pesadas

// Sanitización y parámetros
$termino = isset($_GET['q']) ? trim($_GET['q']) : '';
<<<<<<< Updated upstream
$filtro  = isset($_GET['filtro']) ? $_GET['filtro'] : 'nombre'; // 'nombre' | 'id'
=======
$filtro  = isset($_GET['filtro']) ? $_GET['filtro'] : 'nombre'; // 'nombre' | 'dni'
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
$filtro = ($filtro === 'id') ? 'id' : 'nombre';
=======
$filtro = ($filtro === 'dni') ? 'dni' : 'nombre';
>>>>>>> Stashed changes

// Generar WHERE y parámetros
$where = '1=1';
$params = [];
$types  = '';

if ($termino !== '') {
<<<<<<< Updated upstream
    if ($filtro === 'id') {
        // Buscar por ID exacto o por coincidencia numérica
        // Solo permitir dígitos para evitar cast innecesario
        if (!ctype_digit($termino)) {
            $errores[] = 'Para buscar por ID, ingresa solo números.';
        } else {
            $where .= ' AND p.id = ?';
            $params[] = (int)$termino;
            $types   .= 'i';
        }
    } else {
        // Búsqueda por nombre con LIKE prefix para usar índice si es posible
        // Normalizar término para evitar leading wildcards
        $where .= ' AND p.nombre LIKE ?';
=======
    if ($filtro === 'dni') {
        // Búsqueda por DNI (prefijo para usabilidad)
        $where .= ' AND p.dni LIKE ?';
        $params[] = $termino . '%';
        $types   .= 's';
    } else {
        // Búsqueda por nombre completo con LIKE (prefijo)
        $where .= ' AND p.nombre_completo LIKE ?';
>>>>>>> Stashed changes
        $params[] = $termino . '%';
        $types   .= 's';
    }
}

// Campos específicos a seleccionar para evitar SELECT *
<<<<<<< Updated upstream
$selectCampos = 'p.id, p.nombre, p.apellido, p.fecha_nacimiento, p.telefono, p.email, p.estado';
=======
$selectCampos = 'p.id_pacientes, p.nombre_completo, p.dni, p.fecha_nacimiento, p.edad, p.telefono, p.estado';
>>>>>>> Stashed changes

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
<<<<<<< Updated upstream
    $sql = "SELECT $selectCampos FROM pacientes p WHERE $where ORDER BY p.nombre ASC, p.apellido ASC LIMIT ? OFFSET ?";
=======
    $sql = "SELECT $selectCampos FROM pacientes p WHERE $where ORDER BY p.id_pacientes ASC LIMIT ? OFFSET ?";
>>>>>>> Stashed changes
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
    <title>Búsqueda Avanzada de Pacientes</title>
    <link rel="stylesheet" href="assets/css/estilos.css" />
    <link rel="stylesheet" href="assets/css/estilo.css" />
    <style>
        /* Estilos mínimos y ajustes solicitados */
<<<<<<< Updated upstream
        html, body { height: 100%; }
        body { display: flex; align-items: center; justify-content: center; }
=======
        html, body { height: 100%; background: #ffffff; }
        body { display: flex; align-items: center; justify-content: center; background: #ffffff; }
>>>>>>> Stashed changes
        .busqueda-container { max-width: 900px; width: 90%; margin: 20px auto; background: #e6f2ff; padding: 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(2,33,88,0.12); }
        .busqueda-container.centrado { margin: 0 auto; }
        .filtros { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center; }
        .filtros input[type="text"] { flex: 1 1 360px; padding: 10px; }
        .filtros select, .filtros input[type="number"] { padding: 10px; }
        .tabla-resultados { width: 100%; border-collapse: collapse; margin-top: 16px; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .tabla-resultados th, .tabla-resultados td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        .tabla-resultados th { background: #dbeafe; }
        .paginacion { display: flex; gap: 8px; justify-content: center; align-items: center; margin-top: 16px; flex-wrap: wrap; }
        .paginacion a, .paginacion span { padding: 6px 10px; border: 1px solid #bfdbfe; border-radius: 6px; text-decoration: none; color: #0f172a; background: #ffffff; }
        .paginacion .activo { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        .errores { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-top: 12px; }
        .resumen { color: #374151; margin-top: 8px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; background: #e5e7eb; }
        .badge.activo { background: #dcfce7; color: #166534; }
        .badge.inactivo { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="busqueda-container centrado">
<<<<<<< Updated upstream
        <h1>Búsqueda rápida de pacientes</h1>

        <form method="get" class="filtros" action="">
            <input type="text" name="q" placeholder="Buscar por nombre o ID" value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>" />
            <select name="filtro">
                <option value="nombre" <?= $filtro === 'nombre' ? 'selected' : '' ?>>Por nombre</option>
                <option value="id" <?= $filtro === 'id' ? 'selected' : '' ?>>Por ID</option>
=======
        <div style="position: relative; margin-bottom: 16px;">
            <a href="Menuprincipal.php" style="position: absolute; top: 0; right: 0; display: inline-block; padding: 6px 12px; background: #3b82f6; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 0.875rem; transition: background 0.2s;">Menu Principal</a>
        </div>
        <h1 style="margin:0;">Búsqueda rápida de pacientes</h1>

        <form method="get" class="filtros" action="">
            <input id="q" type="text" name="q" placeholder="Buscar por nombre o DNI" value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>" />
            <select id="filtro" name="filtro">
                <option value="nombre" <?= $filtro === 'nombre' ? 'selected' : '' ?>>Por nombre</option>
                <option value="dni" <?= $filtro === 'dni' ? 'selected' : '' ?>>Por DNI</option>
>>>>>>> Stashed changes
            </select>
            <label>Por página
                <input type="number" name="perPage" min="1" max="<?= (int)$ITEMS_POR_PAGINA_MAX ?>" value="<?= (int)$perPage ?>" style="width:80px" />
            </label>
            <button type="submit">Buscar</button>
            <label style="margin-left:auto;">
                <input type="checkbox" name="demo" value="1" <?= $modoDemo ? 'checked' : '' ?> onchange="this.form.submit()" /> Demo rendimiento
            </label>
        </form>
<<<<<<< Updated upstream
=======
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
>>>>>>> Stashed changes

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $e): ?>
                    <div>- <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="resumen">
            <?= $totalRegistros ?> resultado(s). Página <?= (int)$page ?> de <?= max(1, (int)$totalPaginas) ?>.
        </div>

        <?php if (!empty($resultados)): ?>
            <table class="tabla-resultados">
                <thead>
                    <tr>
                        <th>ID</th>
<<<<<<< Updated upstream
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Fecha Nacimiento</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Estado</th>
=======
                        <th>Nombre completo</th>
                        <th>DNI</th>
                        <th>Fecha Nacimiento</th>
                        <th>Edad</th>
                        <th>Teléfono</th>
>>>>>>> Stashed changes
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $pac): ?>
                        <tr>
<<<<<<< Updated upstream
                            <td><?= (int)$pac['id'] ?></td>
                            <td><?= htmlspecialchars($pac['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($pac['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($pac['fecha_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($pac['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($pac['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                $estado = strtolower((string)($pac['estado'] ?? ''));
                                $clase = ($estado === 'activo') ? 'badge activo' : 'badge inactivo';
                                ?>
                                <span class="<?= $clase ?>"><?= htmlspecialchars($pac['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <a href="Registropacientes.php?id=<?= (int)$pac['id'] ?>">Ver</a>
=======
                            <td><?= (int)$pac['id_pacientes'] ?></td>
                            <td><?= htmlspecialchars($pac['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($pac['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($pac['fecha_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($pac['edad'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($pac['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= strtolower($pac['estado'] ?? 'inactivo') ?>"><?= htmlspecialchars($pac['estado'] ?? 'Inactivo', ENT_QUOTES, 'UTF-8') ?></span>
>>>>>>> Stashed changes
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($termino !== '' && empty($errores)): ?>
            <p>No se encontraron resultados.</p>
        <?php else: ?>
            <p>Ingresa un término de búsqueda para comenzar.</p>
        <?php endif; ?>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
            <div class="paginacion">
                <?php
                $prevPage = max(1, $page - 1);
                $nextPage = min($totalPaginas, $page + 1);
                ?>
                <a href="?<?= qs(['page' => 1]) ?>" title="Primera">« Primera</a>
                <a href="?<?= qs(['page' => $prevPage]) ?>" title="Anterior">‹ Anterior</a>
                <?php
                // Ventana de páginas
                $window = 3;
                $start = max(1, $page - $window);
                $end   = min($totalPaginas, $page + $window);
                for ($i = $start; $i <= $end; $i++):
                    $isActive = ($i === $page);
                ?>
                    <?php if ($isActive): ?>
                        <span class="activo"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= qs(['page' => $i]) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <a href="?<?= qs(['page' => $nextPage]) ?>" title="Siguiente">Siguiente ›</a>
                <a href="?<?= qs(['page' => $totalPaginas]) ?>" title="Última">Última »</a>
            </div>
        <?php endif; ?>

            </div>
</body>
<<<<<<< Updated upstream
</html>
=======
</html>
>>>>>>> Stashed changes
