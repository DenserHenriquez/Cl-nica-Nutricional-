<?php
session_start();
require_once __DIR__ . '/db_connection.php';

// Este archivo implementa la actualización del perfil del usuario/paciente.
// Asume que al autenticarse se guarda en $_SESSION['user_id'] el id del usuario
// y $_SESSION['rol'] con el rol del usuario (opcional). Ajusta los nombres de
// columnas/tabla conforme a tu esquema real.

// CONFIGURACIÓN (ajusta según tus tablas)
$TABLE_USERS = 'usuarios';
$FIELDS_SELECT_USERS = 'id_usuarios, Nombre_completo, Correo_electronico, Usuario, Contrasena';
$TABLE_PATIENTS = 'pacientes';
$FIELDS_SELECT_PATIENTS = 'id_pacientes, id_usuarios, nombre_completo, DNI, fecha_nacimiento, edad, telefono';
// Tabla de historial de actualizaciones (crear en BD si no existe)
$TABLE_HISTORY = 'historial_actualizaciones';

// Crear carpeta para subir fotos si no existe
$uploadDir = __DIR__ . '/assets/images/perfiles';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

// Verificar sesión
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: Login.php');
    exit;
}

// En este proyecto, la tabla `usuarios` usa la clave primaria `id_usuarios`
$userId = isset($_SESSION['id_usuarios']) ? intval($_SESSION['id_usuarios']) : 0;
$errores = [];
$exito = '';

