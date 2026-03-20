<?php
// logout.php — Cierra sesión: desmonta SMB y destruye sesión PHP
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/smb.php';
require_once __DIR__ . '/lib/auth.php';

smb_unmount();   // net use /delete
auth_destroy();  // destruye sesión PHP

header('Location: /index.php?logout=1');
exit;
