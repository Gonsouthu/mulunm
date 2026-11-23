<?php
require __DIR__ . '/includes/auth.php';

// Cerrar sesión solo se acepta por POST con el código CSRF: así ninguna otra página puede
// cerrarle la sesión a alguien con un simple enlace o imagen.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido($_POST['csrf'] ?? null)) {
    cerrar_sesion();
    header('Location: login.php'); exit;
}
header('Location: ' . (usuario_logueado() ? 'perfil.php' : 'login.php')); exit;
