<?php
// lib/auth.php

require_once dirname(__DIR__) . '/config.php';

function vpn_check(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!str_starts_with($ip, '10.10.0.')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Acceso solo desde VPN WireGuard.']);
        exit;
    }
}

function auth_require(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    vpn_check();
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        auth_destroy();
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Sesión expirada.']);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function auth_destroy(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
