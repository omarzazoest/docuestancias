<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user_data.php';

// Verificar que venga desde el login
if (!isset($_SESSION['matricula_temp'])) {
    header('Location: login.php');
    exit();
}

$matricula = $_SESSION['matricula_temp'];
$error = '';
$success = '';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombres = sanitizar($_POST['nombres']);
    $paterno = sanitizar($_POST['paterno']);
    $materno = sanitizar($_POST['materno']);
    $correo = sanitizar($_POST['correo_electronico']);
    $telefono = sanitizar($_POST['telefono']);
    $genero = sanitizar($_POST['genero']);
    
    // Validaciones básicas
    if (empty($nombres) || empty($paterno) || empty($materno) || empty($correo) || empty($telefono) || empty($genero)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido';
    } elseif (!preg_match('/^[0-9]{10,12}$/', $telefono)) {
        $error = 'El teléfono debe tener entre 10 y 12 dígitos';
    } else {
        try {
            // Insertar nuevo alumno
            $query = "INSERT INTO alumnos (matricula, nombres, paterno, materno, correo_electronico, telefono, genero) 
                     VALUES (:matricula, :nombres, :paterno, :materno, :correo, :telefono, :genero)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':matricula', $matricula);
            $stmt->bindParam(':nombres', $nombres);
            $stmt->bindParam(':paterno', $paterno);
            $stmt->bindParam(':materno', $materno);
            $stmt->bindParam(':correo', $correo);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':genero', $genero);
            
            if ($stmt->execute()) {
                // Limpiar matrícula temporal
                unset($_SESSION['matricula_temp']);
                
                // Cargar todos los datos y crear sesión completa
                if (cargarDatosCompletos($matricula, $db)) {
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Error al cargar los datos del usuario';
                }
            } else {
                $error = 'Error al registrar el alumno. Inténtalo más tarde.';
            }
        } catch(PDOException $exception) {
            if ($exception->getCode() == 23000) {
                $error = 'La matrícula ya está registrada';
            } else {
                $error = 'Error en el sistema. Inténtalo más tarde.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Estancias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Registro de Nuevo Alumno</h3>
                        <p class="mb-0">Matrícula: <strong><?php echo htmlspecialchars($matricula); ?></strong></p>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nombres" class="form-label">Nombres *</label>
                                        <input type="text" class="form-control" id="nombres" name="nombres" 
                                               value="<?php echo isset($_POST['nombres']) ? htmlspecialchars($_POST['nombres']) : ''; ?>"
                                               required maxlength="50">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="paterno" class="form-label">Apellido Paterno *</label>
                                        <input type="text" class="form-control" id="paterno" name="paterno" 
                                               value="<?php echo isset($_POST['paterno']) ? htmlspecialchars($_POST['paterno']) : ''; ?>"
                                               required maxlength="50">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="materno" class="form-label">Apellido Materno *</label>
                                        <input type="text" class="form-control" id="materno" name="materno" 
                                               value="<?php echo isset($_POST['materno']) ? htmlspecialchars($_POST['materno']) : ''; ?>"
                                               required maxlength="50">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="genero" class="form-label">Género *</label>
                                        <select class="form-select" id="genero" name="genero" required>
                                            <option value="">Selecciona una opción</option>
                                            <option value="Masculino" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="Femenino" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="correo_electronico" class="form-label">Correo Electrónico *</label>
                                        <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" 
                                               value="<?php echo isset($_POST['correo_electronico']) ? htmlspecialchars($_POST['correo_electronico']) : ''; ?>"
                                               required maxlength="60">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Teléfono *</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" 
                                               value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>"
                                               placeholder="10-12 dígitos" required pattern="[0-9]{10,12}" maxlength="12">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-secondary w-100" onclick="window.location.href='login.php'">
                                        Volver al Login
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-primary w-100">Registrar y Continuar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">* Campos obligatorios</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>