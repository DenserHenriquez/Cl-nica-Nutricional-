<?php
session_start();
require_once __DIR__ . '/db_connection.php';

// Este archivo implementa la actualización del perfil del usuario/paciente.
// Asume que al autenticarse se guarda en $_SESSION['user_id'] el id del usuario
// y $_SESSION['rol'] con el rol del usuario (opcional). Ajusta los nombres de
// columnas/tabla conforme a tu esquema real.

// CONFIGURACIÓN (ajusta según tus tablas)
$TABLE_USERS = 'usuarios';
$FIELDS_SELECT = 'id_usuarios, Nombre_completo, Correo_electronico, Usuario, Contrasena';
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
$userId = intval($_SESSION['id_usuarios']);
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

$usuario = cargarUsuario($conexion, $TABLE_USERS, $userId, $FIELDS_SELECT);
if (!$usuario) {
    $errores[] = 'No se pudo cargar la información del usuario.';
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

// Helper: insertar historial de cambios por campo
function registrar_historial(mysqli $conexion, string $tablaHist, int $userId, array $cambios, int $actorId): void {
    if (!$cambios) return;
    $sqlH = "INSERT INTO $tablaHist (user_id, campo, valor_anterior, valor_nuevo, actualizado_por, fecha_actualizacion) VALUES (?,?,?,?,?,NOW())";
    $stmtH = $conexion->prepare($sqlH);
    if (!$stmtH) return;
    foreach ($cambios as $campo => [$anterior, $nuevo]) {
        $anteriorStr = isset($anterior) ? (string)$anterior : null;
        $nuevoStr = isset($nuevo) ? (string)$nuevo : null;
        $stmtH->bind_param('isssi', $userId, $campo, $anteriorStr, $nuevoStr, $actorId);
        $stmtH->execute();
    }
    $stmtH->close();
}

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitización básica
    $nombreCompleto = trim($_POST['Nombre_completo'] ?? '');
    $correo = trim($_POST['Correo_electronico'] ?? '');
    $usuarioNombre = trim($_POST['Usuario'] ?? '');
    $contrasena = trim($_POST['Contrasena'] ?? '');

    // Validaciones simples
    if ($nombreCompleto === '') $errores[] = 'El nombre completo es obligatorio';
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo electrónico inválido';
    if ($usuarioNombre === '') $errores[] = 'El nombre de usuario es obligatorio';
    // La contraseña es opcional: solo se actualizará si el usuario envía un valor

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

    // Construir payload nuevo para diffs
    $payloadNuevo = [
        'Nombre_completo' => $nombreCompleto,
        'Correo_electronico' => $correo,
        'Usuario' => $usuarioNombre,
    ];
    // La contraseña se maneja aparte para poder hashearla y solo si viene informada

    // Determinar campos modificados
    $permitidos = ['Nombre_completo','Correo_electronico','Usuario'];
    $cambios = diffs_campos($usuario ?? [], $payloadNuevo, $permitidos);
    if (!$subioFoto) {
        // Si no subió foto, no forzar cambio de foto si no cambió valor
        if (isset($cambios['foto_perfil']) && ($cambios['foto_perfil'][0] === $cambios['foto_perfil'][1])) {
            unset($cambios['foto_perfil']);
        }
    }

    if (!$errores) {
        if (!$cambios) {
            $exito = 'No hay cambios para actualizar.';
        } else {
            // Construir UPDATE parcial dinámico
            $sets = [];
            $types = '';
            $values = [];
            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $sets[] = "$campo = ?";
                $types .= 's';
                $values[] = $nuevo;
            }
            $types .= 'i';
            $values[] = $userId;

            $sql = "UPDATE $TABLE_USERS SET " . implode(', ', $sets) . " WHERE id_usuarios = ?";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                $errores[] = 'Error al preparar la consulta de actualización parcial.';
            } else {
                $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                    $exito = 'Perfil actualizado correctamente (' . count($cambios) . ' cambio(s)).';
                    // Registrar historial por campo
                    registrar_historial($conexion, $TABLE_HISTORY, $userId, $cambios, $userId);
                    // Refrescar datos cargados
                    $usuario = cargarUsuario($conexion, $TABLE_USERS, $userId, $FIELDS_SELECT);
                } else {
                    $errores[] = 'No se pudo actualizar el perfil.';
                }
                $stmt->close();
            }
        }
    }
}

// Helper para valor seguro en inputs
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Helper para obtener valor del campo del usuario según nombres de columna reales
function u(array $usuario, string $campo): string { return h($usuario[$campo] ?? ''); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Actualizar Perfil</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <style>
        .container { max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1 1 300px; }
        label { display:block; margin: 10px 0 6px; font-weight: 600; }
        input[type="text"], input[type="email"], input[type="date"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
        .actions { margin-top: 20px; display:flex; gap: 10px; }
        .btn { padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-primary { background: #28a745; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .alert { padding: 10px 12px; border-radius: 6px; margin-bottom: 15px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .avatar { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div style="position: relative; margin-bottom: 16px;">
            <a href="Menuprincipal.php" style="position: absolute; top: 0; right: 0; display: inline-block; padding: 6px 12px; background: var(--primary-500, #1976d2); color: var(--white, #ffffff); text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 0.875rem; transition: background 0.2s;">Menu Principal</a>
        </div>
        <h1>Actualizar Perfil</h1>

        <?php if ($errores): ?>
            <div class="alert alert-error">
                <ul style="margin:0 0 0 18px;">
                    <?php foreach ($errores as $e): ?>
                        <li><?php echo h($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($exito): ?>
            <div class="alert alert-success"><?php echo h($exito); ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col">
                    <label for="Nombre_completo">Nombre completo</label>
                    <input type="text" id="Nombre_completo" name="Nombre_completo" value="<?php echo u($usuario, 'Nombre_completo'); ?>" required>

                    <label for="Correo_electronico">Correo electrónico</label>
                    <input type="email" id="Correo_electronico" name="Correo_electronico" value="<?php echo u($usuario, 'Correo_electronico'); ?>" required>

                    <label for="Usuario">Usuario</label>
                    <input type="text" id="Usuario" name="Usuario" value="<?php echo u($usuario, 'Usuario'); ?>" required>

                    <label for="Contrasena">Nueva contraseña</label>
                    <input type="password" id="Contrasena" name="Contrasena" placeholder="Dejar en blanco para no cambiar">
                </div>
                <div class="col">
                    <p>Nota: Solo se gestionan los campos disponibles en la tabla usuarios.</p>
                </div>
            </div>

<<<<<<< Updated upstream
            <div class="actions">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a class="btn btn-secondary" href="Menuprincipal.php">Volver al menú</a>
=======
            <div class="actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-historial" id="btnHistorial">Ver Historial</button>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
>>>>>>> Stashed changes
            </div>
        </form>
    </div>
</body>
</html>
