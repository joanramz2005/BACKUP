// js/login.js — Lógica del formulario de login

(function () {
    'use strict';

    // ── Mostrar alertas según URL params ──────────────────────────────────────
    const params = new URLSearchParams(window.location.search);
    if (params.has('timeout')) show('alert-timeout');
    if (params.has('logout'))  show('alert-logout');

    // ── Form submit ───────────────────────────────────────────────────────────
    document.getElementById('login-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const btn      = document.getElementById('submit-btn');

        if (!username || !password) {
            showError('Introduce usuario y contraseña.');
            return;
        }

        // Loading state
        btn.disabled = true;
        btn.classList.add('loading');
        btn.textContent = 'Verificando…';
        hideAll();

        try {
            const res  = await fetch('api/login.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ username, password }),
            });

            const data = await res.json();

            if (data.ok) {
                btn.textContent = '¡Acceso concedido!';
                // Pequeña pausa visual antes de redirigir
                setTimeout(() => { window.location.href = 'dashboard.html'; }, 400);
            } else {
                showError(data.error || 'Error desconocido.');
                resetBtn(btn);
            }
        } catch (err) {
            showError('No se pudo contactar con el servidor. Comprueba la VPN.');
            resetBtn(btn);
        }
    });

    // ── Helpers ───────────────────────────────────────────────────────────────
    function show(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
    }

    function hideAll() {
        ['alert-timeout', 'alert-logout', 'alert-error'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        });
    }

    function showError(msg) {
        hideAll();
        const el   = document.getElementById('alert-error');
        const text = document.getElementById('alert-error-text');
        text.textContent = msg;
        el.classList.remove('hidden');
        // Trigger shake animation
        el.classList.remove('shake');
        void el.offsetWidth; // reflow
        el.classList.add('shake');
    }

    function resetBtn(btn) {
        btn.disabled = false;
        btn.classList.remove('loading');
        btn.textContent = 'Acceder →';
    }
})();
