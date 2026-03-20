<?php
// api/files.php — Lista el contenido de un directorio (GET ?dir=ruta/relativa)
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/smb.php';

auth_require();

$mount = smb_ensure_mount();
if (!$mount['ok']) {
    echo json_encode(['ok' => false, 'error' => $mount['error'], 'expired' => true]); exit;
}

$rel_dir = sanitize_rel_path($_GET['dir'] ?? '');
$base    = rtrim($mount['local_path'], '/\\');
$abs_dir = $base . ($rel_dir ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_dir) : '');

if (!@is_dir($abs_dir)) {
    echo json_encode(['ok' => false, 'error' => 'Directorio no encontrado.']); exit;
}

$entries = [];
$items   = @scandir($abs_dir) ?: [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $full = $abs_dir . DIRECTORY_SEPARATOR . $item;
    $is_dir = @is_dir($full);
    $entries[] = [
        'name'  => $item,
        'dir'   => $is_dir,
        'size'  => $is_dir ? 0 : (int)@filesize($full),
        'mtime' => (int)@filemtime($full),
        'rel'   => $rel_dir ? $rel_dir . '/' . $item : $item,
    ];
}
usort($entries, fn($a, $b) => $b['dir'] <=> $a['dir'] ?: strcasecmp($a['name'], $b['name']));

echo json_encode([
    'ok'      => true,
    'dir'     => $rel_dir,
    'entries' => $entries,
    'user'    => $_SESSION['username'],
    'smb'     => $_SESSION['smb_path'],
]);
