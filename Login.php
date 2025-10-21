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
    $nombre = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : '';
    $correo = isset($_POST['Correo_electronico']) ? trim($_POST['Correo_electronico']) : '';
    $usuario = isset($_POST['Usuario']) ? trim($_POST['Usuario']) : '';
    $contrasena = isset($_POST['contrasena']) ? (string)$_POST['contrasena'] : '';

    $is_register = $nombre !== '' && $correo !== '' && $usuario !== '' && $contrasena !== '';

    if ($is_register) {
        // Validaciones básicas
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('Correo electrónico inválido');
        }
        if (strlen($contrasena) < 6) {
            redirect_with_message('La contraseña debe tener al menos 6 caracteres');
        }

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

        // Hashear contraseña
        $hash = password_hash($contrasena, PASSWORD_BCRYPT);
        if ($hash === false) {
            redirect_with_message('Error al procesar la contraseña');
        }

        // Insertar usuario
        $stmt = $conexion->prepare('INSERT INTO usuarios (Nombre_completo, Correo_electronico, Usuario, Contrasena) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            redirect_with_message('Error al preparar inserción: ' . $conexion->error);
        }
        $stmt->bind_param('ssss', $nombre, $correo, $usuario, $hash);

        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_message('Registro exitoso. Ahora puedes iniciar sesión.', true);
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

        $stmt = $conexion->prepare('SELECT id_usuarios, Contrasena, Nombre_completo, Usuario FROM usuarios WHERE Correo_electronico = ? LIMIT 1');
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
