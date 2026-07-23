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
