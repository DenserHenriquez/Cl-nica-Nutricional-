<?php
require_once 'db_connection.php';

// CONSULTA PACIENTES + USUARIOS
$sql = "SELECT p.id_paciente, u.id_usuarios, u.Nombre_completo, u.Correo_electronico,
               p.fecha_nacimiento, p.telefono, p.direccion, p.ocupacion, p.peso, p.talla, p.IMC,
               p.patologias, p.medicamentos, p.estado
        FROM pacientes p
        INNER JOIN usuarios u ON p.id_usuarios = u.id_usuarios
        ORDER BY u.Nombre_completo ASC";

$resultado = $conexion->query($sql);
if (!$resultado) {
    die("Error en la consulta SQL: " . $conexion->error);
}
$total_entradas = $resultado->num_rows;
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lista de Pacientes - Nutrición</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    margin:0; 
    padding:0; 
    background: url('fondo_nutricional.jpg') center center fixed; 
    background-size: cover; 
    background-color: #f7fbff;
}
h1 { color:#0d47a1; text-align:center; margin-top:20px; text-shadow: 1px 1px 3px rgba(0,0,0,0.1); }

.main-content { 
    width:95%; 
    max-width: 1400px;
    margin:20px auto; 
    background:rgba(255, 255, 255, 0.95); 
    border-radius:10px; 
    padding:20px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.2); 
    transition:0.3s; 
}
.main-content:hover { box-shadow: 0 6px 25px rgba(0,0,0,0.25); }

.table-wrapper { overflow-x:auto; margin-top:20px; }
table { width:100%; border-collapse: collapse; font-size:14px; min-width:1200px; }
th, td { padding:12px 15px; text-align:left; }
th { background: linear-gradient(90deg, #1e88e5, #42a5f5); color:white; font-weight:600; }
tr:nth-child(even) { background:#f1f8ff; }
tr:hover { background:#d0e4ff; transition:0.3s; border-left: 4px solid #1e88e5; }

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

/* Botón Menu Principal modificado */
.menu-btn {
    display:inline-block; 
    margin-bottom:15px; 
    padding:12px 25px; 
    background: #0d47a1;  /* Azul más intenso */
    color:white; 
    text-decoration:none; 
    border-radius:10px; 
    font-weight:bold; 
    font-size:16px;
    transition:0.3s; 
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.menu-btn:hover { background:#1565c0; }

@media(max-width:768px) {
    th, td { font-size:12px; padding:8px; }
}
</style>
</head>
<body>

<div class="main-content">
    <a href="Menuprincipal.php" class="menu-btn"><i class="fas fa-arrow-left"></i> Menú Principal</a>
    <h1>Lista de Pacientes</h1>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID Usuario</th>
                    <th>Nombre Completo</th>
                    <th>Correo</th>
                    <th>Fecha Nac.</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th>Ocupación</th>
                    <th>Peso</th>
                    <th>Talla</th>
                    <th>IMC</th>
                    <th>Patologías</th>
                    <th>Medicamentos</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($total_entradas > 0) {
                while($fila = $resultado->fetch_assoc()) {
                    $estado_clase = 'estado-' . htmlspecialchars($fila['estado']);
                    $is_checked = ($fila['estado']=='Activo') ? 'checked' : '';
                    
                    echo "<tr>";
                    echo "<td>".htmlspecialchars($fila['id_usuarios'])."</td>";
                    echo "<td>".htmlspecialchars($fila['Nombre_completo'])."</td>";
                    echo "<td>".htmlspecialchars($fila['Correo_electronico'])."</td>";
                    echo "<td>".htmlspecialchars($fila['fecha_nacimiento'])."</td>";
                    echo "<td>".htmlspecialchars($fila['telefono'])."</td>";
                    echo "<td>".htmlspecialchars($fila['direccion'])."</td>";
                    echo "<td>".htmlspecialchars($fila['ocupacion'])."</td>";
                    echo "<td>".htmlspecialchars($fila['peso'])."</td>";
                    echo "<td>".htmlspecialchars($fila['talla'])."</td>";
                    echo "<td>".htmlspecialchars($fila['IMC'])."</td>";
                    echo "<td>".htmlspecialchars($fila['patologias'])."</td>";
                    echo "<td>".htmlspecialchars($fila['medicamentos'])."</td>";
                    echo "<td class='estado-text {$estado_clase}'>".htmlspecialchars($fila['estado'])."</td>";
                    echo "<td>
                            <label class='switch'>
                                <input type='checkbox' class='estado-switch' 
                                       data-id='".$fila['id_paciente']."' 
                                       {$is_checked}>
                                <span class='slider round'></span>
                            </label>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='14' style='text-align:center;'>No se encontraron pacientes.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.estado-switch').forEach(function(switchEl) {
    switchEl.addEventListener('change', function() {
        const id = this.dataset.id;
        const estado = this.checked ? 'Activo' : 'Inactivo';
        const tdEstado = this.closest('tr').querySelector('.estado-text');

        tdEstado.textContent = estado;
        tdEstado.classList.remove('estado-Activo', 'estado-Inactivo');
        tdEstado.classList.add('estado-' + estado);
        
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "cambiar_estado_paciente.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onload = function() {
            if(xhr.status !== 200) {
                alert("Error al cambiar estado del paciente. Revirtiendo cambio visual.");
                switchEl.checked = !switchEl.checked; 
                const oldEstado = switchEl.checked ? 'Activo' : 'Inactivo';
                tdEstado.textContent = oldEstado;
                tdEstado.classList.remove('estado-Activo', 'estado-Inactivo');
                tdEstado.classList.add('estado-' + oldEstado);
            }
        };
        xhr.send("id=" + id + "&estado=" + estado);
    });
});
</script>

</body>
</html>

<?php $conexion->close(); ?>
