<?php
// Funciones de respaldo para cargar datos básicos del usuario

// Función simplificada para cargar solo datos básicos del alumno
function cargarDatosBasicos($matricula, $db) {
    try {
        // Solo cargar datos del alumno
        $query = "SELECT * FROM alumnos WHERE matricula = :matricula";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':matricula', $matricula);
        $stmt->execute();
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$alumno) {
            error_log("No se encontró alumno con matrícula: " . $matricula);
            return false;
        }
        
        // Guardar datos básicos en sesión
        $_SESSION['matricula'] = $alumno['matricula'];
        $_SESSION['id_alumno'] = $alumno['id_alumno'];
        $_SESSION['nombres'] = $alumno['nombres'];
        $_SESSION['paterno'] = $alumno['paterno'];
        $_SESSION['materno'] = $alumno['materno'];
        $_SESSION['correo_electronico'] = $alumno['correo_electronico'];
        $_SESSION['telefono'] = $alumno['telefono'];
        $_SESSION['genero'] = $alumno['genero'];
        
        // Guardar también en formato completo
        $_SESSION['alumno'] = $alumno;
        $_SESSION['datos_cartas'] = null;
        $_SESSION['estancias'] = [];
        $_SESSION['cooperaciones'] = [];
        $_SESSION['datos_cargados'] = true;
        $_SESSION['ultima_actualizacion'] = time();
        
        error_log("Datos básicos cargados para matrícula: " . $matricula);
        return true;
        
    } catch(PDOException $exception) {
        error_log("Error cargando datos básicos: " . $exception->getMessage());
        return false;
    }
}

