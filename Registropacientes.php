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
            $stmt->bind_param('isssissssssss', $user_id, $user_name, $dni, $fecha_nacimiento, $edad, $telefono, $talla, $peso, $estatura, $imc, $masa_muscular, $enfermedades_base, $medicamentos);
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
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <h1>Registro de Pacientes</h1>
            <p>Complete los datos para registrar un nuevo paciente en la clínica nutricional.</p>
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
                <h5 class="card-title mb-0"><i class="bi bi-person-plus me-2"></i>Información del Paciente</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="nombre_completo" class="form-label">
                            <i class="bi bi-person me-1"></i>Nombre completo
                        </label>
                        <input type="text" class="form-control" id="nombre_completo" value="<?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="dni" class="form-label">
                                <i class="bi bi-card-text me-1"></i>DNI (13 dígitos)
                            </label>
                            <input type="text" class="form-control" id="dni" name="dni" pattern="\d{13}" maxlength="13" placeholder="0823200610125" required>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">
                                <i class="bi bi-telephone me-1"></i>Teléfono (8 dígitos)
                            </label>
                            <input type="text" class="form-control" id="telefono" name="telefono" pattern="\d{8}" maxlength="8" placeholder="99553364" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fecha_nacimiento" class="form-label">
                                <i class="bi bi-calendar me-1"></i>Fecha de nacimiento
                            </label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required onchange="calcularEdad()">
                        </div>
                        <div class="col-md-6">
                            <label for="edad" class="form-label">
                                <i class="bi bi-hash me-1"></i>Edad
                            </label>
                            <input type="text" class="form-control" id="edad" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="talla" class="form-label">
                                <i class="bi bi-rulers me-1"></i>Talla (cm)
                            </label>
                            <input type="number" step="0.01" class="form-control" id="talla" name="talla" placeholder="170.5">
                        </div>
                        <div class="col-md-6">
                            <label for="peso" class="form-label">
                                <i class="bi bi-speedometer2 me-1"></i>Peso (kg)
                            </label>
                            <input type="number" step="0.01" class="form-control" id="peso" name="peso" placeholder="70.5">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="estatura" class="form-label">
                                <i class="bi bi-arrows-expand me-1"></i>Estatura (cm)
                            </label>
                            <input type="number" step="0.01" class="form-control" id="estatura" name="estatura" placeholder="170.5">
                        </div>
                        <div class="col-md-6">
                            <label for="masa_muscular" class="form-label">
                                <i class="bi bi-activity me-1"></i>Masa muscular (kg)
                            </label>
                            <input type="number" step="0.01" class="form-control" id="masa_muscular" name="masa_muscular" placeholder="50.0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="enfermedades_base" class="form-label">
                            <i class="bi bi-clipboard-data me-1"></i>Enfermedades de base
                        </label>
                        <textarea class="form-control" id="enfermedades_base" name="enfermedades_base" rows="3" placeholder="Describa las enfermedades de base"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="medicamentos" class="form-label">
                            <i class="bi bi-capsule me-1"></i>Medicamentos
                        </label>
                        <textarea class="form-control" id="medicamentos" name="medicamentos" rows="3" placeholder="Liste los medicamentos"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Guardar Paciente
                        </button>
                    </div>
                </form>
            </div>
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