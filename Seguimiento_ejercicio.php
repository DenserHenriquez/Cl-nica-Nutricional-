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
    imagen_evidencia VARCHAR(255),
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
    $imagenEvidencia = '';

    // Manejar subida de imagen
    if (isset($_FILES['imagen_evidencia']) && $_FILES['imagen_evidencia']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/ejercicios/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES['imagen_evidencia']['name']);
        $uploadFile = $uploadDir . $fileName;
        $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));

        // Validar tipo de archivo
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['imagen_evidencia']['tmp_name'], $uploadFile)) {
                $imagenEvidencia = $uploadFile;
            } else {
                $message = 'Error al subir la imagen.';
            }
        } else {
            $message = 'Solo se permiten archivos JPG, JPEG, PNG y GIF.';
        }
    }

    if ($tipo && $tiempo > 0 && $fecha) {
        $stmt = $conexion->prepare('INSERT INTO ejercicios (id_pacientes, tipo_ejercicio, tiempo, fecha, imagen_evidencia, notas) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isisss', $idPacientes, $tipo, $tiempo, $fecha, $imagenEvidencia, $notas);
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

// Obtener reportes semanales (última semana) incluyendo imágenes
$reportes = [];
$semanaInicio = date('Y-m-d', strtotime('monday this week'));
$semanaFin = date('Y-m-d', strtotime('sunday this week'));

$stmt = $conexion->prepare('
    SELECT tipo_ejercicio, SUM(tiempo) as total_tiempo,
           GROUP_CONCAT(imagen_evidencia SEPARATOR ";") as imagenes,
           GROUP_CONCAT(notas SEPARATOR ";") as notas_semanales
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
            --primary-100: #e3f2fd;
            --white: #ffffff;
            --text-900: #0b1b34;
            --text-700: #22426e;
            --text-500: #64748b;
            --shadow: 0 10px 25px rgba(13, 71, 161, 0.18);
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.1);
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 10px;
            --bg-sky-blue: #87CEEB;
            --gradient-card: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
            color: var(--text-900);
            background: var(--bg-sky-blue);
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: var(--white);
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary-700), var(--primary-500));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-menu {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-500);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        .btn-menu:hover {
            background: var(--primary-700);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: var(--radius-md);
            color: #155724;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 1px solid #c3e6cb;
            box-shadow: var(--shadow-light);
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section, .report-section {
            background: var(--gradient-card);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-section:hover, .report-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(13, 71, 161, 0.25);
        }

        .form-section h2, .report-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-900);
            border-bottom: 2px solid var(--primary-300);
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-700);
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        button {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
            color: var(--white);
            padding: 12px 25px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        button:hover {
            background: linear-gradient(135deg, var(--primary-700), var(--primary-900));
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-light);
        }

        .report-table th, .report-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .report-table th {
            background: linear-gradient(135deg, var(--primary-100), var(--primary-300));
            font-weight: 600;
            color: var(--text-900);
        }

        .report-table tbody tr {
            transition: background-color 0.3s ease;
        }

        .report-table tbody tr:hover {
            background-color: rgba(25, 118, 210, 0.05);
        }

        .report-table img {
            border-radius: var(--radius-sm);
            transition: transform 0.3s ease;
        }

        .report-table img:hover {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 10px;
            }
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            h1 {
                font-size: 2rem;
            }
            .form-section, .report-section {
                padding: 20px;
            }
            .report-table th, .report-table td {
                padding: 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Seguimiento de Ejercicios</h1>
            <a href="Menuprincipal.php" class="btn-menu">Menu Principal</a>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo e($message); ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Registrar Ejercicio</h2>
            <form method="post" enctype="multipart/form-data">
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
                    <label for="imagen_evidencia">Imagen de Evidencia (opcional):</label>
                    <input type="file" id="imagen_evidencia" name="imagen_evidencia" accept="image/*">
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
                            <th>Imágenes de Evidencia</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportes as $reporte): ?>
                            <tr>
                                <td><?php echo e($reporte['tipo_ejercicio']); ?></td>
                                <td><?php echo e($reporte['total_tiempo']); ?></td>
                                <td>
                                    <?php
                                    $imagenes = explode(';', $reporte['imagenes'] ?? '');
                                    foreach ($imagenes as $imagen) {
                                        if (!empty($imagen)) {
                                            echo '<img src="' . e($imagen) . '" alt="Evidencia" style="max-width: 100px; max-height: 100px; margin: 2px;">';
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo e($reporte['notas_semanales'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
