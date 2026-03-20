// js/dashboard.js — Lógica del explorador de archivos KeepNAS

(function () {
    'use strict';

    // ── Estado ────────────────────────────────────────────────────────────────
    let currentDir    = '';
    let deleteTarget  = '';
    let selectedFiles = [];

    // ── Init ──────────────────────────────────────────────────────────────────
    loadDir('');
    startClock();

    // Cerrar sesión
    document.getElementById('btn-logout').addEventListener('click', async () => {
        await fetch('api/logout.php', { method: 'POST' });
        window.location.href = 'login.html?logout=1';
    });

    // ── Cargar directorio ─────────────────────────────────────────────────────
    async function loadDir(dir) {
        currentDir = dir;
        setLoading();

        try {
            const res  = await fetch('api/files.php?dir=' + encodeURIComponent(dir));
            const data = await res.json();

            if (!data.ok) {
                if (data.expired || res.status === 401) {
                    window.location.href = 'login.html?timeout=1';
                    return;
                }
                showBanner('error', data.error || 'Error al cargar el directorio.');
                setEmpty();
                return;
            }

            updateHeader(data.user, data.smb);
            renderBreadcrumb(dir);
            renderFiles(data.entries);
            updateStats(data.entries);
        } catch (err) {
            showBanner('error', 'No se pudo conectar con el servidor.');
            setEmpty();
        }
    }

    // ── Render archivos ───────────────────────────────────────────────────────
    function renderFiles(entries) {
        const tbody = document.getElementById('file-tbody');
        if (!entries.length) {
            tbody.innerHTML = `
                <tr><td colspan="4">
                    <div class="empty-state">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        <p>Carpeta vacía. Sube tu primer archivo.</p>
                    </div>
                </td></tr>`;
            return;
        }

        tbody.innerHTML = entries.map(e => {
            const icon      = e.dir ? iconDir() : iconFile(e.name);
            const nameClass = 'fname-link' + (e.dir ? ' fname-dir' : '');
            const nameClick = e.dir
                ? `onclick="window._kn.nav('${escJs(e.rel)}')" href="#"`
                : `href="api/download.php?file=${encodeURIComponent(e.rel)}"`;

            return `
            <tr>
                <td>
                    <div class="file-name-cell">
                        <div class="ficon ${icon.cls}">${icon.html}</div>
                        <a class="${nameClass}" ${nameClick}>${escHtml(e.name)}</a>
                    </div>
                </td>
                <td class="fcell-size">${e.dir ? '—' : fmtSize(e.size)}</td>
                <td class="fcell-date">${fmtDate(e.mtime)}</td>
                <td>
                    <div class="fcell-actions">
                        ${!e.dir ? `<a class="btn-sm" href="api/download.php?file=${encodeURIComponent(e.rel)}">↓ Descargar</a>` : ''}
                        <button class="btn-sm btn-sm-danger" onclick="window._kn.askDelete('${escJs(e.rel)}','${escJs(e.name)}')">Eliminar</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Breadcrumb ────────────────────────────────────────────────────────────
    function renderBreadcrumb(dir) {
        const bc = document.getElementById('breadcrumb');
        if (!dir) {
            bc.innerHTML = '<span class="cur">Raíz</span>';
            return;
        }
        const parts = dir.split('/');
        let html = `<a href="#" onclick="window._kn.nav('');return false;">Raíz</a>`;
        let accum = '';
        parts.forEach((p, i) => {
            accum = accum ? accum + '/' + p : p;
            const isLast = i === parts.length - 1;
            html += `<span class="sep">›</span>`;
            if (isLast) html += `<span class="cur">${escHtml(p)}</span>`;
            else {
                const path = accum;
                html += `<a href="#" onclick="window._kn.nav('${escJs(path)}');return false;">${escHtml(p)}</a>`;
            }
        });
        bc.innerHTML = html;
    }

    // ── Header ────────────────────────────────────────────────────────────────
    function updateHeader(user, smb) {
        document.getElementById('user-label').textContent    = user + '@keepnas.sl';
        document.getElementById('header-username').textContent = user;
        document.title = 'KeepNAS — ' + user;
    }

    // ── Stats bar ─────────────────────────────────────────────────────────────
    function updateStats(entries) {
        const bar   = document.getElementById('statsbar');
        const text  = document.getElementById('stats-text');
        const dirs  = entries.filter(e => e.dir).length;
        const files = entries.filter(e => !e.dir);
        const total = files.reduce((s, f) => s + f.size, 0);
        let str = `${dirs} carpeta${dirs !== 1 ? 's' : ''}, ${files.length} archivo${files.length !== 1 ? 's' : ''}`;
        if (total > 0) str += ` &nbsp;·&nbsp; <span class="hi">${fmtSize(total)}</span>`;
        text.innerHTML = str;
        bar.style.display = 'flex';
    }

    // ── Loading / empty states ────────────────────────────────────────────────
    function setLoading() {
        document.getElementById('file-tbody').innerHTML =
            '<tr class="loading-row"><td colspan="4"><span class="spinner"></span>Cargando…</td></tr>';
        document.getElementById('statsbar').style.display = 'none';
        hideBanners();
    }
    function setEmpty() {
        document.getElementById('file-tbody').innerHTML =
            '<tr><td colspan="4"><div class="empty-state"><p>No se pudo cargar el contenido.</p></div></td></tr>';
    }

    // ── Upload ────────────────────────────────────────────────────────────────
    const dropZone   = document.getElementById('drop-zone');
    const fileInput  = document.getElementById('file-input');
    const uploadList = document.getElementById('upload-list');
    const doUpload   = document.getElementById('btn-do-upload');

    document.getElementById('btn-upload').addEventListener('click', () => openModal('modal-upload'));
    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => renderFileList(fileInput.files));

    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('over'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('over');
        renderFileList(e.dataTransfer.files);
    });

    function renderFileList(files) {
        selectedFiles = Array.from(files);
        doUpload.disabled = !selectedFiles.length;
        uploadList.innerHTML = selectedFiles.map(f =>
            `<div class="upload-item">
                <span class="upload-item-name">${escHtml(f.name)}</span>
                <span class="upload-item-size">${fmtSize(f.size)}</span>
             </div>`
        ).join('');
    }

    doUpload.addEventListener('click', async () => {
        if (!selectedFiles.length) return;
        doUpload.disabled = true;
        doUpload.textContent = 'Subiendo…';

        const fd = new FormData();
        fd.append('dir', currentDir);
        selectedFiles.forEach(f => fd.append('files[]', f));

        try {
            const res  = await fetch('api/upload.php', { method: 'POST', body: fd });
            const data = await res.json();
            closeModal('modal-upload');
            if (data.ok || data.uploaded.length) {
                showBanner('ok', `${data.uploaded.length} archivo(s) subido(s) correctamente.`);
            }
            if (data.errors.length) {
                showBanner('error', 'Errores: ' + data.errors.join('; '));
            }
            loadDir(currentDir);
        } catch (err) {
            showBanner('error', 'Error durante la subida.');
        }

        doUpload.disabled = false;
        doUpload.textContent = 'Subir archivos';
        selectedFiles = [];
        uploadList.innerHTML = '';
        fileInput.value = '';
    });

    // ── Mkdir ─────────────────────────────────────────────────────────────────
    document.getElementById('btn-mkdir').addEventListener('click', () => {
        document.getElementById('mkdir-name').value = '';
        openModal('modal-mkdir');
        setTimeout(() => document.getElementById('mkdir-name').focus(), 100);
    });

    document.getElementById('btn-do-mkdir').addEventListener('click', async () => {
        const name = document.getElementById('mkdir-name').value.trim();
        if (!name) return;
        try {
            const res  = await fetch('api/mkdir.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dir: currentDir, name }),
            });
            const data = await res.json();
            closeModal('modal-mkdir');
            if (data.ok) {
                showBanner('ok', `Carpeta "${name}" creada.`);
                loadDir(currentDir);
            } else {
                showBanner('error', data.error || 'No se pudo crear la carpeta.');
            }
        } catch (err) {
            showBanner('error', 'Error al crear la carpeta.');
        }
    });

    document.getElementById('mkdir-name').addEventListener('keydown', e => {
        if (e.key === 'Enter') document.getElementById('btn-do-mkdir').click();
    });

    // ── Delete ────────────────────────────────────────────────────────────────
    function askDelete(rel, name) {
        deleteTarget = rel;
        document.getElementById('delete-target-name').textContent = name;
        openModal('modal-delete');
    }

    document.getElementById('btn-do-delete').addEventListener('click', async () => {
        if (!deleteTarget) return;
        try {
            const res  = await fetch('api/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file: deleteTarget }),
            });
            const data = await res.json();
            closeModal('modal-delete');
            if (data.ok) {
                showBanner('ok', 'Elemento eliminado.');
                loadDir(currentDir);
            } else {
                showBanner('error', data.error || 'No se pudo eliminar.');
            }
        } catch (err) {
            showBanner('error', 'Error al eliminar.');
        }
        deleteTarget = '';
    });

    // ── Modals ────────────────────────────────────────────────────────────────
    function openModal(id)  { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });
    document.querySelectorAll('.modal-backdrop').forEach(bd => {
        bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
    });

    // ── Banners ───────────────────────────────────────────────────────────────
    function showBanner(type, msg) {
        hideBanners();
        const id   = type === 'ok' ? 'banner-ok' : 'banner-error';
        const txtId = id + '-text';
        document.getElementById(txtId).textContent = msg;
        document.getElementById(id).classList.remove('hidden');
        setTimeout(() => document.getElementById(id).classList.add('hidden'), 5000);
    }
    function hideBanners() {
        ['banner-ok','banner-error'].forEach(id => document.getElementById(id).classList.add('hidden'));
    }

    // ── Clock ─────────────────────────────────────────────────────────────────
    function startClock() {
        const el = document.getElementById('session-time');
        function tick() { el.textContent = new Date().toLocaleTimeString('es-ES'); }
        tick();
        setInterval(tick, 1000);
    }

    // ── Utils ─────────────────────────────────────────────────────────────────
    function fmtSize(b) {
        if (b < 1024)       return b + ' B';
        if (b < 1048576)    return (b / 1024).toFixed(1) + ' KB';
        if (b < 1073741824) return (b / 1048576).toFixed(1) + ' MB';
        return (b / 1073741824).toFixed(2) + ' GB';
    }

    function fmtDate(ts) {
        const d = new Date(ts * 1000);
        return d.toLocaleDateString('es-ES') + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escJs(s) {
        return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    }

    function iconDir() { return { cls: 'ficon-dir', html: '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>' }; }
    function iconFile(name) {
        const ext = (name.split('.').pop() || '').toLowerCase();
        if (['jpg','jpeg','png','gif','webp','svg','bmp'].includes(ext))      return { cls: 'ficon-img',  html: 'IMG' };
        if (['mp4','mkv','avi','mov','wmv','webm'].includes(ext))             return { cls: 'ficon-vid',  html: 'VID' };
        if (['mp3','wav','flac','ogg','aac'].includes(ext))                   return { cls: 'ficon-aud',  html: 'AUD' };
        if (ext === 'pdf')                                                     return { cls: 'ficon-pdf',  html: 'PDF' };
        if (['zip','rar','7z','tar','gz','bz2'].includes(ext))                return { cls: 'ficon-arc',  html: 'ZIP' };
        if (['doc','docx','odt'].includes(ext))                               return { cls: 'ficon-doc',  html: 'DOC' };
        if (['xls','xlsx','ods','csv'].includes(ext))                         return { cls: 'ficon-xls',  html: 'XLS' };
        if (['txt','log','md','json','xml','conf','sh','bat','php','js'].includes(ext)) return { cls: 'ficon-txt', html: 'TXT' };
        return { cls: 'ficon-file', html: ext.toUpperCase().slice(0,3) || '···' };
    }

    // ── API pública para los onclick del HTML generado ────────────────────────
    window._kn = {
        nav:       (dir)        => loadDir(dir),
        askDelete: (rel, name)  => askDelete(rel, name),
    };

})();
