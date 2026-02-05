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
$userRole = $_SESSION['rol'] ?? 'Paciente';
$isStaff = in_array($userRole, ['Medico','Administrador'], true);

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

// Determinar usuario objetivo a registrar (staff puede elegir por uid)
$targetUserId = $user_id;
$targetUserName = $user_name;
if ($isStaff && isset($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    if ($uid > 0) {
        if ($st = $conexion->prepare('SELECT Nombre_completo FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
            $st->bind_param('i', $uid);
            $st->execute();
            $st->bind_result($nm);
            if ($st->fetch() && $nm) {
                $targetUserId = $uid;
                $targetUserName = $nm;
            }
            $st->close();
        }
    }
}

$errores = [];
$exito = '';

// Listado de usuarios sin registro en tabla pacientes (solo staff)
$usuariosSinRegistro = [];
if ($isStaff) {
    $sqlList = "SELECT u.id_usuarios, u.Nombre_completo, u.Rol
                FROM usuarios u
                LEFT JOIN pacientes p ON p.id_usuarios = u.id_usuarios
                WHERE p.id_pacientes IS NULL AND u.Rol = 'Paciente'
                ORDER BY u.Nombre_completo ASC";
    if ($res = $conexion->query($sqlList)) {
        while ($row = $res->fetch_assoc()) { $usuariosSinRegistro[] = $row; }
        $res->close();
    }
}

// Manejo de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF simple
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    // Elegir usuario a registrar según rol
    $post_uid = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
    $effectiveUserId = $user_id;
    if ($isStaff && $post_uid > 0) { $effectiveUserId = $post_uid; }

    // Obtener nombre desde BD del usuario objetivo
    $effectiveUserName = '';
    if ($st = $conexion->prepare('SELECT Nombre_completo, Rol FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
        $st->bind_param('i', $effectiveUserId);
        $st->execute();
        $st->bind_result($nmUser, $roleUser);
        if ($st->fetch()) { $effectiveUserName = $nmUser ?: ''; }
        $st->close();
    }
    if ($effectiveUserName === '') { $errores[] = 'Usuario objetivo inválido.'; }

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

    // Verificar si ya existe paciente para ese usuario
    if (empty($errores)) {
        if ($st = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1')) {
            $st->bind_param('i', $effectiveUserId);
            $st->execute();
            $st->store_result();
            if ($st->num_rows > 0) { $errores[] = 'Este usuario ya tiene registro de paciente.'; }
            $st->close();
        }
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

        // Insertar paciente (NOTA: la tabla pacientes no incluye campos clínicos como talla/peso/IMC)
        $sql = "INSERT INTO pacientes (id_usuarios, nombre_completo, DNI, fecha_nacimiento, edad, telefono) VALUES (?,?,?,?,?,?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('isssis', $effectiveUserId, $effectiveUserName, $dni, $fecha_nacimiento, $edad, $telefono);
            if ($stmt->execute()) {
                $nuevoPacienteId = $stmt->insert_id;
                $exito = 'Paciente registrado correctamente.';
                // Insertar registro inicial de expediente si hay al menos un dato clínico
                $hayClinicos = ($talla !== '' || $peso !== '' || $estatura !== '' || $masa_muscular !== '' || $enfermedades_base !== '' || $medicamentos !== '' || $imc !== null);
                if ($hayClinicos) {
                    $sqlExp = "INSERT INTO expediente (id_pacientes, talla, peso, estatura, IMC, masa_muscular, enfermedades_base, medicamentos) VALUES (?,?,?,?,?,?,?,?)";
                    if ($stmtExp = $conexion->prepare($sqlExp)) {
                        // Usamos valores NULL cuando estén vacíos para claridad
                        $valTalla = ($talla !== '') ? $talla : null;
                        $valPeso = ($peso !== '') ? $peso : null;
                        $valEst = ($estatura !== '') ? $estatura : null;
                        $valIMC = ($imc !== null) ? $imc : null;
                        $valMasa = ($masa_muscular !== '') ? $masa_muscular : null;
                        $valEnf = ($enfermedades_base !== '') ? $enfermedades_base : null;
                        $valMed = ($medicamentos !== '') ? $medicamentos : null;
                        $stmtExp->bind_param('isssssss', $nuevoPacienteId, $valTalla, $valPeso, $valEst, $valIMC, $valMasa, $valEnf, $valMed);
                        if ($stmtExp->execute()) {
                            $exito .= ' Se creó expediente inicial.';
                        } else {
                            $errores[] = 'Paciente creado pero fallo al crear expediente: ' . $stmtExp->error;
                        }
                        $stmtExp->close();
                    }
                }
                // PRG: almacenar mensaje y redirigir para refrescar lista y evitar reenvío
                $_SESSION['flash_success'] = $exito;
                header('Location: Registropacientes.php');
                exit;
            } else {
                $errores[] = 'Error al guardar paciente: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errores[] = 'Error preparando inserción de paciente: ' . $conexion->error;
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Recuperar mensaje de éxito tras redirección (PRG)
if (isset($_SESSION['flash_success'])) {
    $exito = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

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
            background-color: #198754;
            border-color: #198754;
        }
        .btn-primary:hover {
            background-color: #146c43;
            border-color: #13653f;
        }
        .bg-primary {
            background-color: #198754 !important;
        }
        .form-label {
            font-weight: 600;
            color: #198754;
        }
        .alert {
            border-radius: 0.375rem;
        }
        .header-section {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
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
        /* Filtros Style */
        .filtros-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        .filtros-titulo {
            font-size: 1rem;
            font-weight: 600;
            color: #198754;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filtro-grupo {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filtro-grupo label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
        }
        .filtro-grupo input,
        .filtro-grupo select {
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95rem;
            background: white;
            color: #212529;
        }
        .filtro-grupo input::placeholder {
            color: #999;
        }
        .filtro-grupo input:focus,
        .filtro-grupo select:focus {
            border-color: #198754;
            outline: none;
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
        }
        .filtro-botones {
            display: flex;
            gap: 8px;
        }
        .filtro-botones button {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            font-size: 0.95rem;
        }
        .btn-filtrar {
            background: #198754;
            color: white;
        }
        .btn-filtrar:hover {
            background: #146c43;
        }
        .btn-limpiar {
            background: #e9ecef;
            color: #495057;
        }
        .btn-limpiar:hover {
            background: #dee2e6;
        }
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 768px) {
            .filtros-grid {
                grid-template-columns: 1fr;
            }
            .filtro-botones {
                flex-direction: column;
                width: 100%;
            }
            .filtro-botones button {
                width: 100%;
            }
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
        <?php if ($isStaff): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Usuarios sin registro de paciente</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($usuariosSinRegistro)): ?>
                        <div class="p-3 text-muted">Todos los usuarios Paciente ya tienen registro.</div>
                    <?php else: ?>
                        <!-- Filtros -->
                        <div class="filtros-card">
                            <div class="filtros-titulo">
                                <i class="bi bi-funnel"></i>Filtros de Usuario
                            </div>
                            <div class="filtros-grid">
                                <div class="filtro-grupo">
                                    <label for="filterNombreReg">
                                        <i class="bi bi-search"></i> Nombre
                                    </label>
                                    <input type="text" id="filterNombreReg" class="form-control" placeholder="Buscar por nombre...">
                                </div>
                                <div class="filtro-botones">
                                    <button class="btn-filtrar" onclick="aplicarFiltrosReg()">
                                        <i class="bi bi-funnel me-1"></i>Filtrar
                                    </button>
                                    <button class="btn-limpiar" onclick="limpiarFiltrosReg()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 enhance-table external-filter" id="usuariosRegTable">
                                <thead>
                                    <tr>
                                        <th style="width:60px;">ID</th>
                                        <th>Nombre</th>
                                        <th style="width:140px;">Rol</th>
                                        <th style="width:180px;" class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuariosSinRegistro as $u): ?>
                                        <tr>
                                            <td><?= (int)$u['id_usuarios'] ?></td>
                                            <td><?= htmlspecialchars($u['Nombre_completo'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($u['Rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-primary" href="Registropacientes.php?uid=<?= (int)$u['id_usuarios'] ?>">
                                                    <i class="bi bi-person-plus me-1"></i> Registrar paciente
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
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
                    <?php if ($isStaff): ?>
                        <input type="hidden" name="uid" value="<?= (int)$targetUserId ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nombre_completo" class="form-label">
                            <i class="bi bi-person me-1"></i>Nombre completo
                        </label>
                        <input type="text" class="form-control" id="nombre_completo" value="<?= htmlspecialchars($targetUserName, ENT_QUOTES, 'UTF-8') ?>" readonly>
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
        // Filtros para tabla de usuarios sin registro
        function aplicarFiltrosReg() {
            const nombre = document.getElementById('filterNombreReg').value.toLowerCase();
            
            const tabla = document.getElementById('usuariosRegTable');
            if (!tabla) return;
            
            const filas = tabla.querySelectorAll('tbody tr');
            
            filas.forEach(fila => {
                const nombreFila = fila.querySelector('td:nth-child(2)').textContent.toLowerCase();
                
                let mostrar = true;
                
                if (nombre && !nombreFila.includes(nombre)) {
                    mostrar = false;
                }
                
                fila.style.display = mostrar ? '' : 'none';
            });
        }
        
        function limpiarFiltrosReg() {
            document.getElementById('filterNombreReg').value = '';
            
            const tabla = document.getElementById('usuariosRegTable');
            if (!tabla) return;
            const filas = tabla.querySelectorAll('tbody tr');
            filas.forEach(fila => {
                fila.style.display = '';
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const inputNombre = document.getElementById('filterNombreReg');
            if (inputNombre) {
                inputNombre.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') aplicarFiltrosReg();
                });
            }
        });
        
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

    <script src="assets/js/script.js"></script>
</body>
</html>