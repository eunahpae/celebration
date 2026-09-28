<?php
date_default_timezone_set('Asia/Seoul');

function config(): array {
    static $c;
    return $c ?? ($c = require __DIR__ . '/config.php');
}

function invite(): array {
    static $c;
    return $c ?? ($c = require __DIR__ . '/invite.php');
}

function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c = config();
    $pdo = new PDO(
        "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
        $c['db_user'],
        $c['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}

function now(): string {
    return date('Y-m-d H:i:s');
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// 비었거나 $max 글자(바이트 아님)를 넘으면 null
function field(string $key, int $max): ?string {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '' || !mb_check_encoding($v, 'UTF-8') || mb_strlen($v) > $max) return null;
    return $v;
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
