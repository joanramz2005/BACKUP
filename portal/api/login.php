<?php
// api/login.php — Endpoint de autenticación (POST, devuelve JSON)
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/smb.php';

vpn_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']); exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(['ok' => false, 'error' => 'Introduce usuario y contraseña.']); exit;
}

$username = preg_replace('/^[^\\\\]+\\\\/', '', $username); // strip DOMAIN\ prefix
$smb_path = get_smb_path_for_user($username);

if (!$smb_path) {
    echo json_encode(['ok' => false, 'error' => 'No hay carpeta asignada para este usuario.']); exit;
}

$result = smb_mount($smb_path, $username, $password, AD_DOMAIN);

if ($result['ok']) {
    $_SESSION['authenticated'] = true;
    $_SESSION['username']      = $username;
    $_SESSION['domain']        = AD_DOMAIN;
    $_SESSION['smb_path']      = $smb_path;
    $_SESSION['login_time']    = time();
    $_SESSION['last_activity'] = time();
    echo json_encode(['ok' => true]);
} else {
    $msg = $result['auth_failed']
        ? 'Usuario o contraseña incorrectos.'
        : 'No se pudo acceder a la carpeta: ' . $result['error'];
    echo json_encode(['ok' => false, 'error' => $msg]);
}
