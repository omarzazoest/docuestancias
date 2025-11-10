<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user_data.php';
require_once '../includes/user_data_backup.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['matricula'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $matricula = sanitizar($_POST['matricula']);
    
    if (empty($matricula)) {
        $error = 'Por favor ingresa tu matrícula';
    } elseif (!validarMatricula($matricula)) {
        $error = 'La matrícula debe tener 10 dígitos';
    } else {
        try {
            // Verificar si el alumno existe en la base de datos
            $query = "SELECT * FROM alumnos WHERE matricula = :matricula";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':matricula', $matricula);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // El alumno existe, cargar todos los datos y iniciar sesión
                error_log("Intentando cargar datos para matrícula: " . $matricula);
                
                // Intentar cargar datos completos, si falla usar versión básica
                if (cargarDatosCompletos($matricula, $db) || cargarDatosCompletosSeguro($matricula, $db)) {
                    error_log("Datos cargados exitosamente para: " . $matricula);
                    header('Location: dashboard.php');
                    exit();
                } else {
                    error_log("Error al cargar datos para matrícula: " . $matricula);
                    $error = 'Error al cargar los datos del usuario. Inténtalo de nuevo.';
                }
            } else {
                // El alumno no existe, redirigir al registro
                $_SESSION['matricula_temp'] = $matricula;
                header('Location: registro.php');
                exit();
            }
        } catch(PDOException $exception) {
            $error = 'Error en el sistema. Inténtalo más tarde.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Estancias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Sistema de Gestión de Estancias</h3>
                        <p class="mb-0">Ingresa tu matrícula para continuar</p>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="matricula" class="form-label">Matrícula</label>
                                <input type="text" class="form-control" id="matricula" name="matricula" 
                                       placeholder="Ingresa tu matrícula de 10 dígitos" required
                                       pattern="[0-9]{10}" title="La matrícula debe contener exactamente 10 dígitos"
                                       maxlength="10">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Ingresar</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small class="text-muted">
                            Si no tienes cuenta, se creará automáticamente después de ingresar tu matrícula
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>