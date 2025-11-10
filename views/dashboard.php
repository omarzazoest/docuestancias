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

$pdfs_disponibles = $resumen['pdfs_disponibles'];

// Obtener información adicional del alumno
$matricula = $_SESSION['matricula'];
$nombres = $_SESSION['nombres'];
$paterno = $_SESSION['paterno'];
$materno = $_SESSION['materno'];
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
                        <a href="#" class="list-group-item list-group-item-action active" onclick="mostrarSeccion('dashboard')">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="mostrarSeccion('datos-cartas')">
                            <i class="fas fa-file-alt me-2"></i>Datos para Cartas
                            <?php if ($resumen['datos_cartas_completos']): ?>
                                <span class="badge bg-success ms-2">Completo</span>
                            <?php elseif ($resumen['tiene_datos_cartas']): ?>
                                <span class="badge bg-warning text-dark ms-2">Incompleto</span>
                            <?php else: ?>
                                <span class="badge bg-danger ms-2">Pendiente</span>
                            <?php endif; ?>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="mostrarSeccion('estancias')">
                            <i class="fas fa-building me-2"></i>Mis Estancias
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="mostrarSeccion('documentos')">
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
                                <p class="text-muted">Matrícula: <?php echo htmlspecialchars($matricula); ?></p>
                                
                                <!-- Barra de progreso del perfil -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Completado del Perfil</h6>
                                        <div class="progress mb-2" style="height: 25px;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" style="width: <?php echo $resumen['perfil_completo']; ?>%"
                                                 aria-valuenow="<?php echo $resumen['perfil_completo']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $resumen['perfil_completo']; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php if ($resumen['perfil_completo'] < 100): ?>
                                                Completa tu información para habilitar la generación de documentos
                                            <?php else: ?>
                                                ¡Perfil completo! Ya puedes generar todos los documentos
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Documentos PDF -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4><i class="fas fa-file-pdf me-2 text-danger"></i>Generación de Documentos</h4>
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
                                            <button class="btn btn-success btn-sm" onclick="generarPDF('presentacion')">
                                                <i class="fas fa-download me-1"></i>Generar PDF
                                            </button>
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
                                            <button class="btn btn-primary btn-sm" onclick="generarPDF('cooperacion')">
                                                <i class="fas fa-download me-1"></i>Generar PDF
                                            </button>
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
                                            <button class="btn btn-warning btn-sm" onclick="generarPDF('termino')">
                                                <i class="fas fa-download me-1"></i>Generar PDF
                                            </button>
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
                                        <button class="btn btn-primary" onclick="mostrarSeccion('datos-cartas')">
                                            <?php if ($resumen['tiene_datos_cartas']): ?>
                                                Ver/Editar Datos
                                            <?php else: ?>
                                                Completar Datos
                                            <?php endif; ?>
                                        </button>
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
                                        <button class="btn btn-success" onclick="mostrarSeccion('estancias')">Ver Estancias</button>
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
                                        <button class="btn btn-info" onclick="mostrarSeccion('documentos')">Ver Documentos</button>
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
        
        function generarPDF(tipo) {
            // Mostrar loading en el botón
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generando...';
            btn.disabled = true;
            
            // Simular generación de PDF
            setTimeout(() => {
                // Aquí iría la llamada real al generador de PDFs
                window.open(`generar_pdf.php?tipo=${tipo}`, '_blank');
                
                // Restaurar botón
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                
                // Mostrar notificación
                EstanciasApp.mostrarNotificacion(`PDF de ${tipo} generado correctamente`, 'success');
            }, 2000);
        }
    </script>
</body>
</html>