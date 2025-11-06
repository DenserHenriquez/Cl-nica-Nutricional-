<?php
// Crear_Receta.php
// Formulario para crear recetas asociadas a un paciente

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];

// Obtener id_pacientes desde la BD usando id_usuarios
$stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $paciente_id = (int)$row['id_pacientes'];
} else {
    // Usuario no es paciente registrado
    header('Location: Menuprincipal.php?error=No eres un paciente registrado.');
    exit;
}
$stmt->close();

// Configuración de subida
$uploadDir = __DIR__ . '/assets/images/recetas';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        $errores[] = 'No se pudo crear el directorio para subir imágenes.';
    }
}
$errores = [];
$exito = '';

// Crear tabla si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    ingredientes TEXT NOT NULL,
    porciones INT DEFAULT NULL,
    instrucciones TEXT,
    nota_nutricional TEXT,
    foto_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paciente (paciente_id),
    CONSTRAINT fk_recetas_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Manejo de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF simple
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $ingredientes = isset($_POST['ingredientes']) ? trim($_POST['ingredientes']) : '';
    $porciones = isset($_POST['porciones']) ? (int)$_POST['porciones'] : null;
    $instrucciones = isset($_POST['instrucciones']) ? trim($_POST['instrucciones']) : '';
    $nota_nutricional = isset($_POST['nota_nutricional']) ? trim($_POST['nota_nutricional']) : '';

    // Validaciones
    if (empty($nombre)) $errores[] = 'El nombre de la receta es obligatorio.';
    if (empty($ingredientes)) $errores[] = 'Los ingredientes son obligatorios.';
    if ($porciones !== null && $porciones <= 0) $errores[] = 'Las porciones deben ser un número positivo.';

    // Validar imagen (opcional)
    $fotoPath = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'Error al subir la imagen.';
        } else {
            $maxSize = 3 * 1024 * 1024; // 3MB
            if ($file['size'] > $maxSize) {
                $errores[] = 'La imagen excede el tamaño máximo (3MB).';
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif'
            ];
            if (!isset($allowed[$mime])) {
                $errores[] = 'Formato de imagen inválido. Solo JPG, PNG o GIF.';
            } else {
                $ext = $allowed[$mime];
                $basename = 'receta_' . $paciente_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $basename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errores[] = 'No se pudo guardar la imagen en el servidor.';
                } else {
                    $fotoPath = 'assets/images/recetas/' . $basename;
                }
            }
        }
    }

    if (empty($errores)) {
        $sql = "INSERT INTO recetas (paciente_id, nombre, ingredientes, porciones, instrucciones, nota_nutricional, foto_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ississs', $paciente_id, $nombre, $ingredientes, $porciones, $instrucciones, $nota_nutricional, $fotoPath);
            if ($stmt->execute()) {
                $exito = 'Receta guardada correctamente.';
            } else {
                $errores[] = 'Error al guardar en BD: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errores[] = 'Error preparando consulta: ' . $conexion->error;
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Crear Receta</title>
    <link rel="stylesheet" href="assets/css/estilos.css" />
    <style>
        :root {
            --primary-900: #0d47a1;
            --primary-700: #1565c0;
            --primary-500: #1976d2;
            --primary-300: #42a5f5;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-700: #374151;
            --gray-900: #111827;
            --success: #10b981;
            --error: #ef4444;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 800px;
            margin: 20px auto;
            background: var(--white);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        h1 {
            color: var(--primary-900);
            text-align: center;
            margin-bottom: 8px;
            font-size: 2rem;
            font-weight: 700;
        }

        .subtitle {
            color: var(--gray-700);
            text-align: center;
            margin-bottom: 24px;
            font-size: 1rem;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .row > * {
            flex: 1 1 200px;
        }

        label {
            display: block;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 4px;
        }

        input, select, textarea, button {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        button {
            background: var(--primary-500);
            color: var(--white);
            border: none;
            cursor: pointer;
            font-weight: 500;
            padding: 10px 16px;
            transition: background 0.2s;
        }

        button:hover {
            background: var(--primary-700);
        }

        .errores {
            background: #fef2f2;
            color: var(--error);
            padding: 12px;
            border-radius: var(--radius);
            border: 1px solid #fecaca;
            margin-bottom: 16px;
        }

        .exito {
            background: #f0fdf4;
            color: var(--success);
            padding: 12px;
            border-radius: var(--radius);
            border: 1px solid #bbf7d0;
            margin-bottom: 16px;
        }

        .preview {
            max-height: 150px;
            border-radius: 6px;
            border: 1px solid var(--gray-200);
        }

        .muted {
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .form-actions a {
            display: inline-block;
            padding: 10px 16px;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 6px;
            border: 1px solid var(--gray-200);
            transition: background 0.2s;
        }

        .form-actions a:hover {
            background: var(--gray-200);
        }

        .btn-back {
            display: inline-block;
            padding: 8px 16px;
            background: var(--primary-500);
            color: var(--white);
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .btn-back:hover {
            background: var(--primary-700);
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 16px;
            }
            .row {
                flex-direction: column;
                gap: 12px;
            }
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h1>Crear Receta</h1>
                <p class="subtitle">Paciente #<?= (int)$paciente_id ?> | Crea una nueva receta nutricional.</p>
            </div>
            <a href="Menuprincipal.php" class="btn-back">← Regresar al Menú Principal</a>
        </div>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $e): ?>
                    <div>- <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="exito"><?= htmlspecialchars($exito, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row">
                    <label>Nombre de la receta
                        <input type="text" name="nombre" required />
                    </label>
                </div>

                <div class="row">
                    <label>Ingredientes
                        <textarea name="ingredientes" rows="4" placeholder="Lista de ingredientes separados por comas o líneas" required></textarea>
                    </label>
                </div>

                <div class="row">
                    <label>Porciones
                        <input type="number" name="porciones" min="1" placeholder="Ej: 4" />
                    </label>
                </div>

                <div class="row">
                    <label>Instrucciones de preparación
                        <textarea name="instrucciones" rows="4" placeholder="Pasos para preparar la receta"></textarea>
                    </label>
                </div>

                <div class="row">
                    <label>Nota nutricional
                        <textarea name="nota_nutricional" rows="3" placeholder="Información nutricional, calorías, etc."></textarea>
                    </label>
                </div>

                <div class="row">
                    <label>Foto de la receta (opcional)
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/gif" id="fotoInput" onchange="previewImage(event)" />
                        <span class="muted">Formatos: JPG, PNG, GIF. Máx 3MB.</span>
                        <div id="imagePreview" style="margin-top: 10px; display: none;">
                            <img id="previewImg" class="preview" alt="Vista previa" />
                        </div>
                    </label>
                </div>

                <button type="submit">Guardar Receta</button>
            </form>
        </div>

        <div class="form-actions">
            <a href="Gestion_Recetas.php">Ver y Gestionar Recetas</a>
            <a href="Menuprincipal.php">Regresar al Menú Principal</a>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
