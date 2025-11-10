<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user_data.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['matricula'])) {
    header('Location: login.php');
    exit();
}

// Verificar que se especifique el tipo de PDF
if (!isset($_GET['tipo'])) {
    die('Tipo de PDF no especificado');
}

$tipo = $_GET['tipo'];
$pdfs_disponibles = verificarPDFsDisponibles();

// Verificar que el PDF esté disponible
if (!isset($pdfs_disponibles[$tipo]) || !$pdfs_disponibles[$tipo]) {
    die('Este PDF no está disponible. Completa los datos necesarios primero.');
}

// Obtener datos del usuario
$alumno = $_SESSION['alumno'];
$datos_cartas = $_SESSION['datos_cartas'];

// Función para generar PDF básico con HTML
function generarPDFHTML($titulo, $contenido) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $titulo . '.pdf"');
    
    // Por ahora, generar HTML que simule un PDF
    // En producción, usar librerías como TCPDF, FPDF o DomPDF
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . $titulo . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; }
            .content { line-height: 1.6; }
            .footer { margin-top: 50px; text-align: center; }
        </style>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </head>
    <body>
        <div class="header">
            <h1>' . $titulo . '</h1>
            <hr>
        </div>
        <div class="content">
            ' . $contenido . '
        </div>
        <div class="footer">
            <p><small>Documento generado el ' . date('d/m/Y H:i:s') . '</small></p>
        </div>
    </body>
    </html>';
}

