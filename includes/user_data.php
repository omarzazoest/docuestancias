<?php
// Funciones para manejo de datos de usuario

// Función para cargar todos los datos del estudiante en la sesión
function cargarDatosCompletos($matricula, $db) {
    try {
        // Datos del alumno
        $query = "SELECT * FROM alumnos WHERE matricula = :matricula";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':matricula', $matricula);
        $stmt->execute();
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$alumno) {
            error_log("No se encontró alumno con matrícula: " . $matricula);
            return false;
        }
        
        // Datos de cartas (más reciente) - simplificado para evitar errores
        $datos_cartas = null;
        try {
            $query = "SELECT * FROM datos_cartas WHERE matricula_estudiante = :matricula ORDER BY id_carta DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':matricula', $matricula);
            $stmt->execute();
            $datos_cartas = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error cargando datos_cartas: " . $e->getMessage());
            // Continuar sin datos de cartas
        }
        
        // Datos de estancias - simplificado
        $estancias = [];
        try {
            $query = "SELECT * FROM estancias WHERE id_alumno = :id_alumno ORDER BY fecha_presentacion DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_alumno', $alumno['id_alumno']);
            $stmt->execute();
            $estancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error cargando estancias: " . $e->getMessage());
            // Continuar sin estancias
        }
        
        // Datos de cooperación (simplificado)
        $cooperaciones = [];
        
        // Guardar en sesión
        $_SESSION['alumno'] = $alumno;
        $_SESSION['datos_cartas'] = $datos_cartas ?: null;
        $_SESSION['estancias'] = $estancias;
        $_SESSION['cooperaciones'] = $cooperaciones;
        $_SESSION['datos_cargados'] = true;
        $_SESSION['ultima_actualizacion'] = time();
        
        // Datos básicos para compatibilidad
        $_SESSION['matricula'] = $alumno['matricula'];
        $_SESSION['id_alumno'] = $alumno['id_alumno'];
        $_SESSION['nombres'] = $alumno['nombres'];
        $_SESSION['paterno'] = $alumno['paterno'];
        $_SESSION['materno'] = $alumno['materno'];
        $_SESSION['correo_electronico'] = $alumno['correo_electronico'];
        
        error_log("Datos cargados exitosamente para matrícula: " . $matricula);
        return true;
        
    } catch(PDOException $exception) {
        error_log("Error cargando datos completos: " . $exception->getMessage());
        return false;
    } catch(Exception $e) {
        error_log("Error general cargando datos: " . $e->getMessage());
        return false;
    }
}

// Función para verificar si los datos de cartas están completos
function datosCartasCompletos() {
    if (!isset($_SESSION['datos_cartas']) || !$_SESSION['datos_cartas']) {
        return false;
    }
    
    $datos = $_SESSION['datos_cartas'];
    $campos_requeridos = [
        'nombres_estudiante', 'paterno_estudiante', 'materno_estudiante',
        'nombre_carrera', 'estancia-estadia', 'periodo', 'horas', 'nombre_empresa',
        'nombres_asesor_academico', 'paterno_asesor_academico', 'materno_asesor_academico',
        'nombres_asesor_organizacion', 'paterno_asesor_organizacion', 'materno_asesor_organizacion',
        'correo_electronico_estudiante'
    ];
    
    foreach ($campos_requeridos as $campo) {
        if (empty($datos[$campo])) {
            return false;
        }
    }
    
    return true;
}

// Función para verificar qué PDFs se pueden generar
function verificarPDFsDisponibles() {
    $pdfs_disponibles = [
        'carta_presentacion' => false,
        'carta_cooperacion' => false,
        'carta_termino' => false,
        'constancia_estancia' => false
    ];
    
    // Verificar carta de presentación
    if (datosCartasCompletos()) {
        $pdfs_disponibles['carta_presentacion'] = true;
    }
    
    // Verificar carta de cooperación
    if (datosCartasCompletos() && isset($_SESSION['datos_cartas']['nombre_empresa'])) {
        $pdfs_disponibles['carta_cooperacion'] = true;
    }
    
    // Verificar carta de término
    if (datosCartasCompletos() && 
        isset($_SESSION['datos_cartas']['fecha_inicio']) && 
        isset($_SESSION['datos_cartas']['fecha_fin']) &&
        !empty($_SESSION['datos_cartas']['fecha_inicio']) &&
        !empty($_SESSION['datos_cartas']['fecha_fin'])) {
        $pdfs_disponibles['carta_termino'] = true;
    }
    
    // Verificar constancia de estancia
    if (datosCartasCompletos() && !empty($_SESSION['estancias'])) {
        $pdfs_disponibles['constancia_estancia'] = true;
    }
    
    return $pdfs_disponibles;
}

