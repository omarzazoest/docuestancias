<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['matricula'])) { header('Location: login.php'); exit(); }
$matricula = $_SESSION['matricula'];
$database = new Database();
$db = $database->getConnection();
$rows = [];
try {
  $stmt = $db->prepare('SELECT e.*, o.nombre_organizacion FROM estancias e LEFT JOIN organizaciones o ON e.id_empresa = o.id_organizacion INNER JOIN alumnos a ON e.id_alumno = a.id_alumno WHERE a.matricula = :m');
  $stmt->bindParam(':m', $matricula);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Estancias</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php"><i class="fas fa-graduation-cap me-2"></i>Sistema de Estancias</a>
  </div>
</nav>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-building me-2"></i>Mis Estancias</h3>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Regresar</a>
  </div>
  <?php if (!$rows): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No tienes estancias registradas aún.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Organización</th>
            <th>Tipo</th>
            <th>Horas</th>
            <th>Inicio</th>
            <th>Fin</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i=>$r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($r['nombre_organizacion'] ?: 'Sin asignar') ?></td>
            <td><?= htmlspecialchars($r['tipo_estancia'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['horas_totales'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['fecha_inicio'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['fecha_fin'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
