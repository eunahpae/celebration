<?php
require __DIR__ . '/db.php';

// 인원 구분. 키는 chilsun_rsvp 의 컬럼 이름이다
const KINDS = ['adults' => '성인', 'teens' => '청소년(중고등)', 'elementary' => '초등학생', 'preschool' => '미취학', 'infants' => '36개월 미만'];
$people = fn($r) => array_sum(array_map(fn($k) => (int)$r[$k], array_keys(KINDS)));

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
        if ($act === 'add_rsvp') {
            $event = (string)($_POST['event'] ?? '');
            if (isset(invite()['events'][$event])) $_SESSION['last_event'] = $event;
            $name = field('name', 30);
            $attend = $_POST['attend'] ?? '';
            $counts = [];
            foreach (KINDS as $col => $_) $counts[$col] = $attend === '1' ? (int)($_POST[$col] ?? 0) : 0;
            if (!isset(invite()['events'][$event])) $_SESSION['flash'] = '행사를 골라주세요.';
            elseif ($name === null) $_SESSION['flash'] = '성함을 30자 이내로 적어주세요.';
            elseif ($attend !== '1' && $attend !== '0') $_SESSION['flash'] = '참석 여부를 골라주세요.';
            elseif (min($counts) < 0 || max($counts) > 50) $_SESSION['flash'] = '인원은 구분마다 0~50명으로 적어주세요.';
            elseif ($attend === '1' && array_sum($counts) === 0) $_SESSION['flash'] = '참석이면 인원을 1명 이상 적어주세요.';
            else db()->prepare('INSERT INTO chilsun_rsvp (event, name, attend, ' . implode(', ', array_keys(KINDS)) . ', ip, created_at) VALUES (' . implode(', ', array_fill(0, 5 + count(KINDS), '?')) . ')')
                ->execute(array_merge([$event, $name, (int)$attend], array_values($counts), [client_ip(), now()]));
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
    fputcsv($f, array_merge(['번호', '행사', '성함', '참석'], array_values(KINDS), ['합계', '작성시각']));
    foreach (db()->query('SELECT * FROM chilsun_rsvp ORDER BY id') as $r) {
        // 엑셀이 =,+,-,@ 로 시작하는 칸을 수식으로 실행하지 않게 막는다
        $safe = fn($s) => preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
        fputcsv($f, array_merge(
            [$r['id'], invite()['events'][$r['event']]['label'] ?? $r['event'], $safe($r['name']), $r['attend'] ? '참석' : '불참'],
            array_map(fn($k) => (int)$r[$k], array_keys(KINDS)),
            [$people($r), $r['created_at']]
        ));
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
        $kinds = [];
        foreach (KINDS as $col => $label) $kinds[$label] = array_sum(array_column($yes, $col));
        $stats[$k] = ['label' => $ev['label'], 'date' => date('n/j', strtotime($ev['date'])), 'people' => array_sum($kinds), 'kinds' => $kinds, 'yes' => count($yes), 'no' => count($rows) - count($yes)];
    }
    // 같은 행사에 같은 이름이 여러 번이면 중복 의심
    $dupKey = fn($r) => $r['event'] . "\0" . $r['name'];
    $nameCount = array_count_values(array_map($dupKey, $rsvp));
}
$csrf = h($_SESSION['csrf'] ?? '');
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
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
.kinds { grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); margin-top: 8px; }
.kinds .stat { padding: 10px 4px; font-size: 13px; word-break: keep-all; }
.kinds .stat b { font-size: 18px; }
.add { display: grid; grid-template-columns: 1fr 1.4fr 1fr; gap: 6px; margin: 12px 0; }
.add input, .add select { font: inherit; padding: 8px; border: 1px solid #ccc; border-radius: 6px; min-width: 0; width: 100%; box-sizing: border-box; }
.add .events { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.add .events label { position: relative; }
.add .events input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.add .events span { display: block; padding: 10px; border: 1px solid #ccc; border-radius: 6px; background: #fff; text-align: center; cursor: pointer; }
.add .events input:checked + span { background: #a4342b; border-color: #a4342b; color: #fff; font-weight: 700; }
.add .events input:focus-visible + span { outline: 2px solid #b8924a; outline-offset: 2px; }
.add .counts { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 6px; }
.add .counts label { display: grid; gap: 2px; font-size: 12px; color: #777; }
.add button { grid-column: 1 / -1; }
.num { text-align: right; }
@media (max-width: 520px) { .add { grid-template-columns: 1fr 1fr; } .add input[name=name] { grid-column: span 2; } }
.scroll td:not(.msg), .scroll th { white-space: nowrap; }
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
  <div class="stats kinds">
    <?php foreach ($s['kinds'] as $label => $n): ?>
    <div class="stat"><b><?= $n ?></b><?= h($label) ?></div>
    <?php endforeach ?>
  </div>
  <?php endforeach ?>

  <div class="top"><h2>참석 여부 (<?= count($rsvp) ?>건)</h2><a class="btn" href="admin.php?csv=1">엑셀로 받기</a></div>
  <form method="post" class="add">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <div class="events" role="radiogroup" aria-label="행사">
      <?php foreach (invite()['events'] as $k => $ev): ?>
      <label><input type="radio" name="event" value="<?= h($k) ?>" required<?= ($_SESSION['last_event'] ?? '') === $k ? ' checked' : '' ?>><span><?= h($ev['label']) ?> <?= h(date('n/j', strtotime($ev['date']))) ?></span></label>
      <?php endforeach ?>
    </div>
    <input name="name" placeholder="성함" maxlength="30" required>
    <select name="attend" required><option value="1">참석</option><option value="0">불참</option></select>
    <div class="counts">
      <?php foreach (KINDS as $col => $label): ?>
      <label><?= h($label) ?><input name="<?= $col ?>" type="number" inputmode="numeric" min="0" max="50" value="<?= $col === 'adults' ? 1 : 0 ?>"></label>
      <?php endforeach ?>
    </div>
    <button name="act" value="add_rsvp">추가</button>
  </form>
  <?php if ($flash): ?><p class="err"><?= h($flash) ?></p><?php endif ?>
  <p class="no">불참이면 인원은 모두 0으로 저장됩니다. 노란 줄은 같은 행사에 같은 성함이 여러 번 입력된 것입니다. 중복이면 지워 주세요.</p>
  <div class="scroll"><table>
    <tr><th>행사</th><th>성함</th><th>참석</th><?php foreach (KINDS as $label): ?><th><?= h($label) ?></th><?php endforeach ?><th>합계</th><th>시각</th><th></th></tr>
    <?php foreach ($rsvp as $r): ?>
    <tr class="<?= $nameCount[$dupKey($r)] > 1 ? 'dup' : '' ?>">
      <td><?= h(invite()['events'][$r['event']]['label'] ?? $r['event']) ?></td>
      <td><?= h($r['name']) ?></td>
      <td><?= $r['attend'] ? '참석' : '<span class="no">불참</span>' ?></td>
      <?php foreach (KINDS as $col => $_): ?><td class="num"><?= $r['attend'] ? (int)$r[$col] : '' ?></td><?php endforeach ?>
      <td class="num"><b><?= $r['attend'] ? $people($r) : '' ?></b></td>
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
