<?php
session_start();
require_once __DIR__ . '/db_connection.php';

if (!isset($_SESSION['id_usuarios'])) {
    header('Location: Login.php');
    exit;
}

$userId = intval($_SESSION['id_usuarios']);
$errores = [];
$exito = '';

// Obtener hash actual y datos del usuario conectado
$hashActual = null;
$nombreUsuario = null;
$correoUsuario = null;
if ($stmt = $conexion->prepare('SELECT Contrasena, Nombre_completo, Correo_electronico FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($hashActual, $nombreUsuario, $correoUsuario);
    $stmt->fetch();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['actual'] ?? '';
    $nueva = $_POST['nueva'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($actual === '') $errores[] = 'La contraseña actual es obligatoria.';
    if ($nueva === '') $errores[] = 'La nueva contraseña es obligatoria.';
    if ($nueva !== $confirm) $errores[] = 'La nueva contraseña y la confirmación no coinciden.';
    // Reglas de contraseña: mínimo 8 caracteres, al menos una mayúscula, un número y un símbolo
    if (strlen($nueva) < 8) $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Z]/', $nueva)) $errores[] = 'La nueva contraseña debe incluir al menos una letra mayúscula.';
    if (!preg_match('/[0-9]/', $nueva)) $errores[] = 'La nueva contraseña debe incluir al menos un número.';
    if (!preg_match('/[\W_]/', $nueva)) $errores[] = 'La nueva contraseña debe incluir al menos un símbolo (por ejemplo: !@#$%).';

    if (empty($errores)) {
        if ($hashActual === null) {
            $errores[] = 'No se encontró la contraseña actual en la base de datos.';
        } elseif (!password_verify($actual, $hashActual)) {
            $errores[] = 'La contraseña actual es incorrecta.';
        } else {
            $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
            if ($stmt = $conexion->prepare('UPDATE usuarios SET Contrasena = ? WHERE id_usuarios = ?')) {
                $stmt->bind_param('si', $nuevoHash, $userId);
                if ($stmt->execute()) {
                    $exito = 'Contraseña actualizada correctamente.';
                    // Intentar registrar en historial si existe la tabla
                    $campo = 'Contrasena';
                    $valorAnterior = '[PROTEGIDO]';
                    $valorNuevo = '[PROTEGIDO]';
                    if ($stmtH = $conexion->prepare("INSERT INTO historial_actualizaciones (id_usuarios, campo, valor_anterior, valor_nuevo, actualizado_por, fecha_actualizacion) VALUES (?,?,?,?,?,NOW())")) {
                        $actor = $userId;
                        $stmtH->bind_param('isssi', $userId, $campo, $valorAnterior, $valorNuevo, $actor);
                        @$stmtH->execute();
                        $stmtH->close();
                    }
                } else {
                    $errores[] = 'Error al actualizar la contraseña.';
                }
                $stmt->close();
            } else {
                $errores[] = 'Error al preparar la actualización.';
            }
        }
    }
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cambiar Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .btn-primary { background-color: #198754; border-color: #198754; }
        .btn-primary:hover { background-color: #146c43; border-color: #13653f; }
        .form-label { font-weight: 600; color: #198754; }
        .header-section { background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white; padding: 0.8rem 0; margin-bottom: 1rem; }
        /* Forzar colores verdes en elementos Bootstrap que usan .bg-primary */
        .bg-primary { background-color: #198754 !important; }
        .card-header.bg-primary { background-color: #198754 !important; border-color: #198754 !important; }
        .alert-success { border-color: #d1e7dd; background-color: #d1e7dd; color: #0f5132; }
        .user-info { color: #e9f7ef; opacity: 0.95; margin-top: 6px; font-weight: 600; }
        /* Floating error box */
        #floatingErrors { position: fixed; top: 20px; right: 20px; z-index: 2000; min-width: 260px; max-width: 420px; display: none; }
        #floatingErrors .card { border-left: 4px solid #dc3545; }
        #floatingErrors.show { display: block; animation: slideIn 240ms ease-out; }
        @keyframes slideIn { from { transform: translateY(-8px); opacity: 0 } to { transform: translateY(0); opacity: 1 } }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon"><i class="bi bi-lock"></i></div>
            <h1>Cambiar Contraseña</h1>
            <p>Actualice su contraseña de acceso de forma segura.</p>
            <?php if (!empty($nombreUsuario) || !empty($correoUsuario)): ?>
                <div class="user-info">
                    <?= h($nombreUsuario ?? $_SESSION['nombre'] ?? '') ?> &nbsp;|&nbsp; <?= h($correoUsuario ?? '') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success"><?= h($exito) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>Cambiar contraseña</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="actual" class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control" id="actual" name="actual" required>
                    </div>
                    <div class="mb-3">
                        <label for="nueva" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control" id="nueva" name="nueva" required>
                        <div id="passwordHelp" class="form-text mt-2">
                            <ul class="mb-0" style="list-style:none;padding-left:0;">
                                <li id="req-length" style="color:#6c757d">• Mínimo 8 caracteres</li>
                                <li id="req-upper" style="color:#6c757d">• Al menos una letra mayúscula</li>
                                <li id="req-number" style="color:#6c757d">• Al menos un número</li>
                                <li id="req-symbol" style="color:#6c757d">• Al menos un símbolo (ej: !@#$%)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm" class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-control" id="confirm" name="confirm" required>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i>Actualizar contraseña</button>
                        <a href="Actualizar_perfil.php" class="btn btn-secondary btn-lg"><i class="bi bi-arrow-left me-2"></i>Volver</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // Client-side password strength checks
        (function(){
            const nueva = document.getElementById('nueva');
            const form = document.querySelector('form');
            const reqLen = document.getElementById('req-length');
            const reqUpper = document.getElementById('req-upper');
            const reqNumber = document.getElementById('req-number');
            const reqSymbol = document.getElementById('req-symbol');

            function updateIndicators() {
                const v = nueva.value || '';
                if (v.length >= 8) { reqLen.style.color = '#198754'; } else { reqLen.style.color = '#6c757d'; }
                if (/[A-Z]/.test(v)) { reqUpper.style.color = '#198754'; } else { reqUpper.style.color = '#6c757d'; }
                if (/[0-9]/.test(v)) { reqNumber.style.color = '#198754'; } else { reqNumber.style.color = '#6c757d'; }
                if (/[^A-Za-z0-9]/.test(v)) { reqSymbol.style.color = '#198754'; } else { reqSymbol.style.color = '#6c757d'; }
            }

            nueva.addEventListener('input', updateIndicators);

            form.addEventListener('submit', function(e){
                const v = nueva.value || '';
                const errors = [];
                if (v.length < 8) errors.push('La nueva contraseña debe tener al menos 8 caracteres.');
                if (!/[A-Z]/.test(v)) errors.push('La nueva contraseña debe incluir al menos una letra mayúscula.');
                if (!/[0-9]/.test(v)) errors.push('La nueva contraseña debe incluir al menos un número.');
                if (!/[^A-Za-z0-9]/.test(v)) errors.push('La nueva contraseña debe incluir al menos un símbolo.');
                if (errors.length) {
                    e.preventDefault();
                    showFloatingErrors(errors);
                }
            });

            // Inicializar indicadores
            updateIndicators();

            // Floating errors helper
            let floatingTimer = null;
            const floatingEl = document.createElement('div');
            floatingEl.id = 'floatingErrors';
            document.body.appendChild(floatingEl);

            function showFloatingErrors(list) {
                clearTimeout(floatingTimer);
                const html = `
                    <div class="card text-white bg-danger mb-0">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2">Errores</h6>
                            <ul style="margin:0;padding-left:18px;">
                                ${list.map(i => `<li>${i}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                `;
                floatingEl.innerHTML = html;
                floatingEl.classList.add('show');
                // Ocultar después de 6s
                floatingTimer = setTimeout(() => {
                    floatingEl.classList.remove('show');
                }, 6000);
            }
        })();
    </script>
</body>
</html>
