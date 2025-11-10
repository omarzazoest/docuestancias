<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
/*

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

// Fetch alumno
$stmt = $db->prepare('SELECT * FROM alumnos WHERE matricula = :matricula LIMIT 1');
$stmt->bindParam(':matricula', $matricula);
$stmt->execute();
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$alumno) {
    echo 'No se encontró el alumno';
    exit();
}

// Fetch cooperacion por id_alumno
$stmt = $db->prepare('SELECT * FROM cooperacion WHERE id_alumno = :id_alumno LIMIT 1');
$stmt->bindParam(':id_alumno', $alumno['id_alumno']);
$stmt->execute();
$coop = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch estancia + organizacion
$stmt = $db->prepare('SELECT e.*, o.nombre_organizacion, o.direccion_org, o.contacto_org_congrado, o.puesto_contacto, o.email_org, o.telefono_org
                      FROM estancias e LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion
                      WHERE e.id_alumno = :id_alumno LIMIT 1');
$stmt->bindParam(':id_alumno', $alumno['id_alumno']);
$stmt->execute();
$est = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Helper values
$nombre_estudiante = trim(($alumno['nombres'] ?? '') . ' ' . ($alumno['paterno'] ?? '') . ' ' . ($alumno['materno'] ?? ''));
$mat = $alumno['matricula'] ?? '';
$empresa = $est['nombre_organizacion'] ?? '';
$direccion_empresa = $est['direccion_org'] ?? '';
$asesor = $est['contacto_org_congrado'] ?? '';
$asesor_puesto = $est['puesto_contacto'] ?? '';
$asesor_tel = $est['telefono_org'] ?? '';
$asesor_mail = $est['email_org'] ?? '';
$periodo_ini = $coop['periodo_inicial'] ?? '';
$periodo_fin = $coop['periodo_final'] ?? '';
$area = $coop['area-departamento'] ?? '';
$nombre_proyecto = $coop['nombre_proyecto'] ?? '';
$objetivos = $coop['objetivos'] ?? '';
$act = [
    $coop['act_1'] ?? '',
    $coop['act_2'] ?? '',
    $coop['act_3'] ?? '',
    $coop['act_4'] ?? '',
];
$meta = [
    $coop['meta_1'] ?? '',
    $coop['meta_2'] ?? '',
    $coop['meta_3'] ?? '',
    $coop['meta_4'] ?? '',
];
$horas = $est['horas'] ?? '';
*/
// LIMPIO: Archivo regenerado para evitar duplicaciones y errores previos.
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

if (!isset($_SESSION['matricula'])) { header('Location: login.php'); exit(); }

$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['presentacion','cooperacion','termino'], true)) { http_response_code(400); die('Tipo inválido'); }

$db = (new Database())->getConnection();
$matricula = $_SESSION['matricula'];

// Alumno
$stmt = $db->prepare('SELECT * FROM alumnos WHERE matricula = :m LIMIT 1');
$stmt->bindValue(':m', $matricula);
$stmt->execute();
$alumno = $stmt->fetch(PDO::FETCH_ASSOC); if (!$alumno) die('Alumno no encontrado');

