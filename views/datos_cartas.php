<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user_data_backup.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['matricula'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$matricula = $_SESSION['matricula'];
$error = '';
$success = '';

// Verificar si ya tiene datos registrados
$datos_existentes = null;
try {
    $query = "SELECT * FROM datos_cartas WHERE matricula_estudiante = :matricula ORDER BY id_carta DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':matricula', $matricula);
    $stmt->execute();
    $datos_existentes = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error consultando datos_cartas: " . $e->getMessage());
    // Continuar sin datos existentes
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitizar datos básicos del formulario
    $nombres_estudiante = sanitizar($_POST['nombres_estudiante'] ?? '');
    $paterno_estudiante = sanitizar($_POST['paterno_estudiante'] ?? '');
    $materno_estudiante = sanitizar($_POST['materno_estudiante'] ?? '');
    $nombre_carrera = sanitizar($_POST['nombre_carrera'] ?? '');
    $estancia_estadia = sanitizar($_POST['estancia_estadia'] ?? '');
    $periodo = sanitizar($_POST['periodo'] ?? '');
    $horas = (int)($_POST['horas'] ?? 0);
    $nombre_empresa = sanitizar($_POST['nombre_empresa'] ?? '');
    $correo_electronico_estudiante = sanitizar($_POST['correo_electronico_estudiante'] ?? '');
    
    // Campos opcionales
    $nombres_asesor_academico = sanitizar($_POST['nombres_asesor_academico'] ?? '');
    $paterno_asesor_academico = sanitizar($_POST['paterno_asesor_academico'] ?? '');
    $materno_asesor_academico = sanitizar($_POST['materno_asesor_academico'] ?? '');
    $nombres_asesor_organizacion = sanitizar($_POST['nombres_asesor_organizacion'] ?? '');
    $paterno_asesor_organizacion = sanitizar($_POST['paterno_asesor_organizacion'] ?? '');
    $materno_asesor_organizacion = sanitizar($_POST['materno_asesor_organizacion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;
    
    // Validaciones básicas
    if (empty($nombres_estudiante) || empty($paterno_estudiante) || empty($materno_estudiante)) {
        $error = 'Los nombres y apellidos del estudiante son obligatorios';
    } elseif (empty($nombre_carrera)) {
        $error = 'La carrera es obligatoria';
    } elseif (empty($estancia_estadia)) {
        $error = 'El tipo de estancia/estadía es obligatorio';
    } elseif (empty($periodo)) {
        $error = 'El período es obligatorio';
    } elseif ($horas < 1) {
        $error = 'Las horas deben ser un número positivo';
    } elseif (empty($nombre_empresa)) {
        $error = 'El nombre de la empresa es obligatorio';
    } elseif (empty($correo_electronico_estudiante) || !filter_var($correo_electronico_estudiante, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico del estudiante es obligatorio y debe ser válido';
    } else {
        try {
            if ($datos_existentes) {
                // Actualizar registro existente
                $query = "UPDATE datos_cartas SET 
                    nombres_estudiante = :nombres_estudiante,
                    paterno_estudiante = :paterno_estudiante,
                    materno_estudiante = :materno_estudiante,
                    nombre_carrera = :nombre_carrera,
                    `estancia-estadia` = :estancia_estadia,
                    periodo = :periodo,
                    horas = :horas,
                    nombre_empresa = :nombre_empresa,
                    correo_electronico_estudiante = :correo_electronico_estudiante,
                    nombres_asesor_academico = :nombres_asesor_academico,
                    paterno_asesor_academico = :paterno_asesor_academico,
                    materno_asesor_academico = :materno_asesor_academico,
                    nombres_asesor_organizacion = :nombres_asesor_organizacion,
                    paterno_asesor_organizacion = :paterno_asesor_organizacion,
                    materno_asesor_organizacion = :materno_asesor_organizacion,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin
                    WHERE matricula_estudiante = :matricula_estudiante";
            } else {
                // Insertar nuevo registro
                $query = "INSERT INTO datos_cartas (
                    nombres_estudiante, matricula_estudiante, paterno_estudiante, materno_estudiante,
                    nombre_carrera, `estancia-estadia`, periodo, horas, nombre_empresa,
                    correo_electronico_estudiante, nombres_asesor_academico, paterno_asesor_academico,
                    materno_asesor_academico, nombres_asesor_organizacion, paterno_asesor_organizacion,
                    materno_asesor_organizacion, fecha_inicio, fecha_fin
                ) VALUES (
                    :nombres_estudiante, :matricula_estudiante, :paterno_estudiante, :materno_estudiante,
                    :nombre_carrera, :estancia_estadia, :periodo, :horas, :nombre_empresa,
                    :correo_electronico_estudiante, :nombres_asesor_academico, :paterno_asesor_academico,
                    :materno_asesor_academico, :nombres_asesor_organizacion, :paterno_asesor_organizacion,
                    :materno_asesor_organizacion, :fecha_inicio, :fecha_fin
                )";
            }

            $stmt = $db->prepare($query);
            
            // Bind parameters
            $stmt->bindParam(':nombres_estudiante', $nombres_estudiante);
            $stmt->bindParam(':matricula_estudiante', $matricula);
            $stmt->bindParam(':paterno_estudiante', $paterno_estudiante);
            $stmt->bindParam(':materno_estudiante', $materno_estudiante);
            $stmt->bindParam(':nombre_carrera', $nombre_carrera);
            $stmt->bindParam(':estancia_estadia', $estancia_estadia);
            $stmt->bindParam(':periodo', $periodo);
            $stmt->bindParam(':horas', $horas);
            $stmt->bindParam(':nombre_empresa', $nombre_empresa);
            $stmt->bindParam(':correo_electronico_estudiante', $correo_electronico_estudiante);
            $stmt->bindParam(':nombres_asesor_academico', $nombres_asesor_academico);
            $stmt->bindParam(':paterno_asesor_academico', $paterno_asesor_academico);
            $stmt->bindParam(':materno_asesor_academico', $materno_asesor_academico);
            $stmt->bindParam(':nombres_asesor_organizacion', $nombres_asesor_organizacion);
            $stmt->bindParam(':paterno_asesor_organizacion', $paterno_asesor_organizacion);
            $stmt->bindParam(':materno_asesor_organizacion', $materno_asesor_organizacion);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);

            if ($stmt->execute()) {
                $success = $datos_existentes ? 'Datos actualizados correctamente' : 'Datos guardados correctamente';
                
                // Recargar datos existentes
                try {
                    $stmt = $db->prepare("SELECT * FROM datos_cartas WHERE matricula_estudiante = :matricula ORDER BY id_carta DESC LIMIT 1");
                    $stmt->bindParam(':matricula', $matricula);
                    $stmt->execute();
                    $datos_existentes = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Actualizar datos en la sesión si las funciones existen
                    if (function_exists('actualizarDatosSesion')) {
                        actualizarDatosSesion('datos_cartas', $datos_existentes);
                    } else {
                        $_SESSION['datos_cartas'] = $datos_existentes;
                    }
                } catch (Exception $e) {
                    error_log("Error recargando datos: " . $e->getMessage());
                }
            } else {
                $error = 'Error al guardar los datos. Inténtalo más tarde.';
            }
        } catch(PDOException $exception) {
            $error = 'Error en el sistema: ' . $exception->getMessage();
            error_log("Error guardando datos_cartas: " . $exception->getMessage());
        }
    }
}

// Si existen datos, usarlos para prellenar el formulario
$form_data = $datos_existentes ?: [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos para Cartas - Sistema de Estancias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>
                Volver al Dashboard
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2>Datos para Cartas Oficiales</h2>
                <p class="text-muted">Completa todos los datos necesarios para generar las cartas de tu estancia.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Información del Estudiante -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-user-graduate me-2"></i>Información del Estudiante</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="nombres_estudiante" class="form-label">Nombres *</label>
                                <input type="text" class="form-control" id="nombres_estudiante" name="nombres_estudiante" 
                                       value="<?php echo htmlspecialchars($form_data['nombres_estudiante'] ?? $_SESSION['nombres']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="paterno_estudiante" class="form-label">Apellido Paterno *</label>
                                <input type="text" class="form-control" id="paterno_estudiante" name="paterno_estudiante" 
                                       value="<?php echo htmlspecialchars($form_data['paterno_estudiante'] ?? $_SESSION['paterno']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="materno_estudiante" class="form-label">Apellido Materno *</label>
                                <input type="text" class="form-control" id="materno_estudiante" name="materno_estudiante" 
                                       value="<?php echo htmlspecialchars($form_data['materno_estudiante'] ?? $_SESSION['materno']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="correo_electronico_estudiante" class="form-label">Correo Electrónico *</label>
                                <input type="email" class="form-control" id="correo_electronico_estudiante" name="correo_electronico_estudiante" 
                                       value="<?php echo htmlspecialchars($form_data['correo_electronico_estudiante'] ?? $_SESSION['correo_electronico']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="genero_estudiante" class="form-label">Género</label>
                                <select class="form-select" id="genero_estudiante" name="genero_estudiante">
                                    <option value="">Seleccionar</option>
                                    <option value="masculino" <?php echo (isset($form_data['genero_estudiante']) && $form_data['genero_estudiante'] == 'masculino') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="femenino" <?php echo (isset($form_data['genero_estudiante']) && $form_data['genero_estudiante'] == 'femenino') ? 'selected' : ''; ?>>Femenino</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="cuatrimestre" class="form-label">Cuatrimestre</label>
                                <select class="form-select" id="cuatrimestre" name="cuatrimestre">
                                    <option value="">Seleccionar</option>
                                    <?php for($i = 1; $i <= 11; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo (isset($form_data['cuatrimestre']) && $form_data['cuatrimestre'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?>° Cuatrimestre</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Académica -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-graduation-cap me-2"></i>Información Académica</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nombre_carrera" class="form-label">Carrera *</label>
                                <select class="form-select" id="nombre_carrera" name="nombre_carrera" required>
                                    <option value="">Seleccionar carrera</option>
                                    <option value="Ingenieria en tecnologias de la información" <?php echo (isset($form_data['nombre_carrera']) && $form_data['nombre_carrera'] == 'Ingenieria en tecnologias de la información') ? 'selected' : ''; ?>>Ingeniería en Tecnologías de la Información</option>
                                    <option value="Ingeniería industrial" <?php echo (isset($form_data['nombre_carrera']) && $form_data['nombre_carrera'] == 'Ingeniería industrial') ? 'selected' : ''; ?>>Ingeniería Industrial</option>
                                    <option value="Licenciatura en administración de empresas" <?php echo (isset($form_data['nombre_carrera']) && $form_data['nombre_carrera'] == 'Licenciatura en administración de empresas') ? 'selected' : ''; ?>>Licenciatura en Administración de Empresas</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="estancia_estadia" class="form-label">Tipo *</label>
                                <select class="form-select" id="estancia_estadia" name="estancia_estadia" required>
                                    <option value="">Seleccionar</option>
                                    <option value="estancia" <?php echo (isset($form_data['estancia-estadia']) && $form_data['estancia-estadia'] == 'estancia') ? 'selected' : ''; ?>>Estancia</option>
                                    <option value="estadia" <?php echo (isset($form_data['estancia-estadia']) && $form_data['estancia-estadia'] == 'estadia') ? 'selected' : ''; ?>>Estadía</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="periodo" class="form-label">Período *</label>
                                <input type="text" class="form-control" id="periodo" name="periodo" 
                                       placeholder="Ej: 2025-1" value="<?php echo htmlspecialchars($form_data['periodo'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="horas" class="form-label">Horas *</label>
                                <input type="number" class="form-control" id="horas" name="horas" min="1" 
                                       value="<?php echo htmlspecialchars($form_data['horas'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?php echo htmlspecialchars($form_data['fecha_inicio'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                       value="<?php echo htmlspecialchars($form_data['fecha_fin'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="competencias_profesionales" class="form-label">Competencias Profesionales</label>
                        <textarea class="form-control" id="competencias_profesionales" name="competencias_profesionales" rows="3"
                                  placeholder="Describe las competencias profesionales que desarrollarás"><?php echo htmlspecialchars($form_data['competencias_profesionales'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Información de la Empresa -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-building me-2"></i>Información de la Empresa/Organización</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="nombre_empresa" class="form-label">Nombre de la Empresa/Organización *</label>
                                <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" 
                                       value="<?php echo htmlspecialchars($form_data['nombre_empresa'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="contacto_organizacion" class="form-label">Persona de Contacto</label>
                                <input type="text" class="form-control" id="contacto_organizacion" name="contacto_organizacion" 
                                       value="<?php echo htmlspecialchars($form_data['contacto_organizacion'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="puesto_contacto" class="form-label">Puesto del Contacto</label>
                        <input type="text" class="form-control" id="puesto_contacto" name="puesto_contacto" 
                               value="<?php echo htmlspecialchars($form_data['puesto_contacto'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Asesor Académico -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chalkboard-teacher me-2"></i>Asesor Académico</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="nombres_asesor_academico" class="form-label">Nombres *</label>
                                <input type="text" class="form-control" id="nombres_asesor_academico" name="nombres_asesor_academico" 
                                       value="<?php echo htmlspecialchars($form_data['nombres_asesor_academico'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="paterno_asesor_academico" class="form-label">Apellido Paterno *</label>
                                <input type="text" class="form-control" id="paterno_asesor_academico" name="paterno_asesor_academico" 
                                       value="<?php echo htmlspecialchars($form_data['paterno_asesor_academico'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="materno_asesor_academico" class="form-label">Apellido Materno *</label>
                                <input type="text" class="form-control" id="materno_asesor_academico" name="materno_asesor_academico" 
                                       value="<?php echo htmlspecialchars($form_data['materno_asesor_academico'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="genero_asesor_academico" class="form-label">Género</label>
                                <select class="form-select" id="genero_asesor_academico" name="genero_asesor_academico">
                                    <option value="">Seleccionar</option>
                                    <option value="masculino" <?php echo (isset($form_data['genero_asesor_academico']) && $form_data['genero_asesor_academico'] == 'masculino') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="femenino" <?php echo (isset($form_data['genero_asesor_academico']) && $form_data['genero_asesor_academico'] == 'femenino') ? 'selected' : ''; ?>>Femenino</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="correo_asesor_academico" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="correo_asesor_academico" name="correo_asesor_academico" 
                                       value="<?php echo htmlspecialchars($form_data['correo_asesor_academico'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="telefono_asesor_academico" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono_asesor_academico" name="telefono_asesor_academico" 
                                       value="<?php echo htmlspecialchars($form_data['telefono_asesor_academico'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asesor de la Organización -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-user-tie me-2"></i>Asesor de la Organización</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="nombres_asesor_organizacion" class="form-label">Nombres *</label>
                                <input type="text" class="form-control" id="nombres_asesor_organizacion" name="nombres_asesor_organizacion" 
                                       value="<?php echo htmlspecialchars($form_data['nombres_asesor_organizacion'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="paterno_asesor_organizacion" class="form-label">Apellido Paterno *</label>
                                <input type="text" class="form-control" id="paterno_asesor_organizacion" name="paterno_asesor_organizacion" 
                                       value="<?php echo htmlspecialchars($form_data['paterno_asesor_organizacion'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="materno_asesor_organizacion" class="form-label">Apellido Materno *</label>
                                <input type="text" class="form-control" id="materno_asesor_organizacion" name="materno_asesor_organizacion" 
                                       value="<?php echo htmlspecialchars($form_data['materno_asesor_organizacion'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="genero_asesor_organizacion" class="form-label">Género</label>
                                <select class="form-select" id="genero_asesor_organizacion" name="genero_asesor_organizacion">
                                    <option value="">Seleccionar</option>
                                    <option value="masculino" <?php echo (isset($form_data['genero_asesor_organizacion']) && $form_data['genero_asesor_organizacion'] == 'masculino') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="femenino" <?php echo (isset($form_data['genero_asesor_organizacion']) && $form_data['genero_asesor_organizacion'] == 'femenino') ? 'selected' : ''; ?>>Femenino</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de la División -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-university me-2"></i>Información de la División</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nombre_division" class="form-label">Nombre de la División</label>
                                <input type="text" class="form-control" id="nombre_division" name="nombre_division" 
                                       value="<?php echo htmlspecialchars($form_data['nombre_division'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="correo_division" class="form-label">Correo de la División</label>
                                <input type="email" class="form-control" id="correo_division" name="correo_division" 
                                       value="<?php echo htmlspecialchars($form_data['correo_division'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="director_division" class="form-label">Director de la División</label>
                                <input type="text" class="form-control" id="director_division" name="director_division" 
                                       value="<?php echo htmlspecialchars($form_data['director_division'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="genero_director" class="form-label">Género del Director</label>
                                <select class="form-select" id="genero_director" name="genero_director">
                                    <option value="">Seleccionar</option>
                                    <option value="masculino" <?php echo (isset($form_data['genero_director']) && $form_data['genero_director'] == 'masculino') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="femenino" <?php echo (isset($form_data['genero_director']) && $form_data['genero_director'] == 'femenino') ? 'selected' : ''; ?>>Femenino</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="telefono_director" class="form-label">Teléfono del Director</label>
                                <input type="tel" class="form-control" id="telefono_director" name="telefono_director" 
                                       value="<?php echo htmlspecialchars($form_data['telefono_director'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="correo_director" class="form-label">Correo del Director</label>
                        <input type="email" class="form-control" id="correo_director" name="correo_director" 
                               value="<?php echo htmlspecialchars($form_data['correo_director'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <button type="button" class="btn btn-secondary w-100" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                    </button>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>
                        <?php echo $datos_existentes ? 'Actualizar Datos' : 'Guardar Datos'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>