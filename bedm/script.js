// ══════════════════════════════════════════════════════════════
// CONFIGURACIÓN GLOBAL
// ══════════════════════════════════════════════════════════════
const ANIO_INICIO_CARRERA = 2023;                 // año en que se inauguró la carrera
const ANIOS_CARRERA = 5;
const CICLO_ACTUAL = new Date().getFullYear();    // se actualiza solo cada año
const NICK_REGEX = /^[a-z0-9._]{3,20}$/;          // misma regla que valida api/nickname.php

// Cuántos años de carrera "existen" en un ciclo lectivo:
// 2023 -> 1, 2024 -> 2, 2025 -> 3, 2026 -> 4, 2027 en adelante -> 5
const aniosDisponibles = (ciclo) => Math.max(0, Math.min(ANIOS_CARRERA, ciclo - ANIO_INICIO_CARRERA + 1));
const numeroDeAnio = (txt) => parseInt(String(txt).replace('Año ', ''), 10);
const capitalizar = (s) => s.charAt(0).toUpperCase() + s.slice(1);
const escapeHTML = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));


// Tarjeta de trabajo reutilizable (index, buscar y perfiles). Al tocarla se abre trabajo.php?id=...
// img puede venir null: en ese caso queda el fondo azul de la tarjeta.
function tarjetaTrabajo({ id, tp, autor, sub, img, oculto, likes }) {
    const fondo = img ? ` style="background-image: url('${escapeHTML(img)}');"` : '';
    const linea = [autor, sub, likes > 0 ? '♥ ' + likes : ''].filter(Boolean).join(' · ');
    return `
        <article class="community-card" data-id="${Number(id) || 0}" role="link" tabindex="0">
            <div class="card-preview"${fondo}></div>
            <div class="card-avatar"><i class="fa-solid fa-user"></i></div>
            <div class="card-content">
                <h4 class="card-title">${escapeHTML(tp)}${oculto ? ' <span class="nick">(oculto)</span>' : ''}</h4>
                <p class="card-author">${escapeHTML(linea)}</p>
            </div>
        </article>`;
}

// Carga una lista de trabajos en una grilla. subDe(t) arma el texto secundario de cada tarjeta.
async function cargarTrabajos(url, grid, vacio, subDe) {
    grid.innerHTML = '<p class="empty-state">Cargando…</p>';
    try {
        const r = await fetch(url);
        const lista = r.ok ? await r.json() : [];
        grid.innerHTML = lista.length
            ? lista.map(t => tarjetaTrabajo({ ...t, sub: subDe(t) })).join('')
            : `<p class="empty-state">${escapeHTML(vacio)}</p>`;
    } catch (e) {
        grid.innerHTML = '<p class="empty-state">No se pudieron cargar los trabajos. Probá recargar la página.</p>';
    }
}

const ICONOS_ORIENTACION = {
  'taller': 'img/taller.png',
  'tecnología': 'img/tecno.png',
  'sistemas': 'img/sistemas.png'
};

function tarjetaMateria(m, subtitulo) {
  const rutaIcono = ICONOS_ORIENTACION[m.categoria] || 'img/taller.png';
  return `
  <article class="subject-card" data-materia="${escapeHTML(m.nombre)}" data-id="${Number(m.id) || 0}">
    <div class="subject-icon-box">
      <img src="${rutaIcono}" alt="${escapeHTML(m.categoria)}" class="subject-icon-img">
    </div>
    <div class="subject-info">
      <h4 title="${escapeHTML(m.nombre)}">${escapeHTML(m.nombre)}</h4>
      <p>${escapeHTML(subtitulo)}</p>
    </div>
  </article>`;
}

// Código CSRF de la página (viene en <meta name="csrf-token">): se manda en cada pedido que modifica datos
const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

