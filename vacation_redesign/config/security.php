<?php
header_remove('X-Powered-By');

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header('Cross-Origin-Resource-Policy: same-origin');
header('X-Permitted-Cross-Domain-Policies: none');

// HTTPS 적용 전까지는 비활성화
// header('Cross-Origin-Opener-Policy: same-origin');
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
/*
 * HTTPS 환경에서만 활성화
 */
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

$cspNonce = bin2hex(random_bytes(16));

$csp =
    "default-src 'self'; "
    . "script-src 'self' https://cdn.jsdelivr.net 'nonce-{$cspNonce}'; "
    . "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'nonce-{$cspNonce}'; "
    . "font-src https://fonts.gstatic.com data:; "
    . "img-src 'self' data:; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "frame-ancestors 'self'; "
    . "form-action 'self';";

header("Content-Security-Policy: $csp");

$_PARSED_BODY = json_decode(file_get_contents('php://input'), true) ?? [];

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken() {
    global $_PARSED_BODY;
    $token = $_PARSED_BODY['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF 토큰이 유효하지 않습니다.']);
        exit;
    }
}
