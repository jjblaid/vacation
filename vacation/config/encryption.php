<?php
/**
 * 주민등록번호 AES-256-CBC 암호화
 * settings 테이블의 encryption_key를 사용
 */

function getEncryptionKey() {
    $key = getSetting('encryption_key');
    if (empty($key)) {
        $key = hash('sha256', random_bytes(32));
        setSetting('encryption_key', $key);
    }
    return hex2bin($key);
}

function encryptResidentNo($plaintext) {
    if (empty($plaintext)) return '';
    $key = getEncryptionKey();
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($ivLen);
    $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptResidentNo($encrypted) {
    if (empty($encrypted)) return '';
    $key = getEncryptionKey();
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $data = base64_decode($encrypted);
    if ($data === false || strlen($data) < $ivLen) return '';
    $iv = substr($data, 0, $ivLen);
    $ciphertext = substr($data, $ivLen);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
}

function maskResidentNo($residentNo) {
    if (empty($residentNo) || strlen($residentNo) < 8) return $residentNo;
    return substr($residentNo, 0, 8) . '*******';
}
