<?php
// Login.php maneja tanto login como registro basados en los campos recibidos

require_once __DIR__ . '/db_connection.php';
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helper para responder y detener ejecución
function redirect_with_message($msg, $ok = false) {
    // Puedes ajustar el redireccionamiento a otra página si lo prefieres
    // Aquí regresamos a index.php con un mensaje en querystring
    $param = $ok ? 'ok' : 'error';
    header('Location: index.php?' . $param . '=' . urlencode($msg));
    exit;
}

try {
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalizar claves esperadas del formulario
    $nombre      = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : '';
    $correo      = isset($_POST['Correo_electronico']) ? trim($_POST['Correo_electronico']) : '';
    $usuario     = isset($_POST['Usuario']) ? trim($_POST['Usuario']) : '';
    $contrasena  = isset($_POST['contrasena']) ? (string)$_POST['contrasena'] : '';
    $contrasena2 = isset($_POST['contrasena_confirm']) ? (string)$_POST['contrasena_confirm'] : '';

    // Detectar registro cuando vienen todos los campos principales
    $is_register = $nombre !== '' && $correo !== '' && $usuario !== '' && $contrasena !== '' && $contrasena2 !== '';

    if ($is_register) {
        // --- FLUJO DE REGISTRO ---
        // Validaciones básicas
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('Correo electronico invalido');
        }
        if (strlen($contrasena) < 6) {
            redirect_with_message('La contrasena debe tener al menos 6 caracteres');
        }
        if ($contrasena !== $contrasena2) {
            redirect_with_message('Las contrasenas no coinciden');
        }
        // Todos los usuarios nuevos se registran como Paciente por defecto
        $rolValido = 'Paciente';

        // Comprobar unicidad de usuario y correo
        $stmt = $conexion->prepare('SELECT 1 FROM usuarios WHERE Usuario = ? OR Correo_electronico = ? LIMIT 1');
        if (!$stmt) {
            redirect_with_message('Error al preparar consulta: ' . $conexion->error);
        }
        $stmt->bind_param('ss', $usuario, $correo);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            redirect_with_message('El usuario o correo ya existe');
        }
        $stmt->close();

        // Comprobar si existe columna Rol, si no agregarla automaticamente
        try {
            $colCheck = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'Rol'");
            if ($colCheck && $colCheck->num_rows === 0) {
                $conexion->query("ALTER TABLE usuarios ADD COLUMN Rol VARCHAR(20) NOT NULL DEFAULT 'Paciente'");
            }
        } catch (Throwable $eAlter) {
            // Si falla, seguimos con default paciente sin interrumpir (se insertara sin rol si no existe)
        }

        // Hashear contraseña
        $hash = password_hash($contrasena, PASSWORD_BCRYPT);
        if ($hash === false) {
            redirect_with_message('Error al procesar la contrasena');
        }

        // Insertar usuario (intentar con Rol, si falla reintentar sin Rol)
        $sqlInsert = 'INSERT INTO usuarios (Nombre_completo, Correo_electronico, Usuario, Contrasena, Rol) VALUES (?, ?, ?, ?, ?)';
        $stmt = $conexion->prepare($sqlInsert);
        if ($stmt) {
            $stmt->bind_param('sssss', $nombre, $correo, $usuario, $hash, $rolValido);
        } else {
            // Intentar sin Rol (por si no se pudo crear la columna)
            $stmt = $conexion->prepare('INSERT INTO usuarios (Nombre_completo, Correo_electronico, Usuario, Contrasena) VALUES (?, ?, ?, ?)');
            if (!$stmt) {
                redirect_with_message('Error al preparar insercion: ' . $conexion->error);
            }
            $stmt->bind_param('ssss', $nombre, $correo, $usuario, $hash);
        }

        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_message('Registro exitoso. Ahora puedes iniciar sesion.', true);
        } else {
            $err = $stmt->error;
            $stmt->close();
            redirect_with_message('Error al registrar: ' . $err);
        }
    } else {
        // Proceso de login
        $correo_login = isset($_POST['Correo_electronico']) ? trim($_POST['Correo_electronico']) : '';
        $pass_login = isset($_POST['contrasena']) ? (string)$_POST['contrasena'] : '';

        if ($correo_login === '' || $pass_login === '') {
            redirect_with_message('Correo y contraseña son requeridos');
        }



        // Intentar recuperar Rol también si existe
        $stmt = $conexion->prepare('SELECT id_usuarios, Contrasena, Nombre_completo, Usuario, Rol FROM usuarios WHERE Correo_electronico = ? LIMIT 1');
        if (!$stmt) {
            // Fallback sin Rol si la columna aun no existe
            $stmt = $conexion->prepare('SELECT id_usuarios, Contrasena, Nombre_completo, Usuario FROM usuarios WHERE Correo_electronico = ? LIMIT 1');
        }
        if (!$stmt) {
            redirect_with_message('Error al preparar consulta: ' . $conexion->error);
        }
        $stmt->bind_param('s', $correo_login);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            redirect_with_message('Credenciales inválidas');
        }

        if (!password_verify($pass_login, $user['Contrasena'])) {
            // Compatibilidad: si en BD hay contraseñas viejas sin hash, permite una sola vez migrar
            if (hash_equals($user['Contrasena'], $pass_login)) {
                // Actualizar a hash
                $nuevo_hash = password_hash($pass_login, PASSWORD_BCRYPT);
                if ($nuevo_hash) {
                    $up = $conexion->prepare('UPDATE usuarios SET Contrasena = ? WHERE id_usuarios = ?');
                    if ($up) {
                        $up->bind_param('si', $nuevo_hash, $user['id_usuarios']);
                        $up->execute();
                        $up->close();
                    }
                }
            } else {
                redirect_with_message('Credenciales inválidas');
            }
        }

        // Aquí podrías iniciar sesión con $_SESSION
        session_start();
        $_SESSION['id_usuarios'] = $user['id_usuarios'];
        $_SESSION['nombre'] = $user['Nombre_completo'];
        $_SESSION['usuario'] = $user['Usuario'];
        if (isset($user['Rol'])) {
            $_SESSION['rol'] = $user['Rol'];
        }

        header('Location: Menuprincipal.php');
        exit;
    }
}

// Si llega por GET u otro método
header('Location: index.php');
exit;
} catch (Throwable $e) {
    error_log('Login.php error: ' . $e->getMessage());
    redirect_with_message('Error del servidor. Intenta nuevamente.');
}
