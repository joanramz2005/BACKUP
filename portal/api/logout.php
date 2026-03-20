<?php
// api/logout.php
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/smb.php';
require_once dirname(__DIR__) . '/lib/auth.php';

smb_unmount();
auth_destroy();
echo json_encode(['ok' => true]);