// Cooperacion
$stmt = $db->prepare('SELECT * FROM cooperacion WHERE id_alumno = :ida LIMIT 1');
$stmt->bindValue(':ida', $alumno['id_alumno']);
$stmt->execute();
$coop = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Estancia + organizacion
$stmt = $db->prepare('SELECT e.*, o.nombre_organizacion, o.direccion_org, o.contacto_org_congrado, o.puesto_contacto, o.email_org, o.telefono_org
                      FROM estancias e LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion
                      WHERE e.id_alumno = :ida LIMIT 1');
$stmt->bindValue(':ida', $alumno['id_alumno']);
$stmt->execute();
$est = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Datos base
$nombre_estudiante = trim(($alumno['nombres']??'').' '.($alumno['paterno']??'').' '.($alumno['materno']??''));
$mat = $alumno['matricula'] ?? '';
$empresa = $est['nombre_organizacion'] ?? '';
$direccion_empresa = $est['direccion_org'] ?? '';
$asesor = $est['contacto_org_congrado'] ?? '';
$asesor_puesto = $est['puesto_contacto'] ?? '';
$asesor_tel = $est['telefono_org'] ?? '';
$asesor_mail = $est['email_org'] ?? '';
$periodo_ini = $coop['periodo_inicial'] ?? '';
$periodo_fin = $coop['periodo_final'] ?? '';
$area = $coop['area-departamento'] ?? '';
$nombre_proyecto = $coop['nombre_proyecto'] ?? '';
$objetivos = $coop['objetivos'] ?? '';
$act = [$coop['act_1']??'',$coop['act_2']??'',$coop['act_3']??'',$coop['act_4']??''];
$meta = [$coop['meta_1']??'',$coop['meta_2']??'',$coop['meta_3']??'',$coop['meta_4']??''];
$horas = $est['horas'] ?? '';

// Membrete
$headerImgPath=''; $imgDir=realpath(__DIR__.'/../imgpdf');
if ($imgDir) { $candidates=glob($imgDir.'/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE); if($candidates){ $headerImgPath=$candidates[0]; } }

// Textos fijos
$directora_division='DIRECTOR DE LA DIVISIÓN DE INGENIERÍA Y TECNOLOGÍA';
$division_nombre='UNIVERSIDAD POLITÉCNICA DEL VALLE DE MÉXICO';
$ciudad='Tultitlán, Estado de México';
setlocale(LC_TIME,'es_MX.UTF-8','es_ES.UTF-8','es.UTF-8'); $hoy=strftime('%e de %B de %Y');

ob_start();
?>
<html><head><meta charset="utf-8"/><style>@page{margin:45mm 20mm 18mm 20mm}body{font-family:Georgia,"Times New Roman",serif;font-size:12pt;line-height:1.6;color:#222}p{text-align:justify;margin:8px 0}.titulo{font-size:14pt;font-weight:bold;letter-spacing:.5px}.firma p{text-align:center;margin-top:40px;font-weight:bold}table.tabla{width:100%;border-collapse:collapse}table.tabla td{vertical-align:top;padding:6px}</style></head><body><div class="content">
<?php if($tipo==='presentacion'):?>
<div style="text-align:right;"><?php echo htmlspecialchars($ciudad)?>, a <?php echo htmlspecialchars($hoy)?></div>
<div class="titulo">Carta de Presentación</div>
<p>PRESENTE</p>
<p>Por medio del presente, me permito presentar al(la) estudiante <strong><?php echo htmlspecialchars($nombre_estudiante)?></strong>, con matrícula <strong><?php echo htmlspecialchars($mat)?></strong>, para que desarrolle su Estancia.</p>
<p>Colaborará en <strong><?php echo htmlspecialchars($area)?></strong> desarrollando <strong><?php echo htmlspecialchars($nombre_proyecto)?></strong>, bajo la asesoría de <strong><?php echo htmlspecialchars($asesor)?></strong>, <em><?php echo htmlspecialchars($asesor_puesto)?></em>.</p>
<p>Duración aproximada: <strong><?php echo htmlspecialchars($horas)?></strong> horas. Periodo: <strong><?php echo htmlspecialchars($periodo_ini)?></strong> al <strong><?php echo htmlspecialchars($periodo_fin)?></strong>.</p>
<p><strong>Organización:</strong> <?php echo htmlspecialchars($empresa)?><br><strong>Dirección:</strong> <?php echo htmlspecialchars($direccion_empresa)?><br><strong>Tel.:</strong> <?php echo htmlspecialchars($asesor_tel)?> <strong>Email:</strong> <?php echo htmlspecialchars($asesor_mail)?></p>
<div class="firma"><p>Atentamente</p><p><strong><?php echo $directora_division?></strong><br><?php echo $division_nombre?></p></div>
<?php elseif($tipo==='cooperacion'):?>
<div class="titulo">Proyecto de Cooperación</div>
<table class="tabla"><tr><td style="width:55%"><strong>Proyecto:</strong><br><?php echo nl2br(htmlspecialchars($nombre_proyecto))?></td><td style="width:45%"><strong>Empresa:</strong><br><?php echo htmlspecialchars($empresa)?></td></tr><tr><td><strong>Estudiante:</strong><br><?php echo htmlspecialchars($nombre_estudiante)?></td><td><strong>Dirección:</strong><br><?php echo htmlspecialchars($direccion_empresa)?></td></tr><tr><td><strong>Teléfono:</strong> <?php echo htmlspecialchars($asesor_tel)?><br><strong>Email:</strong> <?php echo htmlspecialchars($asesor_mail)?></td><td><strong>Área:</strong><br><?php echo htmlspecialchars($area)?></td></tr><tr><td><strong>Objetivos:</strong><br><?php echo nl2br(htmlspecialchars($objetivos))?></td><td><strong>Periodo:</strong><br>Del <?php echo htmlspecialchars($periodo_ini)?> al <?php echo htmlspecialchars($periodo_fin)?></td></tr></table>
<p>Actividades:</p><ol><?php foreach($act as $a){ if(trim($a)!==''){ echo '<li>'.htmlspecialchars($a).'</li>'; } }?></ol>
<p>Metas:</p><ol><?php foreach($meta as $m){ if(trim($m)!==''){ echo '<li>'.htmlspecialchars($m).'</li>'; } }?></ol>
<div class="firma"><table class="tabla"><tr><td style="text-align:center">ASESOR ACADÉMICO</td><td style="text-align:center">ASESOR ORGANIZACIÓN<br><strong><?php echo htmlspecialchars($asesor)?></strong></td><td style="text-align:center">RESPONSABLE ORGANIZACIÓN</td></tr></table></div>
<?php else:?>
<div class="titulo">Carta de Terminación</div>
<p>Se certifica que <strong><?php echo htmlspecialchars($nombre_estudiante)?></strong> (Mat: <strong><?php echo htmlspecialchars($mat)?></strong>) concluyó su estancia en <strong><?php echo htmlspecialchars($empresa)?></strong> desarrollando <strong><?php echo htmlspecialchars($nombre_proyecto)?></strong>, cumpliendo <strong><?php echo htmlspecialchars($horas)?></strong> horas del periodo <strong><?php echo htmlspecialchars($periodo_ini)?></strong> al <strong><?php echo htmlspecialchars($periodo_fin)?></strong>.</p>
<p>Se expide para los fines correspondientes.</p>
<div class="firma"><p>Atentamente</p><p><strong><?php echo $directora_division?></strong><br><?php echo $division_nombre?></p></div>
<?php endif;?>
</div></body></html>
<?php
$html = ob_get_clean();

// Directorios
$basePath = __DIR__ . '/../storage/pdfs'; if(!is_dir($basePath)) { @mkdir($basePath,0777,true); }
$studentDir = $basePath . DIRECTORY_SEPARATOR . preg_replace('/[^0-9A-Za-z_-]/','_', $matricula); if(!is_dir($studentDir)) { @mkdir($studentDir,0777,true); }
foreach (glob($studentDir.'/'.$tipo.'-*.pdf') as $old) { @unlink($old); }
$filename = $tipo.'.pdf'; $filePath = $studentDir.'/'.$filename;

$mpdf = new Mpdf(['mode'=>'utf-8','format'=>'Letter']);
if($headerImgPath && file_exists($headerImgPath)){ $src='file:///'.str_replace('\\','/',realpath($headerImgPath)); $mpdf->SetWatermarkImage($src,1.0); $mpdf->showWatermarkImage=true; $mpdf->watermarkImgBehind=true; }
$mpdf->WriteHTML($html); $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

// Flag cooperacion
if($coop){ $col=null; if($tipo==='presentacion') $col='carta_presentacion_generada'; if($tipo==='cooperacion') $col='carta_cooperacion_generada'; if($tipo==='termino') $col='carta_termino_generada'; if($col){ $q=$db->prepare("UPDATE cooperacion SET $col=1 WHERE id_cooperacion=:id"); $q->bindValue(':id',$coop['id_cooperacion']); $q->execute(); } }

// docus
try { $stmtD=$db->prepare('SELECT * FROM docus WHERE id_alumno=:ida LIMIT 1'); $stmtD->bindValue(':ida',$alumno['id_alumno']); $stmtD->execute(); $docus=$stmtD->fetch(PDO::FETCH_ASSOC); $col=null; if($tipo==='presentacion') $col='url_presentacion'; if($tipo==='cooperacion') $col='url_cooperacion'; if($tipo==='termino') $col='url_termino'; $relative='storage/pdfs/'.rawurlencode($matricula).'/'.rawurlencode($filename); if($col){ if($docus){ $upd=$db->prepare("UPDATE docus SET $col=:u WHERE id_conjdocus=:id"); $upd->bindValue(':u',$relative); $upd->bindValue(':id',$docus['id_conjdocus']); $upd->execute(); } else { $ins=$db->prepare("INSERT INTO docus ($col,id_alumno) VALUES (:u,:ida)"); $ins->bindValue(':u',$relative); $ins->bindValue(':ida',$alumno['id_alumno']); $ins->execute(); } } } catch(Exception $e){ }

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
readfile($filePath); exit;