// Cargar datos actuales del usuario
function cargarUsuario(mysqli $conexion, string $tabla, int $id, string $campos)
{
    $stmt = $conexion->prepare("SELECT $campos FROM $tabla WHERE id_usuarios = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $dato = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $dato ?: null;
}

// Cargar datos actuales del paciente
function cargarPaciente(mysqli $conexion, string $tabla, int $id, string $campos)
{
    $stmt = $conexion->prepare("SELECT $campos FROM $tabla WHERE id_usuarios = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $dato = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $dato ?: null;
}

$usuario = cargarUsuario($conexion, $TABLE_USERS, $userId, $FIELDS_SELECT_USERS);
if (!$usuario) {
    $errores[] = 'No se pudo cargar la información del usuario.';
}

$paciente = cargarPaciente($conexion, $TABLE_PATIENTS, $userId, $FIELDS_SELECT_PATIENTS);
if (!$paciente) {
    $errores[] = 'No se pudo cargar la información del paciente.';
}

// Helper: generar cambios parciales comparando original vs nuevos
function diffs_campos(array $original, array $nuevos, array $permitidos): array {
    $cambios = [];
    foreach ($permitidos as $campo) {
        $orig = $original[$campo] ?? null;
        $nuevo = $nuevos[$campo] ?? null;
        // Normalizar null/strings vacíos
        if ($nuevo === '') { $nuevo = null; }
        if ($orig === '') { $orig = null; }
        if ($orig !== $nuevo) {
            $cambios[$campo] = [$orig, $nuevo];
        }
    }
    return $cambios;
}

// Helper: calcular edad basada en fecha de nacimiento
function calcularEdad(string $fechaNacimiento): int {
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return $hoy->diff($nacimiento)->y;
}

// Helper: insertar historial de cambios por campo
function registrar_historial(mysqli $conexion, string $tablaHist, int $idUsuarios, array $cambios, int $actorId): void {
    if (!$cambios) return;
    $sqlH = "INSERT INTO $tablaHist (id_usuarios, campo, valor_anterior, valor_nuevo, actualizado_por, fecha_actualizacion) VALUES (?,?,?,?,?,NOW())";
    $stmtH = $conexion->prepare($sqlH);
    if (!$stmtH) return;
    foreach ($cambios as $campo => [$anterior, $nuevo]) {
        $anteriorStr = isset($anterior) ? (string)$anterior : null;
        $nuevoStr = isset($nuevo) ? (string)$nuevo : null;
        $stmtH->bind_param('isssi', $idUsuarios, $campo, $anteriorStr, $nuevoStr, $actorId);
        $stmtH->execute();
    }
    $stmtH->close();
}

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitización básica para usuarios
    $correo = trim($_POST['Correo_electronico'] ?? '');
    $contrasena = trim($_POST['Contrasena'] ?? '');

    // Sanitización básica para pacientes
    $nombreCompletoPaciente = trim($_POST['nombre_completo'] ?? '');
    $dni = trim($_POST['DNI'] ?? '');
    $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $edad = trim($_POST['edad'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validaciones simples para usuarios
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo electrónico inválido';
    // La contraseña es opcional: solo se actualizará si el usuario envía un valor

    // Validaciones simples para pacientes
    if ($nombreCompletoPaciente === '') $errores[] = 'El nombre completo del paciente es obligatorio';
    if ($dni === '') $errores[] = 'El DNI es obligatorio';
    if ($fechaNacimiento === '') $errores[] = 'La fecha de nacimiento es obligatoria';
    if ($edad === '' || !is_numeric($edad)) $errores[] = 'La edad debe ser un número válido';
    if ($telefono === '') $errores[] = 'El teléfono es obligatorio';

    // Manejo de foto de perfil opcional
    // La tabla `usuarios` proporcionada no incluye campo de foto; omitimos manejo de imagen
    $fotoNombreFinal = null;
    $subioFoto = false;
    if (isset($_FILES['foto_perfil']) && is_uploaded_file($_FILES['foto_perfil']['tmp_name'])) {
        $file = $_FILES['foto_perfil'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($file['tmp_name']);
            $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if (!isset($permitidos[$mime])) {
                $errores[] = 'Formato de imagen no permitido. Use JPG, PNG, GIF o WEBP.';
            } else {
                $ext = $permitidos[$mime];
                $fotoNombreFinal = 'perfil_' . $userId . '_' . time() . '.' . $ext;
                $destino = $uploadDir . DIRECTORY_SEPARATOR . $fotoNombreFinal;
                if (!move_uploaded_file($file['tmp_name'], $destino)) {
                    $errores[] = 'No se pudo guardar la imagen subida.';
                } else {
                    $subioFoto = true;
                }
            }
        } else {
            $errores[] = 'Error al subir la imagen (código ' . $file['error'] . ').';
        }
    }

    // Construir payload nuevo para usuarios
    $permitidosUsuario = ['Correo_electronico'];
    $payloadNuevoUsuario = [
        'Correo_electronico' => $correo,
    ];
    // La contraseña se maneja aparte para poder hashearla y solo si viene informada
    if ($contrasena !== '') {
        $payloadNuevoUsuario['Contrasena'] = password_hash($contrasena, PASSWORD_DEFAULT);
        $permitidosUsuario[] = 'Contrasena';
    }

    // Construir payload nuevo para pacientes
    $edadCalculada = calcularEdad($fechaNacimiento);
    $payloadNuevoPaciente = [
        'nombre_completo' => $nombreCompletoPaciente,
        'DNI' => $dni,
        'fecha_nacimiento' => $fechaNacimiento,
        'edad' => $edadCalculada,
        'telefono' => $telefono,
    ];

    // Determinar campos modificados para usuarios
    $cambiosUsuario = diffs_campos($usuario ?? [], $payloadNuevoUsuario, $permitidosUsuario);

    // Determinar campos modificados para pacientes
    $permitidosPaciente = ['nombre_completo', 'DNI', 'fecha_nacimiento', 'edad', 'telefono'];
    $cambiosPaciente = diffs_campos($paciente ?? [], $payloadNuevoPaciente, $permitidosPaciente);
    if (!$subioFoto) {
        // Si no subió foto, no forzar cambio de foto si no cambió valor
        if (isset($cambios['foto_perfil']) && ($cambios['foto_perfil'][0] === $cambios['foto_perfil'][1])) {
            unset($cambios['foto_perfil']);
        }
    }

    if (!$errores) {
        $totalCambios = count($cambiosUsuario) + count($cambiosPaciente);
        if ($totalCambios == 0) {
            $exito = 'No hay cambios para actualizar.';
        } else {
            $actualizacionesExitosas = 0;

            // Actualizar tabla usuarios
            if ($cambiosUsuario) {
                $sets = [];
                $types = '';
                $values = [];
                foreach ($cambiosUsuario as $campo => [$anterior, $nuevo]) {
                    $sets[] = "$campo = ?";
                    $types .= 's';
                    $values[] = $nuevo;
                }
                $types .= 'i';
                $values[] = $userId;

                $sql = "UPDATE $TABLE_USERS SET " . implode(', ', $sets) . " WHERE id_usuarios = ?";
                $stmt = $conexion->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$values);
                    if ($stmt->execute()) {
                        $actualizacionesExitosas++;
                        registrar_historial($conexion, $TABLE_HISTORY, $userId, $cambiosUsuario, $userId);
                    } else {
                        $errores[] = 'No se pudo actualizar la información del usuario.';
                    }
                    $stmt->close();
                } else {
                    $errores[] = 'Error al preparar la consulta de actualización del usuario.';
                }
            }

            // Actualizar tabla pacientes
            if ($cambiosPaciente) {
                $sets = [];
                $types = '';
                $values = [];
                foreach ($cambiosPaciente as $campo => [$anterior, $nuevo]) {
                    $sets[] = "$campo = ?";
                    if ($campo == 'edad') {
                        $types .= 'i';
                        $values[] = (int)$nuevo;
                    } else {
                        $types .= 's';
                        $values[] = $nuevo;
                    }
                }
                $types .= 'i';
                $values[] = $userId;

                $sql = "UPDATE $TABLE_PATIENTS SET " . implode(', ', $sets) . " WHERE id_usuarios = ?";
                $stmt = $conexion->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$values);
                    if ($stmt->execute()) {
                        $actualizacionesExitosas++;
                        registrar_historial($conexion, $TABLE_HISTORY, $userId, $cambiosPaciente, $userId);
                    } else {
                        $errores[] = 'No se pudo actualizar la información del paciente.';
                    }
                    $stmt->close();
                } else {
                    $errores[] = 'Error al preparar la consulta de actualización del paciente.';
                }
            }

            if ($actualizacionesExitosas > 0) {
                $exito = 'Perfil actualizado correctamente (' . $totalCambios . ' cambio(s)).';
                // Refrescar datos cargados
                $usuario = cargarUsuario($conexion, $TABLE_USERS, $userId, $FIELDS_SELECT_USERS);
                $paciente = cargarPaciente($conexion, $TABLE_PATIENTS, $userId, $FIELDS_SELECT_PATIENTS);
            }
        }
    }
}

