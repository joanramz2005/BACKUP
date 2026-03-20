// js/admin.js — Lógica del panel de administración KeepNAS

(function () {
    'use strict';

    let deleteTargetId = null;
    let editTargetId   = null;

    // ── Init ──────────────────────────────────────────────────────────────────
    loadUsers();
    loadSessionInfo();

    // Cerrar sesión
    document.getElementById('btn-logout').addEventListener('click', async () => {
        await fetch('api/logout.php', { method: 'POST' });
        window.location.href = 'login.html?logout=1';
    });

    // ── Tabs ─────────────────────────────────────────────────────────────────
    document.querySelectorAll('.sidebar-item[data-tab]').forEach(item => {
        item.addEventListener('click', () => switchTab(item.dataset.tab));
    });

    document.getElementById('btn-goto-add').addEventListener('click', () => switchTab('add'));

    function switchTab(name) {
        document.querySelectorAll('.sidebar-item[data-tab]').forEach(i => i.classList.toggle('active', i.dataset.tab === name));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + name));
    }

    // ── Cargar usuarios ───────────────────────────────────────────────────────
    async function loadUsers() {
        try {
            const res  = await fetch('api/admin.php?action=list');
            const data = await res.json();

            if (!data.ok) {
                if (res.status === 401) { window.location.href = 'login.html?timeout=1'; return; }
                if (res.status === 403) { document.body.innerHTML = '<div style="padding:40px;font-family:monospace;color:#ff4444"><h2>403 — Solo administradores</h2></div>'; return; }
                showBanner('users', 'error', data.error || 'Error al cargar usuarios.');
                return;
            }

            renderUsers(data.users);
            document.getElementById('user-count').textContent = data.users.length + ' usuario' + (data.users.length !== 1 ? 's' : '');
        } catch (err) {
            showBanner('users', 'error', 'No se pudo conectar con el servidor.');
        }
    }

    function renderUsers(users) {
        const tbody = document.getElementById('users-tbody');
        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;font-family:var(--mono);font-size:12px;color:var(--muted)">Sin usuarios registrados. Añade el primero.</td></tr>';
            return;
        }
        tbody.innerHTML = users.map(u => `
            <tr>
                <td class="mono">${escHtml(u.username)}</td>
                <td class="mono muted" style="font-size:11px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(u.smb_path)}">${escHtml(u.smb_path)}</td>
                <td><span class="pill ${u.active ? 'pill-active' : 'pill-inactive'}">${u.active ? 'Activo' : 'Inactivo'}</span></td>
                <td class="muted" style="font-size:12px">${escHtml(u.notes)}</td>
                <td class="mono muted" style="font-size:11px">${u.created.slice(0,10)}</td>
                <td>
                    <div class="td-actions">
                        <button class="btn-sm" onclick="window._ka.edit(${u.id},'${escJs(u.smb_path)}','${escJs(u.notes)}')">Editar</button>
                        <button class="btn-sm" onclick="window._ka.toggle(${u.id},${u.active})">${u.active ? 'Desactivar' : 'Activar'}</button>
                        <button class="btn-sm btn-danger" onclick="window._ka.askDelete(${u.id},'${escJs(u.username)}')">Eliminar</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // ── Cargar info de sesión ─────────────────────────────────────────────────
    async function loadSessionInfo() {
        try {
            const res  = await fetch('api/admin.php?action=session');
            const data = await res.json();
            if (data.ok) {
                document.getElementById('admin-user-label').textContent = data.username + '@' + data.domain;
            }
        } catch (_) {}
    }

    // ── Añadir / actualizar usuario ───────────────────────────────────────────
    document.getElementById('btn-save-user').addEventListener('click', async () => {
        const username = document.getElementById('add-username').value.trim().toLowerCase();
        const smb_path = document.getElementById('add-smb').value.trim();
        const notes    = document.getElementById('add-notes').value.trim();

        if (!username) { showBanner('add', 'error', 'El nombre de usuario es obligatorio.'); return; }

        try {
            const res  = await fetch('api/admin.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'save', username, smb_path, notes }),
            });
            const data = await res.json();
            if (data.ok) {
                showBanner('add', 'ok', `Usuario "${username}" guardado correctamente.`);
                document.getElementById('add-username').value = '';
                document.getElementById('add-smb').value      = '';
                document.getElementById('add-notes').value    = '';
                loadUsers();
                setTimeout(() => switchTab('users'), 1200);
            } else {
                showBanner('add', 'error', data.error || 'Error al guardar.');
            }
        } catch (err) {
            showBanner('add', 'error', 'Error de conexión.');
        }
    });

    // ── Toggle activo/inactivo ────────────────────────────────────────────────
    async function toggle(id, current) {
        try {
            const res  = await fetch('api/admin.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'toggle', id, active: current }),
            });
            const data = await res.json();
            if (data.ok) loadUsers();
            else showBanner('users', 'error', data.error || 'Error al cambiar estado.');
        } catch (err) {
            showBanner('users', 'error', 'Error de conexión.');
        }
    }

    // ── Editar ────────────────────────────────────────────────────────────────
    function openEdit(id, smb, notes) {
        editTargetId = id;
        document.getElementById('edit-id').value    = id;
        document.getElementById('edit-smb').value   = smb;
        document.getElementById('edit-notes').value = notes;
        openModal('modal-edit');
    }

    document.getElementById('btn-do-edit').addEventListener('click', async () => {
        const id    = editTargetId;
        const smb   = document.getElementById('edit-smb').value.trim();
        const notes = document.getElementById('edit-notes').value.trim();
        try {
            const res  = await fetch('api/admin.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'update', id, smb_path: smb, notes }),
            });
            const data = await res.json();
            closeModal('modal-edit');
            if (data.ok) { showBanner('users', 'ok', 'Usuario actualizado.'); loadUsers(); }
            else showBanner('users', 'error', data.error || 'Error al actualizar.');
        } catch (err) {
            showBanner('users', 'error', 'Error de conexión.');
        }
    });

    // ── Eliminar ──────────────────────────────────────────────────────────────
    function askDelete(id, username) {
        deleteTargetId = id;
        document.getElementById('delete-username').textContent = username;
        openModal('modal-delete');
    }

    document.getElementById('btn-do-delete').addEventListener('click', async () => {
        if (!deleteTargetId) return;
        try {
            const res  = await fetch('api/admin.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'delete', id: deleteTargetId }),
            });
            const data = await res.json();
            closeModal('modal-delete');
            if (data.ok) { showBanner('users', 'ok', 'Usuario eliminado.'); loadUsers(); }
            else showBanner('users', 'error', data.error || 'No se pudo eliminar.');
        } catch (err) {
            showBanner('users', 'error', 'Error de conexión.');
        }
        deleteTargetId = null;
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
    function showBanner(section, type, msg) {
        const id = section + '-banner-' + type;
        const el = document.getElementById(id);
        if (!el) return;
        document.getElementById(id + '-text').textContent = msg;
        el.classList.remove('hidden');
        setTimeout(() => el.classList.add('hidden'), 5000);
    }

    // ── Utils ─────────────────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escJs(s) {
        return String(s || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    }

    // ── API pública ───────────────────────────────────────────────────────────
    window._ka = { toggle, askDelete, edit: openEdit };

})();