// Generar contenido según el tipo
switch ($tipo) {
    case 'presentacion':
        $titulo = 'Carta de Presentación';
        $contenido = '
        <p><strong>CARTA DE PRESENTACIÓN</strong></p>
        
        <p>A quien corresponda:</p>
        
        <p>Por medio de la presente, me dirijo a ustedes para presentar al estudiante:</p>
        
        <p><strong>Nombre:</strong> ' . htmlspecialchars($datos_cartas['nombres_estudiante'] . ' ' . $datos_cartas['paterno_estudiante'] . ' ' . $datos_cartas['materno_estudiante']) . '</p>
        <p><strong>Matrícula:</strong> ' . htmlspecialchars($datos_cartas['matricula_estudiante']) . '</p>
        <p><strong>Carrera:</strong> ' . htmlspecialchars($datos_cartas['nombre_carrera']) . '</p>
        <p><strong>Correo:</strong> ' . htmlspecialchars($datos_cartas['correo_electronico_estudiante']) . '</p>
        
        <p>Quien realizará su ' . htmlspecialchars($datos_cartas['estancia-estadia']) . ' en su organización por un período de ' . htmlspecialchars($datos_cartas['horas']) . ' horas durante el período ' . htmlspecialchars($datos_cartas['periodo']) . '.</p>
        
        <p>Agradecemos de antemano su colaboración y apoyo para la formación profesional de nuestro estudiante.</p>
        
        <br><br>
        <p>Atentamente,</p>
        <p><strong>Coordinación Académica</strong></p>';
        break;
        
    case 'cooperacion':
        $titulo = 'Carta de Cooperación';
        $contenido = '
        <p><strong>CARTA DE COOPERACIÓN ACADÉMICA</strong></p>
        
        <p>Estimados representantes de ' . htmlspecialchars($datos_cartas['nombre_empresa']) . ':</p>
        
        <p>Por medio de la presente, formalizamos el acuerdo de cooperación académica para la realización de la ' . htmlspecialchars($datos_cartas['estancia-estadia']) . ' del estudiante:</p>
        
        <p><strong>Estudiante:</strong> ' . htmlspecialchars($datos_cartas['nombres_estudiante'] . ' ' . $datos_cartas['paterno_estudiante'] . ' ' . $datos_cartas['materno_estudiante']) . '</p>
        <p><strong>Programa:</strong> ' . htmlspecialchars($datos_cartas['nombre_carrera']) . '</p>
        <p><strong>Período:</strong> ' . htmlspecialchars($datos_cartas['periodo']) . '</p>
        <p><strong>Duración:</strong> ' . htmlspecialchars($datos_cartas['horas']) . ' horas</p>
        
        <p><strong>Asesor Académico:</strong> ' . htmlspecialchars($datos_cartas['nombres_asesor_academico'] . ' ' . $datos_cartas['paterno_asesor_academico'] . ' ' . $datos_cartas['materno_asesor_academico']) . '</p>
        <p><strong>Asesor en la Organización:</strong> ' . htmlspecialchars($datos_cartas['nombres_asesor_organizacion'] . ' ' . $datos_cartas['paterno_asesor_organizacion'] . ' ' . $datos_cartas['materno_asesor_organizacion']) . '</p>
        
        <p>Este acuerdo establece las bases para una colaboración mutuamente beneficiosa en la formación profesional del estudiante.</p>
        
        <br><br>
        <p>Atentamente,</p>
        <p><strong>Dirección Académica</strong></p>';
        break;
        
    case 'termino':
        $titulo = 'Carta de Término';
        $contenido = '
        <p><strong>CARTA DE TÉRMINO DE ESTANCIA</strong></p>
        
        <p>Por medio de la presente, hacemos constar que el estudiante:</p>
        
        <p><strong>Nombre:</strong> ' . htmlspecialchars($datos_cartas['nombres_estudiante'] . ' ' . $datos_cartas['paterno_estudiante'] . ' ' . $datos_cartas['materno_estudiante']) . '</p>
        <p><strong>Matrícula:</strong> ' . htmlspecialchars($datos_cartas['matricula_estudiante']) . '</p>
        <p><strong>Programa:</strong> ' . htmlspecialchars($datos_cartas['nombre_carrera']) . '</p>
        
        <p>Ha concluido satisfactoriamente su ' . htmlspecialchars($datos_cartas['estancia-estadia']) . ' en ' . htmlspecialchars($datos_cartas['nombre_empresa']) . ' durante el período del ' . htmlspecialchars(date('d/m/Y', strtotime($datos_cartas['fecha_inicio']))) . ' al ' . htmlspecialchars(date('d/m/Y', strtotime($datos_cartas['fecha_fin']))) . ', cumpliendo con ' . htmlspecialchars($datos_cartas['horas']) . ' horas de actividades profesionales.</p>
        
        <p>Durante este período, el estudiante desarrolló las siguientes competencias profesionales:</p>
        <p>' . htmlspecialchars($datos_cartas['competencias_profesionales'] ?: 'No especificadas') . '</p>
        
        <p>Agradecemos la colaboración brindada por la organización para la formación de nuestro estudiante.</p>
        
        <br><br>
        <p>Atentamente,</p>
        <p><strong>Coordinación de Estancias</strong></p>';
        break;
        
    case 'constancia':
        $titulo = 'Constancia de Estancia';
        $contenido = '
        <p><strong>CONSTANCIA DE PARTICIPACIÓN EN ESTANCIA</strong></p>
        
        <p>La institución educativa hace constar que:</p>
        
        <p><strong>' . htmlspecialchars($datos_cartas['nombres_estudiante'] . ' ' . $datos_cartas['paterno_estudiante'] . ' ' . $datos_cartas['materno_estudiante']) . '</strong></p>
        <p>Con matrícula <strong>' . htmlspecialchars($datos_cartas['matricula_estudiante']) . '</strong></p>
        <p>Estudiante del programa <strong>' . htmlspecialchars($datos_cartas['nombre_carrera']) . '</strong></p>
        
        <p>Participó exitosamente en el programa de ' . htmlspecialchars($datos_cartas['estancia-estadia']) . ' en la organización ' . htmlspecialchars($datos_cartas['nombre_empresa']) . ' durante el período académico ' . htmlspecialchars($datos_cartas['periodo']) . '.</p>
        
        <p>Total de horas completadas: <strong>' . htmlspecialchars($datos_cartas['horas']) . ' horas</strong></p>
        
        <p>Esta experiencia forma parte integral de su formación profesional y contribuye al desarrollo de competencias específicas de su área de estudio.</p>
        
        <p>Se extiende la presente constancia para los fines que al interesado convengan.</p>
        
        <br><br>
        <p>Atentamente,</p>
        <p><strong>Dirección Académica</strong></p>
        <p><strong>Coordinación de Estancias Profesionales</strong></p>';
        break;
        
    default:
        die('Tipo de PDF no válido');
}

// Generar el PDF
generarPDFHTML($titulo, $contenido);
?>