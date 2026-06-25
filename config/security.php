<?php
header_remove('X-Powered-By');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
$csp = "default-src 'self'; "
     . "script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; "
     . "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; "
     . "font-src https://fonts.gstatic.com data:; "
     . "img-src 'self' data:; "
     . "frame-ancestors 'self'; "
     . "form-action 'self';";
header("Content-Security-Policy: $csp");