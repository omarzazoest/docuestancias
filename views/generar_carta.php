<?php
session_start();
require_once '../config/database.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['matricula'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$matricula = $_SESSION['matricula'];
$tipo_carta = $_GET['tipo'] ?? '';

if (empty($tipo_carta)) {
    header('Location: datos_cartas_simple.php');
    exit();
}

// Obtener todos los datos necesarios
try {
    // Datos del alumno
    $query_alumno = "SELECT * FROM alumnos WHERE matricula = :matricula LIMIT 1";
    $stmt_alumno = $db->prepare($query_alumno);
    $stmt_alumno->bindParam(':matricula', $matricula);
    $stmt_alumno->execute();
    $datos_alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);
    
    if (!$datos_alumno) {
        die('Error: No se encontraron datos del estudiante');
    }
    
    // Datos del proyecto
    $query_cooperacion = "SELECT c.* FROM cooperacion c 
                         INNER JOIN estancias e ON c.id_cooperacion = e.id_cooperacion 
                         WHERE e.id_alumno = :id_alumno LIMIT 1";
    $stmt_cooperacion = $db->prepare($query_cooperacion);
    $stmt_cooperacion->bindParam(':id_alumno', $datos_alumno['id_alumno']);
    $stmt_cooperacion->execute();
    $proyecto_cooperacion = $stmt_cooperacion->fetch(PDO::FETCH_ASSOC);
    
    // Datos de la empresa
    $query_empresa = "SELECT e.*, o.* FROM estancias e 
                     LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion 
                     WHERE e.id_alumno = :id_alumno LIMIT 1";
    $stmt_empresa = $db->prepare($query_empresa);
    $stmt_empresa->bindParam(':id_alumno', $datos_alumno['id_alumno']);
    $stmt_empresa->execute();
    $datos_empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Error en la base de datos: ' . $e->getMessage());
}

