<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

$a = $_GET['a'] ?? '';

// 참석과 축하 메시지 모두 행사별로 저장된다. 유효한 키는 invite.php 가 정한다
function event_key(string $v): string {
    if (!isset(invite()['events'][$v])) out(400, ['error' => '잘못된 초대장 주소입니다.']);
    return $v;
}
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

try {
    if ($a === 'guestbook' && !$post) {
        // note: 최근 200개만. 더 쌓이면 페이지 나누기 추가
        $st = db()->prepare('SELECT id, name, message, created_at FROM chilsun_guestbook WHERE event = ? ORDER BY id DESC LIMIT 200');
        $st->execute([event_key((string)($_GET['e'] ?? ''))]);
        $rows = $st->fetchAll();
        out(200, ['items' => $rows]);
    }

    if (!$post) out(405, ['error' => '잘못된 요청입니다.']);
    // 사람 눈에 안 보이는 칸. 채워져 있으면 스팸 봇
    if (($_POST['website'] ?? '') !== '') out(200, ['ok' => true]);

    if ($a === 'rsvp') {
        $event = event_key((string)($_POST['event'] ?? ''));
        $name = field('name', 30);
        $attend = $_POST['attend'] ?? '';
        $count = (int)($_POST['headcount'] ?? 0);
        if ($name === null) out(400, ['error' => '성함을 30자 이내로 적어주세요.']);
        if ($attend !== '1' && $attend !== '0') out(400, ['error' => '참석 여부를 골라주세요.']);
        if ($attend === '1' && ($count < 1 || $count > 20)) out(400, ['error' => '인원을 1~20명 사이로 적어주세요.']);
        db()->prepare('INSERT INTO chilsun_rsvp (event, name, attend, headcount, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$event, $name, (int)$attend, $attend === '1' ? $count : 0, client_ip(), now()]);
        out(200, ['ok' => true]);
    }

    if ($a === 'guestbook') {
        $event = event_key((string)($_POST['event'] ?? ''));
        $name = field('name', 30);
        $msg = field('message', 500);
        $pw = field('password', 30);
        if ($name === null) out(400, ['error' => '성함을 30자 이내로 적어주세요.']);
        if ($msg === null) out(400, ['error' => '축하 글을 500자 이내로 적어주세요.']);
        if ($pw === null || mb_strlen($pw) < 4) out(400, ['error' => '비밀번호를 4자 이상 적어주세요.']);
        $recent = db()->prepare('SELECT COUNT(*) FROM chilsun_guestbook WHERE ip = ? AND created_at > ?');
        $recent->execute([client_ip(), date('Y-m-d H:i:s', time() - 30)]);
        if ($recent->fetchColumn() > 0) out(429, ['error' => '잠시 후 다시 남겨주세요.']);
        db()->prepare('INSERT INTO chilsun_guestbook (event, name, message, pw_hash, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$event, $name, $msg, password_hash($pw, PASSWORD_DEFAULT), client_ip(), now()]);
        out(200, ['ok' => true]);
    }

    if ($a === 'guestbook_update' || $a === 'guestbook_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->prepare('SELECT pw_hash FROM chilsun_guestbook WHERE id = ?');
        $st->execute([$id]);
        $hash = $st->fetchColumn();
        if (!$hash || !password_verify((string)($_POST['password'] ?? ''), $hash)) {
            sleep(1);
            out(403, ['error' => '비밀번호가 맞지 않습니다.']);
        }
        if ($a === 'guestbook_delete') {
            db()->prepare('DELETE FROM chilsun_guestbook WHERE id = ?')->execute([$id]);
        } else {
            $msg = field('message', 500);
            if ($msg === null) out(400, ['error' => '축하 글을 500자 이내로 적어주세요.']);
            db()->prepare('UPDATE chilsun_guestbook SET message = ? WHERE id = ?')->execute([$msg, $id]);
        }
        out(200, ['ok' => true]);
    }

    out(404, ['error' => '잘못된 요청입니다.']);
} catch (Throwable $e) {
    error_log('chilsun api: ' . $e->getMessage());
    out(500, ['error' => '잠시 문제가 생겼습니다. 조금 뒤 다시 시도해 주세요.']);
}
