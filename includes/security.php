<?php
// Archivo de funciones de seguridad y utilidades

// Función para verificar si el usuario está autenticado
function verificarAutenticacion() {
    if (!isset($_SESSION['matricula'])) {
        header('Location: login.php');
        exit();
    }
}

// Función para verificar y regenerar ID de sesión
function regenerarSesion() {
    if (!isset($_SESSION['regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['regenerated'] = true;
    }
}

// Función para validar token CSRF
function validarCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para generar token CSRF
function generarCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para limpiar y validar datos de entrada
function validarDatos($datos, $reglas) {
    $errores = [];
    
    foreach ($reglas as $campo => $regla) {
        $valor = isset($datos[$campo]) ? trim($datos[$campo]) : '';
        
        // Verificar si es requerido
        if (isset($regla['required']) && $regla['required'] && empty($valor)) {
            $errores[$campo] = "El campo {$campo} es obligatorio";
            continue;
        }
        
        // Si está vacío y no es requerido, continuar
        if (empty($valor)) {
            continue;
        }
        
        // Validar longitud mínima
        if (isset($regla['min_length']) && strlen($valor) < $regla['min_length']) {
            $errores[$campo] = "El campo {$campo} debe tener al menos {$regla['min_length']} caracteres";
        }
        
        // Validar longitud máxima
        if (isset($regla['max_length']) && strlen($valor) > $regla['max_length']) {
            $errores[$campo] = "El campo {$campo} no puede tener más de {$regla['max_length']} caracteres";
        }
        
        // Validar email
        if (isset($regla['email']) && $regla['email'] && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $errores[$campo] = "El campo {$campo} debe ser un email válido";
        }
        
        // Validar patrón regex
        if (isset($regla['pattern']) && !preg_match($regla['pattern'], $valor)) {
            $errores[$campo] = $regla['message'] ?? "El campo {$campo} no tiene el formato correcto";
        }
        
        // Validar número
        if (isset($regla['numeric']) && $regla['numeric'] && !is_numeric($valor)) {
            $errores[$campo] = "El campo {$campo} debe ser un número";
        }
        
        // Validar rango numérico
        if (isset($regla['min_value']) && is_numeric($valor) && $valor < $regla['min_value']) {
            $errores[$campo] = "El campo {$campo} debe ser mayor o igual a {$regla['min_value']}";
        }
        
        if (isset($regla['max_value']) && is_numeric($valor) && $valor > $regla['max_value']) {
            $errores[$campo] = "El campo {$campo} debe ser menor o igual a {$regla['max_value']}";
        }
    }
    
    return $errores;
}

// Función para registrar logs de actividad
function registrarLog($accion, $detalles = '', $matricula = null) {
    if (!$matricula && isset($_SESSION['matricula'])) {
        $matricula = $_SESSION['matricula'];
    }
    
    $log_entry = [
        'fecha' => date('Y-m-d H:i:s'),
        'matricula' => $matricula,
        'accion' => $accion,
        'detalles' => $detalles,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Escribir al archivo de log
    $log_file = '../logs/activity.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

// Función para limitar intentos de login
function verificarLimiteIntentos($matricula, $max_intentos = 5, $tiempo_bloqueo = 900) {
    $archivo_intentos = '../logs/login_attempts.json';
    $intentos = [];
    
    if (file_exists($archivo_intentos)) {
        $intentos = json_decode(file_get_contents($archivo_intentos), true) ?: [];
    }
    
    $ahora = time();
    $clave_matricula = md5($matricula . $_SERVER['REMOTE_ADDR']);
    
    // Limpiar intentos antiguos
    if (isset($intentos[$clave_matricula])) {
        $intentos[$clave_matricula] = array_filter($intentos[$clave_matricula], function($tiempo) use ($ahora, $tiempo_bloqueo) {
            return ($ahora - $tiempo) < $tiempo_bloqueo;
        });
    }
    
    // Verificar si está bloqueado
    $num_intentos = isset($intentos[$clave_matricula]) ? count($intentos[$clave_matricula]) : 0;
    
    if ($num_intentos >= $max_intentos) {
        return false; // Bloqueado
    }
    
    return true; // No bloqueado
}

// Función para registrar intento de login fallido
function registrarIntentoFallido($matricula) {
    $archivo_intentos = '../logs/login_attempts.json';
    $intentos = [];
    
    if (file_exists($archivo_intentos)) {
        $intentos = json_decode(file_get_contents($archivo_intentos), true) ?: [];
    }
    
    $clave_matricula = md5($matricula . $_SERVER['REMOTE_ADDR']);
    
    if (!isset($intentos[$clave_matricula])) {
        $intentos[$clave_matricula] = [];
    }
    
    $intentos[$clave_matricula][] = time();
    
    // Crear directorio si no existe
    $log_dir = dirname($archivo_intentos);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($archivo_intentos, json_encode($intentos), LOCK_EX);
}

// Función para limpiar intentos de login después de login exitoso
function limpiarIntentosLogin($matricula) {
    $archivo_intentos = '../logs/login_attempts.json';
    
    if (file_exists($archivo_intentos)) {
        $intentos = json_decode(file_get_contents($archivo_intentos), true) ?: [];
        $clave_matricula = md5($matricula . $_SERVER['REMOTE_ADDR']);
        
        if (isset($intentos[$clave_matricula])) {
            unset($intentos[$clave_matricula]);
            file_put_contents($archivo_intentos, json_encode($intentos), LOCK_EX);
        }
    }
}

// Función para escapar salida HTML
function escaparHTML($texto) {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

// Función para generar nombre de archivo seguro
function generarNombreArchivoSeguro($nombre_original) {
    $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
    $nombre_limpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($nombre_original, PATHINFO_FILENAME));
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    
    return $nombre_limpio . '_' . $timestamp . '_' . $random . '.' . $extension;
}

// Función para validar subida de archivos
function validarSubidaArchivo($archivo, $tipos_permitidos = ['pdf', 'doc', 'docx'], $tamaño_maximo = 5242880) {
    $errores = [];
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Error en la subida del archivo';
        return $errores;
    }
    
    // Verificar tamaño
    if ($archivo['size'] > $tamaño_maximo) {
        $errores[] = 'El archivo es demasiado grande. Máximo: ' . ($tamaño_maximo / 1024 / 1024) . 'MB';
    }
    
    // Verificar tipo
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $tipos_permitidos)) {
        $errores[] = 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', $tipos_permitidos);
    }
    
    // Verificar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    $mimes_permitidos = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    if (!in_array($mime_type, $mimes_permitidos)) {
        $errores[] = 'Tipo de archivo no válido';
    }
    
    return $errores;
}

// Función para enviar headers de seguridad
function enviarHeadersSeguridad() {
    // Prevenir XSS
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // HSTS (solo para HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // CSP básico
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:;");
}

// Función para verificar permisos de archivo/directorio
function verificarPermisos($ruta, $permisos_requeridos = 0755) {
    if (!file_exists($ruta)) {
        return false;
    }
    
    $permisos_actuales = fileperms($ruta) & 0777;
    return $permisos_actuales >= $permisos_requeridos;
}

// Función para crear backup de la base de datos
function crearBackupBD($archivo_destino = null) {
    if (!$archivo_destino) {
        $archivo_destino = '../backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
    }
    
    $directorio = dirname($archivo_destino);
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    
    $comando = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($archivo_destino)
    );
    
    exec($comando, $output, $return_code);
    
    return $return_code === 0;
}
?>