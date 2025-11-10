<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user_data.php';

if (!isset($_SESSION['matricula'])) { header('Location: login.php'); exit(); }
$matricula = $_SESSION['matricula'];
$database = new Database();
$db = $database->getConnection();

// Flags de envío/generación actuales
$coDash = [];
try {
    $stmtAl = $db->prepare('SELECT * FROM alumnos WHERE matricula = :m LIMIT 1');
    $stmtAl->bindParam(':m', $matricula);
    $stmtAl->execute();
    $al = $stmtAl->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($al) {
        $stmtCo = $db->prepare('SELECT * FROM cooperacion WHERE id_alumno = :ida LIMIT 1');
        $stmtCo->bindParam(':ida', $al['id_alumno']);
        $stmtCo->execute();
        $coDash = $stmtCo->fetch(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {}

// Obtener URLs desde tabla docus (una por tipo)
$urls = ['presentacion'=>null,'cooperacion'=>null,'termino'=>null];
try {
  if (!empty($al['id_alumno'])) {
    $stmtD = $db->prepare('SELECT url_presentacion, url_cooperacion, url_termino FROM docus WHERE id_alumno = :ida LIMIT 1');
    $stmtD->bindValue(':ida', $al['id_alumno']);
    $stmtD->execute();
    $doc = $stmtD->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($doc) {
      $urls['presentacion'] = $doc['url_presentacion'] ?: null;
      $urls['cooperacion']  = $doc['url_cooperacion']  ?: null;
      $urls['termino']      = $doc['url_termino']      ?: null;
    }
  }
} catch (Exception $e) { /* noop */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Documentos - Sistema de Estancias</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php"><i class="fas fa-graduation-cap me-2"></i>Sistema de Estancias</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>
</nav>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-download me-2"></i>Documentos</h3>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Regresar</a>
  </div>

  <div class="row g-3">
    <?php foreach (['presentacion'=>'Presentación','cooperacion'=>'Cooperación','termino'=>'Término'] as $tkey=>$tlabel): ?>
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-file-pdf me-2 text-danger"></i><?= $tlabel ?></span>
            <div class="d-flex align-items-center gap-2">
              <?php $genFlag = 'carta_'.$tkey.'_generada'; $envFlag = 'carta_'.$tkey.'_enviada'; ?>
              <?php if (!empty($coDash[$genFlag])): ?><span class="badge bg-success">Generada</span><?php endif; ?>
              <?php if (!empty($coDash[$envFlag])): ?><span class="badge bg-primary">Enviada</span><?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <?php
              $url = $urls[$tkey] ?? null;
              // Fallback: si no existe en docus, probar archivo fijo tipo.pdf en storage
              if (!$url) {
                  $urlCandidate = 'storage/pdfs/' . rawurlencode($matricula) . '/' . $tkey . '.pdf';
                  if (is_file(__DIR__ . '/../' . $urlCandidate)) {
                      $url = $urlCandidate;
                  }
              }
            ?>
            <?php if (!empty($url)): ?>
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-truncate" style="max-width:60%"><?= htmlspecialchars(basename($url)) ?></span>
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?='../' . ltrim($url,'/') ?>" target="_blank" title="Ver"><i class="fas fa-eye"></i></a>
                  <a class="btn btn-outline-primary" href="<?='enviar_carta.php?tipo='.urlencode($tkey)?>" title="Enviar por correo"><i class="fas fa-paper-plane"></i></a>
                </div>
              </div>
            <?php else: ?>
              <div class="text-muted">No hay archivo registrado para este tipo.</div>
            <?php endif; ?>
          </div>
          <div class="card-footer text-end">
            <a href="<?='generar_carta.php?tipo='.urlencode($tkey)?>" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Generar nuevo</a>
            <a href="<?='enviar_carta.php?tipo='.urlencode($tkey)?>" class="btn btn-sm btn-outline-primary" <?= empty($coDash[$genFlag]) ? 'disabled' : '' ?>><i class="fas fa-paper-plane me-1"></i>Enviar último</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
