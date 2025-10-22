<?php
require_once 'db_connection.php';

// CONSULTA PACIENTES
$sql = "SELECT id_paciente, codigo, nombres, apellidos, sexo, edad, direccion, estado 
        FROM pacientes ORDER BY codigo ASC";
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
    <title>Lista de Pacientes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin:0; padding-top:50px; background:#f7f7f7;}
        .main-content { width:95%; margin:20px auto; background:white; border-radius:5px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
        table { width:100%; border-collapse: collapse; font-size:14px;}
        th, td { border:1px solid #ddd; padding:8px; text-align:left;}
        th { background:#f0f0f0;}
        tr:nth-child(even) { background:#f9f9f9;}
        /* Switch estilo iPhone */
        .switch { position: relative; display: inline-block; width: 50px; height: 24px;}
        .switch input { opacity: 0; width: 0; height: 0;}
        .slider { position: absolute; cursor: pointer; top:0; left:0; right:0; bottom:0; background-color:#ccc; transition:0.4s; border-radius:24px;}
        .slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; transition:0.4s; border-radius:50%;}
        input:checked + .slider { background-color:#28a745; }
        input:checked + .slider:before { transform: translateX(26px); }
    </style>
</head>
<body>
    <div class="main-content">
        <h1>Lista de Pacientes</h1>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Sexo</th>
                    <th>Edad</th>
                    <th>Dirección</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($total_entradas > 0) {
                while($fila = $resultado->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>".htmlspecialchars($fila['codigo'])."</td>";
                    echo "<td>".htmlspecialchars($fila['nombres'])."</td>";
                    echo "<td>".htmlspecialchars($fila['apellidos'])."</td>";
                    echo "<td>".htmlspecialchars($fila['sexo'])."</td>";
                    echo "<td>".htmlspecialchars($fila['edad'])."</td>";
                    echo "<td>".htmlspecialchars($fila['direccion'])."</td>";
                    echo "<td class='estado-text'>".htmlspecialchars($fila['estado'])."</td>";
                    echo "<td>
                        <label class='switch'>
                            <input type='checkbox' class='estado-switch' 
                                   data-id='".$fila['id_paciente']."' 
                                   ".(($fila['estado']=='Activo')?'checked':'').">
                            <span class='slider round'></span>
                        </label>
                    </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' style='text-align:center;'>No se encontraron pacientes.</td></tr>";
            }
            ?>
            </tbody>
        </table>
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

