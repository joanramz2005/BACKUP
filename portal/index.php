<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/smb.php';

// Ya autenticado → dashboard
if (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Restricción VPN
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!str_starts_with($ip, '10.10.0.')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:monospace;background:#0a0c0f;color:#ff4444;padding:40px">
          <h2>403 — Acceso denegado</h2><p>Conéctate primero a la VPN WireGuard.</p></body></html>';
    exit;
}

$error   = '';
$timeout = isset($_GET['timeout']);
$logout  = isset($_GET['logout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Introduce usuario y contraseña.';
    } else {
        // Limpiar prefijo de dominio si el usuario escribe KEEPNAS\usuario
        $username_clean = preg_replace('/^[^\\\\]+\\\\/', '', $username);

        // Obtener la ruta SMB asignada a este usuario
        $smb_path = get_smb_path_for_user($username_clean);

        if (!$smb_path) {
            $error = 'No se encontró carpeta asignada para este usuario. Contacta con el administrador.';
        } else {
            // AUTENTICACIÓN: intentar montar el SMB con las credenciales del usuario.
            // Windows valida usuario+contraseña contra el dominio al ejecutar net use.
            // Si el montaje tiene éxito → credenciales correctas + permisos OK.
            $result = smb_mount($smb_path, $username_clean, $password, AD_DOMAIN);

            if ($result['ok']) {
                $_SESSION['authenticated'] = true;
                $_SESSION['username']      = $username_clean;
                $_SESSION['domain']        = AD_DOMAIN;
                $_SESSION['smb_path']      = $smb_path;
                $_SESSION['login_time']    = time();
                $_SESSION['last_activity'] = time();
                // No almacenamos la contraseña en sesión

                header('Location: dashboard.php');
                exit;
            } else {
                if ($result['auth_failed']) {
                    $error = 'Usuario o contraseña incorrectos.';
                } else {
                    $error = 'No se pudo acceder a la carpeta. ' . htmlspecialchars($result['error']);
                }
            }
        }
    }
}

/**
 * Obtiene la ruta SMB del usuario: primero consulta la BD SQLite,
 * luego usa la convención \\NAS_HOST\SHARE_BASE\{usuario}.
 */
