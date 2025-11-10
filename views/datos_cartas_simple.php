<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user_data_backup.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['matricula'])) {
    header('Location: login.php');
    exit();
}

// var_dump($_SESSION); // Depuración temporal (deshabilitado)

$database = new Database();
$db = $database->getConnection();
$matricula = $_SESSION['matricula'];
$error = '';
$success = '';

// Catálogo de organizaciones para auto-completar en el formulario
$organizaciones_lista = [];

// Verificar datos del alumno y crear proyecto de cooperación si no existe
$datos_alumno = null;
$proyecto_cooperacion = null;

try {
    // Obtener datos del alumno
    $query_alumno = "SELECT * FROM alumnos WHERE matricula = :matricula LIMIT 1";
    $stmt_alumno = $db->prepare($query_alumno);
    $stmt_alumno->bindParam(':matricula', $matricula);
    $stmt_alumno->execute();
    $datos_alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);
    
    if (!$datos_alumno) {
        $error = 'No se encontraron datos del estudiante en el sistema';
    } else {
    // Buscar proyecto de cooperación existente para este alumno (directo por FK)
    $query_cooperacion = "SELECT * FROM cooperacion WHERE id_alumno = :id_alumno LIMIT 1";
    $stmt_cooperacion = $db->prepare($query_cooperacion);
    $stmt_cooperacion->bindParam(':id_alumno', $datos_alumno['id_alumno']);
    $stmt_cooperacion->execute();
    $proyecto_cooperacion = $stmt_cooperacion->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error consultando datos: " . $e->getMessage());
    $error = 'Error al consultar los datos del sistema';
}

