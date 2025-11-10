<?php
// Clean, print-ready letter generation aligned with official templates
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

if (!isset($_SESSION['matricula'])) { header('Location: login.php'); exit(); }

$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['presentacion','cooperacion','termino'], true)) { http_response_code(400); die('Tipo inválido'); }

// Configuración de orientación y encabezado por tipo
$orientation = ($tipo === 'cooperacion') ? 'L' : 'P';
$assets = realpath(__DIR__ . '/../imgpdf') ?: __DIR__ . '/../imgpdf';
function pick_asset($candidates){ foreach($candidates as $p){ if($p && file_exists($p)) return realpath($p); } return null; }
$headerImg = pick_asset([
        $assets . DIRECTORY_SEPARATOR . 'membrete_' . $tipo . '.jpg',
        $assets . DIRECTORY_SEPARATOR . 'membrete_' . $tipo . '.png',
        $assets . DIRECTORY_SEPARATOR . 'membrete.jpg',
        $assets . DIRECTORY_SEPARATOR . 'membrete.png',
]);
$watermark = pick_asset([
    $assets . DIRECTORY_SEPARATOR . 'upvm_watermark.png',
    $assets . DIRECTORY_SEPARATOR . 'upvm_watermark.jpg'
]);
// Imagen de fondo solicitada (prioridad sobre watermark anterior)
$backgroundLetterhead = pick_asset([
    $assets . DIRECTORY_SEPARATOR . 'imgmembrete.jpg',
    $assets . DIRECTORY_SEPARATOR . 'imgmembrete.png'
]);
$footerMotif = pick_asset([
        $assets . DIRECTORY_SEPARATOR . 'footer_motif.png',
        $assets . DIRECTORY_SEPARATOR . 'footer_motif.jpg'
]);

// Datos
$db = (new Database())->getConnection();
$matricula = $_SESSION['matricula'];

$stmt = $db->prepare('SELECT * FROM alumnos WHERE matricula = :m LIMIT 1');
$stmt->bindValue(':m', $matricula); $stmt->execute();
$alumno = $stmt->fetch(PDO::FETCH_ASSOC); if(!$alumno) die('Alumno no encontrado');

