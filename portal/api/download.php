<?php
// api/download.php — Descarga segura de archivo
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/smb.php';

auth_require_html(); // redirige a login.html si no hay sesión

$rel = sanitize_rel_path($_GET['file'] ?? '');
$mount = smb_ensure_mount();
if (!$mount['ok'] || !$rel) { http_response_code(403); exit('Acceso denegado.'); }

$base      = rtrim($mount['local_path'], '/\\');
$full      = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
$real_base = realpath($base);
$real_file = realpath($full);

if (!$real_file || !$real_base || !str_starts_with($real_file, $real_base) || !is_file($real_file)) {
    http_response_code(404); exit('Archivo no encontrado.');
}

$mime = mime_content_type($real_file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($real_file)) . '"');
header('Content-Length: ' . filesize($real_file));
header('Cache-Control: no-store');
readfile($real_file);
exit;

// Helper: para download redirigimos a HTML en vez de devolver JSON
function auth_require_html(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!str_starts_with($ip, '10.10.0.')) { http_response_code(403); exit('VPN requerida.'); }
    if (empty($_SESSION['authenticated'])) { header('Location: /login.html'); exit; }
}
