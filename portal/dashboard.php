<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/smb.php';
require_once __DIR__ . '/lib/auth.php';

auth_require();

$username  = $_SESSION['username'];
$smb_path  = $_SESSION['smb_path'];
$domain    = $_SESSION['domain'];

// Verify the SMB mount from login is still active (no password needed; ya montado en el login)
$mount_info = smb_ensure_mount($smb_path, $username, '', $domain);
$mount_ok   = $mount_info['ok'];
$mount_path = $mount_info['local_path'] ?? '';
$mount_err  = $mount_info['error'] ?? '';

// Sub-directory navigation (relative to mount root, sanitized)
$rel_dir = '';
if ($mount_ok) {
    $rel_dir = sanitize_rel_path($_GET['dir'] ?? '');
    $abs_dir = rtrim($mount_path, '/\\') . ($rel_dir ? DIRECTORY_SEPARATOR . $rel_dir : '');
    if (!is_dir($abs_dir)) { $rel_dir = ''; $abs_dir = $mount_path; }
} else {
    $abs_dir = '';
}

// Build breadcrumbs
$breadcrumbs = [];
if ($rel_dir) {
    $parts = explode('/', $rel_dir);
    $accum = '';
    foreach ($parts as $p) {
        $accum = $accum ? $accum . '/' . $p : $p;
        $breadcrumbs[] = ['label' => $p, 'path' => $accum];
    }
}

// List directory contents
$entries = [];
if ($mount_ok && is_dir($abs_dir)) {
    $items = @scandir($abs_dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $abs_dir . DIRECTORY_SEPARATOR . $item;
        $entries[] = [
            'name'    => $item,
            'is_dir'  => is_dir($full),
            'size'    => is_file($full) ? filesize($full) : 0,
            'mtime'   => filemtime($full),
            'rel'     => $rel_dir ? $rel_dir . '/' . $item : $item,
        ];
    }
    // Dirs first, then files, both alpha
    usort($entries, fn($a,$b) => $b['is_dir'] <=> $a['is_dir'] ?: strcasecmp($a['name'], $b['name']));
}

