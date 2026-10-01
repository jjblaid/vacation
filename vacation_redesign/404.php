<?php

http_response_code(404);

/*
 * 반드시 제일 먼저 실행
 */
require_once __DIR__ . '/config/security.php';

?>
<!DOCTYPE html>

<html lang="ko">

<head>

<meta charset="UTF-8">

<title>404 Not Found</title>


<style>
body {
    font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    text-align: center;
    margin-top: 120px;
    background: #F1F5F9;
    color: #0F172A;
}
h1 {
    font-size: 48px;
    font-weight: 700;
    color: #14B8A6;
}
p {
    color: #64748B;
    margin-top: 12px;
}
a {
    color: #14B8A6;
    text-decoration: none;
    font-weight: 600;
}
a:hover {
    text-decoration: underline;
}
</style>

</head>

<body>

<h1>404</h1>

<p>요청하신 페이지를 찾을 수 없습니다.</p>

<a href="/">메인으로 이동</a>

</body>

</html>
