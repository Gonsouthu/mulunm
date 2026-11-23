<?php
// Copiá este archivo como config/config.local.php y completá tus datos.
// config.local.php está en .gitignore: nunca se sube al repositorio.
return [
    'host' => 'localhost',
    'name' => 'bedm',
    'user' => 'bedm_app',   // usuario propio con permisos solo sobre la base `bedm` (no uses root)
    'pass' => 'cambiar-esta-clave',

    // ── Previsualización de PDFs (ver README) ──
    // Carpeta donde está poppler (contiene pdftoppm y pdfinfo). En Windows es obligatorio.
    // Usá barras normales (/), no invertidas.
    // 'poppler_dir' => 'C:/poppler/poppler-24.08.0/Library/bin',

    // Solo si diagnostico.php marca error de certificados en la conexión segura:
    // 'ca_bundle' => 'C:/xampp/php/extras/ssl/cacert.pem',

    // ── Emails (verificación de cuenta y recuperación de contraseña) ──
    // Sin 'smtp_host' los emails NO se envían: se guardan como archivos (modo prueba, ver diagnostico.php).
    // Para enviarlos de verdad completá estos datos (los da tu proveedor de correo):
    // 'smtp_host' => 'smtp.tu-proveedor.com', 'smtp_puerto' => 587, 'smtp_seguridad' => 'tls',   // 'tls' (puerto 587), 'ssl' (puerto 465) o 'ninguna' (solo pruebas)
    // 'smtp_usuario' => 'usuario@tu-dominio.edu.ar', 'smtp_clave' => 'la-clave',
    // 'mail_desde' => 'no-responder@tu-dominio.edu.ar', 'mail_desde_nombre' => 'BEDM · Repositorio UNM',
    // Dirección pública del sitio, para los enlaces de los emails. OBLIGATORIA fuera de localhost:
    // 'url_base' => 'https://tu-sitio.edu.ar/BEDM',
    // 'contacto_email' => 'bedm@tu-dominio.edu.ar',   // se muestra en los términos para pedir bajas

    // ── Publicación (valores por defecto) ──
    // 'publicar_max_por_dia' => 10,     // trabajos que una persona puede publicar por día

    // ── Seguridad (valores por defecto) ──
    // 'sesion_inactividad_min' => 480,   // minutos sin actividad antes de cerrar la sesión
    // 'sesion_maxima_dias'     => 14,    // duración máxima de una sesión
    // 'csp'  => 'bloquear',              // 'avisar' solo registra en la consola del navegador; 'no' la apaga
    // 'hsts' => false,                   // true cuando el sitio ya funcione con https
    // 'confiar_proxy' => false,          // true solo si el hosting usa un proxy que informa https

    // Ajustes opcionales (estos son los valores por defecto):
    // 'ingesta_max_mb'        => 40,   // peso máximo del PDF
    // 'ingesta_max_paginas'   => 20,   // páginas que se previsualizan
    // 'ingesta_max_por_10min' => 8,    // previsualizaciones por usuario cada 10 minutos
];
