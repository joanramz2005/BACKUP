<?php
// admin.php — Panel de administración (solo accesible por administradores de dominio)
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

auth_require();

// Solo admins (lista de usuarios en config o grupo AD)
$admin_users = ['administrador', 'admin', 'sysadmin']; // ajustar según dominio
if (!in_array(strtolower($_SESSION['username']), $admin_users)) {
    http_response_code(403);
    echo '<h1>Acceso denegado</h1><p>Esta sección es solo para administradores.</p>';
    exit;
}

// Inicializar SQLite DB
function get_db(): PDO {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $db = new PDO('sqlite:' . USERS_DB);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE COLLATE NOCASE,
        smb_path TEXT NOT NULL,
        active   INTEGER NOT NULL DEFAULT 1,
        notes    TEXT DEFAULT '',
        created  TEXT DEFAULT (datetime('now'))
    )");
    return $db;
}

$db  = get_db();
$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $uname = strtolower(trim($_POST['username'] ?? ''));
        $path  = trim($_POST['smb_path'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($uname && $path) {
            try {
                $db->prepare("INSERT OR REPLACE INTO users (username,smb_path,active,notes) VALUES (?,?,1,?)")
                   ->execute([$uname, $path, $notes]);
                $msg = "✓ Usuario <strong>" . htmlspecialchars($uname) . "</strong> guardado.";
            } catch (Exception $e) {
                $msg = "Error: " . htmlspecialchars($e->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        $id     = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['active'] ?? 0);
        $db->prepare("UPDATE users SET active=? WHERE id=?")->execute([$active ? 0 : 1, $id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Location: admin.php' . ($msg ? '?msg=' . urlencode($msg) : ''));
        exit;
    }
}
if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

$users = $db->query("SELECT * FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$nas_host   = NAS_HOST;
$share_base = SHARE_BASE;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KeepNAS — Administración</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --bg:#0a0c0f;--surface:#111418;--surface2:#161b22;
            --border:#1e2530;--border-hi:#2e3d50;
            --accent:#00c8ff;--accent2:#0077ff;
            --success:#22c55e;--danger:#ff4444;--warn:#f59e0b;
            --text:#d0dce8;--muted:#5a6a7a;
            --mono:'IBM Plex Mono',monospace;--sans:'IBM Plex Sans',sans-serif;
        }
        html,body{min-height:100%;background:var(--bg);color:var(--text);font-family:var(--sans);}

        .header{
            position:sticky;top:0;height:56px;
            background:rgba(10,12,15,.95);border-bottom:1px solid var(--border);
            display:flex;align-items:center;padding:0 28px;gap:16px;z-index:50;
            backdrop-filter:blur(12px);
        }
        .brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
        .brand-icon{width:32px;height:32px;border:1.5px solid var(--accent);border-radius:5px;display:flex;align-items:center;justify-content:center;font-family:var(--mono);font-size:12px;font-weight:600;color:var(--accent);}
        .brand-name{font-family:var(--mono);font-size:14px;font-weight:600;letter-spacing:2px;color:#fff;}
        .badge{padding:3px 10px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:2px;font-family:var(--mono);font-size:10px;color:var(--warn);letter-spacing:1px;}
        .spacer{flex:1;}
        .btn-sm{padding:6px 14px;border-radius:3px;font-family:var(--mono);font-size:11px;letter-spacing:1px;cursor:pointer;text-decoration:none;border:1px solid var(--border);background:transparent;color:var(--muted);transition:all .15s;}
        .btn-sm:hover{border-color:var(--border-hi);color:var(--text);}

        .container{max-width:960px;margin:0 auto;padding:32px 24px;}
        h2{font-family:var(--mono);font-size:13px;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin-bottom:20px;}

        .card{background:var(--surface);border:1px solid var(--border);border-radius:4px;margin-bottom:28px;overflow:hidden;}
        .card-head{padding:14px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
        .card-head-title{font-family:var(--mono);font-size:12px;font-weight:600;letter-spacing:1px;color:var(--text);}
        .card-body{padding:22px;}

        .form-grid{display:grid;grid-template-columns:1fr 2fr 1fr;gap:12px;align-items:end;}
        .field-label{font-family:var(--mono);font-size:10px;letter-spacing:1.5px;color:var(--muted);text-transform:uppercase;margin-bottom:6px;}
        .field-input{width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border);border-radius:3px;color:var(--text);font-family:var(--mono);font-size:12px;outline:none;transition:border-color .2s;}
        .field-input:focus{border-color:var(--accent);}
        .btn-add{padding:9px 20px;background:linear-gradient(135deg,var(--accent2),var(--accent));border:none;border-radius:3px;color:#fff;font-family:var(--mono);font-size:11px;font-weight:600;letter-spacing:1.5px;cursor:pointer;box-shadow:0 2px 12px rgba(0,119,255,.3);transition:all .15s;width:100%;}
        .btn-add:hover{box-shadow:0 2px 18px rgba(0,200,255,.4);}

        .msg-box{padding:10px 16px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:3px;font-size:13px;color:#4ade80;margin-bottom:18px;}

        table{width:100%;border-collapse:collapse;font-size:13px;}
        thead tr{border-bottom:1px solid var(--border);}
        th{padding:10px 12px;text-align:left;font-family:var(--mono);font-size:10px;letter-spacing:1.5px;color:var(--muted);text-transform:uppercase;font-weight:400;}
        tbody tr{border-bottom:1px solid rgba(30,37,48,.6);transition:background .1s;}
        tbody tr:hover{background:rgba(0,200,255,.03);}
        td{padding:10px 12px;vertical-align:middle;}
        .mono{font-family:var(--mono);font-size:12px;}
        .pill{display:inline-block;padding:2px 8px;border-radius:2px;font-family:var(--mono);font-size:10px;font-weight:600;}
        .pill-active{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;}
        .pill-inactive{background:rgba(100,100,100,.1);border:1px solid var(--border);color:var(--muted);}
        .act-btns{display:flex;gap:6px;}
        .abn{padding:4px 10px;border-radius:2px;font-family:var(--mono);font-size:10px;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--muted);transition:all .15s;}
        .abn:hover{border-color:var(--border-hi);color:var(--text);}
        .abn-del:hover{border-color:var(--danger);color:var(--danger);}

        .hint-box{padding:14px 18px;background:rgba(0,200,255,.04);border:1px solid rgba(0,200,255,.15);border-radius:3px;font-family:var(--mono);font-size:11px;color:var(--muted);line-height:1.8;}
        .hint-box code{color:var(--accent);background:rgba(0,200,255,.07);padding:1px 5px;border-radius:2px;}
    </style>
</head>
<body>
<header class="header">
    <a class="brand" href="dashboard.php">
        <div class="brand-icon">KN</div>
        <span class="brand-name">KEEPNAS</span>
    </a>
    <span class="badge">ADMIN</span>
    <div class="spacer"></div>
    <a class="btn-sm" href="dashboard.php">← Volver al portal</a>
    <a class="btn-sm" href="logout.php">Cerrar sesión</a>
</header>

<div class="container">
    <h2>Gestión de usuarios y carpetas SMB</h2>

    <?php if ($msg): ?>
    <div class="msg-box"><?= $msg ?></div>
    <?php endif; ?>

    <!-- ADD USER FORM -->
    <div class="card">
        <div class="card-head">
            <span class="card-head-title">AÑADIR / ACTUALIZAR USUARIO</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div>
                        <div class="field-label">Usuario (dominio)</div>
                        <input class="field-input" type="text" name="username" placeholder="cliente01" required>
                    </div>
                    <div>
                        <div class="field-label">Ruta SMB (UNC)</div>
                        <input class="field-input" type="text" name="smb_path"
                               placeholder="\\<?= htmlspecialchars($nas_host) ?>\<?= htmlspecialchars($share_base) ?>\cliente01" required>
                    </div>
                    <div>
                        <div class="field-label">Notas (opcional)</div>
                        <input class="field-input" type="text" name="notes" placeholder="Empresa X">
                    </div>
                </div>
                <div style="margin-top:14px;display:flex;gap:12px;align-items:center;">
                    <button type="submit" class="btn-add" style="width:auto;padding:9px 28px;">Guardar usuario</button>
                    <span style="font-family:var(--mono);font-size:11px;color:var(--muted);">Si el usuario ya existe, se actualizará su ruta.</span>
                </div>
            </form>

            <div class="hint-box" style="margin-top:18px;">
                <strong style="color:var(--text);">Ruta SMB por convención:</strong>
                <code>\\<?= htmlspecialchars($nas_host) ?>\<?= htmlspecialchars($share_base) ?>\{usuario}</code><br>
                Si no se añade el usuario aquí, el portal usará esa ruta automáticamente.
                Para rutas personalizadas (shares distintos, subcarpetas), regístralas en esta tabla.
            </div>
        </div>
    </div>

    <!-- USERS TABLE -->
    <div class="card">
        <div class="card-head">
            <span class="card-head-title">USUARIOS REGISTRADOS (<?= count($users) ?>)</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($users)): ?>
            <div style="padding:40px;text-align:center;font-family:var(--mono);font-size:12px;color:var(--muted);">
                Sin usuarios registrados. Usa el formulario de arriba para añadir.
            </div>
            <?php else: ?>
            <table>
                <thead><tr>
                    <th>Usuario</th>
                    <th>Ruta SMB</th>
                    <th>Estado</th>
                    <th>Notas</th>
                    <th>Creado</th>
                    <th style="text-align:right">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="mono"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="mono" style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($u['smb_path']) ?></td>
                    <td><span class="pill <?= $u['active'] ? 'pill-active' : 'pill-inactive' ?>"><?= $u['active'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($u['notes']) ?></td>
                    <td class="mono" style="font-size:11px;color:var(--muted)"><?= htmlspecialchars(substr($u['created'],0,10)) ?></td>
                    <td>
                        <div class="act-btns" style="justify-content:flex-end">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="active" value="<?= $u['active'] ?>">
                                <button class="abn" type="submit"><?= $u['active'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar usuario <?= htmlspecialchars(addslashes($u['username'])) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button class="abn abn-del" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
