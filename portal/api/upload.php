<?php
// api/upload.php — Subida de archivos (POST multipart, devuelve JSON)
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/smb.php';

auth_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit; }

$mount = smb_ensure_mount();
if (!$mount['ok']) { echo json_encode(['ok'=>false,'error'=>$mount['error'],'expired'=>true]); exit; }

$rel_dir  = sanitize_rel_path($_POST['dir'] ?? '');
$base     = rtrim($mount['local_path'], '/\\');
$dest_dir = $base . ($rel_dir ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_dir) : '');

if (!@is_dir($dest_dir)) { echo json_encode(['ok'=>false,'error'=>'Directorio destino no existe.']); exit; }

$errors = []; $uploaded = [];
if (!empty($_FILES['files']['name'][0])) {
    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $name . ': error ' . $_FILES['files']['error'][$i]; continue;
        }
        $safe = preg_replace('/[^a-zA-Z0-9_\-\. \(\)\[\]áéíóúÁÉÍÓÚñÑüÜ]/u', '_', $name);
        $dest = $dest_dir . DIRECTORY_SEPARATOR . $safe;
        $n = 1;
        while (file_exists($dest)) {
            $ext  = pathinfo($safe, PATHINFO_EXTENSION);
            $base2 = pathinfo($safe, PATHINFO_FILENAME);
            $dest = $dest_dir . DIRECTORY_SEPARATOR . $base2 . '_' . $n++ . ($ext ? '.' . $ext : '');
        }
        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $dest)) {
            $uploaded[] = basename($dest);
        } else {
            $errors[] = $name . ': no se pudo guardar.';
        }
    }
}

echo json_encode(['ok' => empty($errors), 'uploaded' => $uploaded, 'errors' => $errors]);