document.addEventListener('DOMContentLoaded', () => {

    // --- Bloqueo de descarga / clic derecho sobre previsualizaciones de trabajos ---
const visorTrabajo = document.querySelector('.create-pdf-viewer');
if (visorTrabajo) {
  visorTrabajo.addEventListener('contextmenu', (e) => e.preventDefault());
  visorTrabajo.addEventListener('dragstart', (e) => e.preventDefault());
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 's' || e.key.toLowerCase() === 'p')) {
      e.preventDefault();
    }
  });
}

    // --- 1. Barra inferior: se oculta solo durante el Hero de index.php ---
    const bottomNav = document.getElementById('bottomNav');
    const heroSection = document.getElementById('heroSection');

    if (heroSection && bottomNav) {
        // La barra aparece al pasar casi todo el banner, o al llegar al final de la página
        // (si hay poco contenido debajo del banner, nunca se llegaría al 80% del banner).
        const actualizarBarra = () => {
            const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
            const pasoElBanner = window.scrollY > heroSection.offsetHeight * 0.8;
            const alFinal = maxScroll <= 4 || window.scrollY >= maxScroll - 4;   // maxScroll <= 4: la página ni siquiera se desplaza
            bottomNav.classList.toggle('hidden', !(pasoElBanner || alFinal));
        };
        window.addEventListener('scroll', actualizarBarra);
        window.addEventListener('resize', actualizarBarra);
        actualizarBarra();
    } else if (bottomNav) {
        bottomNav.classList.remove('hidden');
    }

    // --- 1b. Sketch p5.js del hero: se centra y se escala para cubrir (o entrar en) el banner ---
    const p5Stage = document.getElementById('p5Stage');
    if (p5Stage && heroSection) {
        const ajustarP5 = () => {
            const ancho = Number(p5Stage.dataset.ancho) || 1080;
            const alto = Number(p5Stage.dataset.alto) || 1920;
            if (!heroSection.clientWidth || !heroSection.clientHeight) return;   // todavía sin tamaño: no escalar a 0
            const kx = heroSection.clientWidth / ancho;
            const ky = heroSection.clientHeight / alto;
            const k = p5Stage.dataset.ajuste === 'contain' ? Math.min(kx, ky) : Math.max(kx, ky);
            p5Stage.style.setProperty('--p5-escala', k);
        };
        window.addEventListener('resize', ajustarP5);
        ajustarP5();
    }

    // --- 1c. Botones con data-accion (reemplazan a los onclick del HTML, que la política de seguridad no permite) ---
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-accion]');
        if (!b) return;
        if (b.dataset.accion === 'volver') window.history.back();
        else if (b.dataset.accion === 'cerrar-materia-global') cerrarMateriaGlobal();
        else if (b.dataset.accion === 'cerrar-materia-perfil') cerrarMateriaPerfil();
    });

    // --- 1c2. Tarjetas de trabajo: abren el detalle (con clic o con Enter) ---
    const abrirTrabajo = (e) => {
        const t = e.target.closest('.community-card[data-id]');
        if (t && Number(t.dataset.id)) window.location.href = 'trabajo.php?id=' + Number(t.dataset.id);
    };
    document.addEventListener('click', abrirTrabajo);
    document.addEventListener('keydown', (e) => { if (e.key === 'Enter') abrirTrabajo(e); });

    // --- 1d. Aviso "confirmá tu correo": reenviar el email ---
    const btnReenviar = document.getElementById('reenviarVerificacion');
    if (btnReenviar) {
        btnReenviar.addEventListener('click', async () => {
            const texto = document.getElementById('avisoVerificacionTexto');
            btnReenviar.disabled = true;
            try {
                const r = await fetch('api/reenviar_verificacion.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() } });
                const res = await r.json().catch(() => ({}));
                texto.textContent = res.mensaje || res.error || 'No pudimos enviar el email ahora.';
                if (r.ok) btnReenviar.remove(); else btnReenviar.disabled = false;
            } catch (e) {
                texto.textContent = 'No hay conexión con el servidor.';
                btnReenviar.disabled = false;
            }
        });
    }

    // --- 2. Últimos trabajos subidos (index.php) ---
    const communityGrid = document.getElementById('communityGrid');
    if (communityGrid) {
        cargarTrabajos('api/ultimos_trabajos.php', communityGrid, 'Todavía no hay trabajos publicados.', t => t.materia);
    }

    // --- MATERIAS (vienen de la base: api/materias.php) ---
    let materiasDB = [];
    let materiasCargadas = false;
    const alActualizarMaterias = [];   // funciones que repintan las grillas cuando llegan las materias

    // --- 3A. BUSCADOR GLOBAL (buscar.php) ---
    const gridGlobal = document.getElementById('materiasGridGlobal');
    if (gridGlobal) {
        const searchInput = document.getElementById('searchInput');
        const cycleSel = document.getElementById('cycleSelectorGlobal');
        const yearSel = document.getElementById('yearSelectorGlobal');
        const termSel = document.getElementById('termSelectorGlobal');
        const catSel = document.getElementById('categorySelector');

        // Ciclos: desde el año actual hacia atrás hasta 2023
        function poblarCiclos() {
            let html = '<option value="todos" selected>Todos los ciclos</option>';
            for (let c = CICLO_ACTUAL; c >= ANIO_INICIO_CARRERA; c--) {
                html += `<option value="${c}">Ciclo ${c}</option>`;
            }
            cycleSel.innerHTML = html;
        }

        // Máximo año de carrera que puede tener trabajos según el ciclo elegido
        const topeAnios = () => aniosDisponibles(cycleSel.value === 'todos' ? CICLO_ACTUAL : Number(cycleSel.value));

        // Bloquea los años que todavía no existían en ese ciclo
        function actualizarAnios() {
            const tope = topeAnios();
            Array.from(yearSel.options).forEach(opt => {
                if (opt.value === 'todos') return;
                const bloqueado = numeroDeAnio(opt.value) > tope;
                opt.disabled = bloqueado;
                opt.hidden = bloqueado;
            });
            if (yearSel.value !== 'todos' && numeroDeAnio(yearSel.value) > tope) yearSel.value = 'todos';
        }

        let reqId = 0;   // descarta respuestas viejas si el usuario sigue escribiendo

        // Usuarios reales desde la base (api/buscar_usuarios.php)
        async function renderUsuarios(q) {
            const miReq = ++reqId;
            let res = [];
            try {
                const r = await fetch('api/buscar_usuarios.php?q=' + encodeURIComponent(q));
                res = r.ok ? await r.json() : [];
            } catch (e) { res = []; }
            if (miReq !== reqId) return;

            gridGlobal.innerHTML = res.length
                ? res.map(u => `
                    <a class="subject-card user-result" href="perfil.php?u=${encodeURIComponent(u.nickname)}">
                        <div class="subject-icon-box"><i class="fa-solid fa-user"></i></div>
                        <div class="subject-info">
                            <h4>@${escapeHTML(u.nickname)}</h4>
                            <p>${escapeHTML(u.nombre + ' ' + u.apellido)}</p>
                        </div>
                    </a>`).join('')
                : '<p class="empty-state">No se encontraron usuarios.</p>';
        }

        // Trabajos que coinciden con el texto (título, descripción, autores, materia o ciclo)
        const bloqueTrab = document.getElementById('trabajosBusqueda');
        const gridTrab = document.getElementById('trabajosBusquedaGrid');
        let temporizadorTrab = null, reqTrab = 0;
        function renderTrabajosBusqueda(texto) {
            clearTimeout(temporizadorTrab);
            if (!bloqueTrab) return;
            if (texto.length < 2 || texto.startsWith('@')) { reqTrab++; bloqueTrab.style.display = 'none'; gridTrab.innerHTML = ''; return; }
            temporizadorTrab = setTimeout(async () => {
                const mi = ++reqTrab;
                const p = new URLSearchParams({ q: texto });
                if (cycleSel.value !== 'todos') p.set('ciclo', cycleSel.value);
                if (yearSel.value !== 'todos') p.set('anio', numeroDeAnio(yearSel.value));
                if (termSel.value !== 'todos') p.set('cuatri', termSel.value.startsWith('1') ? 1 : 2);
                if (catSel.value !== 'todas') p.set('categoria', catSel.value);
                bloqueTrab.style.display = 'block';
                gridTrab.innerHTML = '<p class="empty-state">Buscando trabajos…</p>';
                try {
                    const r = await fetch('api/trabajos.php?' + p.toString());
                    const lista = r.ok ? await r.json() : [];
                    if (mi !== reqTrab) return;   // el usuario siguió escribiendo: esta respuesta ya no sirve
                    gridTrab.innerHTML = lista.length
                        ? lista.map(t => tarjetaTrabajo({ ...t, sub: t.materia + ' · ' + t.ciclo })).join('')
                        : '<p class="empty-state">No se encontraron trabajos.</p>';
                } catch (e) {
                    if (mi === reqTrab) gridTrab.innerHTML = '<p class="empty-state">No se pudo buscar. Probá de nuevo.</p>';
                }
            }, 300);
        }

        function renderGlobal() {
            const texto = searchInput.value.trim().toLowerCase();
            renderTrabajosBusqueda(texto);
            if (texto.startsWith('@')) { renderUsuarios(texto.slice(1)); return; }
            reqId++;   // cancela cualquier búsqueda de usuarios pendiente
            if (!materiasCargadas) { gridGlobal.innerHTML = '<p class="empty-state">Cargando materias…</p>'; return; }

            const tope = topeAnios();
            const filtradas = materiasDB.filter(m =>
                m.nombre.toLowerCase().includes(texto) &&
                numeroDeAnio(m.anio) <= tope &&
                (yearSel.value === 'todos' || m.anio === yearSel.value) &&
                (termSel.value === 'todos' || m.cuatrimestre === termSel.value) &&
                (catSel.value === 'todas' || m.categoria === catSel.value)
            );

            gridGlobal.innerHTML = filtradas.length
                ? filtradas.map(m => tarjetaMateria(m, `${m.anio} • ${capitalizar(m.categoria)}`)).join('')
                : '<p class="empty-state">No se encontraron materias.</p>';
        }

        gridGlobal.addEventListener('click', (e) => {
            const card = e.target.closest('[data-materia]');
            if (card) abrirMateriaGlobal(card.dataset.materia, cycleSel.value, card.dataset.id);
        });

        cycleSel.addEventListener('change', () => { actualizarAnios(); renderGlobal(); });
        [searchInput].forEach(el => el.addEventListener('input', renderGlobal));
        [yearSel, termSel, catSel].forEach(el => el.addEventListener('change', renderGlobal));

        alActualizarMaterias.push(renderGlobal);
        poblarCiclos();
        actualizarAnios();
        renderGlobal();
    }

    // --- 3B. MIS MATERIAS (perfil.php, modo propio) ---
    const gridPerfil = document.getElementById('materiasGrid');
    if (gridPerfil) {
        const yearSelP = document.getElementById('yearSelector');
        const termSelP = document.getElementById('termSelector');

        function renderPerfil() {
            if (!materiasCargadas) { gridPerfil.innerHTML = '<p class="empty-state">Cargando materias…</p>'; return; }
            const filtradas = materiasDB.filter(m =>
                (yearSelP.value === 'todos' || m.anio === yearSelP.value) &&
                (termSelP.value === 'todos' || m.cuatrimestre === termSelP.value)
            );
            gridPerfil.innerHTML = filtradas.length
                ? filtradas.map(m => tarjetaMateria(m, `${m.anio} • ${m.cuatrimestre}`)).join('')
                : '<p class="empty-state">No tenés materias en este filtro.</p>';
        }

        gridPerfil.addEventListener('click', (e) => {
            const card = e.target.closest('[data-materia]');
            if (card) abrirMateriaPerfil(card.dataset.materia, card.dataset.id);
        });
        yearSelP.addEventListener('change', renderPerfil);
        termSelP.addEventListener('change', renderPerfil);
        alActualizarMaterias.push(renderPerfil);
        renderPerfil();
    }

    // --- Carga de materias desde la base y repintado de las grillas ---
    if (gridGlobal || gridPerfil) {
        fetch('api/materias.php')
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
            .then(lista => {
                materiasDB = lista;
                materiasCargadas = true;
                alActualizarMaterias.forEach(f => f());
            })
            .catch(() => {
                [gridGlobal, gridPerfil].forEach(g => {
                    if (g) g.innerHTML = '<p class="empty-state">No se pudieron cargar las materias. Probá recargar la página.</p>';
                });
            });
    }

    // --- 3C. PERFIL PÚBLICO (perfil.php?u=nickname) ---
    const gridUsuario = document.getElementById('trabajosUsuarioGrid');
    if (gridUsuario) {
        cargarTrabajos('api/trabajos.php?usuario=' + encodeURIComponent(document.body.dataset.nick), gridUsuario,
            'Todavía no hay trabajos publicados.', t => t.materia + ' · ' + t.ciclo);
    }

    // --- 4. NICKNAME (perfil.php, modo propio) ---
    const nickEditBtn = document.getElementById('nickEditBtn');
    if (nickEditBtn) {
        const nickText = document.getElementById('nickText');
        const nickModal = document.getElementById('nickModal');
        const nickInput = document.getElementById('nickInput');
        const nickError = document.getElementById('nickError');
        const nickSave = document.getElementById('nickSave');
        const nickCancel = document.getElementById('nickCancel');

        const abrir = () => {
            nickInput.value = nickText.textContent.replace('@', '');
            nickError.classList.remove('show');
            nickModal.style.display = 'flex';
            nickInput.focus();
        };
        const cerrar = () => { nickModal.style.display = 'none'; };
        const mostrarError = (msg) => { nickError.textContent = msg; nickError.classList.add('show'); };

        // Guarda en la base (api/nickname.php valida formato y unicidad)
        async function guardarNickname(nick) {
            try {
                const r = await fetch('api/nickname.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ nickname: nick })
                });
                return await r.json();   // { ok: true, nickname } | { ok: false, error }
            } catch (e) {
                return { ok: false, error: 'No hay conexión con el servidor.' };
            }
        }

        nickEditBtn.addEventListener('click', abrir);
        nickCancel.addEventListener('click', cerrar);
        nickModal.addEventListener('click', (e) => { if (e.target === nickModal) cerrar(); });
        nickInput.addEventListener('input', () => nickError.classList.remove('show'));

        nickSave.addEventListener('click', async () => {
            const nick = nickInput.value.trim().toLowerCase().replace(/^@/, '');
            if (!NICK_REGEX.test(nick)) {
                return mostrarError('Usá de 3 a 20 caracteres: minúsculas, números, punto o guion bajo.');
            }
            const res = await guardarNickname(nick);
            if (!res.ok) return mostrarError(res.error || 'Ese nickname ya está en uso.');
            nickText.textContent = '@' + (res.nickname || nick);
            document.body.dataset.nick = res.nickname || nick;
            cerrar();
        });
    }

    // --- 5. Subida visual de archivos ---
    document.querySelectorAll('.file-upload-input').forEach(input => {
        input.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                this.parentElement.querySelector('p').innerHTML = `Listo para subir:<br><small>${escapeHTML(this.files[0].name)}</small>`;
                const icon = this.parentElement.querySelector('i');
                icon.classList.replace('fa-cloud-arrow-up', 'fa-file-circle-check');
                icon.style.color = 'var(--blue-top)';
            }
        });
    });

    // --- 6. Modal de logout ---
    const logoutTrigger = document.getElementById('logoutTrigger');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogout = document.getElementById('cancelLogout');

    if (logoutTrigger && logoutModal && cancelLogout) {
        logoutTrigger.addEventListener('click', (e) => { e.preventDefault(); logoutModal.style.display = 'flex'; });
        cancelLogout.addEventListener('click', () => { logoutModal.style.display = 'none'; });
        logoutModal.addEventListener('click', (e) => { if (e.target === logoutModal) logoutModal.style.display = 'none'; });
    }

    // --- 8. Nota: solo números de 0 a 10 (crear.php) ---
    const gradeInput = document.querySelector('.grade-input');
    if (gradeInput) {
        gradeInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 2);
            if (Number(this.value) > 10) this.value = '10';
        });
    }
});

