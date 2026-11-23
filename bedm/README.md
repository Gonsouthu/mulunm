# BEDM · Repositorio Curricular Situado (UNM)

Portal donde los alumnos de Diseño Multimedial de la Universidad Nacional de Moreno guardan y muestran los trabajos de su cursada.
Es un repositorio de trabajos, no una bolsa de empleo.

> Estado: prototipo en desarrollo. Funcionan registro, login, perfiles y búsqueda de usuarios. La subida y previsualización de trabajos está en construcción.

## Requisitos
- PHP 8.1+ con las extensiones `pdo_mysql`, `mbstring`, `curl` y `openssl` (XAMPP ya las trae)
- [poppler](https://github.com/oschwartz10612/poppler-windows/releases) (herramientas `pdftoppm` y `pdfinfo`) para generar las previsualizaciones. En Linux: `apt install poppler-utils`
- MariaDB 10.4+ o MySQL 8+
- Apache (XAMPP sirve para desarrollo local)

## Instalación local
1. Copiá el proyecto a `htdocs/` (XAMPP) o a tu carpeta web.
2. Importá `database/schema.sql` desde phpMyAdmin (crea la base `bedm` con sus tablas y las 47 materias de la carrera).
   - Si ya tenías la base de una versión anterior, **no** uses ese archivo: ejecutá en orden los de `database/migraciones/` que te falten (002, 003…). Conservan tus datos. Hacelo **antes** de actualizar los archivos del sitio.
3. Creá un usuario MySQL propio para la app, con permisos solo sobre `bedm` (no uses `root`).
4. Copiá `config/config.example.php` como `config/config.local.php` y completá tus datos. Ese archivo no se sube a git.
5. Indicá la carpeta de poppler en `config/config.local.php` (ver comentarios de `config.example.php`).
6. Abrí `http://localhost/BEDM/diagnostico.php`: revisa que todo esté listo y te dice qué falta.
7. Abrí `http://localhost/BEDM/register.php` y creá tu cuenta.

## Estructura
- `index.php`, `buscar.php`, `perfil.php`, `crear.php`: páginas (requieren sesión)
- `login.php`, `register.php`, `logout.php`: autenticación
- `api/`: endpoints JSON
- `includes/auth.php`, `config/db.php`: sesión y conexión a la base
- `database/schema.sql`: esquema completo para instalaciones nuevas (sin datos de usuarios)
- `database/migraciones/`: pasos para actualizar una base existente (`002_base_v2.sql`: versión 1 → 2)
- `api/materias.php`: lista de materias de la carrera del usuario
- `api/previsualizar.php`, `api/preview_img.php`: del link de la nube a las imágenes de previsualización
- `includes/ingesta.php`: motor de ingestión (descarga segura, PDF a imágenes). El archivo original **no se guarda**
- `includes/seguridad.php`: sesión segura, cabeceras (CSP), CSRF, límites de intentos, tokens de un solo uso
- `includes/mailer.php` + `lib/phpmailer/`: envío de emails (verificación y recuperación de contraseña)
- `login.php`, `register.php`, `logout.php`, `olvide.php`, `restablecer.php`, `verificar.php`, `terminos.php`: acceso y cuenta
- `crear.php`, `trabajo.php`, `includes/trabajos.php`: publicar un trabajo, verlo, editarlo, ocultarlo o eliminarlo; coautorías
- `api/trabajo_*.php`, `api/coautoria.php`, `api/trabajos.php`: APIs de trabajos (todas exigen sesión y CSRF si modifican datos)
- `includes/interaccion.php`, `api/trabajo_like.php`, `api/comentarios.php`: «me gusta» y comentarios
- `includes/cuentas.php`, `baja.php`, `reactivar.php`, `exportar.php`, `api/perfil_*.php`: foto de perfil y portada, historial descargable, baja y reactivación de cuenta
- `mantenimiento.php` (solo desde localhost) y `tools/purgar_bajas.php` (línea de comandos): copia de seguridad de las imágenes y anonimización de bajas vencidas
- `diagnostico.php`: revisión del servidor (solo desde localhost)
- `tests/`: pruebas automáticas (`php tests/ingesta_test.php`)

## Seguridad (resumen)
- Sesión: cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` con https), vence por inactividad (8 h) y a los 14 días.
- CSRF: todos los formularios y APIs que modifican datos exigen un código por sesión.
- Login: máximo 5 fallos por cuenta y conexión cada 15 min; mismo mensaje y tiempo exista o no el correo.
- Contraseñas: hash bcrypt, entre 8 y 72 caracteres. Al restablecerla se cierran todas las sesiones abiertas.
- Cabeceras: CSP (solo scripts propios), `nosniff`, `X-Frame-Options`, `Referrer-Policy`.
- Emails: sin `smtp_host` quedan como archivos en `storage/mails/` (modo prueba). En un servidor real configurá SMTP y `url_base` (ver `config.example.php`).
- Los términos y condiciones (`terminos.php`) son un borrador: deben revisarse con la Universidad antes de abrir el sitio.

## Publicación de trabajos (resumen)
- El archivo original **no se guarda**: solo el link, imágenes de baja resolución con un sello de autoría al pie, y la huella SHA-256.
- El nombre del trabajo se arma en el servidor: `Apellido, Nombre — Materia — Ciclo — Título`.
- Para publicar hay que tener el correo verificado y declarar la autoría. Máximo 10 publicaciones por día.
- El mismo archivo (misma huella) no se puede publicar dos veces; quien llega primero queda registrado y el resto debe figurar como coautor/a.
- Los coautores con cuenta deben aceptar. El link al archivo original solo lo ve quien publicó (salvo licencia Creative Commons).
- La extensión `gd` de PHP agrega el sello y las miniaturas; sin ella se publica igual, sin sello.

## «Me gusta», comentarios y búsqueda (resumen)
- Hace falta el correo verificado para dar «me gusta» o comentar. No se puede dar «me gusta» al propio trabajo.
- Comentarios de hasta 1000 caracteres (máximo 3 por minuto). Los borra quien los escribió o quien publicó el trabajo.
- Los trabajos ocultos no se pueden comentar ni aparecen en las búsquedas de otras personas.
- El buscador (`buscar.php`) busca por nombre del trabajo, descripción, autores, materia y ciclo, y respeta los filtros de año, cuatrimestre, categoría y ciclo. No distingue mayúsculas ni tildes.

## Cuenta y preservación (resumen)
- Foto de perfil (400×400) y portada (1200×400): se vuelven a codificar como JPEG, lo que elimina cualquier dato oculto (por ejemplo la ubicación GPS de las fotos de celular). Solo JPG, PNG o WebP. Requiere la extensión `gd`.
- «Mi cuenta» (en el perfil propio): presentación, legajo (solo lo ve su dueño/a), quitar fotos, descargar el historial y dar de baja la cuenta.
- El historial es un ZIP con imágenes, datos, comentarios recibidos y un `historial.html` para leer sin conexión. Requiere la extensión `zip`.
- Baja: pide la contraseña; los trabajos se ocultan o se eliminan; se borran comentarios y «me gusta». Hay 30 días para reactivar la cuenta ingresando; después los datos personales se anonimizan (`mantenimiento.php` o `php tools/purgar_bajas.php`, que se puede programar con cron).
- **Copias de seguridad:** las imágenes de `storage/` son lo único que se conserva de cada trabajo (los originales no se guardan). Hay que copiar `storage/trabajos/` y `storage/perfiles/` junto con la base de datos (phpMyAdmin → Exportar), con la misma fecha. `mantenimiento.php` descarga las imágenes en un ZIP.

## Reglas del proyecto
- Nunca subir credenciales, dumps con datos reales ni archivos de alumnos al repositorio.
- Los `.htaccess` de `config/`, `includes/` y `database/` bloquean el acceso directo por web (requieren Apache con `AllowOverride` habilitado).
