<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['matricula'])) {
    header('Location: views/dashboard.php');
    exit();
}

// Redirigir a la página de login
header('Location: views/login.php');
exit();
?>