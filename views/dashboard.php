<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user_data.php';
require_once '../includes/user_data_backup.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['matricula'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Recargar datos si es necesario
if (function_exists('necesitaRecargarDatos') && necesitaRecargarDatos()) {
    cargarDatosCompletos($_SESSION['matricula'], $db);
}

// Obtener resumen de datos (usar función de respaldo si la principal no existe)
if (function_exists('obtenerResumenDatos')) {
    $resumen = obtenerResumenDatos();
} else {
    $resumen = obtenerResumenDatosBasico();
}

// Obtener información adicional del alumno (antes de usar $matricula)
$matricula = $_SESSION['matricula'];
$nombres = $_SESSION['nombres'];
$paterno = $_SESSION['paterno'];
$materno = $_SESSION['materno'];

$pdfs_disponibles = $resumen['pdfs_disponibles'];
// Sincronización: asegurar fila cooperacion existente y flags generados/enviados coherentes con archivos y parámetro ?sent
try {
    $stmtAl = $db->prepare('SELECT * FROM alumnos WHERE matricula = :mat LIMIT 1');
    $stmtAl->bindParam(':mat', $matricula); $stmtAl->execute();
    $alRow = $stmtAl->fetch(PDO::FETCH_ASSOC);
    if ($alRow) {
        // cooperacion row
    $stmtCo = $db->prepare('SELECT * FROM cooperacion WHERE id_alumno = :ida LIMIT 1');
    $stmtCo->bindValue(':ida', $alRow['id_alumno']);
        $stmtCo->execute();
        $coDash = $stmtCo->fetch(PDO::FETCH_ASSOC);
        if (!$coDash) {
            $insMin = $db->prepare('INSERT INTO cooperacion (id_alumno) VALUES (:ida)');
            $insMin->bindValue(':ida', $alRow['id_alumno']);
            $insMin->execute();
            $stmtCo->execute();
            $coDash = $stmtCo->fetch(PDO::FETCH_ASSOC);
        }

        // docus row
        $stmtDocs = $db->prepare('SELECT url_presentacion, url_cooperacion, url_termino FROM docus WHERE id_alumno = :ida LIMIT 1');
        $stmtDocs->bindValue(':ida', $alRow['id_alumno']);
        $stmtDocs->execute();
        $docRow = $stmtDocs->fetch(PDO::FETCH_ASSOC) ?: [];

        $updateParts = [];
        $map = [
            'presentacion' => ['gen' => 'carta_presentacion_generada', 'env' => 'carta_presentacion_enviada', 'col' => 'url_presentacion'],
            'cooperacion'  => ['gen' => 'carta_cooperacion_generada',  'env' => 'carta_cooperacion_enviada',  'col' => 'url_cooperacion'],
            'termino'      => ['gen' => 'carta_termino_generada',      'env' => 'carta_termino_enviada',      'col' => 'url_termino'],
        ];

        foreach ($map as $t => $info) {
            $url = $docRow[$info['col']] ?? null;
            if (!$url) {
                $candidate = 'storage/pdfs/' . rawurlencode($matricula) . '/' . $t . '.pdf';
                if (is_file(__DIR__ . '/../' . $candidate)) { $url = $candidate; }
            }
            // marcar generada si hay archivo y flag en 0
            if ($url && empty($coDash[$info['gen']])) { $updateParts[] = $info['gen'] . ' = 1'; $coDash[$info['gen']] = 1; }
        }

        // manejo de parámetro ?sent=tipo para mostrar alerta y marcar enviado
        $sentTipo = isset($_GET['sent']) ? strtolower(trim($_GET['sent'])) : '';
        if ($sentTipo && isset($map[$sentTipo])) {
            $envFlag = $map[$sentTipo]['env'];
            if (empty($coDash[$envFlag])) { $updateParts[] = $envFlag . ' = 1'; $coDash[$envFlag] = 1; }
        }

        if ($updateParts) {
            $sqlU = 'UPDATE cooperacion SET ' . implode(', ', $updateParts) . ' WHERE id_cooperacion = :idc';
            $u = $db->prepare($sqlU); $u->bindValue(':idc', $coDash['id_cooperacion']); $u->execute();
        }

        // actualizar disponibilidad de cartas
        foreach (['presentacion','cooperacion','termino'] as $t) {
            $flagGen = $map[$t]['gen'];
            if (!empty($coDash[$flagGen])) { $pdfs_disponibles['carta_'.$t] = true; }
        }
    }
} catch (Exception $e) { /* silencioso */ }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Estancias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                Sistema de Estancias
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($nombres . ' ' . $paterno); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="mostrarPerfil()">
                                <i class="fas fa-user me-2"></i>Mi Perfil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar bg-light">
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="datos_cartas_simple.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-alt me-2"></i>Datos para Cartas
                            <?php if ($resumen['datos_cartas_completos']): ?>
                                <span class="badge bg-success ms-2">Completo</span>
                            <?php elseif ($resumen['tiene_datos_cartas']): ?>
                                <span class="badge bg-warning text-dark ms-2">Incompleto</span>
                            <?php else: ?>
                                <span class="badge bg-danger ms-2">Pendiente</span>
                            <?php endif; ?>
                        </a>
                        <a href="estancias.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-building me-2"></i>Mis Estancias
                        </a>
                        <a href="documentos.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-download me-2"></i>Documentos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 col-lg-10">
                <div class="container mt-4">
                    
                    <!-- Dashboard Section -->
                    <div id="dashboard" class="content-section">
                        <div class="row mb-4">
                            <div class="col-12">
                                <h2>Bienvenido, <?php echo htmlspecialchars($nombres . ' ' . $paterno . ' ' . $materno); ?></h2>
                                <?php if (!empty($_GET['sent']) && in_array($_GET['sent'], ['presentacion','cooperacion','termino'], true)): ?>
                                    <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                                        <i class="fas fa-check-circle me-1"></i> Carta de <strong><?php echo htmlspecialchars(ucfirst($_GET['sent'])); ?></strong> enviada correctamente.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <p class="text-muted">Matrícula: <?php echo htmlspecialchars($matricula); ?></p>
                                
                                
                            </div>
                        </div>

                        <!-- Sección de Documentos PDF -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4><i class="fas fa-file-pdf me-2 text-danger"></i>Documentos y Progreso</h4>
                                <?php
                                // Calcular progreso
                                // 1. Datos llenados (uso de campos clave en alumnos + cooperacion + estancias + organizacion)
                                $totalCampos = 0; $completos = 0;
                                $camposAlumno = [ 'nombres','paterno','materno','correo_electronico','telefono' ];
                                $camposCoop   = [ 'nombre_proyecto','objetivos','area-departamento','periodo_inicial','periodo_final','act_1','act_2','act_3','act_4','meta_1','meta_2','meta_3','meta_4' ];
                                $camposOrg    = [ 'nombre_organizacion','direccion_org','contacto_org_congrado','puesto_contacto','email_org','telefono_org' ];
                                $camposClave = array_merge($camposAlumno, $camposCoop, $camposOrg);
                                // Recuperar sets
                                $stmtDashAl = $db->prepare('SELECT * FROM alumnos WHERE matricula = :mat LIMIT 1');
                                $stmtDashAl->bindParam(':mat', $matricula); $stmtDashAl->execute();
                                $alDash = $stmtDashAl->fetch(PDO::FETCH_ASSOC) ?: [];
                                $stmtDashCo = $db->prepare('SELECT * FROM cooperacion WHERE id_alumno = :ida LIMIT 1');
                                $stmtDashCo->bindValue(':ida', $alDash['id_alumno'] ?? 0); $stmtDashCo->execute();
                                $coDash = $stmtDashCo->fetch(PDO::FETCH_ASSOC) ?: [];
                                $stmtDashEs = $db->prepare('SELECT o.* FROM estancias e LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion WHERE e.id_alumno = :ida LIMIT 1');
                                $stmtDashEs->bindValue(':ida', $alDash['id_alumno'] ?? 0); $stmtDashEs->execute();
                                $orgDash = $stmtDashEs->fetch(PDO::FETCH_ASSOC) ?: [];
                                $totalCampos = count($camposClave);
                                foreach ($camposClave as $c) {
                                    $val = $alDash[$c] ?? $coDash[$c] ?? $orgDash[$c] ?? '';
                                    if (!empty(trim((string)$val))) $completos++;
                                }
                                $pctDatos = $totalCampos ? round(($completos / $totalCampos) * 100) : 0;
                                // 2. PDFs generados (flags cooperacion)
                                $generados = 0; $porGenerados = 0; $enviados = 0; $porEnviados = 0;
                                if ($coDash) {
                                    $flagsGen = [ 'carta_presentacion_generada','carta_cooperacion_generada','carta_termino_generada' ];
                                    $flagsEnv = [ 'carta_presentacion_enviada','carta_cooperacion_enviada','carta_termino_enviada' ];
                                    foreach ($flagsGen as $fg) { if (!empty($coDash[$fg])) $generados++; }
                                    foreach ($flagsEnv as $fe) { if (!empty($coDash[$fe])) $enviados++; }
                                    $porGenerados = round(($generados / count($flagsGen))*100);
                                    $porEnviados = round(($enviados / count($flagsEnv))*100);
                                }
                                ?>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title mb-2"><i class="fas fa-database me-1"></i>Datos Llenados</h6>
                                                <div class="progress" style="height:20px;">
                                                    <div class="progress-bar bg-info" style="width: <?= $pctDatos ?>%;"><?= $pctDatos ?>%</div>
                                                </div>
                                                <small class="text-muted">Campos completos: <?= $completos ?>/<?= $totalCampos ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title mb-2"><i class="fas fa-file-pdf me-1"></i>Cartas Generadas</h6>
                                                <div class="progress" style="height:20px;">
                                                    <div class="progress-bar bg-success" style="width: <?= $porGenerados ?>%;"><?= $porGenerados ?>%</div>
                                                </div>
                                                <small class="text-muted">Generadas: <?= $generados ?>/3</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title mb-2"><i class="fas fa-paper-plane me-1"></i>Cartas Enviadas</h6>
                                                <div class="progress" style="height:20px;">
                                                    <div class="progress-bar bg-warning" style="width: <?= $porEnviados ?>%;"><?= $porEnviados ?>%</div>
                                                </div>
                                                <small class="text-muted">Enviadas: <?= $enviados ?>/3</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sección: Cartas generadas (listado) -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Cartas generadas</h5>
                                        <small class="text-muted">Archivos guardados por tipo</small>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // Recuperar URLs desde docus (uno por tipo)
                                        $docusUrls = ['presentacion'=>null,'cooperacion'=>null,'termino'=>null];
                                        try {
                                            if (!empty($alDash['id_alumno'])) {
                                                $stmtD = $db->prepare('SELECT url_presentacion, url_cooperacion, url_termino FROM docus WHERE id_alumno = :ida LIMIT 1');
                                                $stmtD->bindValue(':ida', $alDash['id_alumno']);
                                                $stmtD->execute();
                                                $docRow = $stmtD->fetch(PDO::FETCH_ASSOC) ?: [];
                                                if ($docRow) {
                                                    $docusUrls['presentacion'] = $docRow['url_presentacion'] ?: null;
                                                    $docusUrls['cooperacion']  = $docRow['url_cooperacion']  ?: null;
                                                    $docusUrls['termino']      = $docRow['url_termino']      ?: null;
                                                }
                                            }
                                        } catch (Exception $e) { /* noop */ }
                                        ?>
                                        <div class="row">
                                            <?php foreach (['presentacion'=>'Presentación','cooperacion'=>'Cooperación','termino'=>'Término'] as $tkey=>$tlabel): ?>
                                                <div class="col-md-4 mb-3">
                                                    <h6><i class="fas fa-file-pdf me-1"></i><?= $tlabel ?></h6>
                                                    <?php
                                                        $url = $docusUrls[$tkey] ?? null;
                                                        if (!$url) {
                                                            $candidate = 'storage/pdfs/' . rawurlencode($matricula) . '/' . $tkey . '.pdf';
                                                            if (is_file(__DIR__ . '/../' . $candidate)) { $url = $candidate; }
                                                        }
                                                    ?>
                                                    <?php if (!empty($url)): ?>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="text-truncate" style="max-width:60%><?= htmlspecialchars(basename($url)) ?></span>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a class="btn btn-outline-secondary" href="<?php echo '../' . ltrim($url,'/'); ?>" target="_blank" title="Ver">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a class="btn btn-outline-primary" href="<?php echo 'enviar_carta.php?tipo=' . urlencode($tkey); ?>" title="Enviar por correo">
                                                                    <i class="fas fa-paper-plane"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-muted small">No hay archivos generados</div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-file-signature fa-3x <?php echo $pdfs_disponibles['carta_presentacion'] ? 'text-success' : 'text-muted'; ?> mb-3"></i>
                                        <h6 class="card-title">Carta de Presentación</h6>
                                        <p class="card-text small">Para presentarte ante la empresa</p>
                                        <?php if ($pdfs_disponibles['carta_presentacion']): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <button class="btn btn-success btn-sm" onclick="generarCarta('presentacion')">
                                                    <i class="fas fa-download me-1"></i>Generar PDF
                                                </button>
                                                <button class="btn btn-outline-success btn-sm" onclick="enviarCarta('presentacion')" <?= !empty($coDash['carta_presentacion_generada']) ? '' : 'disabled' ?>>
                                                    <i class="fas fa-paper-plane me-1"></i>Enviar
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-lock me-1"></i>No disponible
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-handshake fa-3x <?php echo $pdfs_disponibles['carta_cooperacion'] ? 'text-primary' : 'text-muted'; ?> mb-3"></i>
                                        <h6 class="card-title">Carta de Cooperación</h6>
                                        <p class="card-text small">Convenio con la empresa</p>
                                        <?php if ($pdfs_disponibles['carta_cooperacion']): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <button class="btn btn-primary btn-sm" onclick="generarCarta('cooperacion')">
                                                    <i class="fas fa-download me-1"></i>Generar PDF
                                                </button>
                                                <button class="btn btn-outline-primary btn-sm" onclick="enviarCarta('cooperacion')" <?= !empty($coDash['carta_cooperacion_generada']) ? '' : 'disabled' ?>>
                                                    <i class="fas fa-paper-plane me-1"></i>Enviar
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-lock me-1"></i>No disponible
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-certificate fa-3x <?php echo $pdfs_disponibles['carta_termino'] ? 'text-warning' : 'text-muted'; ?> mb-3"></i>
                                        <h6 class="card-title">Carta de Término</h6>
                                        <p class="card-text small">Al finalizar la estancia</p>
                                        <?php if ($pdfs_disponibles['carta_termino']): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <button class="btn btn-warning btn-sm" onclick="generarCarta('termino')">
                                                    <i class="fas fa-download me-1"></i>Generar PDF
                                                </button>
                                                <button class="btn btn-outline-warning btn-sm" onclick="enviarCarta('termino')" <?= !empty($coDash['carta_termino_generada']) ? '' : 'disabled' ?>>
                                                    <i class="fas fa-paper-plane me-1"></i>Enviar
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-lock me-1"></i>No disponible
                                            </button>
                                            <small class="text-muted d-block mt-1">Requiere fechas de inicio y fin</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-award fa-3x <?php echo $pdfs_disponibles['constancia_estancia'] ? 'text-info' : 'text-muted'; ?> mb-3"></i>
                                        <h6 class="card-title">Constancia de Estancia</h6>
                                        <p class="card-text small">Certificado de participación</p>
                                        <?php if ($pdfs_disponibles['constancia_estancia']): ?>
                                            <button class="btn btn-info btn-sm" onclick="generarPDF('constancia')">
                                                <i class="fas fa-download me-1"></i>Generar PDF
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-lock me-1"></i>No disponible
                                            </button>
                                            <small class="text-muted d-block mt-1">Requiere estancia registrada</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Acciones Principales -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4><i class="fas fa-tasks me-2"></i>Acciones Principales</h4>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-file-alt fa-3x text-primary mb-3"></i>
                                        <h5 class="card-title">Datos para Cartas</h5>
                                        <p class="card-text">Completa la información necesaria para generar las cartas oficiales.</p>
                                        <a class="btn btn-primary" href="datos_cartas_simple.php">
                                            <?php if ($resumen['tiene_datos_cartas']): ?>
                                                Ver/Editar Datos
                                            <?php else: ?>
                                                Completar Datos
                                            <?php endif; ?>
                                        </a>
                                        <?php if ($resumen['tiene_datos_cartas']): ?>
                                            <span class="badge bg-success mt-2">Completado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning mt-2">Pendiente</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-building fa-3x text-success mb-3"></i>
                                        <h5 class="card-title">Mis Estancias</h5>
                                        <p class="card-text">Consulta el historial y estado de tus estancias registradas.</p>
                                        <a class="btn btn-success" href="estancias.php">Ver Estancias</a>
                                        <span class="badge bg-info mt-2"><?php echo $resumen['num_estancias']; ?> registrada(s)</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-download fa-3x text-info mb-3"></i>
                                        <h5 class="card-title">Documentos</h5>
                                        <p class="card-text">Descarga las cartas y documentos generados para tus estancias.</p>
                                        <a class="btn btn-info" href="documentos.php">Ver Documentos</a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-user fa-3x text-warning mb-3"></i>
                                        <h5 class="card-title">Mi Perfil</h5>
                                        <p class="card-text">Actualiza tu información personal y de contacto.</p>
                                        <button class="btn btn-warning" onclick="mostrarPerfil()">Ver Perfil</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Datos para Cartas Section -->
                    <div id="datos-cartas" class="content-section" style="display: none;">
                        <div class="row mb-4">
                            <div class="col-12">
                                <h2>Datos para Cartas</h2>
                                <p class="text-muted">Completa toda la información necesaria para generar las cartas oficiales de tu estancia.</p>
                            </div>
                        </div>
                        <div id="datos-cartas-content">
                            <!-- El contenido se cargará dinámicamente -->
                        </div>
                    </div>

                    <!-- Estancias Section -->
                    <div id="estancias" class="content-section" style="display: none;">
                        <div class="row mb-4">
                            <div class="col-12">
                                <h2>Mis Estancias</h2>
                                <p class="text-muted">Historial y estado de tus estancias registradas.</p>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Esta sección mostrará el historial de tus estancias una vez que tengas registros en el sistema.
                        </div>
                    </div>

                    <!-- Documentos Section -->
                    <div id="documentos" class="content-section" style="display: none;">
                        <div class="row mb-4">
                            <div class="col-12">
                                <h2>Documentos</h2>
                                <p class="text-muted">Descarga las cartas y documentos generados.</p>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Los documentos estarán disponibles una vez que completes los datos necesarios y se generen las cartas oficiales.
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar perfil -->
    <div class="modal fade" id="perfilModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mi Perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-4"><strong>Matrícula:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($matricula); ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Nombres:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($nombres); ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Apellidos:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($paterno . ' ' . $materno); ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Email:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($_SESSION['correo_electronico']); ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function mostrarSeccion(seccion) {
            // Ocultar todas las secciones
            document.querySelectorAll('.content-section').forEach(el => el.style.display = 'none');
            
            // Remover clase active de todos los enlaces
            document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));
            
            // Mostrar la sección seleccionada
            document.getElementById(seccion).style.display = 'block';
            
            // Agregar clase active al enlace clickeado
            event.target.classList.add('active');
            
            // Cargar contenido específico si es necesario
            if (seccion === 'datos-cartas') {
                cargarFormularioDatos();
            }
        }
        
        function mostrarPerfil() {
            const modal = new bootstrap.Modal(document.getElementById('perfilModal'));
            modal.show();
        }
        
        function cargarFormularioDatos() {
            // Aquí cargaremos el formulario de datos vía AJAX
            const content = document.getElementById('datos-cartas-content');
            content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando formulario...</div>';
            
            // Simular carga del formulario
            setTimeout(() => {
                window.location.href = 'datos_cartas_simple.php';
            }, 1000);
        }
        
        function generarCarta(tipo) {
            const btn = event.target.closest('button');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generando...';
            btn.disabled = true;
            window.location.href = `generar_carta.php?tipo=${tipo}`;
            setTimeout(()=>{ btn.innerHTML = original; btn.disabled = false; }, 2000);
        }
        function enviarCarta(tipo) {
            const btn = event.target.closest('button');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enviando...';
            btn.disabled = true;
            window.location.href = `enviar_carta.php?tipo=${tipo}`;
        }
    </script>
</body>
</html>