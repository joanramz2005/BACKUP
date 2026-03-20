<?php
// api/admin.php — API REST del panel de administración
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/auth.php';

// Solo acceso autenticado + solo admins
auth_require();

$admin_users = ['administrador', 'admin', 'sysadmin']; // ajustar según el dominio
if (!in_array(strtolower($_SESSION['username']), $admin_users)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solo administradores.']);
    exit;
}

// ── DB ────────────────────────────────────────────────────────────────────────
function get_db(): PDO {
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $db = new PDO('sqlite:' . USERS_DB);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        username  TEXT NOT NULL UNIQUE COLLATE NOCASE,
        smb_path  TEXT NOT NULL DEFAULT '',
        active    INTEGER NOT NULL DEFAULT 1,
        notes     TEXT NOT NULL DEFAULT '',
        created   TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    return $db;
}

// ── Routing ───────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'list') {
        $db    = get_db();
        $users = $db->query("SELECT * FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'users' => $users]);
        exit;
    }
    if ($action === 'session') {
        echo json_encode(['ok' => true, 'username' => $_SESSION['username'], 'domain' => $_SESSION['domain']]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $db     = get_db();

    if ($action === 'save') {
        $username = strtolower(trim($body['username'] ?? ''));
        $smb_path = trim($body['smb_path'] ?? '');
        $notes    = trim($body['notes'] ?? '');
        if (!$username) { echo json_encode(['ok' => false, 'error' => 'Usuario requerido.']); exit; }
        // Si no hay ruta SMB se guarda vacía → se usará la convención al login
        $db->prepare("INSERT INTO users (username, smb_path, active, notes)
                      VALUES (:u, :p, 1, :n)
                      ON CONFLICT(username) DO UPDATE SET smb_path=excluded.smb_path, notes=excluded.notes, active=1")
           ->execute([':u' => $username, ':p' => $smb_path, ':n' => $notes]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'toggle') {
        $id     = (int)($body['id'] ?? 0);
        $active = (int)($body['active'] ?? 0);
        $db->prepare("UPDATE users SET active = :a WHERE id = :id")
           ->execute([':a' => $active ? 0 : 1, ':id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'update') {
        $id    = (int)($body['id'] ?? 0);
        $smb   = trim($body['smb_path'] ?? '');
        $notes = trim($body['notes'] ?? '');
        $db->prepare("UPDATE users SET smb_path = :p, notes = :n WHERE id = :id")
           ->execute([':p' => $smb, ':n' => $notes, ':id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        $db->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
