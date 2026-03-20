<?php
// lib/smb.php

require_once dirname(__DIR__) . '/config.php';

function smb_mount(string $smb_path, string $username, string $password, string $domain): array {
    $drive = smb_free_drive();
    if (!$drive) {
        return ['ok' => false, 'local_path' => '', 'error' => 'No hay letras de unidad disponibles.', 'auth_failed' => false];
    }

    $unc      = str_replace('/', '\\', $smb_path);
    $u_arg    = escapeshellarg($domain . '\\' . $username);
    $p_arg    = escapeshellarg($password);
    $d_arg    = escapeshellarg($drive);
    $unc_arg  = escapeshellarg($unc);

    shell_exec("cmd /c net use {$d_arg} /delete /yes 2>&1");
    $output = trim(shell_exec("cmd /c net use {$d_arg} {$unc_arg} {$p_arg} /user:{$u_arg} /persistent:no 2>&1") ?? '');

    if (@is_dir($drive . '\\')) {
        $_SESSION['drive_letter'] = $drive;
        return ['ok' => true, 'local_path' => $drive . '\\', 'error' => '', 'auth_failed' => false];
    }

    if (@is_dir($unc)) {
        return ['ok' => true, 'local_path' => $unc . '\\', 'error' => '', 'auth_failed' => false];
    }

    $auth_failed = str_contains($output, '1326') || str_contains($output, 'Access is denied') || str_contains($output, 'password');

    return ['ok' => false, 'local_path' => '', 'error' => $output ?: 'Error al montar la carpeta.', 'auth_failed' => $auth_failed];
}

function smb_ensure_mount(): array {
    if (!empty($_SESSION['drive_letter'])) {
        $drive = $_SESSION['drive_letter'];
        if (@is_dir($drive . '\\')) return ['ok' => true, 'local_path' => $drive . '\\'];
        unset($_SESSION['drive_letter']);
    }
    return ['ok' => false, 'local_path' => '', 'error' => 'Sesión SMB expirada. Vuelve a iniciar sesión.'];
}

function smb_unmount(): void {
    if (!empty($_SESSION['drive_letter'])) {
        $d = escapeshellarg($_SESSION['drive_letter']);
        shell_exec("cmd /c net use {$d} /delete /yes 2>&1");
        unset($_SESSION['drive_letter']);
    }
}

function smb_free_drive(): string|false {
    for ($c = ord('H'); $c <= ord('Z'); $c++) {
        $d = chr($c) . ':';
        if (!@is_dir($d . '\\')) return $d;
    }
    return false;
}

function get_smb_path_for_user(string $username): string|false {
    if (file_exists(USERS_DB)) {
        try {
            $db   = new PDO('sqlite:' . USERS_DB);
            $stmt = $db->prepare('SELECT smb_path FROM users WHERE username = :u AND active = 1 LIMIT 1');
            $stmt->execute([':u' => strtolower($username)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['smb_path'])) return $row['smb_path'];
        } catch (Exception $e) {}
    }
    return '\\\\' . NAS_HOST . '\\' . SHARE_BASE . '\\' . $username;
}

function sanitize_rel_path(string $path): string {
    $path  = str_replace('\\', '/', $path);
    $parts = explode('/', $path);
    $safe  = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '' || $p === '.' || $p === '..') continue;
        if (preg_match('/^[a-zA-Z0-9_\-\. \(\)\[\]áéíóúÁÉÍÓÚñÑüÜ]+$/u', $p)) $safe[] = $p;
    }
    return implode('/', $safe);
}

function fmt_size(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}