function get_smb_path_for_user(string $username): string|false {
    if (file_exists(USERS_DB)) {
        try {
            $db   = new PDO('sqlite:' . USERS_DB);
            $stmt = $db->prepare('SELECT smb_path FROM users WHERE username = :u AND active = 1 LIMIT 1');
            $stmt->execute([':u' => strtolower($username)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['smb_path'])) return $row['smb_path'];
        } catch (Exception $e) { /* fall through */ }
    }
    // Convención: \\truenas\clients\{usuario}
    return '\\\\' . NAS_HOST . '\\' . SHARE_BASE . '\\' . $username;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KeepNAS — Acceso Seguro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:      #0a0c0f;
            --surface: #111418;
            --border:  #1e2530;
            --accent:  #00c8ff;
            --accent2: #0077ff;
            --danger:  #ff4444;
            --success: #22c55e;
            --text:    #d0dce8;
            --muted:   #5a6a7a;
            --mono:    'IBM Plex Mono', monospace;
            --sans:    'IBM Plex Sans', sans-serif;
        }
        html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); overflow: hidden; }

        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 40px 40px;
            opacity: 0.45;
            animation: gridPan 60s linear infinite;
        }
        @keyframes gridPan { to { background-position: 40px 40px; } }

        body::after {
            content: '';
            position: fixed; top: -200px; left: 50%; transform: translateX(-50%);
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(0,200,255,0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        .wrap {
            position: relative; z-index: 10;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            min-height: 100vh; padding: 2rem; gap: 2rem;
        }

        /* Brand */
        .brand { text-align: center; animation: fadeDown .6s ease both; }
        .brand-logo { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 8px; }
        .brand-icon {
            width: 44px; height: 44px;
            border: 2px solid var(--accent); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-family: var(--mono); font-size: 18px; font-weight: 600; color: var(--accent);
            box-shadow: 0 0 20px rgba(0,200,255,.2), inset 0 0 10px rgba(0,200,255,.05);
        }
        .brand-name { font-family: var(--mono); font-size: 22px; font-weight: 600; letter-spacing: 3px; color: #fff; }
        .brand-tag  { font-family: var(--mono); font-size: 11px; letter-spacing: 2px; color: var(--muted); text-transform: uppercase; }

        /* Card */
        .card {
            width: 100%; max-width: 420px;
            background: var(--surface); border: 1px solid var(--border); border-radius: 4px;
            overflow: hidden; animation: fadeUp .6s ease .1s both;
            box-shadow: 0 0 60px rgba(0,0,0,.5), 0 0 0 1px rgba(0,200,255,.05);
        }
        .card-head {
            padding: 16px 28px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
            background: linear-gradient(90deg, rgba(0,200,255,.04), transparent);
        }
        .head-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--accent); box-shadow: 0 0 8px var(--accent);
            animation: blink 2s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
        .head-title { font-family: var(--mono); font-size: 11px; letter-spacing: 2px; color: var(--muted); text-transform: uppercase; }

        .card-body { padding: 30px 28px; }

        /* Alerts */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 11px 14px; border-radius: 3px; margin-bottom: 18px;
            font-size: 13px; animation: shake .4s ease;
        }
        .alert-error { background: rgba(255,68,68,.08); border: 1px solid rgba(255,68,68,.3); color: #ff8888; }
        .alert-info  { background: rgba(0,200,255,.07); border: 1px solid rgba(0,200,255,.25); color: var(--accent); }
        .alert-ok    { background: rgba(34,197,94,.08); border: 1px solid rgba(34,197,94,.25); color: #4ade80; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-5px)} 40%{transform:translateX(5px)} 60%{transform:translateX(-3px)} 80%{transform:translateX(3px)} }

        /* Fields */
        .field { margin-bottom: 18px; }
        .field label { display: block; font-family: var(--mono); font-size: 10px; letter-spacing: 1.5px; color: var(--muted); text-transform: uppercase; margin-bottom: 7px; }
        .inp-wrap { position: relative; }
        .inp-wrap svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; transition: color .2s; }
        .inp-wrap:focus-within svg { color: var(--accent); }
        .field input {
            width: 100%; padding: 11px 13px 11px 40px;
            background: var(--bg); border: 1px solid var(--border); border-radius: 3px;
            color: var(--text); font-family: var(--mono); font-size: 13px; outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .field input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,200,255,.1); }
        .hint { margin-top: 5px; font-family: var(--mono); font-size: 10px; color: var(--muted); }

        /* Submit */
        .btn-submit {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, var(--accent2), var(--accent));
            border: none; border-radius: 3px; color: #fff;
            font-family: var(--mono); font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
            cursor: pointer; transition: opacity .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 20px rgba(0,119,255,.3);
            position: relative; overflow: hidden;
        }
        .btn-submit:hover  { opacity: .9; box-shadow: 0 4px 28px rgba(0,200,255,.4); }
        .btn-submit:active { transform: scale(.98); }
        .btn-submit.loading { pointer-events: none; opacity: .7; }
        .btn-submit.loading::after {
            content: '';
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
            border-radius: 50%; animation: spin .6s linear infinite;
        }
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        /* Footer */
        .card-foot {
            padding: 12px 28px; border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 8px;
        }
        .vpn-ok { display: flex; align-items: center; gap: 6px; font-family: var(--mono); font-size: 10px; color: var(--muted); }
        .vpn-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--success); box-shadow: 0 0 6px var(--success); }
        .wg-badge {
            margin-left: auto; padding: 3px 8px;
            background: rgba(0,200,255,.07); border: 1px solid rgba(0,200,255,.2);
            border-radius: 2px; font-family: var(--mono); font-size: 10px; color: var(--accent); letter-spacing: 1px;
        }

        /* Info box */
        .info-box {
            margin-top: 16px; padding: 12px 14px;
            background: rgba(0,200,255,.04); border: 1px solid rgba(0,200,255,.12); border-radius: 3px;
            font-family: var(--mono); font-size: 11px; color: var(--muted); line-height: 1.7;
        }
        .info-box strong { color: var(--text); }

        .scanline {
            position: fixed; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0; animation: scan 8s ease-in-out infinite;
        }
        @keyframes scan { 0%{top:0;opacity:0} 5%{opacity:.5} 95%{opacity:.5} 100%{top:100vh;opacity:0} }
        @keyframes fadeDown { from{opacity:0;transform:translateY(-14px)} to{opacity:1;transform:none} }
        @keyframes fadeUp   { from{opacity:0;transform:translateY(14px)}  to{opacity:1;transform:none} }
    </style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">

    <div class="brand">
        <div class="brand-logo">
            <div class="brand-icon">KN</div>
            <span class="brand-name">KEEPNAS</span>
        </div>
        <div class="brand-tag">Portal de Acceso Seguro &nbsp;·&nbsp; keepnas.sl</div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="head-dot"></div>
            <span class="head-title">Autenticación de dominio</span>
        </div>

        <div class="card-body">

            <?php if ($timeout): ?>
            <div class="alert alert-info">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Sesión expirada por inactividad. Vuelve a iniciar sesión.
            </div>
            <?php elseif ($logout): ?>
            <div class="alert alert-ok">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
                Sesión cerrada correctamente.
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert-error">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="field">
                    <label for="username">Usuario</label>
                    <div class="inp-wrap">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="username" name="username" placeholder="usuario" required autofocus
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="hint">// sin prefijo de dominio</div>
                </div>

                <div class="field">
                    <label for="password">Contraseña</label>
                    <div class="inp-wrap">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Acceder &nbsp;→
                </button>
            </form>

            <div class="info-box">
                <strong>Cómo funciona:</strong><br>
                Tus credenciales se verifican directamente contra el dominio
                <strong>keepnas.sl</strong> al acceder a tu carpeta. No se almacena
                ninguna contraseña en el servidor web.
            </div>
        </div>

        <div class="card-foot">
            <div class="vpn-ok">
                <div class="vpn-dot"></div>
                VPN activa &nbsp;·&nbsp; <?= htmlspecialchars($ip) ?>
            </div>
            <div class="wg-badge">WireGuard</div>
        </div>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.textContent = 'Verificando…';
});
</script>
</body>
</html>