// Función para verificar si una tabla existe
function tablaExiste($tabla, $db) {
    try {
        $query = "SHOW TABLES LIKE :tabla";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tabla', $tabla);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

// Función mejorada para cargar datos con verificaciones
function cargarDatosCompletosSeguro($matricula, $db) {
    try {
        // Primero cargar datos básicos
        if (!cargarDatosBasicos($matricula, $db)) {
            return false;
        }
        
        $alumno = $_SESSION['alumno'];
        
        // Intentar cargar datos_cartas si la tabla existe
        $datos_cartas = null;
        if (tablaExiste('datos_cartas', $db)) {
            try {
                $query = "SELECT * FROM datos_cartas WHERE matricula_estudiante = :matricula ORDER BY id_carta DESC LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':matricula', $matricula);
                $stmt->execute();
                $datos_cartas = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['datos_cartas'] = $datos_cartas ?: null;
            } catch(PDOException $e) {
                error_log("Error cargando datos_cartas: " . $e->getMessage());
            }
        }
        
        // Intentar cargar estancias si la tabla existe
        $estancias = [];
        if (tablaExiste('estancias', $db)) {
            try {
                $query = "SELECT * FROM estancias WHERE id_alumno = :id_alumno ORDER BY fecha_presentacion DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_alumno', $alumno['id_alumno']);
                $stmt->execute();
                $estancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $_SESSION['estancias'] = $estancias;
            } catch(PDOException $e) {
                error_log("Error cargando estancias: " . $e->getMessage());
            }
        }
        
        // Intentar cargar cooperaciones si la tabla existe
        $cooperaciones = [];
        if (tablaExiste('cooperacion', $db) && !empty($estancias)) {
            try {
                foreach ($estancias as $estancia) {
                    if (isset($estancia['id_cooperacion']) && $estancia['id_cooperacion']) {
                        $query = "SELECT * FROM cooperacion WHERE id_cooperacion = :id_cooperacion";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id_cooperacion', $estancia['id_cooperacion']);
                        $stmt->execute();
                        $cooperacion = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($cooperacion) {
                            $cooperaciones[$estancia['id_cooperacion']] = $cooperacion;
                        }
                    }
                }
                $_SESSION['cooperaciones'] = $cooperaciones;
            } catch(PDOException $e) {
                error_log("Error cargando cooperaciones: " . $e->getMessage());
            }
        }
        
        error_log("Datos completos cargados exitosamente para matrícula: " . $matricula);
        return true;
        
    } catch(Exception $e) {
        error_log("Error general cargando datos: " . $e->getMessage());
        return false;
    }
}

// Funciones de respaldo para compatibilidad con dashboard

// Función para verificar si los datos de cartas están completos (versión básica)
function datosCartasCompletosBasico() {
    if (!isset($_SESSION['datos_cartas']) || !$_SESSION['datos_cartas']) {
        return false;
    }
    
    $datos = $_SESSION['datos_cartas'];
    $campos_basicos = [
        'nombres_estudiante', 'paterno_estudiante', 'materno_estudiante',
        'nombre_carrera', 'nombre_empresa', 'correo_electronico_estudiante'
    ];
    
    foreach ($campos_basicos as $campo) {
        if (empty($datos[$campo])) {
            return false;
        }
    }
    
    return true;
}

// Función para verificar qué PDFs se pueden generar (versión básica)
function verificarPDFsDisponiblesBasico() {
    $pdfs_disponibles = [
        'carta_presentacion' => false,
        'carta_cooperacion' => false,
        'carta_termino' => false,
        'constancia_estancia' => false
    ];
    
    // Solo verificar si hay datos básicos
    if (isset($_SESSION['datos_cartas']) && $_SESSION['datos_cartas']) {
        $datos = $_SESSION['datos_cartas'];
        
        // Verificar carta de presentación (solo datos básicos)
        if (!empty($datos['nombres_estudiante']) && !empty($datos['nombre_empresa'])) {
            $pdfs_disponibles['carta_presentacion'] = true;
            $pdfs_disponibles['carta_cooperacion'] = true;
        }
        
        // Verificar carta de término (con fechas)
        if (!empty($datos['fecha_inicio']) && !empty($datos['fecha_fin'])) {
            $pdfs_disponibles['carta_termino'] = true;
        }
        
        // Constancia solo si hay estancias
        if (!empty($_SESSION['estancias'])) {
            $pdfs_disponibles['constancia_estancia'] = true;
        }
    }
    
    return $pdfs_disponibles;
}

// Función para obtener porcentaje de completado (versión básica)
function porcentajeCompletadoBasico() {
    $total_campos = 0;
    $campos_completos = 0;
    
    // Datos básicos del alumno
    if (isset($_SESSION['alumno'])) {
        $alumno = $_SESSION['alumno'];
        $campos_alumno = ['nombres', 'paterno', 'materno', 'correo_electronico', 'telefono', 'genero'];
        $total_campos += count($campos_alumno);
        
        foreach ($campos_alumno as $campo) {
            if (!empty($alumno[$campo])) {
                $campos_completos++;
            }
        }
    }
    
    // Algunos datos de cartas
    if (isset($_SESSION['datos_cartas']) && $_SESSION['datos_cartas']) {
        $datos = $_SESSION['datos_cartas'];
        $campos_cartas = ['nombres_estudiante', 'nombre_carrera', 'nombre_empresa', 'correo_electronico_estudiante'];
        $total_campos += count($campos_cartas);
        
        foreach ($campos_cartas as $campo) {
            if (!empty($datos[$campo])) {
                $campos_completos++;
            }
        }
    } else {
        $total_campos += 4; // Agregar campos aunque no existan datos
    }
    
    return $total_campos > 0 ? round(($campos_completos / $total_campos) * 100) : 0;
}

// Función para obtener resumen de datos (versión básica)
function obtenerResumenDatosBasico() {
    $resumen = [
        'perfil_completo' => porcentajeCompletadoBasico(),
        'tiene_datos_cartas' => isset($_SESSION['datos_cartas']) && $_SESSION['datos_cartas'],
        'num_estancias' => isset($_SESSION['estancias']) ? count($_SESSION['estancias']) : 0,
        'pdfs_disponibles' => verificarPDFsDisponiblesBasico(),
        'datos_cartas_completos' => datosCartasCompletosBasico()
    ];
    
    return $resumen;
}
?>