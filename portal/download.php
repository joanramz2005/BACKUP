<?php
// download.php — Descarga segura de archivos desde la carpeta SMB del usuario
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/smb.php';
require_once __DIR__ . '/lib/auth.php';

auth_require();

$rel_file   = sanitize_rel_path($_GET['file'] ?? '');
$mount_info = smb_ensure_mount($_SESSION['smb_path'], $_SESSION['username'], '', $_SESSION['domain']);

if (!$mount_info['ok'] || !$rel_file) {
    http_response_code(403);
    exit('Acceso denegado o archivo no encontrado.');
}

$local_path = rtrim($mount_info['local_path'], '/\\') . DIRECTORY_SEPARATOR
              . str_replace('/', DIRECTORY_SEPARATOR, $rel_file);

// Doble comprobación: el path resuelto debe estar dentro del mount
$real_mount = realpath($mount_info['local_path']);
$real_file  = realpath($local_path);

if (!$real_file || !$real_mount || !str_starts_with($real_file, $real_mount)) {
    http_response_code(403);
    exit('Ruta no permitida.');
}

if (!is_file($real_file)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

// Enviar el archivo
$filename = basename($real_file);
$mime     = mime_content_type($real_file) ?: 'application/octet-stream';
$size     = filesize($real_file);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

readfile($real_file);
exit;
