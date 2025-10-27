<?php
// Seguimiento_ejercicio.php
// Permite a los pacientes registrar rutinas de ejercicio y ver reportes semanales

session_start();
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db_connection.php';

$userId = (int)($_SESSION['id_usuarios'] ?? 0);
$userName = $_SESSION['nombre'] ?? '';

// Obtener id_pacientes del usuario
$idPacientes = null;
if ($userId > 0) {
    $stmt = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($idPacientes);
    $stmt->fetch();
    $stmt->close();
}

if (!$idPacientes) {
    die('No se encontró el registro de paciente para este usuario.');
}

$createTableQuery = "
CREATE TABLE IF NOT EXISTS ejercicios (
    id_ejercicio INT AUTO_INCREMENT PRIMARY KEY,
    id_pacientes INT NOT NULL,
    tipo_ejercicio VARCHAR(100) NOT NULL,
    tiempo INT NOT NULL COMMENT 'Duración en minutos',
    fecha DATE NOT NULL,
    notas TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pacientes) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";
$conexion->query($createTableQuery);

// Manejar envío del formulario
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_ejercicio'])) {
    $tipo = trim($_POST['tipo_ejercicio'] ?? '');
    $tiempo = (int)($_POST['tiempo'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    if ($tipo && $tiempo > 0 && $fecha) {
        $stmt = $conexion->prepare('INSERT INTO ejercicios (id_pacientes, tipo_ejercicio, tiempo, fecha, notas) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isiss', $idPacientes, $tipo, $tiempo, $fecha, $notas);
        if ($stmt->execute()) {
            $message = 'Ejercicio registrado exitosamente.';
        } else {
            $message = 'Error al registrar el ejercicio: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = 'Por favor, complete todos los campos obligatorios.';
    }
}

// Obtener reportes semanales (última semana)
$reportes = [];
$semanaInicio = date('Y-m-d', strtotime('monday this week'));
$semanaFin = date('Y-m-d', strtotime('sunday this week'));

$stmt = $conexion->prepare('
    SELECT tipo_ejercicio, SUM(tiempo) as total_tiempo, COUNT(*) as sesiones
    FROM ejercicios
    WHERE id_pacientes = ? AND fecha BETWEEN ? AND ?
    GROUP BY tipo_ejercicio
    ORDER BY total_tiempo DESC
');
$stmt->bind_param('iss', $idPacientes, $semanaInicio, $semanaFin);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reportes[] = $row;
}
$stmt->close();

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Seguimiento de Ejercicios | Clínica Nutricional</title>
    <style>
        :root {
            --primary-900: #0d47a1;
            --primary-700: #1565c0;
            --primary-500: #1976d2;
            --primary-300: #42a5f5;
            --white: #ffffff;
            --text-900: #0b1b34;
            --text-700: #22426e;
            --shadow: 0 10px 25px rgba(13, 71, 161, 0.18);
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 10px;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
            color: var(--text-900);
            background: linear-gradient(180deg, #f7fbff 0%, #f3f8ff 100%);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-section, .report-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--radius-sm);
        }

        button {
            background: var(--primary-500);
            color: var(--white);
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }

        button:hover {
            background: var(--primary-700);
        }

        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: var(--radius-sm);
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th, .report-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .report-table th {
            background: var(--primary-100);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Seguimiento de Ejercicios</h1>

        <?php if ($message): ?>
            <div class="message"><?php echo e($message); ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Registrar Ejercicio</h2>
            <form method="post">
                <div class="form-group">
                    <label for="tipo_ejercicio">Tipo de Ejercicio:</label>
                    <select id="tipo_ejercicio" name="tipo_ejercicio" required>
                        <option value="">Seleccione...</option>
                        <option value="Caminata">Caminata</option>
                        <option value="Correr">Correr</option>
                        <option value="Natación">Natación</option>
                        <option value="Ciclismo">Ciclismo</option>
                        <option value="Gimnasio">Gimnasio</option>
                        <option value="Yoga">Yoga</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tiempo">Tiempo (minutos):</label>
                    <input type="number" id="tiempo" name="tiempo" min="1" required>
                </div>
                <div class="form-group">
                    <label for="fecha">Fecha:</label>
                    <input type="date" id="fecha" name="fecha" required>
                </div>
                <div class="form-group">
                    <label for="notas">Notas (opcional):</label>
                    <textarea id="notas" name="notas" rows="3"></textarea>
                </div>
                <button type="submit" name="registrar_ejercicio">Registrar</button>
            </form>
        </div>

        <div class="report-section">
            <h2>Reporte Semanal (<?php echo e($semanaInicio); ?> a <?php echo e($semanaFin); ?>)</h2>
            <?php if (empty($reportes)): ?>
                <p>No hay ejercicios registrados esta semana.</p>
            <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Tipo de Ejercicio</th>
                            <th>Total Tiempo (min)</th>
                            <th>Sesiones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportes as $reporte): ?>
                            <tr>
                                <td><?php echo e($reporte['tipo_ejercicio']); ?></td>
                                <td><?php echo e($reporte['total_tiempo']); ?></td>
                                <td><?php echo e($reporte['sesiones']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <a href="Menuprincipal.php">Volver al Menú</a>
    </div>
</body>
</html>