// Función para generar la carta de presentación
function generarCartaPresentacion($datos_alumno, $datos_empresa) {
    $fecha_actual = date('d/m/Y');
    $nombre_completo = trim($datos_alumno['nombres'] . ' ' . $datos_alumno['paterno'] . ' ' . $datos_alumno['materno']);
    
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                margin: 40px;
                color: #333;
            }
            .header { 
                text-align: center; 
                margin-bottom: 40px;
                border-bottom: 2px solid #0066cc;
                padding-bottom: 20px;
            }
            .logo {
                font-size: 18px;
                font-weight: bold;
                color: #0066cc;
                margin-bottom: 10px;
            }
            .fecha { 
                text-align: right; 
                margin-bottom: 30px;
                font-size: 14px;
            }
            .destinatario {
                margin-bottom: 30px;
                font-weight: bold;
            }
            .cuerpo {
                text-align: justify;
                margin-bottom: 30px;
                line-height: 1.8;
            }
            .firma {
                margin-top: 60px;
                text-align: center;
            }
            .linea-firma {
                border-top: 1px solid #333;
                width: 300px;
                margin: 0 auto 10px auto;
            }
            .datos-estudiante {
                background-color: #f8f9fa;
                padding: 15px;
                border-left: 4px solid #0066cc;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo">INSTITUTO TECNOLÓGICO SUPERIOR DE HUAUCHINANGO</div>
            <div>División de Estudios Profesionales</div>
            <div>Departamento de Vinculación y Extensión</div>
        </div>
        
        <div class="fecha">
            Huauchinango, Puebla a ' . $fecha_actual . '
        </div>
        
        <div class="destinatario">
            ' . htmlspecialchars($datos_empresa['contacto_org_congrado'] ?? 'ASESOR EMPRESARIAL') . '<br>
            ' . htmlspecialchars($datos_empresa['puesto_contacto'] ?? 'CARGO') . '<br>
            ' . htmlspecialchars($datos_empresa['nombre_organizacion'] ?? 'EMPRESA') . '<br>
            PRESENTE
        </div>
        
        <div class="cuerpo">
            <p>Por medio de la presente, me dirijo a usted de la manera más atenta para presentar 
            al estudiante <strong>' . htmlspecialchars($nombre_completo) . '</strong>, 
            con matrícula <strong>' . htmlspecialchars($datos_alumno['matricula']) . '</strong>, 
            quien cursará su estancia profesional en esa prestigiosa empresa.</p>
            
            <div class="datos-estudiante">
                <strong>DATOS DEL ESTUDIANTE:</strong><br>
                <strong>Nombre:</strong> ' . htmlspecialchars($nombre_completo) . '<br>
                <strong>Matrícula:</strong> ' . htmlspecialchars($datos_alumno['matricula']) . '<br>
                <strong>Correo:</strong> ' . htmlspecialchars($datos_alumno['correo_electronico'] ?? 'No especificado') . '<br>
                <strong>Teléfono:</strong> ' . htmlspecialchars($datos_alumno['telefono'] ?? 'No especificado') . '
            </div>
            
            <p>El estudiante ha demostrado un excelente rendimiento académico y cuenta con las 
            competencias necesarias para desarrollar las actividades que le sean asignadas 
            durante su período de estancia profesional.</p>
            
            <p>Agradecemos de antemano la atención brindada a la presente y quedamos a sus 
            órdenes para cualquier aclaración que considere necesaria.</p>
        </div>
        
        <div class="cuerpo">
            <p><strong>ATENTAMENTE</strong></p>
        </div>
        
        <div class="firma">
            <div class="linea-firma"></div>
            <div><strong>COORDINADOR DE VINCULACIÓN</strong></div>
            <div>Instituto Tecnológico Superior de Huauchinango</div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Procesar según el tipo de carta
$html_content = '';
$nombre_archivo = '';

switch ($tipo_carta) {
    case 'presentacion':
        // Verificar que existan los datos necesarios
        if (empty($datos_empresa['contacto_org_congrado']) || 
            empty($datos_empresa['puesto_contacto']) || 
            empty($datos_empresa['nombre_organizacion']) || 
            empty($datos_alumno['nombres'])) {
            die('Error: Faltan datos necesarios para generar la carta de presentación');
        }
        
        $html_content = generarCartaPresentacion($datos_alumno, $datos_empresa);
        $nombre_archivo = 'carta_presentacion_' . $datos_alumno['matricula'] . '_' . date('Y-m-d') . '.pdf';
        break;
        
    default:
        die('Error: Tipo de carta no reconocido');
}

// Si no se especifica generar PDF, mostrar vista previa
if (!isset($_GET['generar_pdf'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Vista Previa - Carta de Presentación</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="datos_cartas_simple.php">
                    <i class="fas fa-arrow-left me-2"></i>
                    Volver al Formulario
                </a>
            </div>
        </nav>
        
        <div class="container mt-4">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Vista Previa de la Carta</h2>
                        <div>
                            <a href="?tipo=<?php echo urlencode($tipo_carta); ?>&generar_pdf=1" 
                               class="btn btn-success me-2">
                                <i class="fas fa-download me-2"></i>Descargar PDF
                            </a>
                            <a href="datos_cartas_simple.php" class="btn btn-secondary">
                                <i class="fas fa-edit me-2"></i>Editar Datos
                            </a>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <div style="background: white; padding: 20px; border: 1px solid #ddd;">
                                <?php echo $html_content; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Generar PDF usando mPDF (si está disponible) o mostrar HTML para imprimir
if (class_exists('Mpdf\Mpdf')) {
    require_once '../vendor/autoload.php';
    
    $mpdf = new \Mpdf\Mpdf([
        'format' => 'Letter',
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_left' => 20,
        'margin_right' => 20
    ]);
    
    $mpdf->WriteHTML($html_content);
    $mpdf->Output($nombre_archivo, 'D');
} else {
    // Si no hay mPDF, mostrar para imprimir
    header('Content-Type: text/html; charset=utf-8');
    echo $html_content;
    echo '<script>window.print();</script>';
}
?>