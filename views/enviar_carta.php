<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar variables de entorno (.env)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

if (!isset($_SESSION['matricula'])) {
    header('Location: login.php');
    exit();
}

$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['presentacion','cooperacion','termino'], true)) {
    http_response_code(400);
    echo 'Tipo de carta no válido';
    exit();
}

$database = new Database();
$db = $database->getConnection();
$matricula = $_SESSION['matricula'];

// Datos base
$stmt = $db->prepare('SELECT * FROM alumnos WHERE matricula = :matricula LIMIT 1');
$stmt->bindParam(':matricula', $matricula);
$stmt->execute();
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$alumno) { die('Alumno no encontrado'); }

$stmt = $db->prepare('SELECT * FROM cooperacion WHERE id_alumno = :id_alumno LIMIT 1');
$stmt->bindParam(':id_alumno', $alumno['id_alumno']);
$stmt->execute();
$coop = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare('SELECT e.*, o.nombre_organizacion, o.email_org FROM estancias e LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion WHERE e.id_alumno = :id_alumno LIMIT 1');
$stmt->bindParam(':id_alumno', $alumno['id_alumno']);
$stmt->execute();
$est = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$destino = $est['email_org'] ?? '';
if (!$destino || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
    die('No hay email de la organización válido para enviar');
}

// Resolver ruta del PDF
$latest = '';
try {
    $stmtD = $db->prepare('SELECT url_presentacion, url_cooperacion, url_termino FROM docus WHERE id_alumno = :ida LIMIT 1');
    $stmtD->bindValue(':ida', $alumno['id_alumno']);
    $stmtD->execute();
    $doc = $stmtD->fetch(PDO::FETCH_ASSOC) ?: [];
    $col = $tipo === 'presentacion' ? 'url_presentacion' : ($tipo === 'cooperacion' ? 'url_cooperacion' : 'url_termino');
    if (!empty($doc[$col])) {
        $candidate = realpath(__DIR__ . '/../' . $doc[$col]);
        if ($candidate && is_file($candidate)) { $latest = $candidate; }
    }
} catch (Exception $e) { /* noop */ }

// Fallback a archivo fijo por tipo
if (!$latest) {
    $baseDir = __DIR__ . '/../storage/pdfs/' . preg_replace('/[^0-9A-Za-z_-]/','_', $matricula);
    $fixed = $baseDir . '/' . $tipo . '.pdf';
    if (is_file($fixed)) { $latest = $fixed; }
}
// Fallback a patrón viejo con timestamp
if (!$latest) {
    $baseDir = __DIR__ . '/../storage/pdfs/' . preg_replace('/[^0-9A-Za-z_-]/','_', $matricula);
    $files = glob($baseDir . '/' . $tipo . '-*.pdf');
    if ($files) { rsort($files); $latest = $files[0]; }
}

if (!$latest || !is_file($latest)) {
    die('Primero genera el PDF para poder enviarlo por correo.');
}

try {
    $mailer = new PHPMailer(true);
    // Configurar SMTP con variables de entorno
    $host = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST');
    $user = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER');
    $pass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS');
    $port = (int)(($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT')) ?: 587);
    $secure = strtolower(($_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE')) ?: 'tls');

    if ($host && $user && $pass) {
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->SMTPAuth = true;
        $mailer->Username = $user;
        $mailer->Password = $pass;
        $mailer->Port = $port;
        if (in_array($secure, ['ssl','tls'], true)) { $mailer->SMTPSecure = $secure; }
        $mailer->SMTPOptions = [
            'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ]
        ];
    } else {
        throw new Exception('SMTP no configurado. Define SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_PORT, SMTP_SECURE en el entorno.');
    }
    $mailer->CharSet = 'UTF-8';

    $from = $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: 'no-reply@upvm.edu.mx';
    $fromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Sistema de Estancias';
    $mailer->setFrom($from, $fromName);
    $mailer->addAddress($destino, $est['nombre_organizacion'] ?? 'Organización');
    if (!empty($alumno['correo_electronico']) && filter_var($alumno['correo_electronico'], FILTER_VALIDATE_EMAIL)) {
        $mailer->addCC($alumno['correo_electronico'], trim(($alumno['nombres']??'').' '.($alumno['paterno']??'')));
    }

    $mailer->Subject = 'Documento de Estancias - ' . ucfirst($tipo);
    $mailer->Body = "Estimado(a),\n\nAdjuntamos la carta de " . ucfirst($tipo) . " correspondiente al(la) estudiante " . trim(($alumno['nombres']??'') . ' ' . ($alumno['paterno']??'')) . ".\n\nSaludos cordiales.";

    $mailer->addAttachment($latest);

    $mailer->send();

    // Actualizar bandera de enviado
    if (!empty($coop)) {
        $col = null;
        if ($tipo === 'presentacion') $col = 'carta_presentacion_enviada';
        if ($tipo === 'cooperacion') $col = 'carta_cooperacion_enviada';
        if ($tipo === 'termino') $col = 'carta_termino_enviada';
        if ($col) {
            $q = $db->prepare("UPDATE cooperacion SET $col = 1 WHERE id_cooperacion = :id");
            $q->bindValue(':id', $coop['id_cooperacion']);
            $q->execute();
        }
    }

    header('Location: dashboard.php?sent=' . urlencode($tipo));
    exit();
} catch (Exception $e) {
    echo 'Error al enviar correo: ' . $e->getMessage();
}