// Cargar catálogo de organizaciones existentes para auto-completar
try {
    $stmt_orgs = $db->query("SELECT id_organizacion, nombre_organizacion, direccion_org, contacto_org_congrado, puesto_contacto, email_org, telefono_org FROM organizaciones ORDER BY nombre_organizacion ASC");
    $organizaciones_lista = $stmt_orgs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error cargando organizaciones: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $success = '';
    $error = '';
    $seccion = $_POST['seccion'] ?? '';

    if (!$datos_alumno) {
        $error = 'No se pueden guardar datos sin información del estudiante';
    } else {
        try {
            // ----------------------------------------
            // SECCIÓN: ESTUDIANTE (solo si se envió ese formulario)
            // ----------------------------------------
            if ($seccion === 'estudiante' || $seccion === 'general') {
                $campos_estudiante = [
                    'nombres_estudiante' => 'nombres',
                    'paterno_estudiante' => 'paterno',
                    'materno_estudiante' => 'materno',
                    'correo_electronico_estudiante' => 'correo_electronico',
                    'telefono' => 'telefono'
                ];
                $setParts = [];
                $params = [];

                // Email y teléfono validaciones ligeras
                if (isset($_POST['correo_electronico_estudiante']) && $_POST['correo_electronico_estudiante'] !== '') {
                    $email_tmp = trim($_POST['correo_electronico_estudiante']);
                    if (!filter_var($email_tmp, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('El correo electrónico no es válido');
                    }
                }
                if (isset($_POST['telefono']) && $_POST['telefono'] !== '' && strlen($_POST['telefono']) > 12) {
                    throw new Exception('El teléfono no puede tener más de 12 caracteres');
                }

                foreach ($campos_estudiante as $postKey => $colName) {
                    if (isset($_POST[$postKey]) && trim($_POST[$postKey]) !== '') {
                        $setParts[] = "$colName = :$colName";
                        $params[$colName] = sanitizar($_POST[$postKey]);
                    }
                }
                // Género (solo si viene y es válido)
                if (isset($_POST['genero']) && trim($_POST['genero']) !== '') {
                    $genero_limpio = trim($_POST['genero']);
                    if (in_array($genero_limpio, ['Masculino','Femenino'], true)) {
                        $setParts[] = "genero = :genero";
                        $params['genero'] = $genero_limpio;
                    } else {
                        throw new Exception('El género debe ser exactamente "Masculino" o "Femenino"');
                    }
                }

                if (!empty($setParts)) {
                    $sql = "UPDATE alumnos SET " . implode(', ', $setParts) . " WHERE id_alumno = :id_alumno";
                    $stmt_upd = $db->prepare($sql);
                    foreach ($params as $k => $v) {
                        $stmt_upd->bindValue(':'.$k, $v);
                    }
                    $stmt_upd->bindValue(':id_alumno', $datos_alumno['id_alumno']);
                    $stmt_upd->execute();
                    $success .= 'Datos del estudiante guardados. ';
                } else {
                    $success .= 'No se detectaron cambios en datos del estudiante. ';
                }
            }

            // ----------------------------------------
            // SECCIÓN: PROYECTO (solo si se envió ese formulario)
            // ----------------------------------------
            if ($seccion === 'proyecto' || $seccion === 'general') {
                // Mapeo POST->columna para cooperacion
                $map = [
                    'nombre_proyecto' => 'nombre_proyecto',
                    'objetivos' => 'objetivos',
                    'area_departamento' => '`area-departamento`',
                    'periodo_inicial' => 'periodo_inicial',
                    'periodo_final' => 'periodo_final',
                    'act_1' => 'act_1',
                    'act_2' => 'act_2',
                    'act_3' => 'act_3',
                    'act_4' => 'act_4',
                    'meta_1' => 'meta_1',
                    'meta_2' => 'meta_2',
                    'meta_3' => 'meta_3',
                    'meta_4' => 'meta_4'
                ];

                $updates = [];
                $params = [];
                foreach ($map as $postKey => $colName) {
                    if (isset($_POST[$postKey]) && trim((string)$_POST[$postKey]) !== '') {
                        $updates[] = "$colName = :$postKey";
                        // fechas van crudas, otros sanitizados
                        if (in_array($postKey, ['periodo_inicial','periodo_final'], true)) {
                            $params[":$postKey"] = $_POST[$postKey];
                        } else {
                            $params[":$postKey"] = sanitizar($_POST[$postKey]);
                        }
                    }
                }

                if ($proyecto_cooperacion) {
                    if (!empty($updates)) {
                        $sql = "UPDATE cooperacion SET " . implode(', ', $updates) . " WHERE id_cooperacion = :idc";
                        $stmt = $db->prepare($sql);
                        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
                        $stmt->bindValue(':idc', $proyecto_cooperacion['id_cooperacion']);
                        $stmt->execute();
                        $success .= 'Datos del proyecto actualizados. ';
                    } else {
                        $success .= 'No se detectaron cambios en datos del proyecto. ';
                    }
                } else {
                    // Crear cooperacion vacía con id_alumno y solo campos proporcionados
                    $cols = ['id_alumno'];
                    $placeholders = [':id_alumno'];
                    $insertParams = [':id_alumno' => $datos_alumno['id_alumno']];
                    foreach ($map as $postKey => $colName) {
                        if (isset($_POST[$postKey]) && trim((string)$_POST[$postKey]) !== '') {
                            $cols[] = $colName;
                            $placeholders[] = ":$postKey";
                            if (in_array($postKey, ['periodo_inicial','periodo_final'], true)) {
                                $insertParams[":$postKey"] = $_POST[$postKey];
                            } else {
                                $insertParams[":$postKey"] = sanitizar($_POST[$postKey]);
                            }
                        }
                    }
                    // Solo crear si al menos viene algún dato del proyecto, o si seccion es general y quieres asegurar relación
                    if (count($cols) > 1) {
                        $sql = "INSERT INTO cooperacion (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
                        $stmt = $db->prepare($sql);
                        foreach ($insertParams as $k => $v) { $stmt->bindValue($k, $v); }
                        $stmt->execute();
                        $nuevo_id_cooperacion = $db->lastInsertId();
                        // Vincular/crear estancia única por alumno
                        $stmt_estancia_chk = $db->prepare("SELECT * FROM estancias WHERE id_alumno = :id_alumno LIMIT 1");
                        $stmt_estancia_chk->bindValue(':id_alumno', $datos_alumno['id_alumno']);
                        $stmt_estancia_chk->execute();
                        $existe_estancia = $stmt_estancia_chk->fetch(PDO::FETCH_ASSOC);
                        if ($existe_estancia) {
                            $stmt_upd_est = $db->prepare("UPDATE estancias SET id_cooperacion = :idc WHERE id_estancia_estadia = :ide");
                            $stmt_upd_est->bindValue(':idc', $nuevo_id_cooperacion);
                            $stmt_upd_est->bindValue(':ide', $existe_estancia['id_estancia_estadia']);
                            $stmt_upd_est->execute();
                        } else {
                            $stmt_ins_est = $db->prepare("INSERT INTO estancias (`estancia-estadia`, numero, horas, id_alumno, id_empresa, fecha_presentacion, fecha_termino, id_cooperacion) VALUES ('Estancia','N/A',0,:ida,:idemp,CURDATE(),CURDATE(),:idc)");
                            $stmt_ins_est->bindValue(':ida', $datos_alumno['id_alumno']);
                            $stmt_ins_est->bindValue(':idemp', null, PDO::PARAM_NULL);
                            $stmt_ins_est->bindValue(':idc', $nuevo_id_cooperacion);
                            $stmt_ins_est->execute();
                        }
                        $success .= 'Proyecto creado. ';
                        // Refrescar objeto de proyecto
                        $stmt_cooperacion->execute();
                        $proyecto_cooperacion = $stmt_cooperacion->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $success .= 'Proyecto sin cambios (no se capturaron datos). ';
                    }
                }
            }

            // ----------------------------------------
            // SECCIÓN: EMPRESA (solo si se envió ese formulario)
            // ----------------------------------------
            if ($seccion === 'empresa' || $seccion === 'general') {
                $nombre_empresa = sanitizar($_POST['nombre_empresa'] ?? '');
                $direccion_empresa = sanitizar($_POST['direccion_empresa'] ?? '');
                $asesor_nombre = sanitizar($_POST['asesor_nombre'] ?? '');
                $asesor_cargo = sanitizar($_POST['asesor_cargo'] ?? '');
                $asesor_telefono = sanitizar($_POST['asesor_telefono'] ?? '');
                $asesor_email = sanitizar($_POST['asesor_email'] ?? '');
                // Campos de estancias opcionales
                $estancia_tipo = isset($_POST['estancia_tipo']) && $_POST['estancia_tipo'] !== '' ? $_POST['estancia_tipo'] : null;
                $estancia_numero = isset($_POST['estancia_numero']) && $_POST['estancia_numero'] !== '' ? $_POST['estancia_numero'] : null;
                $horas = isset($_POST['horas']) && $_POST['horas'] !== '' ? (int)$_POST['horas'] : null;
                $fecha_presentacion = $_POST['fecha_presentacion'] ?? null;
                $fecha_termino = $_POST['fecha_termino'] ?? null;

                if (!empty($nombre_empresa)) {
                    // Buscar si existe estancia para este alumno
                    $query_estancia = "SELECT e.* FROM estancias e WHERE e.id_alumno = :id_alumno LIMIT 1";
                    $stmt_estancia = $db->prepare($query_estancia);
                    $stmt_estancia->bindParam(':id_alumno', $datos_alumno['id_alumno']);
                    $stmt_estancia->execute();
                    $estancia_existente = $stmt_estancia->fetch(PDO::FETCH_ASSOC);

                    // Buscar o crear organización
                    $query_org = "SELECT id_organizacion FROM organizaciones WHERE nombre_organizacion = :nombre LIMIT 1";
                    $stmt_org = $db->prepare($query_org);
                    $stmt_org->bindParam(':nombre', $nombre_empresa);
                    $stmt_org->execute();
                    $organizacion = $stmt_org->fetch(PDO::FETCH_ASSOC);

                    if (!$organizacion) {
                        $query_nueva_org = "INSERT INTO organizaciones (
                            nombre_organizacion, direccion_org, contacto_org_congrado, 
                            puesto_contacto, email_org, telefono_org
                        ) VALUES (:nombre, :direccion, :asesor_nombre, :asesor_cargo, :asesor_email, :asesor_telefono)";
                        $stmt_nueva_org = $db->prepare($query_nueva_org);
                        $stmt_nueva_org->bindParam(':nombre', $nombre_empresa);
                        $stmt_nueva_org->bindParam(':direccion', $direccion_empresa);
                        $stmt_nueva_org->bindParam(':asesor_nombre', $asesor_nombre);
                        $stmt_nueva_org->bindParam(':asesor_cargo', $asesor_cargo);
                        $stmt_nueva_org->bindParam(':asesor_email', $asesor_email);
                        $stmt_nueva_org->bindParam(':asesor_telefono', $asesor_telefono);
                        $stmt_nueva_org->execute();
                        $id_empresa = $db->lastInsertId();
                    } else {
                        $id_empresa = $organizacion['id_organizacion'];
                        $query_update_org = "UPDATE organizaciones SET 
                            direccion_org = :direccion,
                            contacto_org_congrado = :asesor_nombre,
                            puesto_contacto = :asesor_cargo,
                            email_org = :asesor_email,
                            telefono_org = :asesor_telefono
                            WHERE id_organizacion = :id_organizacion";
                        $stmt_update_org = $db->prepare($query_update_org);
                        $stmt_update_org->bindParam(':direccion', $direccion_empresa);
                        $stmt_update_org->bindParam(':asesor_nombre', $asesor_nombre);
                        $stmt_update_org->bindParam(':asesor_cargo', $asesor_cargo);
                        $stmt_update_org->bindParam(':asesor_email', $asesor_email);
                        $stmt_update_org->bindParam(':asesor_telefono', $asesor_telefono);
                        $stmt_update_org->bindParam(':id_organizacion', $id_empresa);
                        $stmt_update_org->execute();
                    }

                    if ($estancia_existente) {
                        // Actualizar estancia existente: empresa + campos opcionales
                        $setEst = ["id_empresa = :id_empresa"]; 
                        if ($estancia_tipo !== null) $setEst[] = "`estancia-estadia` = :estancia_tipo";
                        if ($estancia_numero !== null) $setEst[] = "numero = :estancia_numero";
                        if ($horas !== null) $setEst[] = "horas = :horas";
                        if (!empty($fecha_presentacion)) $setEst[] = "fecha_presentacion = :fecha_presentacion";
                        if (!empty($fecha_termino)) $setEst[] = "fecha_termino = :fecha_termino";
                        $query_update_estancia = "UPDATE estancias SET " . implode(', ', $setEst) . " WHERE id_estancia_estadia = :id_estancia";
                        $stmt_update = $db->prepare($query_update_estancia);
                        $stmt_update->bindParam(':id_empresa', $id_empresa);
                        if ($estancia_tipo !== null) $stmt_update->bindParam(':estancia_tipo', $estancia_tipo);
                        if ($estancia_numero !== null) $stmt_update->bindParam(':estancia_numero', $estancia_numero);
                        if ($horas !== null) $stmt_update->bindParam(':horas', $horas, PDO::PARAM_INT);
                        if (!empty($fecha_presentacion)) $stmt_update->bindParam(':fecha_presentacion', $fecha_presentacion);
                        if (!empty($fecha_termino)) $stmt_update->bindParam(':fecha_termino', $fecha_termino);
                        $stmt_update->bindParam(':id_estancia', $estancia_existente['id_estancia_estadia']);
                        $stmt_update->execute();
                    } else {
                        // No existe estancia: crearla y vincular alumno + empresa (+ cooperacion si existe)
                        $query_insert_estancia = "INSERT INTO estancias (
                            `estancia-estadia`, numero, horas, id_alumno, id_empresa, fecha_presentacion, fecha_termino, id_cooperacion
                        ) VALUES (
                            :estancia_tipo, :estancia_numero, :horas, :id_alumno, :id_empresa, :fecha_presentacion, :fecha_termino, :id_cooperacion
                        )";
                        $stmt_ins_est = $db->prepare($query_insert_estancia);
                        // valores, permitiendo NULL cuando no se proporcionan
                        if ($estancia_tipo !== null) { $stmt_ins_est->bindParam(':estancia_tipo', $estancia_tipo); } else { $stmt_ins_est->bindValue(':estancia_tipo', null, PDO::PARAM_NULL); }
                        if ($estancia_numero !== null) { $stmt_ins_est->bindParam(':estancia_numero', $estancia_numero); } else { $stmt_ins_est->bindValue(':estancia_numero', null, PDO::PARAM_NULL); }
                        if ($horas !== null) { $stmt_ins_est->bindParam(':horas', $horas, PDO::PARAM_INT); } else { $stmt_ins_est->bindValue(':horas', null, PDO::PARAM_NULL); }
                        $stmt_ins_est->bindParam(':id_alumno', $datos_alumno['id_alumno']);
                        $stmt_ins_est->bindParam(':id_empresa', $id_empresa);
                        if (!empty($fecha_presentacion)) { $stmt_ins_est->bindParam(':fecha_presentacion', $fecha_presentacion); } else { $stmt_ins_est->bindValue(':fecha_presentacion', null, PDO::PARAM_NULL); }
                        if (!empty($fecha_termino)) { $stmt_ins_est->bindParam(':fecha_termino', $fecha_termino); } else { $stmt_ins_est->bindValue(':fecha_termino', null, PDO::PARAM_NULL); }
                        if ($proyecto_cooperacion && !empty($proyecto_cooperacion['id_cooperacion'])) {
                            $stmt_ins_est->bindParam(':id_cooperacion', $proyecto_cooperacion['id_cooperacion']);
                        } else {
                            $stmt_ins_est->bindValue(':id_cooperacion', null, PDO::PARAM_NULL);
                        }
                        $stmt_ins_est->execute();
                    }
                    $success .= 'Datos de la empresa guardados. ';
                } else {
                    $success .= 'No se detectaron cambios en datos de la empresa. ';
                }
            }

            // Recargar TODOS los datos después de cualquier guardado
            recargarDatosCompletos($db, $datos_alumno, $proyecto_cooperacion, $form_data);
            $cartas_disponibles = verificarCartasDisponibles($datos_alumno, $form_data);
            if (!empty($cartas_disponibles)) {
                $success .= '<br><strong>Cartas disponibles:</strong> Puedes generar ' . count($cartas_disponibles) . ' carta(s) con los datos actuales.';
            }
        } catch (Exception $ex) {
            $error = $ex->getMessage();
        } catch (PDOException $pdoEx) {
            $error = 'Error en el sistema: ' . $pdoEx->getMessage();
            error_log('Error guardando datos: ' . $pdoEx->getMessage());
        }
    }
}

