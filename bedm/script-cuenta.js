// ══════════════════════════════════════════════════════════════
// Perfil propio: presentación, foto, banner, nickname y legajo.
// ══════════════════════════════════════════════════════════════
(() => {
    const $ = (id) => document.getElementById(id);

    async function respuesta(r) {
        return r.json().catch(() => ({ ok: false, error: 'El servidor respondió algo inesperado.' }));
    }

    // ── 1. Guardar Presentación (tarjeta sobre "Añadir trabajo") ──
    const btnBio = $('btnGuardarBio');
    if (btnBio) {
        const bioErr = $('bioError'), bioOk = $('bioOk');
        const msgBio = (txt, err = true) => {
            bioErr.classList.toggle('show', err && !!txt);
            bioOk.classList.toggle('show', !err && !!txt);
            (err ? bioErr : bioOk).textContent = txt;
        };

        btnBio.addEventListener('click', async () => {
            msgBio('');
            btnBio.disabled = true;
            const r = await fetch('api/perfil_editar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({
                    bio: $('bioInput').value,
                    legajo: $('legajoInput') ? $('legajoInput').value : ''
                })
            });
            const res = await respuesta(r);
            btnBio.disabled = false;
            if (r.ok && res.ok) msgBio('Presentación guardada.', false);
            else msgBio(res.error || 'No se pudo guardar la presentación.');
        });
    }

    // ── 2. Modal "Editar perfil" ──
    const trigger = $('editProfileTrigger');
    const modal = $('editProfileModal');
    const cancelBtn = $('cancelEditProfile');
    const errorEl = $('cuentaError'), okEl = $('cuentaOk');

    const mostrar = (texto, esError = true) => {
        if (!errorEl) { if (texto) alert(texto); return; }
        errorEl.classList.toggle('show', esError && !!texto);
        okEl.classList.toggle('show', !esError && !!texto);
        (esError ? errorEl : okEl).textContent = texto;
        (esError ? okEl : errorEl).textContent = '';
    };

    if (trigger && modal) {
        trigger.addEventListener('click', () => {
            mostrar('');
            modal.style.display = 'flex';
        });
        if (cancelBtn) cancelBtn.addEventListener('click', () => { modal.style.display = 'none'; });
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
    }

    // Subir foto o portada: el servidor la redimensiona y la guarda limpia
    async function subir(tipo, archivo) {
        const limite = tipo === 'avatar' ? 3 : 5;
        if (archivo.size > limite * 1048576) { mostrar(`La imagen pesa demasiado (máximo ${limite} MB).`); return; }
        const datos = new FormData();
        datos.append('tipo', tipo);
        datos.append('archivo', archivo);
        const r = await fetch('api/perfil_imagen.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() }, body: datos });
        const res = await respuesta(r);
        if (r.ok && res.ok) window.location.reload();
        else mostrar(res.error || 'No se pudo subir la imagen.');
    }

    [['avatarImageInput', 'avatar'], ['bannerImageInput', 'banner']].forEach(([id, tipo]) => {
        const input = $(id);
        if (input) input.addEventListener('change', () => { if (input.files[0]) subir(tipo, input.files[0]); input.value = ''; });
    });

    document.querySelectorAll('[data-quitar-imagen]').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('¿Quitar esta imagen?')) return;
        const r = await fetch('api/perfil_imagen.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
            body: JSON.stringify({ accion: 'quitar', tipo: b.dataset.quitarImagen })
        });
        const res = await respuesta(r);
        if (r.ok && res.ok) window.location.reload();
        else mostrar(res.error || 'No se pudo quitar la imagen.');
    }));

    // Guardar Nickname y Legajo desde el modal
    const guardar = $('btnGuardarPerfil');
    if (guardar) {
        guardar.addEventListener('click', async () => {
            mostrar('');
            const nickInput = $('nickInput');
            const nick = nickInput.value.trim().toLowerCase().replace(/^@/, '');
            const nickActual = (document.body.dataset.nick || '').toLowerCase();

            if (!NICK_REGEX.test(nick)) {
                return mostrar('El nickname debe tener entre 3 y 20 caracteres: minúsculas, números, punto o guion bajo.');
            }

            guardar.disabled = true;

            if (nick !== nickActual) {
                const rNick = await fetch('api/nickname.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ nickname: nick })
                });
                const resNick = await respuesta(rNick);
                if (!rNick.ok || !resNick.ok) {
                    guardar.disabled = false;
                    return mostrar(resNick.error || 'Ese nickname ya está en uso.');
                }
                document.body.dataset.nick = resNick.nickname || nick;
                const nickText = $('nickText');
                if (nickText) nickText.textContent = '@' + (resNick.nickname || nick);
            }

            const rPerfil = await fetch('api/perfil_editar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({
                    bio: $('bioInput') ? $('bioInput').value : '',
                    legajo: $('legajoInput').value
                })
            });
            const resPerfil = await respuesta(rPerfil);
            guardar.disabled = false;

            if (rPerfil.ok && resPerfil.ok) window.location.reload();
            else mostrar(resPerfil.error || 'No se pudieron guardar los cambios.');
        });
    }
})();