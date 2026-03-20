<?php
// mkdir.php — Crear nueva carpeta dentro de la carpeta SMB del usuario
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/smb.php';
require_once __DIR__ . '/lib/auth.php';

auth_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }

$rel_dir     = sanitize_rel_path($_POST['dir'] ?? '');
$folder_name = sanitize_rel_path($_POST['folder_name'] ?? '');
$mount_info  = smb_ensure_mount($_SESSION['smb_path'], $_SESSION['username'], '', $_SESSION['domain']);

$redirect = 'dashboard.php' . ($rel_dir ? '?dir=' . urlencode($rel_dir) : '');

if (!$mount_info['ok'] || !$folder_name) { header('Location: ' . $redirect); exit; }

$base     = rtrim($mount_info['local_path'], '/\\');
$dest_dir = $base . ($rel_dir ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_dir) : '');
$new_dir  = $dest_dir . DIRECTORY_SEPARATOR . $folder_name;

if (!is_dir($new_dir)) {
    mkdir($new_dir, 0775, true);
}

header('Location: ' . $redirect);
exit;