function fmt_size(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes/1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes/1048576, 1) . ' MB';
    return round($bytes/1073741824, 2) . ' GB';
}
function fmt_date(int $ts): string { return date('d/m/Y H:i', $ts); }
function file_icon(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match(true) {
        in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp'])   => 'img',
        in_array($ext, ['mp4','mkv','avi','mov','wmv','webm'])          => 'vid',
        in_array($ext, ['mp3','wav','flac','ogg','aac'])                 => 'aud',
        in_array($ext, ['pdf'])                                          => 'pdf',
        in_array($ext, ['zip','rar','7z','tar','gz','bz2'])             => 'arc',
        in_array($ext, ['doc','docx','odt'])                             => 'doc',
        in_array($ext, ['xls','xlsx','ods','csv'])                      => 'xls',
        in_array($ext, ['ppt','pptx','odp'])                            => 'ppt',
        in_array($ext, ['txt','log','md','conf','ini','sh','bat','php','js','css','html','xml','json']) => 'txt',
        default => 'file',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KeepNAS — <?= htmlspecialchars($username) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:       #0a0c0f;
            --surface:  #111418;
            --surface2: #161b22;
            --border:   #1e2530;
            --border-hi:#2e3d50;
            --accent:   #00c8ff;
            --accent2:  #0077ff;
            --success:  #22c55e;
            --danger:   #ff4444;
            --warn:     #f59e0b;
            --text:     #d0dce8;
            --muted:    #5a6a7a;
            --mono:     'IBM Plex Mono', monospace;
            --sans:     'IBM Plex Sans', sans-serif;
            --sidebar-w: 240px;
            --header-h:  56px;
        }
        html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); }

        /* ── HEADER ── */
        .header {
            position: fixed; top: 0; left: 0; right: 0; height: var(--header-h);
            background: rgba(10,12,15,0.95);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 20px;
            gap: 16px; z-index: 100;
            backdrop-filter: blur(12px);
        }
        .header-brand {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }
        .header-brand-icon {
            width: 32px; height: 32px;
            border: 1.5px solid var(--accent);
            border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            font-family: var(--mono); font-size: 12px; font-weight: 600;
            color: var(--accent);
        }
        .header-brand-name {
            font-family: var(--mono); font-size: 14px; font-weight: 600;
            letter-spacing: 2px; color: #fff;
        }
        .header-sep { width: 1px; height: 24px; background: var(--border); flex-shrink: 0; }
        .header-path {
            flex: 1;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--muted);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .header-path span { color: var(--accent); }
        .header-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .header-user {
            display: flex; align-items: center; gap: 8px;
            padding: 5px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 3px;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--text);
        }
        .header-user-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); box-shadow: 0 0 6px var(--success); }
        .btn-logout {
            padding: 6px 14px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 3px;
            color: var(--muted);
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 1px;
            cursor: pointer;
            text-decoration: none;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-logout:hover { border-color: var(--danger); color: var(--danger); }

        /* ── LAYOUT ── */
        .layout {
            display: flex;
            margin-top: var(--header-h);
            min-height: calc(100vh - var(--header-h));
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            flex-shrink: 0;
            border-right: 1px solid var(--border);
            background: var(--surface);
            padding: 20px 0;
            position: sticky;
            top: var(--header-h);
            height: calc(100vh - var(--header-h));
            overflow-y: auto;
        }
        .sidebar-section { margin-bottom: 24px; }
        .sidebar-label {
            padding: 0 18px 8px;
            font-family: var(--mono);
            font-size: 10px;
            letter-spacing: 2px;
            color: var(--muted);
            text-transform: uppercase;
        }
        .sidebar-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 18px;
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
            cursor: pointer;
            border-left: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s, background 0.15s;
        }
        .sidebar-item.active, .sidebar-item:hover {
            color: var(--text);
            background: rgba(0,200,255,0.05);
            border-left-color: var(--accent);
        }
        .sidebar-item svg { flex-shrink: 0; }

        .storage-bar {
            margin: 0 18px;
            padding: 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 3px;
        }
        .storage-bar-label {
            display: flex; justify-content: space-between;
            font-family: var(--mono); font-size: 10px; color: var(--muted);
            margin-bottom: 8px;
        }
        .storage-track {
            height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;
        }
        .storage-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent2), var(--accent));
            border-radius: 2px;
            width: 38%;
        }

        /* ── MAIN ── */
        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

        .toolbar {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            flex-wrap: wrap;
        }
        .breadcrumb {
            display: flex; align-items: center; gap: 6px;
            font-family: var(--mono); font-size: 12px;
            flex: 1;
            overflow: hidden;
        }
        .breadcrumb a {
            color: var(--accent); text-decoration: none;
            white-space: nowrap;
            transition: opacity 0.15s;
        }
        .breadcrumb a:hover { opacity: 0.7; }
        .breadcrumb span { color: var(--muted); }
        .breadcrumb .current { color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .toolbar-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .btn {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            border-radius: 3px;
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 1px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text);
        }
        .btn:hover { border-color: var(--border-hi); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent2), var(--accent));
            border-color: transparent;
            color: #fff;
            box-shadow: 0 2px 12px rgba(0,119,255,0.3);
        }
        .btn-primary:hover { box-shadow: 0 2px 18px rgba(0,200,255,0.4); }

        .content-area { flex: 1; overflow-y: auto; padding: 24px; }

        /* ── ERROR BANNER ── */
        .alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 18px;
            border-radius: 3px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .alert-error { background: rgba(255,68,68,0.08); border: 1px solid rgba(255,68,68,0.25); color: #ff8888; }
        .alert-warn  { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #fbbf24; }

        /* ── FILE TABLE ── */
        .file-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .file-table thead tr {
            border-bottom: 1px solid var(--border);
        }
        .file-table th {
            padding: 10px 12px;
            text-align: left;
            font-family: var(--mono);
            font-size: 10px;
            letter-spacing: 1.5px;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: 400;
        }
        .file-table tbody tr {
            border-bottom: 1px solid rgba(30,37,48,0.6);
            transition: background 0.1s;
        }
        .file-table tbody tr:hover { background: rgba(0,200,255,0.03); }
        .file-table td { padding: 11px 12px; vertical-align: middle; }

        .file-name-cell { display: flex; align-items: center; gap: 10px; }
        .file-icon {
            width: 32px; height: 32px;
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-size: 10px;
            font-family: var(--mono);
            font-weight: 600;
        }
        .icon-dir  { background: rgba(0,119,255,0.12); color: var(--accent2); border: 1px solid rgba(0,119,255,0.2); }
        .icon-img  { background: rgba(168,85,247,0.12); color: #c084fc; border: 1px solid rgba(168,85,247,0.2); }
        .icon-pdf  { background: rgba(239,68,68,0.12);  color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
        .icon-arc  { background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.2); }
        .icon-doc  { background: rgba(59,130,246,0.12); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2); }
        .icon-xls  { background: rgba(34,197,94,0.12);  color: #4ade80; border: 1px solid rgba(34,197,94,0.2); }
        .icon-ppt  { background: rgba(249,115,22,0.12); color: #fb923c; border: 1px solid rgba(249,115,22,0.2); }
        .icon-vid  { background: rgba(236,72,153,0.12); color: #f472b6; border: 1px solid rgba(236,72,153,0.2); }
        .icon-aud  { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.2); }
        .icon-txt  { background: rgba(148,163,184,0.08);color: #94a3b8; border: 1px solid rgba(148,163,184,0.12); }
        .icon-file { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }

        .file-name-link {
            color: var(--text); text-decoration: none;
            font-weight: 500;
            transition: color 0.15s;
        }
        .file-name-link:hover { color: var(--accent); }
        .dir-link { color: var(--accent2) !important; }

        .file-size, .file-date { color: var(--muted); font-family: var(--mono); font-size: 12px; }

        .file-actions { display: flex; gap: 6px; justify-content: flex-end; }
        .action-btn {
            padding: 5px 10px;
            border-radius: 2px;
            font-family: var(--mono);
            font-size: 10px;
            letter-spacing: 1px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            transition: all 0.15s;
        }
        .action-btn:hover { border-color: var(--accent); color: var(--accent); }
        .action-btn-danger:hover { border-color: var(--danger); color: var(--danger); }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        .empty-state svg { margin: 0 auto 16px; display: block; opacity: 0.3; }
        .empty-state p { font-family: var(--mono); font-size: 13px; }

        /* ── UPLOAD MODAL ── */
        .modal-backdrop {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 200;
            align-items: center; justify-content: center;
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 4px;
            width: 480px;
            max-width: 95vw;
            overflow: hidden;
            animation: modalIn 0.2s ease;
        }
        @keyframes modalIn { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:none} }
        .modal-header {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-title { font-family: var(--mono); font-size: 13px; font-weight: 600; letter-spacing: 1px; color: #fff; }
        .modal-close {
            width: 28px; height: 28px;
            border: 1px solid var(--border);
            border-radius: 3px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
        }
        .modal-close:hover { border-color: var(--danger); color: var(--danger); }
        .modal-body { padding: 22px; }

        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 4px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(0,200,255,0.04);
        }
        .drop-zone svg { margin: 0 auto 12px; display: block; color: var(--muted); }
        .drop-zone p { font-family: var(--mono); font-size: 12px; color: var(--muted); }
        .drop-zone span { color: var(--accent); }
        #file-input { display: none; }

        .upload-list { margin-top: 16px; }
        .upload-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }
        .upload-item-name { flex: 1; font-family: var(--mono); color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .upload-item-size { color: var(--muted); font-family: var(--mono); font-size: 11px; }
        .progress-bar { height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; margin-top: 4px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--accent2), var(--accent)); width: 0; transition: width 0.3s; }

        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            display: flex; gap: 10px; justify-content: flex-end;
        }

        /* Delete confirm */
        .confirm-text { font-size: 14px; color: var(--text); margin-bottom: 6px; }
        .confirm-sub  { font-family: var(--mono); font-size: 12px; color: var(--muted); }

        /* Stats bar */
        .stats-bar {
            display: flex; gap: 20px;
            padding: 10px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            font-family: var(--mono);
            font-size: 11px;
            color: var(--muted);
        }
        .stats-bar span { color: var(--text); }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <a href="dashboard.php" class="header-brand">
        <div class="header-brand-icon">KN</div>
        <span class="header-brand-name">KEEPNAS</span>
    </a>
    <div class="header-sep"></div>
    <div class="header-path">
        <span><?= htmlspecialchars($smb_path) ?></span>
        <?php if ($rel_dir): ?>
        &nbsp;›&nbsp; <?= htmlspecialchars(str_replace('/', ' › ', $rel_dir)) ?>
        <?php endif; ?>
    </div>
    <div class="header-actions">
        <div class="header-user">
            <div class="header-user-dot"></div>
            <?= htmlspecialchars($username) ?>@<?= htmlspecialchars($domain) ?>
        </div>
        <a href="logout.php" class="btn-logout">Cerrar sesión</a>
    </div>
</header>

<!-- LAYOUT -->
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-label">Navegación</div>
            <a class="sidebar-item active" href="dashboard.php">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Mi carpeta
            </a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-label">Almacenamiento</div>
            <div class="storage-bar">
                <div class="storage-bar-label">
                    <span>Usado</span>
                    <span>38%</span>
                </div>
                <div class="storage-track"><div class="storage-fill"></div></div>
            </div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-label">Sesión</div>
            <div class="sidebar-item" style="font-size:11px; color: var(--muted); padding-top:4px; padding-bottom:4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= date('H:i:s') ?>
            </div>
            <div class="sidebar-item" style="font-size:11px; color: var(--muted); padding-top:4px; padding-bottom:4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                VPN 10.10.0.x
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="breadcrumb">
                <a href="dashboard.php">Raíz</a>
                <?php foreach ($breadcrumbs as $bc): ?>
                    <span>›</span>
                    <a href="dashboard.php?dir=<?= urlencode($bc['path']) ?>"><?= htmlspecialchars($bc['label']) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="toolbar-actions">
                <button class="btn" onclick="document.getElementById('newFolderModal').classList.add('open')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    Nueva carpeta
                </button>
                <button class="btn btn-primary" onclick="document.getElementById('uploadModal').classList.add('open')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    Subir archivo
                </button>
            </div>
        </div>

        <!-- STATS BAR -->
        <?php if ($mount_ok): ?>
        <div class="stats-bar">
            <div><?php
                $dirs  = array_filter($entries, fn($e) => $e['is_dir']);
                $files = array_filter($entries, fn($e) => !$e['is_dir']);
                $total_size = array_sum(array_column(array_values($files), 'size'));
                echo count($dirs) . ' carpetas, ' . count($files) . ' archivos';
                if ($total_size > 0) echo ' &nbsp;·&nbsp; <span>' . fmt_size($total_size) . '</span>';
            ?></div>
        </div>
        <?php endif; ?>

        <div class="content-area">

            <?php if (!$mount_ok): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    <strong>No se pudo montar la carpeta SMB.</strong><br>
                    <span style="font-size:12px;opacity:.7"><?= htmlspecialchars($mount_err) ?></span>
                </div>
            </div>
            <?php elseif (empty($entries)): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                <p>Carpeta vacía. Sube tu primer archivo.</p>
            </div>
            <?php else: ?>
            <table class="file-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tamaño</th>
                        <th>Modificado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e):
                        $icon_type = $e['is_dir'] ? 'dir' : file_icon($e['name']);
                    ?>
                    <tr>
                        <td>
                            <div class="file-name-cell">
                                <div class="file-icon icon-<?= $icon_type ?>">
                                    <?php if ($e['is_dir']): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                    <?php else: echo strtoupper($icon_type); endif; ?>
                                </div>
                                <?php if ($e['is_dir']): ?>
                                <a class="file-name-link dir-link" href="dashboard.php?dir=<?= urlencode($e['rel']) ?>">
                                    <?= htmlspecialchars($e['name']) ?>
                                </a>
                                <?php else: ?>
                                <a class="file-name-link" href="download.php?file=<?= urlencode($e['rel']) ?>">
                                    <?= htmlspecialchars($e['name']) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="file-size"><?= $e['is_dir'] ? '—' : fmt_size($e['size']) ?></td>
                        <td class="file-date"><?= fmt_date($e['mtime']) ?></td>
                        <td>
                            <div class="file-actions">
                                <?php if (!$e['is_dir']): ?>
                                <a class="action-btn" href="download.php?file=<?= urlencode($e['rel']) ?>">↓ Descargar</a>
                                <?php endif; ?>
                                <button class="action-btn action-btn-danger"
                                        onclick="confirmDelete('<?= htmlspecialchars(addslashes($e['name'])) ?>', '<?= urlencode($e['rel']) ?>')">
                                    Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- UPLOAD MODAL -->
<div class="modal-backdrop" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">SUBIR ARCHIVOS</span>
            <button class="modal-close" onclick="document.getElementById('uploadModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
            <form id="uploadForm" method="POST" action="upload.php" enctype="multipart/form-data">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($rel_dir) ?>">
                <div class="drop-zone" id="dropZone" onclick="document.getElementById('file-input').click()">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    <p>Arrastra archivos aquí o <span>haz clic para seleccionar</span></p>
                </div>
                <input type="file" id="file-input" name="files[]" multiple>
                <div class="upload-list" id="uploadList"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="document.getElementById('uploadModal').classList.remove('open')">Cancelar</button>
            <button class="btn btn-primary" id="uploadBtn" onclick="submitUpload()" disabled>Subir archivos</button>
        </div>
    </div>
</div>

<!-- NEW FOLDER MODAL -->
<div class="modal-backdrop" id="newFolderModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">NUEVA CARPETA</span>
            <button class="modal-close" onclick="document.getElementById('newFolderModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST" action="mkdir.php">
            <div class="modal-body">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($rel_dir) ?>">
                <div style="margin-bottom:8px; font-family: var(--mono); font-size:11px; color: var(--muted); letter-spacing:1px; text-transform:uppercase;">Nombre de la carpeta</div>
                <input type="text" name="folder_name" required autofocus
                       style="width:100%; padding:10px 14px; background:var(--bg); border:1px solid var(--border); border-radius:3px; color:var(--text); font-family:var(--mono); font-size:13px; outline:none;"
                       placeholder="nueva-carpeta">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('newFolderModal').classList.remove('open')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" style="color:#ff8888">CONFIRMAR ELIMINACIÓN</span>
            <button class="modal-close" onclick="document.getElementById('deleteModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
            <p class="confirm-text">¿Estás seguro de que quieres eliminar este elemento?</p>
            <p class="confirm-sub" id="deleteTargetName"></p>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="document.getElementById('deleteModal').classList.remove('open')">Cancelar</button>
            <a id="deleteConfirmBtn" href="#" class="btn" style="border-color:var(--danger);color:var(--danger)">Eliminar</a>
        </div>
    </div>
</div>

<script>
// Drop zone
const dz = document.getElementById('dropZone');
const fi = document.getElementById('file-input');
const ul = document.getElementById('uploadList');
const ub = document.getElementById('uploadBtn');

fi.addEventListener('change', () => renderFiles(fi.files));
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('dragover');
    renderFiles(e.dataTransfer.files);
});

function renderFiles(files) {
    ul.innerHTML = '';
    if (!files.length) { ub.disabled = true; return; }
    ub.disabled = false;
    Array.from(files).forEach(f => {
        ul.innerHTML += `
        <div class="upload-item">
            <div class="upload-item-name">${escHtml(f.name)}</div>
            <div class="upload-item-size">${fmtSize(f.size)}</div>
        </div>`;
    });
}

function submitUpload() {
    const form = document.getElementById('uploadForm');
    const fd = new FormData(form);
    // Manually add dropped files if any
    document.getElementById('uploadModal').classList.remove('open');
    form.submit();
}

function fmtSize(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
    return (b/1073741824).toFixed(2) + ' GB';
}
function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function confirmDelete(name, encPath) {
    document.getElementById('deleteTargetName').textContent = name;
    document.getElementById('deleteConfirmBtn').href = 'delete.php?file=' + encPath;
    document.getElementById('deleteModal').classList.add('open');
}

// Close modals on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});
</script>
</body>
</html>
