<?php
// delete.php — Eliminar archivo o carpeta
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/smb.php';
require_once __DIR__ . '/lib/auth.php';

auth_require();

$rel_file   = sanitize_rel_path($_GET['file'] ?? '');
$mount_info = smb_ensure_mount($_SESSION['smb_path'], $_SESSION['username'], '', $_SESSION['domain']);

if (!$mount_info['ok'] || !$rel_file) { header('Location: dashboard.php'); exit; }

$base      = rtrim($mount_info['local_path'], '/\\');
$real_base = realpath($base);
$full      = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_file);
$real_full = realpath($full);

if (!$real_full || !$real_base || !str_starts_with($real_full, $real_base)) {
    header('Location: dashboard.php?error=path_denied');
    exit;
}

$parent_rel = sanitize_rel_path(dirname($rel_file));
$redirect   = 'dashboard.php' . ($parent_rel ? '?dir=' . urlencode($parent_rel) : '');

if (is_file($real_full)) {
    unlink($real_full);
} elseif (is_dir($real_full)) {
    // Only delete empty directories for safety
    $items = array_diff(scandir($real_full), ['.','..']);
    if (empty($items)) {
        rmdir($real_full);
    } else {
        header('Location: ' . $redirect . (str_contains($redirect,'?')?'&':'?') . 'error=dir_not_empty');
        exit;
    }
}

header('Location: ' . $redirect);
exit;