// ══════════════════════════════════════════════════════════════
// CAMBIO DE VISTA DENTRO DE LA MISMA PÁGINA (globales: las usan los onclick del HTML)
// ══════════════════════════════════════════════════════════════

// buscar.php
function abrirMateriaGlobal(nombre, ciclo, id) {
    document.getElementById('vista-busqueda-global').style.display = 'none';
    document.getElementById('vista-trabajos-global').style.display = 'block';
    const todos = (!ciclo || ciclo === 'todos');
    document.getElementById('tpTitleGlobal').textContent = `Trabajos: ${nombre} · ${todos ? 'Todos los ciclos' : 'Ciclo ' + ciclo}`;
    const url = `api/trabajos.php?materia=${encodeURIComponent(id)}` + (todos ? '' : `&ciclo=${encodeURIComponent(ciclo)}`);
    cargarTrabajos(url, document.getElementById('trabajosComunidadGrid'), 'Todavía no hay trabajos publicados en esta materia.', t => 'Ciclo ' + t.ciclo);
    window.scrollTo(0, 0);
}
function cerrarMateriaGlobal() {
    document.getElementById('vista-trabajos-global').style.display = 'none';
    document.getElementById('vista-busqueda-global').style.display = 'block';
}

// perfil.php (perfil propio): mis trabajos de una materia, con acceso a publicar uno nuevo
function abrirMateriaPerfil(nombre, id) {
    document.getElementById('vista-perfil-principal').style.display = 'none';
    document.getElementById('vista-trabajos').style.display = 'block';
    document.getElementById('tpTitle').textContent = `Mis trabajos: ${nombre}`;
    document.getElementById('btnAddMateria').href = 'crear.php?materia=' + encodeURIComponent(id);
    cargarTrabajos(`api/trabajos.php?materia=${encodeURIComponent(id)}&usuario=${encodeURIComponent(document.body.dataset.nick)}`,
        document.getElementById('trabajosMateriaGrid'), 'Todavía no publicaste trabajos en esta materia.', t => 'Ciclo ' + t.ciclo);
    window.scrollTo(0, 0);
}
function cerrarMateriaPerfil() {
    document.getElementById('vista-trabajos').style.display = 'none';
    document.getElementById('vista-perfil-principal').style.display = 'block';
}