// Función para obtener el porcentaje de completado del perfil
function porcentajeCompletado() {
    $total_campos = 0;
    $campos_completos = 0;
    
    // Datos básicos del alumno (6 campos)
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
    
    // Datos de cartas (15 campos principales)
    if (isset($_SESSION['datos_cartas']) && $_SESSION['datos_cartas']) {
        $datos = $_SESSION['datos_cartas'];
        $campos_cartas = [
            'nombres_estudiante', 'paterno_estudiante', 'materno_estudiante',
            'nombre_carrera', 'estancia-estadia', 'periodo', 'horas', 'nombre_empresa',
            'nombres_asesor_academico', 'paterno_asesor_academico', 'materno_asesor_academico',
            'nombres_asesor_organizacion', 'paterno_asesor_organizacion', 'materno_asesor_organizacion',
            'correo_electronico_estudiante'
        ];
        $total_campos += count($campos_cartas);
        
        foreach ($campos_cartas as $campo) {
            if (!empty($datos[$campo])) {
                $campos_completos++;
            }
        }
    } else {
        $total_campos += 15; // Agregar campos aunque no existan datos
    }
    
    return $total_campos > 0 ? round(($campos_completos / $total_campos) * 100) : 0;
}

// Función para actualizar datos específicos en la sesión
function actualizarDatosSesion($tipo, $datos) {
    switch ($tipo) {
        case 'alumno':
            $_SESSION['alumno'] = $datos;
            // Actualizar datos básicos para compatibilidad
            $_SESSION['nombres'] = $datos['nombres'];
            $_SESSION['paterno'] = $datos['paterno'];
            $_SESSION['materno'] = $datos['materno'];
            $_SESSION['correo_electronico'] = $datos['correo_electronico'];
            break;
            
        case 'datos_cartas':
            $_SESSION['datos_cartas'] = $datos;
            break;
            
        case 'estancias':
            $_SESSION['estancias'] = $datos;
            break;
            
        case 'cooperaciones':
            $_SESSION['cooperaciones'] = $datos;
            break;
    }
    
    $_SESSION['ultima_actualizacion'] = time();
}

// Función para limpiar datos de sesión
function limpiarDatosSesion() {
    $campos_a_mantener = ['matricula', 'id_alumno'];
    $sesion_temp = [];
    
    foreach ($campos_a_mantener as $campo) {
        if (isset($_SESSION[$campo])) {
            $sesion_temp[$campo] = $_SESSION[$campo];
        }
    }
    
    session_destroy();
    session_start();
    
    foreach ($sesion_temp as $campo => $valor) {
        $_SESSION[$campo] = $valor;
    }
}

// Función para verificar si necesita recargar datos (cada 30 minutos)
function necesitaRecargarDatos() {
    if (!isset($_SESSION['ultima_actualizacion'])) {
        return true;
    }
    
    $tiempo_limite = 30 * 60; // 30 minutos
    return (time() - $_SESSION['ultima_actualizacion']) > $tiempo_limite;
}

// Función para obtener resumen de datos para dashboard
function obtenerResumenDatos() {
    $resumen = [
        'perfil_completo' => porcentajeCompletado(),
        'tiene_datos_cartas' => isset($_SESSION['datos_cartas']) && $_SESSION['datos_cartas'],
        'num_estancias' => isset($_SESSION['estancias']) ? count($_SESSION['estancias']) : 0,
        'pdfs_disponibles' => verificarPDFsDisponibles(),
        'datos_cartas_completos' => datosCartasCompletos()
    ];
    
    return $resumen;
}
?>