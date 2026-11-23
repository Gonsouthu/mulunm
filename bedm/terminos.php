<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/plantilla_auth.php';
$contacto = cfg_valor('contacto_email');

auth_inicio('Términos y condiciones', 'Términos y condiciones', 'legal-box');
?>
            <div class="legal-text">
                <p class="legal-version">Versión 1 · borrador</p>

                <h2>1. Qué es BEDM</h2>
                <p>BEDM es un repositorio académico donde estudiantes de Diseño Multimedial de la Universidad Nacional de Moreno guardan y muestran los trabajos de su cursada. No es una bolsa de trabajo ni un servicio de contratación.</p>

                <h2>2. Autoría</h2>
                <p>Cada trabajo pertenece a sus autores. Al publicar declarás que sos autor/a del trabajo, o que contás con el permiso de tus coautores y de los titulares del material de terceros que uses (imágenes, tipografías, música, etc.). Si un trabajo es grupal, todas las personas que participaron deben figurar como autoras.</p>

                <h2>3. Qué guarda BEDM de tu trabajo</h2>
                <p>BEDM <b>no guarda el archivo original</b>. Conserva únicamente: el enlace que indicaste, imágenes de baja resolución para la previsualización, y una huella digital del archivo (SHA-256) con su fecha, que sirve para acreditar que el trabajo existía en ese momento. El archivo original sigue en tu nube, bajo tu control.</p>

                <h2>4. Permiso que le das a BEDM</h2>
                <p>Le permitís a BEDM mostrar las imágenes de previsualización dentro del portal, a las personas con cuenta, con tu nombre como autor/a. Esto no autoriza a nadie a copiar, descargar o reutilizar tu trabajo: seguís siendo titular de todos los derechos. Podés indicar una licencia para cada trabajo; si no elegís ninguna, rigen todos los derechos reservados.</p>

                <h2>5. Identidad</h2>
                <p>Cada cuenta debe usar tu nombre real. BEDM no puede verificar quién cursa la carrera, por eso la autoría se muestra siempre junto a cada trabajo. Hacerse pasar por otra persona o publicar trabajos ajenos como propios es motivo de baja de la cuenta.</p>

                <h2>6. Qué no se puede publicar</h2>
                <p>Contenido ilegal, que ataque o discrimine a otras personas, que exponga datos personales de terceros, o que vulnere derechos de autor de otras personas.</p>

                <h2>7. Denuncias y bajas</h2>
                <p>Si creés que un trabajo vulnera tus derechos, avisá a los administradores<?= $contacto ? ' (<b>' . h($contacto) . '</b>)' : '' ?> y el trabajo se ocultará mientras se revisa. Podés pedir en cualquier momento que se oculte o elimine un trabajo tuyo, o tu cuenta.</p>

                <h2>8. Datos personales</h2>
                <p>BEDM guarda tu nombre, apellido, nickname, correo electrónico y, si lo cargás, tu legajo (solo lo ves vos). Las notas son privadas salvo que decidas mostrarlas. Los datos se usan solo para el funcionamiento del portal, no se venden ni se ceden, y podés pedir su corrección o eliminación, conforme a la Ley 25.326 de Protección de los Datos Personales.</p>

                <h2>9. Fotos, historial y baja de la cuenta</h2>
                <p>Al subir una foto de perfil o una portada declarás que tenés derecho a usarla. Podés descargar en cualquier momento una copia de todo tu historial en BEDM. Podés dar de baja tu cuenta cuando quieras: tenés 30 días para reactivarla; pasado ese plazo tus datos personales se anonimizan y tus trabajos ocultos se eliminan. Al darte de baja se borran tus comentarios y «me gusta», y los trabajos en los que figurabas como coautor/a dejan de mostrarte.</p>

                <h2>10. Cambios</h2>
                <p>Estos términos pueden actualizarse; los cambios importantes se avisarán en el portal.</p>
            </div>
<?php auth_fin('<a href="register.php">Volver al registro</a> · <a href="login.php">Iniciar sesión</a>'); ?>
