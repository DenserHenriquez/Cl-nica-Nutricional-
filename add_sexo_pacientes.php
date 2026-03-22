<?php
require_once 'db_connection.php';

echo "<h2>Agregar columna Sexo a tabla pacientes</h2>";

$check = $conexion->query("SHOW COLUMNS FROM pacientes LIKE 'sexo'");
if ($check->num_rows > 0) {
    echo "<div class='alert alert-info'>Columna 'sexo' ya existe en pacientes. ✅</div>";
    // Opcional: sincronizar con usuarios.sexo para existentes
    $sync = $conexion->query("UPDATE pacientes p JOIN usuarios u ON p.id_usuarios = u.id_usuarios SET p.sexo = u.sexo WHERE p.sexo != u.sexo OR p.sexo IS NULL");
    if ($sync) {
        $affected = $conexion->affected_rows;
        echo "<div class='alert alert-success'>Sincronizados $affected registros con usuarios.sexo.</div>";
    }
    $conexion->close();
    exit;
}

$sql = "ALTER TABLE pacientes 
        ADD COLUMN sexo ENUM('M','F') NOT NULL DEFAULT 'M' AFTER nombre_completo";

if ($conexion->query($sql) === TRUE) {
    echo "<div class='alert alert-success'>✅ Columna 'sexo' agregada exitosamente a tabla pacientes.</div>";
    echo "<p><strong>Detalles:</strong> ENUM('M','F') NOT NULL DEFAULT 'M' AFTER nombre_completo</p>";
    echo "<p>Usuarios existentes en pacientes mantienen DEFAULT 'M'.</p>";
    
    // Sincronizar inmediatamente con usuarios.sexo donde exista
    $syncSql = "UPDATE pacientes p JOIN usuarios u ON p.id_usuarios = u.id_usuarios SET p.sexo = u.sexo";
    if ($conexion->query($syncSql)) {
        $synced = $conexion->affected_rows;
        echo "<div class='alert alert-info'>Sincronizados $synced registros con sexo de usuarios.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>❌ Error: " . $conexion->error . "</div>";
}

$verify = $conexion->query("DESCRIBE pacientes");
if ($verify) {
    echo "<h3>Tabla pacientes actualizada:</h3>";
    echo "<div style='overflow-x:auto;'><table class='table table-striped' style='font-size:0.9em;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $verify->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table></div>";
}

$conexion->close();
echo "<p><a href='index.php' class='btn btn-primary'>Volver a Inicio</a> ";
echo "<a href='Registropacientes.php' class='btn btn-success'>Probar Registro Pacientes</a> ";
echo "<a href='Actualizar_perfil.php' class='btn btn-info'>Probar Actualizar Perfil</a></p>";

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
?>