$stmt = $db->prepare('SELECT * FROM cooperacion WHERE id_alumno = :ida LIMIT 1');
$stmt->bindValue(':ida', $alumno['id_alumno']); $stmt->execute();
$coop = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare('SELECT e.*, o.nombre_organizacion, o.direccion_org, o.contacto_org_congrado, o.puesto_contacto, o.email_org, o.telefono_org
                                            FROM estancias e LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion
                                            WHERE e.id_alumno = :ida LIMIT 1');
$stmt->bindValue(':ida', $alumno['id_alumno']); $stmt->execute();
$est = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Variables
$nombre_estudiante = ucwords(strtolower(trim(($alumno['paterno'] ?? '') . ' ' . ($alumno['materno'] ?? '') . ' ' . ($alumno['nombres'] ?? ''))));

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
$act = [$coop['act_1']??'', $coop['act_2']??'', $coop['act_3']??'', $coop['act_4']??''];
$meta = [$coop['meta_1']??'', $coop['meta_2']??'', $coop['meta_3']??'', $coop['meta_4']??''];
$horas = $est['horas'] ?? '';

// Textos fijos
$director_div = 'M. EN S.C. GUSTAVO ZEA NÁPOLES';
$director_cargo = 'DIRECTOR DE LA DIVISIÓN DE INGENIERÍA EN INFORMÁTICA';
$division_nombre = 'UNIVERSIDAD POLITÉCNICA DEL VALLE DE MÉXICO';
$ciudad = 'Tultitlán, Estado de México'; setlocale(LC_TIME,'es_MX.UTF-8','es_ES.UTF-8','es.UTF-8'); $hoy = strftime('%e de %B del %Y');
$director_tel = '(55) 5062 6460 Ext. 6570';
$director_mail = 'informatica@upvm.edu.mx';
$carrera = $alumno['carrera'] ?? 'Ingeniería en Tecnologías de la Información';
$cuatrimestre = $alumno['cuatrimestre'] ?? '';
// Género para redacción
$sexo = strtolower(trim($alumno['genero'] ?? ($alumno['sexo'] ?? 'm')));
$esMujer = in_array($sexo, ['f','femenino','mujer','female']);
$prefijoEst = $esMujer ? 'a la estudiante' : 'al estudiante';
$adscrito = $esMujer ? 'adscrita' : 'adscrito';
$asesorAcadTitulo = $esMujer ? 'Asesora Académica (UPVM)' : 'Asesor Académico (UPVM)';
$asesorOrgTitulo = $esMujer ? 'Asesora de la Organización' : 'Asesor de la Organización';
$nuestroEst = $esMujer ? 'nuestra estudiante' : 'nuestro estudiante';
// Utilidad para mayúsculas con acentos
$toUpper = function($s){ return function_exists('mb_strtoupper') ? mb_strtoupper($s,'UTF-8') : strtoupper($s); };

// Márgenes dinámicos: si hay fondo (imgmembrete), baja el texto
$topMarginMm = $backgroundLetterhead ? (($orientation==='L') ? 45 : 30) : (($orientation==='L') ? 25 : 27);
$sideMarginMm = ($orientation==='L') ? 18 : 20; $bottomMarginMm = 18;
$pageMargins = $topMarginMm.'mm '.$sideMarginMm.'mm '.$bottomMarginMm.'mm '.$sideMarginMm.'mm';

// HTML
ob_start();
?>
<html>
<head>
    <meta charset="utf-8" />
    <style>
        @page { margin: <?= $pageMargins ?>; }
        body { font-family: "Arial", sans-serif; font-size: 10pt; color:#222; line-height: 1.55; }
        .header img{ max-width:100%; height:auto; }
        .asunto { margin-top: 6px; font-weight: bold; text-transform: uppercase; }
        .asunto .fecha { float:right; font-weight: normal; }
        p { text-align: justify; margin: 8px 0; }
        .titulo { text-transform: uppercase; font-weight: bold; text-align:center; margin: 8px 0 12px; }
        table.meta { width:100%; border-collapse: collapse; margin:8px 0; }
        table.meta td { border: 1px solid #bbb; padding: 6px; vertical-align: top; }
        table.eval { width:100%; border-collapse: collapse; margin:10px 0; }
        table.eval th, table.eval td { border:1px solid #777; padding:6px; font-size:11pt; }
        table.eval th { background:#f0f0f0; text-transform: uppercase; }
        .opc { text-align:center; }
        .opc .o { display:inline-block; width:12px; height:12px; border:1px solid #333; border-radius:12px; margin:0 6px; }
        .firma { margin-top: 36px; }
        .firmas { width:100%; text-align:center; margin-top: 24px; }
        .firmas td { padding-top: 28px; }
        .line { border-top:1px solid #333; width:80%; margin: 0 auto 6px; }
        .small { font-size: 10.5pt; color:#333; }
        .footer { margin-top: 20px; text-align:center; font-size:10pt; color:#555; }
    </style>
</head>
<body>
    <div class="header">
        <?php if(!$backgroundLetterhead && $headerImg): ?><img src="<?= 'file:///' . str_replace('\\','/', $headerImg) ?>" /><?php endif; ?>
    </div>

    <?php if ($tipo==='presentacion'): ?>
          <div class="asunto">Asunto: Carta de Presentación <span class="fecha">Fecha: <?= htmlspecialchars($hoy) ?></span></div>
          <!-- Bloque de destinatario: Contacto (3), Puesto (4), Organización (5) -->
          <p><strong><?= htmlspecialchars($toUpper($asesor)) ?></strong><br>
              <strong><?= htmlspecialchars($toUpper($asesor_puesto)) ?></strong><br>
              <strong><?= htmlspecialchars($toUpper($empresa)) ?></strong></p>
        <p style="text-transform:uppercase; font-weight:bold;">PRESENTE</p>
          <p>Por medio del presente, me permito presentar <?= $prefijoEst ?> <strong><?= htmlspecialchars($nombre_estudiante) ?></strong> con matrícula <strong><?= htmlspecialchars($mat) ?></strong>, <?= $cuatrimestre? $adscrito.' al <strong>'.htmlspecialchars($cuatrimestre).'</strong> cuatrimestre de la carrera de <strong>'.htmlspecialchars($carrera).'</strong>' : $adscrito.' a la carrera de <strong>'.htmlspecialchars($carrera).'</strong>' ?>. La Estancia es un espacio académico con el propósito de incorporar <?= $nuestroEst ?> al ámbito laboral, a través de la realización de proyectos de vinculación con las organizaciones, que le permitan preparar a futuros profesionistas con experiencia profesional, y conscientes de los problemas y necesidades del sector productivo, además que permitan apoyar el desarrollo tecnológico regional; cubriendo un tiempo de <strong><?= htmlspecialchars($horas) ?></strong> horas, en el período de <strong><?= htmlspecialchars($periodo_ini) ?></strong> - <strong><?= htmlspecialchars($periodo_fin) ?></strong>.</p>

        <p style="text-transform:uppercase; font-weight:bold;">Competencias profesionales</p>
        <ul>
          <?php
            $competencias = [
              'Administrar la infraestructura tecnológica mediante el mantenimiento y soporte técnico; técnicas de diseño y administración de redes para optimizar el desempeño y la operación de la red de la organización.',
              'Administrar redes de datos mediante el análisis del entorno y de los requerimientos, con base en procedimientos, herramientas, estándares y políticas aplicables para garantizar la seguridad y operatividad de la red.'
            ];
            foreach ($competencias as $c) echo '<li>'.htmlspecialchars($c).'</li>';
          ?>
        </ul>

    <p>Así mismo presento a <strong><?= $director_div ?></strong> (Correo: <strong><?= htmlspecialchars($director_mail) ?></strong>, Tel. <strong><?= htmlspecialchars($director_tel) ?></strong>) como <?= $asesorAcadTitulo ?> que, junto con <?= $asesorOrgTitulo ?> de la Organización, darán asesoría y seguimiento a las estancias del(la) estudiante, en las que se realizarán las siguientes actividades: definición del proyecto; emisión de la carta de aceptación del estudiante; validación de los informes quincenales del estudiante; evaluación de la presentación del estudiante; calificación de las evidencias y aportaciones profesionales correspondientes. Sin otro particular por este momento, aprovecho la ocasión para enviarle un cordial saludo y quedo a sus órdenes para cualquier comentario, aclaración o información al respecto.</p>

        <div class="firmas" style="margin-top:28px;">
            <table style="width:100%"><tr>
                <td>
                    <div class="line"></div>
                    <div><strong><?= $director_div ?></strong></div>
                    <div class="small">DIRECTOR(A) DE LA DIVISIÓN DE <?= strtoupper(htmlspecialchars($carrera)) ?><br>Teléfono: <?= htmlspecialchars($director_tel) ?>, Correo electrónico: <?= htmlspecialchars($director_mail) ?></div>
                </td>
            </tr></table>
        </div>

    <?php elseif ($tipo==='cooperacion'): ?>
        <div class="titulo">Proyecto de Cooperación</div>
        <table class="meta">
            <tr>
                <td style="width:55%"><strong>Nombre del proyecto</strong><br><?= nl2br(htmlspecialchars($nombre_proyecto)) ?></td>
                <td style="width:45%"><strong>Empresa</strong><br><?= htmlspecialchars($empresa) ?></td>
            </tr>
            <tr>
                <td><strong>Nombre del estudiante</strong><br><?= htmlspecialchars($nombre_estudiante) ?></td>
                <td><strong>Dirección</strong><br><?= htmlspecialchars($direccion_empresa) ?></td>
            </tr>
            <tr>
                <td><strong>Área / Departamento</strong><br><?= htmlspecialchars($area) ?></td>
                <td><strong>Periodo</strong><br>Del <?= htmlspecialchars($periodo_ini) ?> al <?= htmlspecialchars($periodo_fin) ?></td>
            </tr>
            <tr>
                <td><strong>Teléfono</strong><br><?= htmlspecialchars($asesor_tel) ?></td>
                <td><strong>Email</strong><br><?= htmlspecialchars($asesor_mail) ?></td>
            </tr>
        </table>
        <div class="section"><strong>Objetivo(s)</strong></div>
        <p><?= nl2br(htmlspecialchars($objetivos)) ?></p>
        <div class="section"><strong>Metas</strong></div>
        <table class="meta">
            <tr><td>
                <ol>
                    <?php foreach($meta as $m){ if(trim($m)!==''){ echo '<li>'.htmlspecialchars($m).'</li>'; } }
                                if (!array_filter($meta)) echo '<li>—</li>'; ?>
                </ol>
            </td></tr>
        </table>
        <div class="section"><strong>Actividades</strong></div>
        <table class="meta">
            <tr><td>
                <ol>
                    <?php foreach($act as $a){ if(trim($a)!==''){ echo '<li>'.htmlspecialchars($a).'</li>'; } }
                                if (!array_filter($act)) echo '<li>—</li>'; ?>
                </ol>
            </td></tr>
        </table>
        <table class="firmas" style="margin-top:18px"><tr>
            <td><div class="line"></div><div><strong>ASESOR ACADÉMICO</strong></div></td>
            <td><div class="line"></div><div><strong>ASESOR DE LA ORGANIZACIÓN</strong><?= $asesor? '<br><span class="small">'.htmlspecialchars($asesor).'</span>':''; ?></div></td>
        </tr></table>

    <?php else: /* termino */ ?>
        <div class="asunto">Asunto: Carta de Terminación <span class="fecha">Fecha: <?= htmlspecialchars($hoy) ?></span></div>
        <p>Por medio de la presente se informa que <strong><?= htmlspecialchars($nombre_estudiante) ?></strong>, con número de matrícula <strong><?= htmlspecialchars($mat) ?></strong>, ha concluido su estancia en la empresa <strong><?= htmlspecialchars($empresa) ?></strong>, desarrollando el proyecto <strong><?= htmlspecialchars($nombre_proyecto) ?></strong>, cubriendo <strong><?= htmlspecialchars($horas) ?></strong> horas durante el periodo <strong><?= htmlspecialchars($periodo_ini) ?></strong> al <strong><?= htmlspecialchars($periodo_fin) ?></strong>.</p>
        <p>Cabe mencionar que la participación estudiantil en esta estancia es considerada como:</p>
        <table class="eval">
            <tr><th colspan="4">Evaluación realizada por parte de la empresa</th></tr>
            <tr>
                <th>Aspecto</th>
                <th class="opc">2 Puntos<br>Excelente</th>
                <th class="opc">1 Punto<br>Suficiente</th>
                <th class="opc">0 Puntos<br>Deficiente</th>
            </tr>
            <?php $aspectos=[
                'Cumplimiento de valores (Honestidad, responsabilidad, tolerancia)',
                'Actitud profesional que muestra en el desarrollo del proyecto (Puntual, ordenado, limpio, proactivo, respetuoso)',
                'Aplicación de las competencias en el desarrollo del proyecto (Conocimientos, desempeño y desarrollo de productos)',
                'Cumplimiento del proyecto (Concluyó el proyecto en tiempo y forma)'
            ]; foreach($aspectos as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a) ?></td>
                <td class="opc"><span class="o"></span></td>
                <td class="opc"><span class="o"></span></td>
                <td class="opc"><span class="o"></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <table class="eval">
            <tr><th colspan="4">Evaluación realizada por parte de la UPVM</th></tr>
            <tr>
                <th>Aspecto</th>
                <th class="opc">2 Puntos<br>Excelente</th>
                <th class="opc">1 Punto<br>Suficiente</th>
                <th class="opc">0 Puntos<br>Deficiente</th>
            </tr>
            <tr>
                <td>Integración del expediente del estudiante (Cumplimiento de la documentación solicitada por la Universidad)</td>
                <td class="opc"><span class="o"></span></td>
                <td class="opc"><span class="o"></span></td>
                <td class="opc"><span class="o"></span></td>
            </tr>
        </table>
        <p>Se extiende la presente para los fines que al interesado(a) convengan. Sin otro particular por el momento, quedamos a sus órdenes para cualquier duda o aclaración al respecto.</p>
        <div class="firmas">
            <table style="width:100%"><tr>
                <td>
                    <div class="line"></div>
                    <div><strong>MTRA. AMÉRICA IVET MUÑOZ GALICIA</strong><br><span class="small">Asesora de la Organización</span></div>
                </td>
                <td>
                    <div class="line"></div>
                    <div><strong><?= $director_div ?></strong><br><span class="small">Asesor Académico</span></div>
                </td>
            </tr></table>
        </div>
    <?php endif; ?>

    <?php if ($footerMotif): ?>
        <div class="footer">
            <img src="<?= 'file:///' . str_replace('\\','/', $footerMotif) ?>" style="max-height:18px"/>
            <div class="small">Av. Mexiquense s/n, Col. Villa Esmeralda, C.P. 54910, Tultitlán, Estado de México. Tel. 55 5062 6460</div>
        </div>
    <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

// Directorios y archivo
$basePath = __DIR__ . '/../storage/pdfs'; if(!is_dir($basePath)) @mkdir($basePath,0777,true);
$studentDir = $basePath . DIRECTORY_SEPARATOR . preg_replace('/[^0-9A-Za-z_-]/','_', $matricula); if(!is_dir($studentDir)) @mkdir($studentDir,0777,true);
foreach (glob($studentDir.'/'.$tipo.'-*.pdf') as $old) @unlink($old);
$filename = $tipo.'.pdf'; $filePath = $studentDir.'/'.$filename;

// MPDF
$mpdf = new Mpdf(['mode'=>'utf-8','format'=>'Letter-'.$orientation]);
// Si existe imgmembrete.* usarlo como fondo completo detrás del texto
if ($backgroundLetterhead) {
    $pageWmm = ($orientation==='L') ? 279.4 : 215.9;
    $pageHmm = ($orientation==='L') ? 215.9 : 279.4;
    $mpdf->SetWatermarkImage('file:///'.str_replace('\\','/',$backgroundLetterhead), 1.0, [$pageWmm, $pageHmm]);
    $mpdf->showWatermarkImage = true;
    $mpdf->watermarkImgBehind = true;
} elseif ($watermark) { // fallback al watermark ligero previo
    $mpdf->SetWatermarkImage('file:///'.str_replace('\\','/',$watermark), 0.08);
    $mpdf->showWatermarkImage = true;
    $mpdf->watermarkImgBehind = true;
}
$mpdf->WriteHTML($html);
$mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

// Flags y docus
if($coop){ $col=null; if($tipo==='presentacion') $col='carta_presentacion_generada'; if($tipo==='cooperacion') $col='carta_cooperacion_generada'; if($tipo==='termino') $col='carta_termino_generada'; if($col){ $q=$db->prepare("UPDATE cooperacion SET $col=1 WHERE id_cooperacion=:id"); $q->bindValue(':id',$coop['id_cooperacion']); $q->execute(); } }
try { $stmtD=$db->prepare('SELECT * FROM docus WHERE id_alumno=:ida LIMIT 1'); $stmtD->bindValue(':ida',$alumno['id_alumno']); $stmtD->execute(); $docus=$stmtD->fetch(PDO::FETCH_ASSOC); $col=null; if($tipo==='presentacion') $col='url_presentacion'; if($tipo==='cooperacion') $col='url_cooperacion'; if($tipo==='termino') $col='url_termino'; $relative='storage/pdfs/'.rawurlencode($matricula).'/'.rawurlencode($filename); if($col){ if($docus){ $upd=$db->prepare("UPDATE docus SET $col=:u WHERE id_conjdocus=:id"); $upd->bindValue(':u',$relative); $upd->bindValue(':id',$docus['id_conjdocus']); $upd->execute(); } else { $ins=$db->prepare("INSERT INTO docus ($col,id_alumno) VALUES (:u,:ida)"); $ins->bindValue(':u',$relative); $ins->bindValue(':ida',$alumno['id_alumno']); $ins->execute(); } } } catch(Exception $e){ }

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
readfile($filePath); exit;