// Preparar datos para el formulario - usar función unificada
$form_data = [];
if ($datos_alumno) {
    recargarDatosCompletos($db, $datos_alumno, $proyecto_cooperacion, $form_data);
}

// Información de debug temporal para verificar el flujo
$debug_info = [
    'matricula_sesion' => $matricula,
    'datos_alumno_encontrados' => !empty($datos_alumno),
    'form_data_generado' => !empty($form_data),
    'total_campos_form_data' => count($form_data)
];

// Función para recargar todos los datos después de guardar
function recargarDatosCompletos($db, &$datos_alumno, &$proyecto_cooperacion, &$form_data) {
    try {
        // Recargar datos del alumno
        $query_alumno = "SELECT * FROM alumnos WHERE id_alumno = :id_alumno LIMIT 1";
        $stmt_alumno = $db->prepare($query_alumno);
        $stmt_alumno->bindParam(':id_alumno', $datos_alumno['id_alumno']);
        $stmt_alumno->execute();
        $datos_alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);
        
    // Recargar datos del proyecto (directo por FK en cooperacion)
    $query_cooperacion = "SELECT * FROM cooperacion WHERE id_alumno = :id_alumno LIMIT 1";
    $stmt_cooperacion = $db->prepare($query_cooperacion);
    $stmt_cooperacion->bindParam(':id_alumno', $datos_alumno['id_alumno']);
    $stmt_cooperacion->execute();
    $proyecto_cooperacion = $stmt_cooperacion->fetch(PDO::FETCH_ASSOC);
        
        // Recargar datos para el formulario desde el alumno
        $form_data = [
            'nombres_estudiante' => $datos_alumno['nombres'] ?? '',
            'paterno_estudiante' => $datos_alumno['paterno'] ?? '',
            'materno_estudiante' => $datos_alumno['materno'] ?? '',
            'correo_electronico_estudiante' => $datos_alumno['correo_electronico'] ?? '',
            'telefono' => $datos_alumno['telefono'] ?? '',
            'genero' => $datos_alumno['genero'] ?? '',
        ];
        
        // Agregar datos del proyecto si existe
        if ($proyecto_cooperacion) {
            $form_data = array_merge($form_data, [
                'nombre_proyecto' => $proyecto_cooperacion['nombre_proyecto'] ?? '',
                'objetivos' => $proyecto_cooperacion['objetivos'] ?? '',
                'area_departamento' => $proyecto_cooperacion['area-departamento'] ?? '',
                'periodo_inicial' => $proyecto_cooperacion['periodo_inicial'] ?? '',
                'periodo_final' => $proyecto_cooperacion['periodo_final'] ?? '',
                'act_1' => $proyecto_cooperacion['act_1'] ?? '',
                'act_2' => $proyecto_cooperacion['act_2'] ?? '',
                'act_3' => $proyecto_cooperacion['act_3'] ?? '',
                'act_4' => $proyecto_cooperacion['act_4'] ?? '',
                'meta_1' => $proyecto_cooperacion['meta_1'] ?? '',
                'meta_2' => $proyecto_cooperacion['meta_2'] ?? '',
                'meta_3' => $proyecto_cooperacion['meta_3'] ?? '',
                'meta_4' => $proyecto_cooperacion['meta_4'] ?? '',
            ]);
        }
        
        // Recargar datos de empresa
        $query_estancia_completa = "SELECT e.*, o.nombre_organizacion as nombre_empresa, o.direccion_org as direccion_empresa, 
                                   o.contacto_org_congrado as asesor_nombre, o.puesto_contacto as asesor_cargo,
                                   o.email_org as asesor_email, o.telefono_org as asesor_telefono
                                   FROM estancias e 
                                   LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion 
                                   WHERE e.id_alumno = :id_alumno LIMIT 1";
        $stmt_estancia_completa = $db->prepare($query_estancia_completa);
        $stmt_estancia_completa->bindParam(':id_alumno', $datos_alumno['id_alumno']);
        $stmt_estancia_completa->execute();
        $estancia_completa = $stmt_estancia_completa->fetch(PDO::FETCH_ASSOC);
        
        if ($estancia_completa) {
            $form_data = array_merge($form_data, [
                'nombre_empresa' => $estancia_completa['nombre_empresa'] ?? '',
                'direccion_empresa' => $estancia_completa['direccion_empresa'] ?? '',
                'asesor_nombre' => $estancia_completa['asesor_nombre'] ?? '',
                'asesor_cargo' => $estancia_completa['asesor_cargo'] ?? '',
                'asesor_telefono' => $estancia_completa['asesor_telefono'] ?? '',
                'asesor_email' => $estancia_completa['asesor_email'] ?? '',
                'estancia_tipo' => $estancia_completa['estancia-estadia'] ?? '',
                'estancia_numero' => $estancia_completa['numero'] ?? '',
                'horas' => $estancia_completa['horas'] ?? '',
                'fecha_presentacion' => $estancia_completa['fecha_presentacion'] ?? '',
                'fecha_termino' => $estancia_completa['fecha_termino'] ?? '',
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Error recargando datos: " . $e->getMessage());
    }
}

// Función para verificar qué cartas se pueden generar
function verificarCartasDisponibles($datos_alumno, $form_data) {
    $cartas_disponibles = [];
    // Helper para nombre completo estudiante
    $nombre_estudiante_completo = trim(
        ($form_data['nombres_estudiante'] ?? '') . ' ' .
        ($form_data['paterno_estudiante'] ?? '') . ' ' .
        ($form_data['materno_estudiante'] ?? '')
    );

    // Carta de Presentación (estudiante se presenta a la empresa)
    $tiene_presentacion = !empty($form_data['asesor_nombre']) &&
                          !empty($form_data['asesor_cargo']) &&
                          !empty($form_data['nombre_empresa']) &&
                          !empty($nombre_estudiante_completo) &&
                          !empty($datos_alumno['matricula']);

    if ($tiene_presentacion) {
        $cartas_disponibles[] = [
            'tipo' => 'presentacion',
            'nombre' => 'Carta de Presentación',
            'descripcion' => 'Presentación formal del estudiante ante la organización',
            'icono' => 'fas fa-handshake',
            'clase' => 'btn-primary'
        ];
    }

    // Carta de Cooperación (requiere proyecto y datos completos de organización + actividades + metas + periodo)
    $requisitos_cooperacion = [
        $nombre_estudiante_completo,
        $datos_alumno['matricula'] ?? '',
        $form_data['nombre_proyecto'] ?? '',
        $form_data['area_departamento'] ?? '',
        $form_data['nombre_empresa'] ?? '',
        $form_data['direccion_empresa'] ?? '',
        $form_data['asesor_nombre'] ?? '',
        $form_data['asesor_cargo'] ?? '',
        $form_data['asesor_email'] ?? '',
        $form_data['asesor_telefono'] ?? '',
        $form_data['periodo_inicial'] ?? '',
        $form_data['periodo_final'] ?? '',
        $form_data['act_1'] ?? '',
        $form_data['act_2'] ?? '',
        $form_data['act_3'] ?? '',
        $form_data['act_4'] ?? '',
        $form_data['meta_1'] ?? '',
        $form_data['meta_2'] ?? '',
        $form_data['meta_3'] ?? '',
        $form_data['meta_4'] ?? ''
    ];
    $tiene_cooperacion = array_reduce($requisitos_cooperacion, function($carry, $item) {
        return $carry && !empty(trim($item));
    }, true);

    if ($tiene_cooperacion) {
        $cartas_disponibles[] = [
            'tipo' => 'cooperacion',
            'nombre' => 'Carta de Cooperación',
            'descripcion' => 'Establece formalmente el acuerdo de cooperación y proyecto.',
            'icono' => 'fas fa-file-signature',
            'clase' => 'btn-success'
        ];
    }

    // Carta de Término (al finalizar: requiere datos básicos de estudiante y contacto)
    $tiene_termino = !empty($nombre_estudiante_completo) &&
                     !empty($datos_alumno['matricula']) &&
                     !empty($form_data['asesor_nombre']) &&
                     !empty($form_data['asesor_cargo']);

    if ($tiene_termino) {
        $cartas_disponibles[] = [
            'tipo' => 'termino',
            'nombre' => 'Carta de Término',
            'descripcion' => 'Confirma la finalización satisfactoria de la estancia.',
            'icono' => 'fas fa-flag-checkered',
            'clase' => 'btn-secondary'
        ];
    }

    return $cartas_disponibles;
}

// Obtener cartas disponibles
$cartas_disponibles = verificarCartasDisponibles($datos_alumno, $form_data);
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
                <h2>Gestión de Datos del Estudiante y Proyecto</h2>
                <p class="text-muted">Administra tu información personal y detalles del proyecto de estancia.</p>
                
                <?php if ($proyecto_cooperacion): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Proyecto existente:</strong> <?php echo htmlspecialchars($proyecto_cooperacion['nombre_proyecto']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Sección de Cartas Disponibles -->
                <div class="card border-info mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-alt text-info me-2"></i>Cartas Disponibles para Generar
                        </h5>
                        <small class="text-muted">
                            Las cartas se habilitan automáticamente cuando tienes los datos necesarios
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cartas_disponibles)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>No hay cartas disponibles aún.</strong><br>
                                <small>Completa los datos necesarios para poder generar las cartas:</small>
                                <ul class="mt-2 mb-0">
                                    <li><strong>Carta de Presentación:</strong> Requiere nombre del estudiante, matrícula, nombre de la empresa, nombre del asesor y su cargo</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($cartas_disponibles as $carta): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card border-primary h-100">
                                            <div class="card-body text-center">
                                                <i class="<?php echo $carta['icono']; ?> fa-2x text-primary mb-3"></i>
                                                <h6 class="card-title"><?php echo htmlspecialchars($carta['nombre']); ?></h6>
                                                <p class="card-text small text-muted">
                                                    <?php echo htmlspecialchars($carta['descripcion']); ?>
                                                </p>
                                                <a href="generar_carta.php?tipo=<?php echo $carta['tipo']; ?>" 
                                                   class="btn <?php echo $carta['clase']; ?> btn-sm">
                                                    <i class="fas fa-download me-1"></i>Generar PDF
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>¡Excelente!</strong> Tienes <?php echo count($cartas_disponibles); ?> carta(s) disponible(s) para generar.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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

        <div class="row">
            <!-- Formulario General: Estudiante + Proyecto + Empresa -->
            <div class="col-12">
                <form method="POST" action="" id="form-general">
                    <input type="hidden" name="seccion" value="general">
                    <input type="hidden" name="matricula_estudiante" value="<?php echo htmlspecialchars($matricula); ?>">
                    
                    <div class="card mb-4" id="card-estudiante">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5><i class="fas fa-user-graduate me-2"></i>Información del Estudiante</h5>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Matrícula: <strong><?php echo htmlspecialchars($matricula); ?></strong>
                                </small>
                            </div>
                            <?php
                            $estudiante_completo = !empty($form_data['nombres_estudiante']) && 
                                                  !empty($form_data['paterno_estudiante']) && 
                                                  !empty($form_data['correo_electronico_estudiante']);
                            ?>
                            <span class="badge <?php echo $estudiante_completo ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $estudiante_completo ? 'Completado' : 'Incompleto'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="nombres_estudiante" class="form-label">
                                        Nombres 
                                        <?php if (!empty($form_data['nombres_estudiante'])): ?>
                                            <i class="fas fa-check-circle text-success ms-1" title="Precargado desde la base de datos"></i>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" class="form-control" id="nombres_estudiante" name="nombres_estudiante" 
                                           value="<?php echo htmlspecialchars($form_data['nombres_estudiante'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="paterno_estudiante" class="form-label">
                                        Apellido Paterno 
                                        <?php if (!empty($form_data['paterno_estudiante'])): ?>
                                            <i class="fas fa-check-circle text-success ms-1" title="Precargado desde la base de datos"></i>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" class="form-control" id="paterno_estudiante" name="paterno_estudiante" 
                                           value="<?php echo htmlspecialchars($form_data['paterno_estudiante'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="materno_estudiante" class="form-label">
                                        Apellido Materno
                                        <?php if (!empty($form_data['materno_estudiante'])): ?>
                                            <i class="fas fa-check-circle text-success ms-1" title="Precargado desde la base de datos"></i>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" class="form-control" id="materno_estudiante" name="materno_estudiante" 
                                           value="<?php echo htmlspecialchars($form_data['materno_estudiante'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="correo_electronico_estudiante" class="form-label">
                                        Correo Electrónico 
                                        <?php if (!empty($form_data['correo_electronico_estudiante'])): ?>
                                            <i class="fas fa-check-circle text-success ms-1" title="Precargado desde la base de datos"></i>
                                        <?php endif; ?>
                                    </label>
                                    <input type="email" class="form-control" id="correo_electronico_estudiante" name="correo_electronico_estudiante" 
                                           value="<?php echo htmlspecialchars($form_data['correo_electronico_estudiante'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="telefono" class="form-label">
                                        Teléfono
                                        <?php if (!empty($form_data['telefono'])): ?>
                                            <i class="fas fa-check-circle text-success ms-1" title="Precargado desde la base de datos"></i>
                                        <?php endif; ?>
                                    </label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($form_data['telefono'] ?? ''); ?>" 
                                           placeholder="2491234567" maxlength="12">
                                </div>
                                <div class="col-md-3">
                                    <label for="genero" class="form-label">
                                        Género
                                        <?php if (!empty($form_data['genero'])): ?>
                                            <i class="fas fa-check-circle text-success ms-1" title="Precargado desde la base de datos"></i>
                                        <?php endif; ?>
                                    </label>
                                    <select class="form-select" id="genero" name="genero">
                                        <option value="">Seleccionar</option>
                                        <option value="Masculino" <?php echo ($form_data['genero'] ?? '') == 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="Femenino" <?php echo ($form_data['genero'] ?? '') == 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Campos no existentes en BD (carrera/periodo) eliminados para ajustarse al esquema -->
                            
                        </div>
                    </div>
                    <!-- Sección 2: Información del Proyecto -->
                    <hr class="my-4">
                    <h4 class="mb-3"><i class="fas fa-project-diagram me-2"></i>Información del Proyecto</h4>
                    
                    <div class="card mb-4" id="card-proyecto">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-project-diagram me-2"></i>Información del Proyecto</h5>
                            <?php
                            $proyecto_completo = !empty($form_data['nombre_proyecto']) && 
                                               !empty($form_data['objetivos']);
                            ?>
                            <span class="badge <?php echo $proyecto_completo ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $proyecto_completo ? 'Completado' : 'Sin datos'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="nombre_proyecto" class="form-label">
                                        <i class="fas fa-clipboard-list text-primary me-1"></i>Nombre del Proyecto *
                                    </label>
                                    <input type="text" class="form-control" id="nombre_proyecto" name="nombre_proyecto" 
                                           value="<?php echo htmlspecialchars($form_data['nombre_proyecto'] ?? ''); ?>" 
                                           placeholder="Ejemplo: Desarrollo de Sistema de Gestión">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="objetivos" class="form-label">
                                        <i class="fas fa-bullseye text-primary me-1"></i>Objetivos del Proyecto *
                                    </label>
                                    <textarea class="form-control" id="objetivos" name="objetivos" rows="4" 
                                              placeholder="Describe los objetivos principales del proyecto..."><?php echo htmlspecialchars($form_data['objetivos'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="area_departamento" class="form-label">
                                        <i class="fas fa-sitemap text-primary me-1"></i>Área/Departamento *
                                    </label>
                                    <input type="text" class="form-control" id="area_departamento" name="area_departamento" 
                                           value="<?php echo htmlspecialchars($form_data['area_departamento'] ?? ''); ?>" 
                                           placeholder="Ej: Desarrollo de Software">
                                </div>
                                <div class="col-md-3">
                                    <label for="periodo_inicial" class="form-label">
                                        <i class="fas fa-calendar-alt text-primary me-1"></i>Período Inicial *
                                    </label>
                                    <input type="date" class="form-control" id="periodo_inicial" name="periodo_inicial" 
                                           value="<?php echo htmlspecialchars($form_data['periodo_inicial'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="periodo_final" class="form-label">
                                        <i class="fas fa-calendar-check text-primary me-1"></i>Período Final *
                                    </label>
                                    <input type="date" class="form-control" id="periodo_final" name="periodo_final" 
                                           value="<?php echo htmlspecialchars($form_data['periodo_final'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3"><i class="fas fa-tasks me-2"></i>Actividades del Proyecto</h6>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="act_1" class="form-label">Actividad 1</label>
                                    <textarea class="form-control" id="act_1" name="act_1" rows="2" 
                                              placeholder="Describe la primera actividad..."><?php echo htmlspecialchars($form_data['act_1'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="act_2" class="form-label">Actividad 2</label>
                                    <textarea class="form-control" id="act_2" name="act_2" rows="2" 
                                              placeholder="Describe la segunda actividad..."><?php echo htmlspecialchars($form_data['act_2'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="act_3" class="form-label">Actividad 3</label>
                                    <textarea class="form-control" id="act_3" name="act_3" rows="2" 
                                              placeholder="Describe la tercera actividad..."><?php echo htmlspecialchars($form_data['act_3'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="act_4" class="form-label">Actividad 4</label>
                                    <textarea class="form-control" id="act_4" name="act_4" rows="2" 
                                              placeholder="Describe la cuarta actividad..."><?php echo htmlspecialchars($form_data['act_4'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <h6 class="text-success mb-3"><i class="fas fa-bullseye me-2"></i>Metas del Proyecto</h6>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="meta_1" class="form-label">Meta 1</label>
                                    <textarea class="form-control" id="meta_1" name="meta_1" rows="2" 
                                              placeholder="Describe la primera meta..."><?php echo htmlspecialchars($form_data['meta_1'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="meta_2" class="form-label">Meta 2</label>
                                    <textarea class="form-control" id="meta_2" name="meta_2" rows="2" 
                                              placeholder="Describe la segunda meta..."><?php echo htmlspecialchars($form_data['meta_2'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="meta_3" class="form-label">Meta 3</label>
                                    <textarea class="form-control" id="meta_3" name="meta_3" rows="2" 
                                              placeholder="Describe la tercera meta..."><?php echo htmlspecialchars($form_data['meta_3'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="meta_4" class="form-label">Meta 4</label>
                                    <textarea class="form-control" id="meta_4" name="meta_4" rows="2" 
                                              placeholder="Describe la cuarta meta..."><?php echo htmlspecialchars($form_data['meta_4'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                        </div>
                    <!-- Sección 3: Información de la Empresa/Organización -->
                    <hr class="my-4">
                    <h4 class="mb-3"><i class="fas fa-building me-2"></i>Información de la Empresa / Organización</h4>
                    
                    <div class="card mb-4" id="card-empresa">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-building me-2"></i>Información de la Empresa/Organización</h5>
                            <?php
                            $empresa_completa = !empty($form_data['nombre_empresa']) || 
                                              !empty($form_data['asesor_nombre']);
                            ?>
                            <span class="badge <?php echo $empresa_completa ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $empresa_completa ? 'Con Datos' : 'Sin datos'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h6 class="text-primary mb-3"><i class="fas fa-id-card-alt me-2"></i>Datos de la Estancia</h6>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="estancia_tipo" class="form-label">Tipo</label>
                                    <select class="form-select" id="estancia_tipo" name="estancia_tipo">
                                        <option value="">Seleccionar</option>
                                        <option value="Estancia" <?php echo ($form_data['estancia_tipo'] ?? '') === 'Estancia' ? 'selected' : ''; ?>>Estancia</option>
                                        <option value="Estadia" <?php echo ($form_data['estancia_tipo'] ?? '') === 'Estadia' ? 'selected' : ''; ?>>Estadía</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="estancia_numero" class="form-label">Número</label>
                                    <select class="form-select" id="estancia_numero" name="estancia_numero">
                                        <option value="">Seleccionar</option>
                                        <option value="N/A" <?php echo ($form_data['estancia_numero'] ?? '') === 'N/A' ? 'selected' : ''; ?>>N/A</option>
                                        <option value="1" <?php echo ($form_data['estancia_numero'] ?? '') === '1' ? 'selected' : ''; ?>>1</option>
                                        <option value="2" <?php echo ($form_data['estancia_numero'] ?? '') === '2' ? 'selected' : ''; ?>>2</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="horas" class="form-label">Horas</label>
                                    <input type="number" class="form-control" id="horas" name="horas" min="0" 
                                           value="<?php echo htmlspecialchars($form_data['horas'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="fecha_presentacion" class="form-label">Fecha Presentación</label>
                                    <input type="date" class="form-control" id="fecha_presentacion" name="fecha_presentacion" 
                                           value="<?php echo htmlspecialchars($form_data['fecha_presentacion'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="fecha_termino" class="form-label">Fecha Término</label>
                                    <input type="date" class="form-control" id="fecha_termino" name="fecha_termino" 
                                           value="<?php echo htmlspecialchars($form_data['fecha_termino'] ?? ''); ?>">
                                </div>
                            </div>

                            <h6 class="text-primary mb-3"><i class="fas fa-building me-2"></i>Datos de la Empresa</h6>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="nombre_empresa" class="form-label">Nombre de la Empresa *</label>
                                    <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" 
                                           value="<?php echo htmlspecialchars($form_data['nombre_empresa'] ?? ''); ?>" 
                                           placeholder="Nombre completo de la empresa u organización">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="direccion_empresa" class="form-label">Dirección</label>
                                    <input type="text" class="form-control" id="direccion_empresa" name="direccion_empresa" 
                                           value="<?php echo htmlspecialchars($form_data['direccion_empresa'] ?? ''); ?>" 
                                           placeholder="Dirección completa de la empresa">
                                </div>
                            </div>

                            <h6 class="text-info mb-3"><i class="fas fa-user-tie me-2"></i>Datos del Asesor Empresarial</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="asesor_nombre" class="form-label">Nombre Completo del Asesor</label>
                                    <input type="text" class="form-control" id="asesor_nombre" name="asesor_nombre" 
                                           value="<?php echo htmlspecialchars($form_data['asesor_nombre'] ?? ''); ?>" 
                                           placeholder="Nombre completo del asesor empresarial">
                                </div>
                                <div class="col-md-6">
                                    <label for="asesor_cargo" class="form-label">Cargo/Puesto</label>
                                    <input type="text" class="form-control" id="asesor_cargo" name="asesor_cargo" 
                                           value="<?php echo htmlspecialchars($form_data['asesor_cargo'] ?? ''); ?>" 
                                           placeholder="Cargo o puesto del asesor">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="asesor_telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="asesor_telefono" name="asesor_telefono" 
                                           value="<?php echo htmlspecialchars($form_data['asesor_telefono'] ?? ''); ?>" 
                                           placeholder="Teléfono de contacto del asesor">
                                </div>
                                <div class="col-md-6">
                                    <label for="asesor_email" class="form-label">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="asesor_email" name="asesor_email" 
                                           value="<?php echo htmlspecialchars($form_data['asesor_email'] ?? ''); ?>" 
                                           placeholder="correo@empresa.com">
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Botón único de guardado general -->
                    <div class="text-center mt-5">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Guardar Todos los Datos
                        </button>
                        <p class="text-muted mt-2 small"><i class="fas fa-info-circle me-1"></i>Al guardar se procesarán los datos de estudiante, proyecto y empresa en un solo paso.</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Botón de navegación -->
        <div class="row mb-5">
            <div class="col-12 text-center">
                <a href="dashboard.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Datos precargados desde PHP para auto-completar (organizaciones)
        const ORGANIZACIONES = <?php echo json_encode($organizaciones_lista, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        // Auto-relleno de empresa al elegir nombre existente
        document.addEventListener('DOMContentLoaded', () => {
            const nombreEmpresaInput = document.getElementById('nombre_empresa');
            if (nombreEmpresaInput) {
                // Crear datalist dinámico
                let dataList = document.getElementById('lista_empresas');
                if (!dataList) {
                    dataList = document.createElement('datalist');
                    dataList.id = 'lista_empresas';
                    document.body.appendChild(dataList);
                }
                nombreEmpresaInput.setAttribute('list', 'lista_empresas');
                dataList.innerHTML = ORGANIZACIONES.map(org => `<option value="${org.nombre_organizacion}"></option>`).join('');

                nombreEmpresaInput.addEventListener('change', () => {
                    const val = nombreEmpresaInput.value.trim();
                    const org = ORGANIZACIONES.find(o => o.nombre_organizacion === val);
                    if (org) {
                        // Auto llenar campos de la empresa
                        const fillMap = {
                            direccion_empresa: 'direccion_org',
                            asesor_nombre: 'contacto_org_congrado',
                            asesor_cargo: 'puesto_contacto',
                            asesor_email: 'email_org',
                            asesor_telefono: 'telefono_org'
                        };
                        Object.entries(fillMap).forEach(([inputId, orgKey]) => {
                            const el = document.getElementById(inputId);
                            if (el && org[orgKey]) el.value = org[orgKey];
                        });
                    }
                });
            }
        });

        // Función para mostrar mensaje de confirmación al guardar
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar eventos a todos los formularios
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const button = form.querySelector('button[type="submit"]');
                    if (button) {
                        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
                        button.disabled = true;
                        
                        // Mostrar mensaje de que se verificarán las cartas disponibles
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-info mt-3';
                        alertDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>Guardando datos y verificando cartas disponibles...';
                        button.parentNode.appendChild(alertDiv);
                    }
                });
            });

            // Auto-scroll a sección con errores o éxito
            <?php if (!empty($error) || !empty($success)): ?>
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
                
                // Si hay éxito, mostrar mensaje sobre cartas
                <?php if (!empty($success) && !empty($cartas_disponibles)): ?>
                    setTimeout(function() {
                        const cartasSection = document.querySelector('.border-info');
                        if (cartasSection) {
                            cartasSection.scrollIntoView({behavior: 'smooth'});
                            cartasSection.style.boxShadow = '0 0 15px rgba(0, 123, 255, 0.3)';
                            setTimeout(() => {
                                cartasSection.style.boxShadow = '';
                            }, 3000);
                        }
                    }, 1000);
                <?php endif; ?>
            <?php endif; ?>
        });

        // Función para validar email en tiempo real
        document.getElementById('correo_electronico_estudiante')?.addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.classList.add('is-invalid');
                if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('invalid-feedback')) {
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = 'Por favor ingresa un correo electrónico válido';
                    this.parentNode.appendChild(feedback);
                }
            } else {
                this.classList.remove('is-invalid');
                const feedback = this.parentNode.querySelector('.invalid-feedback');
                if (feedback) feedback.remove();
            }
        });
        
        // Función para mostrar tooltips informativos
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>