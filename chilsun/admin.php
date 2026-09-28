<?php
require __DIR__ . '/db.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_name('chilsun_admin');
session_start();

$authed = !empty($_SESSION['admin']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$authed) {
        if (config()['admin_hash'] !== '' && password_verify((string)($_POST['password'] ?? ''), config()['admin_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: admin.php');
            exit;
        }
        sleep(1);
        $error = '비밀번호가 맞지 않습니다.';
    } else {
        if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            exit('잘못된 요청입니다. 새로고침 후 다시 시도해 주세요.');
        }
        $act = $_POST['act'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($act === 'logout') {
            session_destroy();
            header('Location: admin.php');
            exit;
        }
        if ($act === 'del_rsvp') db()->prepare('DELETE FROM chilsun_rsvp WHERE id = ?')->execute([$id]);
        if ($act === 'del_gb') db()->prepare('DELETE FROM chilsun_guestbook WHERE id = ?')->execute([$id]);
        header('Location: admin.php');
        exit;
    }
}

if ($authed && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rsvp-' . date('Ymd') . '.csv"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF"); // 엑셀이 한글을 UTF-8 로 읽게 하는 BOM
    fputcsv($f, ['번호', '행사', '성함', '참석', '인원', '작성시각']);
    foreach (db()->query('SELECT * FROM chilsun_rsvp ORDER BY id') as $r) {
        // 엑셀이 =,+,-,@ 로 시작하는 칸을 수식으로 실행하지 않게 막는다
        $safe = fn($s) => preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
        fputcsv($f, [$r['id'], invite()['events'][$r['event']]['label'] ?? $r['event'], $safe($r['name']), $r['attend'] ? '참석' : '불참', $r['headcount'], $r['created_at']]);
    }
    exit;
}

if ($authed) {
    $rsvp = db()->query('SELECT * FROM chilsun_rsvp ORDER BY id DESC')->fetchAll();
    $gb = db()->query('SELECT id, event, name, message, created_at FROM chilsun_guestbook ORDER BY id DESC')->fetchAll();
    $stats = [];
    foreach (invite()['events'] as $k => $ev) {
        $rows = array_filter($rsvp, fn($r) => $r['event'] === $k);
        $yes = array_filter($rows, fn($r) => $r['attend']);
        $stats[$k] = ['label' => $ev['label'], 'date' => date('n/j', strtotime($ev['date'])), 'people' => array_sum(array_column($yes, 'headcount')), 'yes' => count($yes), 'no' => count($rows) - count($yes)];
    }
    // 같은 행사에 같은 이름이 여러 번이면 중복 의심
    $dupKey = fn($r) => $r['event'] . "\0" . $r['name'];
    $nameCount = array_count_values(array_map($dupKey, $rsvp));
}
$csrf = h($_SESSION['csrf'] ?? '');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>칠순 잔치 관리</title>
<style>
body { font-family: -apple-system, 'Malgun Gothic', sans-serif; margin: 0; background: #f5f2ec; color: #333; font-size: 14px; }
main { max-width: 760px; margin: 0 auto; padding: 20px 16px 60px; }
h1 { font-size: 20px; } h2 { font-size: 16px; margin-top: 36px; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.stat { background: #fff; border-radius: 10px; padding: 14px; text-align: center; }
.stat b { display: block; font-size: 24px; color: #a4342b; }
table { width: 100%; border-collapse: collapse; background: #fff; }
th, td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; word-break: break-all; }
th { background: #faf7f2; white-space: nowrap; }
.dup { background: #fff4d6; }
.no { color: #999; }
button, .btn { font: inherit; padding: 6px 12px; border: 1px solid #ccc; border-radius: 6px; background: #fff; cursor: pointer; color: #333; text-decoration: none; }
.top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.msg { white-space: pre-wrap; }
.scroll { overflow-x: auto; }
input[type=password] { font: inherit; padding: 10px; width: 100%; max-width: 280px; box-sizing: border-box; }
.err { color: #a4342b; }
</style>
</head>
<body>
<main>
<?php if (!$authed): ?>
  <h1>칠순 잔치 관리</h1>
  <form method="post">
    <p><input type="password" name="password" placeholder="관리자 비밀번호" autofocus required></p>
    <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif ?>
    <button>들어가기</button>
  </form>
<?php else: ?>
  <div class="top">
    <h1>칠순 잔치 관리</h1>
    <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><button name="act" value="logout">나가기</button></form>
  </div>

  <?php foreach ($stats as $s): ?>
  <h2><?= h($s['label']) ?> · <?= h($s['date']) ?></h2>
  <div class="stats">
    <div class="stat"><b><?= $s['people'] ?>명</b>참석 인원</div>
    <div class="stat"><b><?= $s['yes'] ?>건</b>참석 응답</div>
    <div class="stat"><b><?= $s['no'] ?>건</b>불참 응답</div>
  </div>
  <?php endforeach ?>

  <div class="top"><h2>참석 여부 (<?= count($rsvp) ?>건)</h2><a class="btn" href="admin.php?csv=1">엑셀로 받기</a></div>
  <p class="no">노란 줄은 같은 행사에 같은 성함으로 여러 번 응답한 것입니다. 중복이면 지워 주세요.</p>
  <div class="scroll"><table>
    <tr><th>행사</th><th>성함</th><th>참석</th><th>인원</th><th>시각</th><th></th></tr>
    <?php foreach ($rsvp as $r): ?>
    <tr class="<?= $nameCount[$dupKey($r)] > 1 ? 'dup' : '' ?>">
      <td><?= h(invite()['events'][$r['event']]['label'] ?? $r['event']) ?></td>
      <td><?= h($r['name']) ?></td>
      <td><?= $r['attend'] ? '참석' : '<span class="no">불참</span>' ?></td>
      <td><?= $r['attend'] ? (int)$r['headcount'] : '' ?></td>
      <td><?= h(substr($r['created_at'], 5, 11)) ?></td>
      <td><form method="post" onsubmit="return confirm('이 응답을 지울까요?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button name="act" value="del_rsvp">삭제</button></form></td>
    </tr>
    <?php endforeach ?>
  </table></div>

  <h2>축하 메시지 (<?= count($gb) ?>건)</h2>
  <div class="scroll"><table>
    <tr><th>행사</th><th>성함</th><th>내용</th><th>시각</th><th></th></tr>
    <?php foreach ($gb as $g): ?>
    <tr>
      <td><?= h(invite()['events'][$g['event']]['label'] ?? $g['event']) ?></td>
      <td><?= h($g['name']) ?></td>
      <td class="msg"><?= h($g['message']) ?></td>
      <td><?= h(substr($g['created_at'], 5, 11)) ?></td>
      <td><form method="post" onsubmit="return confirm('이 메시지를 지울까요?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button name="act" value="del_gb">삭제</button></form></td>
    </tr>
    <?php endforeach ?>
  </table></div>
<?php endif ?>
</main>
</body>
</html>
