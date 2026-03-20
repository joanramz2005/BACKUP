<?php
// upload.php — Subida de archivos a la carpeta SMB del usuario
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/smb.php';
require_once __DIR__ . '/lib/auth.php';

auth_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }

$rel_dir    = sanitize_rel_path($_POST['dir'] ?? '');
$mount_info = smb_ensure_mount($_SESSION['smb_path'], $_SESSION['username'], '', $_SESSION['domain']);

if (!$mount_info['ok']) {
    header('Location: dashboard.php?error=' . urlencode('No se pudo acceder a la carpeta.'));
    exit;
}

$base = rtrim($mount_info['local_path'], '/\\');
$dest_dir = $base . ($rel_dir ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_dir) : '');

$errors = [];
$ok     = 0;

if (!empty($_FILES['files']['name'][0])) {
    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = htmlspecialchars($name) . ': error de subida (' . $_FILES['files']['error'][$i] . ')';
            continue;
        }
        $safe_name = preg_replace('/[^a-zA-Z0-9_\-\. \(\)\[\]áéíóúÁÉÍÓÚñÑüÜ]/', '_', $name);
        $dest = $dest_dir . DIRECTORY_SEPARATOR . $safe_name;

        // Avoid overwrite — append _N if exists
        $counter = 1;
        while (file_exists($dest)) {
            $ext   = pathinfo($safe_name, PATHINFO_EXTENSION);
            $base2 = pathinfo($safe_name, PATHINFO_FILENAME);
            $dest  = $dest_dir . DIRECTORY_SEPARATOR . $base2 . '_' . $counter++ . ($ext ? '.' . $ext : '');
        }

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $dest)) {
            $ok++;
        } else {
            $errors[] = htmlspecialchars($name) . ': no se pudo guardar.';
        }
    }
}

$redirect = 'dashboard.php' . ($rel_dir ? '?dir=' . urlencode($rel_dir) : '');
if ($errors) {
    $redirect .= (str_contains($redirect,'?') ? '&' : '?') . 'upload_errors=' . urlencode(implode('; ', $errors));
}
header('Location: ' . $redirect);
exit;
