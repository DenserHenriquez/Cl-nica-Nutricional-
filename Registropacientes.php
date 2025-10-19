<?php
session_start();
require_once __DIR__ . '/db_connection.php';

function generarExpediente() {
    $fecha = date('Ymd');
    $rand  = strtoupper(bin2hex(random_bytes(4)));
    return "EXP-{$fecha}-{$rand}";
}

$errores = [];
$exito = null;

// Insertar si viene POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = isset($_POST['usuario_id']) ? trim($_POST['usuario_id']) : '';
    $telefono   = isset($_POST['contacto_emergencia_telefono']) ? trim($_POST['contacto_emergencia_telefono']) : '';
    $activo     = isset($_POST['activo']) ? 1 : 0;

    if ($usuario_id === '' || !ctype_digit($usuario_id) || (int)$usuario_id <= 0) {
        $errores[] = "El campo Usuario ID es obligatorio y debe ser numérico.";
    }

    if ($telefono === '') {
        $errores[] = "El teléfono de emergencia es obligatorio.";
    } else {
        $soloDigitos = preg_replace('/\D+/', '', $telefono);
        if (strlen($soloDigitos) < 7 || strlen($soloDigitos) > 20) {
            $errores[] = "El teléfono de emergencia no parece válido.";
        }
    }

    if (!$errores) {
        $expediente = generarExpediente();
        $stmt = $conexion->prepare("INSERT INTO pacientes (usuario_id, contacto_emergencia_telefono, expediente_unique, activo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $usuario_id, $telefono, $expediente, $activo);

        if ($stmt->execute()) {
            $exito = "Paciente registrado correctamente. Expediente: {$expediente}";
        } else {
            $errores[] = "Error al guardar en la base de datos.";
        }
        $stmt->close();
    }
}

// Consultar últimos pacientes (puedes ajustar el límite)
$limit = 20;
$pacientes = [];
$q = $conexion->prepare("SELECT id_paciente, usuario_id, contacto_emergencia_telefono, expediente_unique, activo, fecha_registro FROM pacientes ORDER BY id_paciente DESC LIMIT ?");
$q->bind_param("i", $limit);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) {
    $pacientes[] = $row;
}
$q->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Pacientes</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <style>
        .form-wrapper {
            max-width: 420px;
            background: white;
            margin: 40px auto 20px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 8px 20px rgba(0,0,0,0.1);
        }
        .form-wrapper h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #46A2FD;
        }
        .form-group { margin-top: 14px; }
        .form-group label { font-size: 14px; color: #444; }
        .form-group input {
            width: 100%; padding: 10px; background: #F2F2F2; border: none; border-radius: 6px; margin-top: 4px; outline: none;
        }
        .btn { width: 100%; background: #46A2FD; color: #fff; border: none; padding: 10px; margin-top: 18px; border-radius: 6px; cursor: pointer; font-weight: bold; }

        .alert { margin-top: 12px; padding: 10px; border-radius: 6px; font-size: 14px; }
        .alert.ok { background: #e8f9ee; color: #156d2d; border: 1px solid #b8eac7; }
        .alert.err { background: #fdecea; color: #8a1c1c; border: 1px solid #f5c2c0; }

        .list-wrapper {
            max-width: 980px;
            margin: 10px auto 60px auto;
            background: rgba(255,255,255,0.92);
            border-radius: 12px;
            box-shadow: 0px 8px 20px rgba(0,0,0,0.08);
            padding: 20px;
        }
        .list-wrapper h3 { color: #46A2FD; margin: 0 0 12px 0; }
        .tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .tabla thead th {
            text-align: left;
            background: #f2f6ff;
            padding: 10px;
            border-bottom: 1px solid #e6ecf5;
        }
        .tabla tbody td {
            padding: 10px;
            border-bottom: 1px solid #eef1f5;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
        }
        .badge.ok { background: #e7f7ed; color: #1b6d36; border: 1px solid #bfe6cd; }
        .badge.off { background: #fff2ee; color: #8a1c1c; border: 1px solid #f5c2c0; }
        .empty {
            padding: 16px; background: #fafbfe; border: 1px dashed #dbe3f0; border-radius: 10px; color: #6b7280;
        }
        @media (max-width: 720px) {
            .tabla thead { display: none; }
            .tabla, .tabla tbody, .tabla tr, .tabla td { display: block; width: 100%; }
            .tabla tr { margin-bottom: 12px; border: 1px solid #eef1f5; border-radius: 8px; }
            .tabla tbody td { border-bottom: none; }
            .tabla tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                display: block;
                margin-bottom: 4px;
                color: #374151;
            }
        }
    </style>
</head>
<body>

<div class="form-wrapper">
    <h2>Registro de Paciente</h2>

    <?php if ($exito): ?>
        <div class="alert ok"><?php echo htmlspecialchars($exito, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="alert err">
            <?php foreach ($errores as $e) echo "<div>".htmlspecialchars($e, ENT_QUOTES, 'UTF-8')."</div>"; ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="form-group">
            <label>Usuario ID</label>
            <input type="number" name="usuario_id" required>
        </div>

        <div class="form-group">
            <label>Teléfono de emergencia</label>
            <input type="text" name="contacto_emergencia_telefono" placeholder="+504 9999-1234" required>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="activo" checked> Activo (1)</label>
        </div>

        <button class="btn">Guardar</button>
    </form>
</div>

<div class="list-wrapper">
    <h3>Pacientes registrados (últimos <?php echo (int)$limit; ?>)</h3>

    <?php if (empty($pacientes)): ?>
        <div class="empty">Aún no hay pacientes registrados.</div>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario ID</th>
                    <th>Teléfono emergencia</th>
                    <th>Expediente</th>
                    <th>Estado</th>
                    <th>Fecha registro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $p): ?>
                    <tr>
                        <td data-label="ID"><?php echo (int)$p['id_paciente']; ?></td>
                        <td data-label="Usuario ID"><?php echo (int)$p['usuario_id']; ?></td>
                        <td data-label="Teléfono emergencia"><?php echo htmlspecialchars($p['contacto_emergencia_telefono'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Expediente"><?php echo htmlspecialchars($p['expediente_unique'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Estado">
                            <?php if ((int)$p['activo'] === 1): ?>
                                <span class="badge ok">Activo</span>
                            <?php else: ?>
                                <span class="badge off">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Fecha registro">
                            <?php echo htmlspecialchars($p['fecha_registro'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