// Helper para valor seguro en inputs
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Helper para obtener valor del campo del usuario según nombres de columna reales
function u(array $usuario, string $campo): string { return h($usuario[$campo] ?? ''); }

// Helper para obtener valor del campo del paciente según nombres de columna reales
function p(array $paciente, string $campo): string { return h($paciente[$campo] ?? ''); }

// Cargar historial de actualizaciones del usuario
function cargarHistorial(mysqli $conexion, string $tablaHist, int $idUsuarios): array {
    $stmt = $conexion->prepare("SELECT campo, valor_anterior, valor_nuevo, fecha_actualizacion FROM $tablaHist WHERE id_usuarios = ? ORDER BY fecha_actualizacion DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $idUsuarios);
    $stmt->execute();
    $res = $stmt->get_result();
    $historial = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    // Formatear fechas y traducir campos
    $traducciones = [
        'Correo_electronico' => 'Correo Electrónico',
        'Contrasena' => 'Contraseña',
        'nombre_completo' => 'Nombre Completo',
        'DNI' => 'DNI',
        'fecha_nacimiento' => 'Fecha de Nacimiento',
        'edad' => 'Edad',
        'telefono' => 'Teléfono',
    ];
    foreach ($historial as &$registro) {
        $registro['campo'] = $traducciones[$registro['campo']] ?? $registro['campo'];
        $registro['fecha_actualizacion'] = date('d/m/Y H:i', strtotime($registro['fecha_actualizacion']));
    }
    return $historial;
}

$historial = cargarHistorial($conexion, $TABLE_HISTORY, $userId);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Actualizar Perfil</title>
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
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .alert {
            border-radius: 0.375rem;
        }
        .header-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .header-section p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .medical-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ffffff;
        }
        /* Estilos para el modal */
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #ffffff; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: #000; text-decoration: none; cursor: pointer; }
        .modal-header { background-color: #007bff; color: white; padding: 10px; border-radius: 8px 8px 0 0; }
        .modal-body { padding: 20px; }
        .historial-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .historial-table th, .historial-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .historial-table th { background-color: #007bff; color: white; font-weight: bold; }
        .historial-table tr:nth-child(even) { background-color: #f9f9f9; }
        .historial-table tr:hover { background-color: #e3f2fd; }
        .btn-historial { background: #007bff; color: #fff; padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; margin-left: 10px; }
        .btn-historial:hover { background: #0056b3; }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-person-gear"></i>
            </div>
            <h1>Actualizar Perfil</h1>
            <p>Actualice su información personal y de paciente en la clínica nutricional.</p>
            <a href="Menuprincipal.php" class="btn btn-light position-absolute top-50 end-0 translate-middle-y me-3">
                <i class="bi bi-house-door"></i> Menú Principal
            </a>
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
        <?php if ($exito): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($exito, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-person-plus me-2"></i>Información del Perfil</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">

                    <div class="mb-3">
                        <label for="Correo_electronico" class="form-label">
                            <i class="bi bi-envelope me-1"></i>Correo electrónico
                        </label>
                        <input type="email" class="form-control" id="Correo_electronico" name="Correo_electronico" value="<?= htmlspecialchars($usuario['Correo_electronico'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="Contrasena" class="form-label">
                            <i class="bi bi-lock me-1"></i>Nueva contraseña
                        </label>
                        <input type="password" class="form-control" id="Contrasena" name="Contrasena" placeholder="Dejar en blanco para no cambiar">
                    </div>

                    <div class="mb-3">
                        <label for="nombre_completo" class="form-label">
                            <i class="bi bi-person me-1"></i>Nombre completo
                        </label>
                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?= htmlspecialchars($paciente['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="DNI" class="form-label">
                                <i class="bi bi-card-text me-1"></i>DNI
                            </label>
                            <input type="text" class="form-control" id="DNI" name="DNI" value="<?= htmlspecialchars($paciente['DNI'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">
                                <i class="bi bi-telephone me-1"></i>Teléfono
                            </label>
                            <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($paciente['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fecha_nacimiento" class="form-label">
                                <i class="bi bi-calendar me-1"></i>Fecha de nacimiento
                            </label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($paciente['fecha_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required onchange="calcularEdad()">
                        </div>
                        <div class="col-md-6">
                            <label for="edad" class="form-label">
                                <i class="bi bi-hash me-1"></i>Edad
                            </label>
                            <input type="text" class="form-control" id="edad" value="<?= htmlspecialchars($paciente['edad'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Guardar Cambios
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" id="btnHistorial">
                            <i class="bi bi-clock-history me-2"></i>Ver Historial
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para el historial -->
    <div id="modalHistorial" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
                <h2>Historial de Actualizaciones</h2>
            </div>
            <div class="modal-body">
                <?php if (empty($historial)): ?>
                    <p style="text-align: center; color: #666;">No hay actualizaciones registradas para este usuario.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="historial-table">
                            <thead>
                                <tr>
                                    <th>Campo Modificado</th>
                                    <th>Valor Anterior</th>
                                    <th>Valor Nuevo</th>
                                    <th>Fecha y Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $registro): ?>
                                    <tr>
                                        <td><?php echo h($registro['campo']); ?></td>
                                        <td><?php echo h($registro['valor_anterior'] ?? 'N/A'); ?></td>
                                        <td><?php echo h($registro['valor_nuevo'] ?? 'N/A'); ?></td>
                                        <td><?php echo h($registro['fecha_actualizacion']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('fecha_nacimiento').addEventListener('change', function() {
            const fecha = new Date(this.value);
            const hoy = new Date();
            let edad = hoy.getFullYear() - fecha.getFullYear();
            const mes = hoy.getMonth() - fecha.getMonth();
            if (mes < 0 || (mes === 0 && hoy.getDate() < fecha.getDate())) {
                edad--;
            }
            document.getElementById('edad').value = edad;
        });

        // Funcionalidad del modal
        var modal = document.getElementById("modalHistorial");
        var btn = document.getElementById("btnHistorial");
        var span = document.getElementsByClassName("close")[0];

        btn.onclick = function() {
            modal.style.display = "block";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>