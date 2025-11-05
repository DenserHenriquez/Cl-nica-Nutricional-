<?php
require_once 'db_connection.php';

// CONSULTA PACIENTES + USUARIOS
$sql = "SELECT p.id_pacientes, p.id_usuarios, p.nombre_completo, p.DNI, p.fecha_nacimiento, p.edad, p.telefono, p.estado,
               u.Nombre_completo as usuario_nombre, u.Correo_electronico
        FROM pacientes p
        INNER JOIN usuarios u ON p.id_usuarios = u.id_usuarios
        ORDER BY p.nombre_completo ASC";

$resultado = $conexion->query($sql);
if (!$resultado) {
    die("Error en la consulta SQL: " . $conexion->error);
}
$total_entradas = $resultado->num_rows;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lista de Pacientes - Nutrición</title>
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
        .estado-text { font-weight: bold; }
        .estado-Activo { color: #0d47a1; }
        .estado-Inactivo { color: #d32f2f; }

        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top:0; left:0; right:0; bottom:0; background-color:#ccc; transition:0.4s; border-radius:24px; }
        .slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; transition:0.4s; border-radius:50%; }
        input:checked + .slider { background-color:#1e88e5; }
        input:not(:checked) + .slider { background-color: #d32f2f; }
        input:checked + .slider:before { transform: translateX(26px); }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <h1>Lista de Pacientes</h1>
            <p>Administra el estado de los pacientes registrados en la clínica nutricional.</p>
            <a href="Menuprincipal.php" class="btn btn-light position-absolute top-50 end-0 translate-middle-y me-3">
                <i class="bi bi-house-door"></i> Menú Principal
            </a>
        </div>
    </div>

    <div class="container mb-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-list-check me-2"></i>Pacientes Registrados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Paciente</th>
                                <th>ID Usuario</th>
                                <th>Nombre Completo</th>
                                <th>DNI</th>
                                <th>Fecha Nac.</th>
                                <th>Edad</th>
                                <th>Teléfono</th>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if ($total_entradas > 0) {
                            while($fila = $resultado->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>".htmlspecialchars($fila['id_pacientes'])."</td>";
                                echo "<td>".htmlspecialchars($fila['id_usuarios'])."</td>";
                                echo "<td>".htmlspecialchars($fila['nombre_completo'])."</td>";
                                echo "<td>".htmlspecialchars($fila['DNI'])."</td>";
                                echo "<td>".htmlspecialchars($fila['fecha_nacimiento'])."</td>";
                                echo "<td>".htmlspecialchars($fila['edad'])."</td>";
                                echo "<td>".htmlspecialchars($fila['telefono'])."</td>";
                                echo "<td>".htmlspecialchars($fila['usuario_nombre'])."</td>";
                                echo "<td>".htmlspecialchars($fila['Correo_electronico'])."</td>";
                                echo "<td class='estado-text estado-".htmlspecialchars($fila['estado'])."'>".htmlspecialchars($fila['estado'])."</td>";
                                echo "<td>
                                    <label class='switch'>
                                        <input type='checkbox' class='estado-switch'
                                               data-id='".$fila['id_pacientes']."'
                                               ".(($fila['estado']=='Activo')?'checked':'').">
                                        <span class='slider round'></span>
                                    </label>
                                </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='11' class='text-center'>No se encontraron pacientes.</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
document.querySelectorAll('.estado-switch').forEach(function(switchEl) {
    switchEl.addEventListener('change', function() {
        const id = this.dataset.id;
        const estado = this.checked ? 'Activo' : 'Inactivo';
        const tdEstado = this.closest('tr').querySelector('.estado-text');

        // AJAX POST
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "cambiar_estado_paciente.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onload = function() {
            if(xhr.status === 200) {
                tdEstado.textContent = estado; // Actualizar columna Estado
                tdEstado.className = 'estado-text estado-' + estado; // Actualizar clase para color
            } else {
                alert("Error al cambiar estado del paciente");
                // Revertir switch si falla
                switchEl.checked = !switchEl.checked;
            }
        };
        xhr.send("id=" + id + "&estado=" + estado);
    });
});
</script>

</body>
</html>

<?php $conexion->close(); ?>