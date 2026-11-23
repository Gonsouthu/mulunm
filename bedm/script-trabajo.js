// ══════════════════════════════════════════════════════════════
// Pantallas de publicar (crear.php) y de detalle de un trabajo (trabajo.php).
// Usa csrfToken() y escapeHTML() de script.js.
// ══════════════════════════════════════════════════════════════
(() => {
    const $ = (id) => document.getElementById(id);

    // POST con JSON y código CSRF. Devuelve { r, res }.
    async function post(url, cuerpo) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
            body: JSON.stringify(cuerpo)
        });
        const res = await r.json().catch(() => ({ ok: false, error: 'El servidor respondió algo inesperado.' }));
        return { r, res };
    }

    // ── Buscador de personas (autocompletado por nombre o nickname) ──
    function iniciarBuscadorPersonas(input, lista, { excluir = () => [], alElegir }) {
        if (!input || !lista) return;
        let temporizador = null, pedido = 0;
        const limpiar = () => { lista.innerHTML = ''; };
        input.addEventListener('input', () => {
            clearTimeout(temporizador);
            const q = input.value.trim();
            if (q.length < 2) { limpiar(); return; }
            temporizador = setTimeout(async () => {
                const mio = ++pedido;
                try {
                    const r = await fetch('api/buscar_usuarios.php?q=' + encodeURIComponent(q));
                    const datos = r.ok ? await r.json() : [];
                    if (mio !== pedido) return;
                    const yo = document.body.dataset.yoNick;
                    const ocultos = new Set([yo, ...excluir()]);
                    const filtrados = datos.filter(u => !ocultos.has(u.nickname)).slice(0, 6);
                    lista.innerHTML = filtrados.length
                        ? filtrados.map(u => `<button type="button" class="co-item" data-nick="${escapeHTML(u.nickname)}" data-nombre="${escapeHTML(u.apellido + ', ' + u.nombre)}">${escapeHTML(u.apellido + ', ' + u.nombre)} <span class="nick">@${escapeHTML(u.nickname)}</span></button>`).join('')
                        : '<p class="form-ayuda">No encontramos a nadie con ese nombre.</p>';
                } catch (e) { limpiar(); }
            }, 250);
        });
        lista.addEventListener('click', (e) => {
            const b = e.target.closest('.co-item');
            if (!b) return;
            alElegir({ nickname: b.dataset.nick, etiqueta: b.dataset.nombre + ' (@' + b.dataset.nick + ')' });
            input.value = ''; limpiar();
        });
    }

    // ════════════════════════════════════════════════
    // crear.php
    // ════════════════════════════════════════════════
    const form = $('trabajoForm');
    if (form) {
        const actual = Number(form.dataset.actual);
        const autor = form.dataset.autor;
        const coautores = [];           // { nickname, etiqueta } | { nombre, etiqueta }
        const materiaSel = $('materiaSelect'), cicloSel = $('cicloSelect');
        const error = $('formError');
        const mostrarError = (t) => { error.textContent = t; error.classList.toggle('show', !!t); };

        // Ciclos posibles según el año de la materia (mismo criterio que valida el servidor)
        function actualizarCiclos() {
            const op = materiaSel.selectedOptions[0];
            const previo = cicloSel.value;
            cicloSel.innerHTML = '';
            if (!op || !op.value) { cicloSel.innerHTML = '<option value="">Primero elegí la materia</option>'; return; }
            const min = Number(op.dataset.inicio) + Number(op.dataset.anio) - 1;
            if (min > actual) { cicloSel.innerHTML = `<option value="">Esta materia se cursa desde ${min}</option>`; return; }
            for (let y = actual; y >= min; y--) cicloSel.insertAdjacentHTML('beforeend', `<option value="${y}">${y}</option>`);
            if (previo && cicloSel.querySelector(`option[value="${previo}"]`)) cicloSel.value = previo;
            $('electivaBloque').style.display = op.dataset.electiva === '1' ? 'block' : 'none';
        }
        function nombreCanonico() {
            const op = materiaSel.selectedOptions[0];
            if (!op || !op.value) { $('nombreCanonico').textContent = ''; return; }
            let materia = op.textContent.trim();
            if (op.dataset.electiva === '1' && $('electivaInput').value.trim()) materia += ': ' + $('electivaInput').value.trim();
            const titulo = $('tituloInput').value.trim() || '…';
            $('nombreCanonico').textContent = `Se va a publicar como: ${autor} — ${materia} — ${cicloSel.value || '…'} — ${titulo}`;
        }
        materiaSel.addEventListener('change', () => { actualizarCiclos(); nombreCanonico(); });
        [cicloSel, $('electivaInput'), $('tituloInput')].forEach(el => el.addEventListener('input', nombreCanonico));
        cicloSel.addEventListener('change', nombreCanonico);
        actualizarCiclos(); nombreCanonico();

        // ── Coautores ──
        const chips = $('coChips');
        function pintarChips() {
            chips.innerHTML = coautores.map((c, i) => `<span class="chip">${escapeHTML(c.etiqueta)}${c.nickname ? '' : ' · sin cuenta'} <button type="button" data-quitar="${i}" aria-label="Quitar">×</button></span>`).join('');
        }
        chips.addEventListener('click', (e) => {
            const b = e.target.closest('[data-quitar]');
            if (b) { coautores.splice(Number(b.dataset.quitar), 1); pintarChips(); }
        });
        iniciarBuscadorPersonas($('coInput'), $('coResultados'), {
            excluir: () => coautores.filter(c => c.nickname).map(c => c.nickname),
            alElegir: (u) => { if (coautores.length < 7) { coautores.push(u); pintarChips(); } }
        });
        $('coExternoBtn').addEventListener('click', () => {
            const n = $('coExterno').value.trim();
            if (n.length >= 2 && coautores.length < 7) { coautores.push({ nombre: n, etiqueta: n }); pintarChips(); $('coExterno').value = ''; }
        });

        // ── Previsualización del PDF (el servidor descarga el archivo, genera las imágenes y borra el original) ──
        const pdfUrlInput = $('pdfUrlInput'), pdfViewer = $('pdfViewer'), previewInfo = $('previewInfo'), previewToken = $('previewToken');
        const AYUDA = previewInfo.textContent;
        const NOMBRES = { drive: 'Google Drive', dropbox: 'Dropbox', onedrive: 'OneDrive' };
        let ultimaUrl = '', controlador = null;
        const mostrarInfo = (texto, esError = false) => { previewInfo.textContent = texto; previewInfo.classList.toggle('error', esError); };
        const limpiarPreview = () => { pdfViewer.innerHTML = ''; pdfViewer.style.display = 'none'; previewToken.value = ''; };
        const pesoLegible = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';

        async function previsualizar() {
            const url = pdfUrlInput.value.trim();
            if (url === ultimaUrl) return;
            ultimaUrl = url;
            if (controlador) controlador.abort();
            limpiarPreview();
            if (url === '') { mostrarInfo(AYUDA); return; }
            controlador = new AbortController();
            mostrarInfo('Procesando el archivo… puede tardar hasta un minuto.');
            try {
                const r = await fetch('api/previsualizar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ url }),
                    signal: controlador.signal
                });
                const res = await r.json().catch(() => ({ ok: false, error: 'El servidor respondió algo inesperado.' }));
                if (!r.ok || !res.ok) { ultimaUrl = ''; mostrarInfo(res.error || 'No se pudo procesar el archivo.', true); return; }
                pdfViewer.innerHTML = res.imagenes.map((src, i) => `<img src="${escapeHTML(src)}" alt="Página ${i + 1}" loading="lazy">`).join('');
                pdfViewer.style.display = 'flex';
                previewToken.value = res.token;
                const paginas = res.truncado ? `primeras ${res.paginas} de ${res.total_paginas} páginas` : `${res.total_paginas} ${res.total_paginas === 1 ? 'página' : 'páginas'}`;
                mostrarInfo(`${NOMBRES[res.proveedor] || 'Archivo'} · ${paginas} · ${pesoLegible(res.bytes)}`);
            } catch (e) {
                if (e.name === 'AbortError') return;
                ultimaUrl = '';
                mostrarInfo('No hay conexión con el servidor.', true);
            }
        }
        pdfUrlInput.addEventListener('change', previsualizar);
        pdfUrlInput.addEventListener('paste', () => setTimeout(previsualizar, 0));
        pdfUrlInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); previsualizar(); } });

        // ── Publicar ──
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            mostrarError('');
            if (!materiaSel.value) return mostrarError('Elegí la materia.');
            if (!cicloSel.value) return mostrarError('Elegí el ciclo lectivo.');
            if ($('electivaBloque').style.display !== 'none' && $('electivaInput').value.trim().length < 3) return mostrarError('Indicá qué electiva cursaste.');
            if ($('tituloInput').value.trim().length < 3) return mostrarError('Escribí el nombre del trabajo.');
            if (!previewToken.value) return mostrarError('Pegá el link de tu PDF y esperá a que aparezca la previsualización.');
            if (!$('declaracion').checked) return mostrarError('Tenés que marcar la declaración de autoría.');

            const btn = $('btnPublicar');
            btn.disabled = true; btn.textContent = 'Publicando…';
            const { r, res } = await post('api/trabajo_crear.php', {
                token: previewToken.value,
                materia_id: Number(materiaSel.value),
                ciclo: Number(cicloSel.value),
                electiva: $('electivaInput').value,
                titulo: $('tituloInput').value,
                descripcion: $('descripcionInput').value,
                licencia: $('licenciaSelect').value,
                nota: $('notaInput').value,
                nota_publica: $('notaPublica').checked,
                declaracion: $('declaracion').checked,
                coautores: coautores.map(c => c.nickname ? { nickname: c.nickname } : { nombre: c.nombre })
            });
            if (r.ok && res.ok) { window.location.href = res.url; return; }
            if (res.codigo === 'preview') { ultimaUrl = ''; limpiarPreview(); mostrarInfo(AYUDA); }
            mostrarError(res.error || 'No se pudo publicar el trabajo.');
            btn.disabled = false; btn.textContent = 'Publicar trabajo';
        });
    }

    // ════════════════════════════════════════════════
    // trabajo.php
    // ════════════════════════════════════════════════
    const trabajoId = Number(document.body.dataset.trabajoId || 0);
    if (trabajoId) {
        const panelError = $('panelError');
        const err = (t) => { if (panelError) { panelError.textContent = t; panelError.classList.toggle('show', !!t); } };
        const coautoria = async (cuerpo) => {
            const { r, res } = await post('api/coautoria.php', { trabajo_id: trabajoId, ...cuerpo });
            if (r.ok && res.ok) { window.location.reload(); return; }
            err(res.error || 'No se pudo completar la acción.');
            if (!panelError) alert(res.error || 'No se pudo completar la acción.');
        };

        document.querySelectorAll('[data-coautoria]').forEach(b => b.addEventListener('click', () => coautoria({ accion: b.dataset.coautoria })));
        document.querySelectorAll('[data-quitar]').forEach(b => b.addEventListener('click', () => {
            if (confirm('¿Quitar a esta persona de los autores?')) coautoria({ accion: 'quitar', autor_id: Number(b.dataset.quitar) });
        }));

        // ── «Me gusta» ──
        const btnLike = $('btnLike');
        if (btnLike && !btnLike.disabled) {
            btnLike.addEventListener('click', async () => {
                const quiere = btnLike.getAttribute('aria-pressed') !== 'true';
                btnLike.disabled = true;
                const { r, res } = await post('api/trabajo_like.php', { id: trabajoId, me_gusta: quiere });
                btnLike.disabled = false;
                if (r.ok && res.ok) {
                    btnLike.setAttribute('aria-pressed', res.me_gusta ? 'true' : 'false');
                    btnLike.classList.toggle('activo', res.me_gusta);
                    btnLike.querySelector('.like-corazon').textContent = res.me_gusta ? '♥' : '♡';
                    $('likeCount').textContent = res.likes;
                } else {
                    alert(res.error || 'No se pudo registrar tu «me gusta».');
                }
            });
        }

        // ── Comentarios ──
        const btnComentar = $('btnComentar');
        if (btnComentar) {
            btnComentar.addEventListener('click', async () => {
                const errCom = $('comentarioError');
                errCom.classList.remove('show');
                const texto = $('comentarioInput').value.trim();
                if (!texto) { errCom.textContent = 'Escribí algo para comentar.'; errCom.classList.add('show'); return; }
                btnComentar.disabled = true;
                const { r, res } = await post('api/comentarios.php', { accion: 'crear', trabajo_id: trabajoId, texto });
                btnComentar.disabled = false;
                if (r.ok && res.ok) { window.location.hash = 'comentarios'; window.location.reload(); }
                else { errCom.textContent = res.error || 'No se pudo publicar el comentario.'; errCom.classList.add('show'); }
            });
        }
        document.querySelectorAll('[data-borrar-comentario]').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('¿Borrar este comentario?')) return;
            const { r, res } = await post('api/comentarios.php', { accion: 'borrar', id: Number(b.dataset.borrarComentario) });
            if (r.ok && res.ok) {
                const li = b.closest('.comentario'); if (li) li.remove();
                const tot = $('comentariosTotal'); if (tot) tot.textContent = Math.max(0, Number(tot.textContent) - 1);
            } else { alert(res.error || 'No se pudo borrar el comentario.'); }
        }));

        const guardar = $('btnGuardarEdicion');
        if (guardar) {
            guardar.addEventListener('click', async () => {
                err('');
                guardar.disabled = true;
                const { r, res } = await post('api/trabajo_editar.php', {
                    id: trabajoId,
                    titulo: $('editTitulo').value,
                    descripcion: $('editDescripcion').value,
                    licencia: $('editLicencia').value,
                    nota: $('editNota').value,
                    nota_publica: $('editNotaPublica').checked
                });
                guardar.disabled = false;
                if (r.ok && res.ok) window.location.reload(); else err(res.error || 'No se pudieron guardar los cambios.');
            });
            $('btnOcultar').addEventListener('click', async (e) => {
                const { r, res } = await post('api/trabajo_editar.php', { id: trabajoId, estado: e.currentTarget.dataset.estado });
                if (r.ok && res.ok) window.location.reload(); else err(res.error || 'No se pudo cambiar la visibilidad.');
            });
            $('btnEliminar').addEventListener('click', async () => {
                if (!confirm('¿Eliminar este trabajo? Se borran las imágenes y no se puede deshacer.')) return;
                const { r, res } = await post('api/trabajo_eliminar.php', { id: trabajoId });
                if (r.ok && res.ok) window.location.href = 'perfil.php'; else err(res.error || 'No se pudo eliminar el trabajo.');
            });
            iniciarBuscadorPersonas($('coInput'), $('coResultados'), {
                alElegir: (u) => coautoria({ accion: 'agregar', coautor: { nickname: u.nickname } })
            });
            $('coExternoBtn').addEventListener('click', () => {
                const n = $('coExterno').value.trim();
                if (n.length >= 2) coautoria({ accion: 'agregar', coautor: { nombre: n } });
            });
        }
    }
})();
