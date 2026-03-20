<?php
// api/mkdir.php — Crear carpeta (POST JSON, devuelve JSON)
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/smb.php';

auth_require();

$data        = json_decode(file_get_contents('php://input'), true) ?? [];
$rel_dir     = sanitize_rel_path($data['dir'] ?? '');
$folder_name = sanitize_rel_path($data['name'] ?? '');

if (!$folder_name) { echo json_encode(['ok'=>false,'error'=>'Nombre de carpeta inválido.']); exit; }

$mount = smb_ensure_mount();
if (!$mount['ok']) { echo json_encode(['ok'=>false,'error'=>$mount['error'],'expired'=>true]); exit; }

$base     = rtrim($mount['local_path'], '/\\');
$dest_dir = $base . ($rel_dir ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_dir) : '');
$new_dir  = $dest_dir . DIRECTORY_SEPARATOR . $folder_name;

if (is_dir($new_dir))   { echo json_encode(['ok'=>false,'error'=>'La carpeta ya existe.']); exit; }
if (mkdir($new_dir, 0775, true)) echo json_encode(['ok'=>true]);
else echo json_encode(['ok'=>false,'error'=>'No se pudo crear la carpeta.']);
