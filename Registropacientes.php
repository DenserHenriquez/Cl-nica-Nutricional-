<?php
// Registropacientes.php
// Formulario para registrar pacientes con campos específicos

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];
$user_name = $_SESSION['nombre'] ?? '';

// Obtener nombre completo del usuario logueado
if ($user_id > 0 && empty($user_name)) {
    if ($stmt = $conexion->prepare('SELECT Nombre_completo FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($dbName);
        if ($stmt->fetch() && $dbName) {
            $user_name = $dbName;
            $_SESSION['nombre'] = $dbName;
        }
        $stmt->close();
    }
}

$errores = [];
$exito = '';

// Manejo de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF simple
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    $dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    $talla = isset($_POST['talla']) ? trim($_POST['talla']) : '';
    $peso = isset($_POST['peso']) ? trim($_POST['peso']) : '';
    $estatura = isset($_POST['estatura']) ? trim($_POST['estatura']) : '';
    $masa_muscular = isset($_POST['masa_muscular']) ? trim($_POST['masa_muscular']) : '';
    $enfermedades_base = isset($_POST['enfermedades_base']) ? trim($_POST['enfermedades_base']) : '';
    $medicamentos = isset($_POST['medicamentos']) ? trim($_POST['medicamentos']) : '';

    // Validaciones
    if (!preg_match('/^\d{13}$/', $dni)) {
        $errores[] = 'DNI debe contener exactamente 13 dígitos numéricos.';
    }

    if (empty($fecha_nacimiento)) {
        $errores[] = 'Fecha de nacimiento es obligatoria.';
    } else {
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha_obj) {
            $errores[] = 'Fecha de nacimiento inválida.';
        } else {
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_obj)->y;
            if ($edad < 0 || $edad > 150) {
                $errores[] = 'Fecha de nacimiento inválida.';
            }
        }
    }

    if (!preg_match('/^\d{8}$/', $telefono)) {
        $errores[] = 'Teléfono debe contener exactamente 8 dígitos numéricos.';
    }

    // Validaciones para nuevos campos
    if (!empty($talla) && !is_numeric($talla)) {
        $errores[] = 'Talla debe ser un número válido.';
    }

    if (!empty($peso) && !is_numeric($peso)) {
        $errores[] = 'Peso debe ser un número válido.';
    }

    if (!empty($estatura) && !is_numeric($estatura)) {
        $errores[] = 'Estatura debe ser un número válido.';
    }

    if (!empty($masa_muscular) && !is_numeric($masa_muscular)) {
        $errores[] = 'Masa muscular debe ser un número válido.';
    }

    if (empty($errores)) {
        // Calcular edad
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_obj)->y;

        // Calcular IMC si peso y estatura están presentes
        $imc = null;
        if (!empty($peso) && !empty($estatura) && $estatura > 0) {
            $imc = $peso / (($estatura / 100) ** 2);
        }

        // Insertar paciente
        $sql = "INSERT INTO pacientes (id_usuarios, nombre_completo, DNI, fecha_nacimiento, edad, telefono, talla, peso, estatura, IMC, masa_muscular, enfermedades_base, medicamentos, fecha_registro)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('isssisssssssss', $user_id, $user_name, $dni, $fecha_nacimiento, $edad, $telefono, $talla, $peso, $estatura, $imc, $masa_muscular, $enfermedades_base, $medicamentos);
            if ($stmt->execute()) {
                $exito = 'Paciente registrado correctamente.';
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
    <title>Registro de Pacientes</title>
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
            max-width: 600px;
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
            font-size: 1.5rem;
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

        input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        button {
            background: var(--primary-500);
            color: var(--white);
            border: none;
            cursor: pointer;
            font-weight: 500;
            padding: 10px 16px;
            border-radius: 6px;
            transition: background 0.2s;
            width: 100%;
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
            flex: 1;
            text-align: center;
        }

        .form-actions a:hover {
            background: var(--gray-200);
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="position: relative; margin-bottom: 16px;">
            <h1 style="text-align: center;">Registro de Pacientes</h1>
            <p class="subtitle" style="text-align: center;">Complete los datos para registrar un nuevo paciente.</p>
            <a href="Menuprincipal.php" style="position: absolute; top: 0; right: 0; display: inline-block; padding: 6px 12px; background: var(--primary-500); color: var(--white); text-decoration: none; border-radius: 6px; font-weight: 500; transition: background 0.2s; white-space: nowrap;">Menu Principal</a>
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
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row">
                    <label>Nombre completo
                        <input type="text" value="<?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>" readonly />
                    </label>
                </div>

                <div class="row">
                    <label>DNI (13 dígitos)
                        <input type="text" name="dni" pattern="\d{13}" maxlength="13" placeholder="0823200610125" required />
                    </label>
                </div>

                <div class="row">
                    <label>Fecha de nacimiento
                        <input type="date" name="fecha_nacimiento" required onchange="calcularEdad()" />
                    </label>
                    <label>Edad
                        <input type="text" id="edad" readonly />
                    </label>
                </div>

                <div class="row">
                    <label>Teléfono (8 dígitos)
                        <input type="text" name="telefono" pattern="\d{8}" maxlength="8" placeholder="99553364" required />
                    </label>
                </div>

                <div class="row">
                    <label>Talla (cm)
                        <input type="number" step="0.01" name="talla" placeholder="170.5" />
                    </label>
                    <label>Peso (kg)
                        <input type="number" step="0.01" name="peso" placeholder="70.5" />
                    </label>
                </div>

                <div class="row">
                    <label>Estatura (cm)
                        <input type="number" step="0.01" name="estatura" placeholder="170.5" />
                    </label>
                    <label>Masa muscular (kg)
                        <input type="number" step="0.01" name="masa_muscular" placeholder="50.0" />
                    </label>
                </div>

                <div class="row">
                    <label>Enfermedades de base
                        <textarea name="enfermedades_base" rows="3" placeholder="Describa las enfermedades de base"></textarea>
                    </label>
                </div>

                <div class="row">
                    <label>Medicamentos
                        <textarea name="medicamentos" rows="3" placeholder="Liste los medicamentos"></textarea>
                    </label>
                </div>

                <button type="submit">Guardar</button>
            </form>
        </div>
    </div>

    <script>
        function calcularEdad() {
            const fechaInput = document.querySelector('input[name="fecha_nacimiento"]');
            const edadInput = document.getElementById('edad');

            if (fechaInput.value) {
                const fechaNac = new Date(fechaInput.value);
                const hoy = new Date();
                let edad = hoy.getFullYear() - fechaNac.getFullYear();
                const mes = hoy.getMonth() - fechaNac.getMonth();

                if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
                    edad--;
                }

                edadInput.value = edad;
            } else {
                edadInput.value = '';
            }
        }
    </script>
</body>
</html>