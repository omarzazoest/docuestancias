<?php
// Archivo de prueba para verificar la conexión y datos

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Prueba de Conexión y Datos</h2>";

// Probar conexión
try {
    require_once '../config/database.php';
    echo "<p>✅ Archivo database.php cargado correctamente</p>";
    
    $database = new Database();
    $db = $database->getConnection();
    echo "<p>✅ Conexión a base de datos establecida</p>";
    
    // Probar consulta de alumnos
    $query = "SELECT COUNT(*) as total FROM alumnos";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>✅ Tabla 'alumnos' encontrada. Total de registros: " . $result['total'] . "</p>";
    
    // Probar consulta específica del usuario de prueba
    $matricula_prueba = '1323141370';
    $query = "SELECT * FROM alumnos WHERE matricula = :matricula";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':matricula', $matricula_prueba);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✅ Usuario de prueba encontrado:</p>";
        echo "<ul>";
        echo "<li>Matrícula: " . $alumno['matricula'] . "</li>";
        echo "<li>Nombre: " . $alumno['nombres'] . " " . $alumno['paterno'] . " " . $alumno['materno'] . "</li>";
        echo "<li>Email: " . $alumno['correo_electronico'] . "</li>";
        echo "</ul>";
    } else {
        echo "<p>❌ Usuario de prueba no encontrado</p>";
    }
    
    // Verificar tabla datos_cartas
    try {
        $query = "SELECT COUNT(*) as total FROM datos_cartas";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✅ Tabla 'datos_cartas' encontrada. Total de registros: " . $result['total'] . "</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Error con tabla 'datos_cartas': " . $e->getMessage() . "</p>";
    }
    
    // Probar funciones de carga
    echo "<h3>Probando funciones de carga de datos</h3>";
    
    require_once '../includes/user_data_backup.php';
    echo "<p>✅ Archivo user_data_backup.php cargado</p>";
    
    if (cargarDatosCompletosSeguro($matricula_prueba, $db)) {
        echo "<p>✅ Función cargarDatosCompletosSeguro() funciona correctamente</p>";
        echo "<p>Datos en sesión:</p>";
        echo "<ul>";
        echo "<li>Matrícula: " . ($_SESSION['matricula'] ?? 'No definida') . "</li>";
        echo "<li>Nombres: " . ($_SESSION['nombres'] ?? 'No definido') . "</li>";
        echo "<li>Datos cartas: " . (isset($_SESSION['datos_cartas']) ? 'Sí' : 'No') . "</li>";
        echo "<li>Estancias: " . count($_SESSION['estancias'] ?? []) . "</li>";
        echo "</ul>";
    } else {
        echo "<p>❌ Error en función cargarDatosCompletosSeguro()</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Detalles del error:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='login.php'>Volver al Login</a></p>";
?>