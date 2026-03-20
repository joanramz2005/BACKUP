<?php
// api/delete.php — Eliminar archivo o carpeta vacía (POST JSON, devuelve JSON)
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/smb.php';

auth_require();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$rel  = sanitize_rel_path($data['file'] ?? '');

$mount = smb_ensure_mount();
if (!$mount['ok'] || !$rel) { echo json_encode(['ok'=>false,'error'=>'Error de acceso.']); exit; }

$base      = rtrim($mount['local_path'], '/\\');
$real_base = realpath($base);
$full      = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
$real_full = realpath($full);

if (!$real_full || !str_starts_with($real_full, $real_base)) {
    echo json_encode(['ok'=>false,'error'=>'Ruta no permitida.']); exit;
}

if (is_file($real_full)) {
    unlink($real_full);
    echo json_encode(['ok'=>true]);
} elseif (is_dir($real_full)) {
    $items = array_diff(scandir($real_full), ['.','..']);
    if (empty($items)) { rmdir($real_full); echo json_encode(['ok'=>true]); }
    else echo json_encode(['ok'=>false,'error'=>'La carpeta no está vacía.']);
} else {
    echo json_encode(['ok'=>false,'error'=>'Elemento no encontrado.']);
}
