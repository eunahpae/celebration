<?php
require __DIR__ . '/db.php';
$inv = invite();
$key = $_GET['e'] ?? '';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$origin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

if (!isset($inv['events'][$key])) {
    // 행사 주소 없이 들어오면 고르는 화면
    ?><!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($inv['father'] . ' · ' . $inv['mother']) ?> 칠순 잔치</title>
<style>
body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f7f1e8; color: #3b2f2a; font-family: 'Gowun Batang', serif; }
main { text-align: center; padding: 24px; }
h1 { font-size: 22px; margin-bottom: 28px; }
a { display: block; min-width: 220px; margin: 10px auto; padding: 14px 20px; border: 1px solid rgba(184,146,74,.5); border-radius: 12px; background: #fffdf8; color: inherit; text-decoration: none; }
</style></head><body><main>
<h1><?= h($inv['father'] . ' · ' . $inv['mother']) ?><br>칠순 잔치</h1>
<?php foreach ($inv['events'] as $k => $ev): ?>
<a href="<?= h($base . $k) ?>"><?= h($ev['label']) ?> · <?= h(date('n월 j일', strtotime($ev['date']))) ?></a>
<?php endforeach ?>
</main></body></html><?php
    exit;
}

$ev = $inv['events'][$key];
$page = $inv;
unset($page['events']);
$ts = strtotime($ev['date']);
$when = date('Y년 n월 j일', $ts) . ' ' . ['일', '월', '화', '수', '목', '금', '토'][date('w', $ts)] . '요일 ' . $ev['time'];
$page += ['event' => $key, 'date' => $ev['date'], 'when' => $when, 'timeNote' => $ev['timeNote'], 'venue' => $ev['venue'], 'guide' => $ev['guide']];
$title = "{$inv['father']} · {$inv['mother']} 칠순 잔치에 초대합니다";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= h($base) ?>">
<title><?= h($title) ?></title>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= h($title) ?>">
<meta property="og:description" content="<?= h($when . ' · ' . ($ev['venue']['name'] ?? '장소 추후 안내')) ?>">
<meta property="og:image" content="<?= h($origin . $base . 'images/og.jpg') ?>">
<meta property="og:url" content="<?= h($origin . $base . $key) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Gowun+Batang:wght@400;700&family=Nanum+Myeongjo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #f7f1e8;
  --paper: #fffdf8;
  --ink: #3b2f2a;
  --muted: #8a7a6e;
  --red: #a4342b;
  --gold: #b8924a;
  --line: rgba(184, 146, 74, 0.35);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  background: #e9dfd0;
  color: var(--ink);
  font-family: 'Gowun Batang', serif;
  line-height: 1.8;
  -webkit-text-size-adjust: 100%;
}
.wrap {
  max-width: 440px;
  margin: 0 auto;
  background: var(--bg);
  min-height: 100vh;
  overflow: hidden;
}
section { padding: 64px 28px; text-align: center; }
section + section { border-top: 1px solid var(--line); }
.eyebrow {
  font-family: 'Nanum Myeongjo', serif;
  font-size: 12px;
  letter-spacing: 0.35em;
  color: var(--gold);
  margin-bottom: 14px;
}
h2 {
  font-family: 'Nanum Myeongjo', serif;
  font-weight: 700;
  font-size: 21px;
  margin-bottom: 28px;
}

/* 표지 */
.cover { padding: 0 0 56px; background: var(--paper); }
.cover-photo { width: 100%; height: auto; display: block; }
.cover-photo.missing { display: none; }
.cover-seal {
  width: 92px; height: 92px; margin: 48px auto 0;
  border: 2px solid var(--red); border-radius: 50%;
  display: grid; place-items: center;
  font-family: 'Nanum Myeongjo', serif; font-weight: 800;
  font-size: 34px; color: var(--red);
}
.cover-title {
  font-family: 'Nanum Myeongjo', serif; font-weight: 800;
  font-size: 30px; letter-spacing: 0.08em; margin-top: 28px;
}
.cover-sub { color: var(--muted); font-size: 14px; margin-top: 6px; }
.cover-names { font-size: 19px; margin-top: 24px; }
.cover-names b { font-weight: 700; }
.cover-date { margin-top: 18px; font-size: 15px; color: var(--muted); line-height: 1.7; }

/* 인사말 */
.greeting p { font-size: 15.5px; white-space: pre-line; word-break: keep-all; }
.hosts { margin-top: 36px; font-size: 15px; line-height: 2; }
.hosts span { color: var(--muted); font-size: 13px; margin-right: 8px; }
.host-family + .host-family::before {
  content: ''; display: block; width: 40px; height: 1px; margin: 10px auto; background: var(--gold); opacity: 0.5;
}

/* 달력 */
.cal { max-width: 300px; margin: 0 auto; }
.cal-head { font-family: 'Nanum Myeongjo', serif; font-size: 17px; margin-bottom: 16px; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); row-gap: 8px; font-size: 14px; }
.cal-grid .dow { color: var(--muted); font-size: 12px; }
.cal-grid .sun { color: var(--red); }
.cal-grid .day { width: 34px; height: 34px; margin: 0 auto; display: grid; place-items: center; }
.cal-grid .on { background: var(--red); color: #fff; border-radius: 50%; font-weight: 700; }
.time-note { font-size: 14px; color: var(--muted); margin: -18px 0 24px; word-break: keep-all; }
.time-note:empty { display: none; }
.dday { margin-top: 28px; font-size: 15px; }
.dday b { color: var(--red); font-size: 17px; }

/* 사진 */
.gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.gallery img { width: 100%; aspect-ratio: 1; object-fit: cover; cursor: zoom-in; display: block; }
.viewer {
  position: fixed; inset: 0; background: rgba(0,0,0,0.92);
  display: none; align-items: center; justify-content: center; z-index: 10;
}
.viewer.open { display: flex; }
.viewer img { max-width: 100%; max-height: 100%; }

/* 오시는 길 */
.venue-name { font-size: 18px; font-weight: 700; }
.venue-addr { color: var(--muted); font-size: 14px; margin-top: 4px; word-break: keep-all; }
.btn-row { display: flex; gap: 8px; justify-content: center; margin-top: 22px; flex-wrap: wrap; }
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-height: 44px; padding: 0 18px;
  border: 1px solid var(--line); border-radius: 22px;
  background: var(--paper); color: var(--ink);
  font: inherit; font-size: 14px; text-decoration: none; cursor: pointer;
}
.btn.primary { background: var(--red); border-color: var(--red); color: #fff; }
.guide { text-align: left; margin-top: 32px; font-size: 14px; }
.guide dt { font-weight: 700; color: var(--red); margin-top: 14px; }
.guide dd { color: var(--ink); word-break: keep-all; }

/* 연락처 · 마음 전하실 곳 */
.list { text-align: left; }
.item {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 14px 0; border-bottom: 1px solid var(--line);
}
.item-label { font-size: 13px; color: var(--muted); }
.item-main { font-size: 15px; word-break: break-all; }
.item .btn { min-height: 38px; padding: 0 14px; font-size: 13px; flex-shrink: 0; }

/* 참석 여부 · 축하 메시지 */
.form { text-align: left; display: grid; gap: 12px; }
.form label { font-size: 13px; color: var(--muted); display: grid; gap: 4px; }
.form input, .form textarea {
  font: inherit; font-size: 16px; color: var(--ink);
  padding: 11px 14px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper);
  width: 100%;
}
.form textarea { resize: vertical; min-height: 96px; }
.choice { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.choice label { position: relative; }
.choice input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.choice span {
  display: grid; place-items: center; min-height: 46px;
  border: 1px solid var(--line); border-radius: 10px; background: var(--paper);
  font-size: 15px; color: var(--ink); cursor: pointer;
}
.choice input:checked + span { background: var(--red); border-color: var(--red); color: #fff; }
.choice input:focus-visible + span { outline: 2px solid var(--gold); outline-offset: 2px; }
.hp { position: absolute; left: -9999px; }
.form [hidden] { display: none; }
.form .btn { width: 100%; min-height: 48px; font-size: 15px; }
.form-msg { font-size: 14px; min-height: 1.4em; text-align: center; color: var(--red); }
.done { font-size: 15px; padding: 24px 0; }
.gb-list { margin-top: 32px; text-align: left; display: grid; gap: 10px; }
.gb-item { background: var(--paper); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; }
.gb-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.gb-name { font-weight: 700; font-size: 15px; }
.gb-date { font-size: 12px; color: var(--muted); }
.gb-body { font-size: 14.5px; margin-top: 6px; white-space: pre-wrap; word-break: break-all; }
.gb-del { background: none; border: 0; color: var(--muted); font: inherit; font-size: 12px; cursor: pointer; padding: 4px 0 0; }
.gb-actions { display: flex; gap: 12px; }
.gb-edit { margin-top: 10px; }
.gb-edit .gb-actions { gap: 8px; }
.gb-edit .gb-actions .btn { flex: 1; }
.gb-empty { text-align: center; color: var(--muted); font-size: 14px; }

footer { padding: 40px 28px 64px; text-align: center; color: var(--muted); font-size: 13px; }
.toast {
  position: fixed; left: 50%; bottom: 32px; transform: translateX(-50%);
  background: rgba(40, 30, 25, 0.9); color: #fff; font-size: 14px;
  padding: 10px 18px; border-radius: 20px; opacity: 0; transition: opacity 0.25s;
  pointer-events: none; z-index: 20;
}
.toast.show { opacity: 1; }

.reveal { opacity: 0; transform: translateY(18px); transition: opacity 0.9s, transform 0.9s; }
.reveal.in { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) {
  .reveal { opacity: 1; transform: none; transition: none; }
}
</style>
</head>
<body>
<div class="wrap">

  <section class="cover">
    <img class="cover-photo" src="images/main.jpg" alt="배종성 원정희 부부 사진" width="1024" height="1536" onerror="this.classList.add('missing')">
    <div class="cover-seal">壽</div>
    <div class="cover-title">칠순 잔치</div>
    <div class="cover-sub">七旬宴</div>
    <div class="cover-names" id="coverNames"></div>
    <div class="cover-date" id="coverDate"></div>
  </section>

  <section class="greeting reveal">
    <div class="eyebrow">INVITATION</div>
    <h2>초대합니다</h2>
    <p id="greeting"></p>
    <div class="hosts" id="hosts"></div>
  </section>

  <section class="reveal">
    <div class="eyebrow">DATE</div>
    <h2 id="dateTitle"></h2>
    <p class="time-note" id="timeNote"></p>
    <div class="cal">
      <div class="cal-head" id="calHead"></div>
      <div class="cal-grid" id="calGrid"></div>
    </div>
    <div class="dday" id="dday"></div>
  </section>

  <section class="reveal" id="gallerySection">
    <div class="eyebrow">GALLERY</div>
    <h2>함께한 시간</h2>
    <div class="gallery" id="gallery"></div>
  </section>

  <section class="reveal">
    <div class="eyebrow">LOCATION</div>
    <h2>오시는 길</h2>
    <div class="venue-name" id="venueName"></div>
    <div class="venue-addr" id="venueAddr"></div>
    <div class="btn-row" id="mapButtons">
      <a class="btn" id="naverMap" target="_blank" rel="noopener">네이버 지도</a>
      <a class="btn" id="kakaoMap" target="_blank" rel="noopener">카카오맵</a>
      <button class="btn" id="copyAddr">주소 복사</button>
    </div>
    <dl class="guide" id="guide"></dl>
  </section>

  <section class="reveal">
    <div class="eyebrow">RSVP</div>
    <h2>참석 여부를 알려주세요</h2>
    <form class="form" id="rsvpForm">
      <label>성함<input name="name" maxlength="30" required autocomplete="name"></label>
      <div class="choice" role="radiogroup" aria-label="참석 여부">
        <label><input type="radio" name="attend" value="1" required><span>참석합니다</span></label>
        <label><input type="radio" name="attend" value="0"><span>참석이 어렵습니다</span></label>
      </div>
      <label id="countRow">함께 오시는 인원 (본인 포함)<input name="headcount" type="number" inputmode="numeric" min="1" max="20" value="1"></label>
      <input type="hidden" name="event" value="<?= h($key) ?>">
      <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button class="btn primary">보내기</button>
      <div class="form-msg" role="status"></div>
    </form>
  </section>

  <section class="reveal">
    <div class="eyebrow">MESSAGE</div>
    <h2>축하 메시지</h2>
    <form class="form" id="gbForm">
      <label>성함<input name="name" maxlength="30" required autocomplete="name"></label>
      <label>축하 글<textarea name="message" maxlength="500" required></textarea></label>
      <label>비밀번호 (내 글 지울 때 필요)<input name="password" type="password" minlength="4" maxlength="30" required autocomplete="new-password"></label>
      <input type="hidden" name="event" value="<?= h($key) ?>">
      <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button class="btn primary">남기기</button>
      <div class="form-msg" role="status"></div>
    </form>
    <div class="gb-list" id="gbList"></div>
  </section>

  <section class="reveal">
    <div class="eyebrow">CONTACT</div>
    <h2>연락하기</h2>
    <div class="list" id="contacts"></div>
  </section>

  <footer>
    <div class="btn-row" style="margin:0 0 24px">
      <button class="btn primary" id="share">초대장 공유하기</button>
    </div>
    <div id="footerText"></div>
  </footer>
</div>

<div class="viewer" id="viewer"><img alt=""></div>
<div class="toast" id="toast"></div>

<script>
// 내용은 invite.php 에서 고친다
const INVITE = <?= json_encode($page, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const DOW = ['일', '월', '화', '수', '목', '금', '토'];

const [y, m, d] = INVITE.date.split('-').map(Number);

$('coverNames').innerHTML = `<b>${esc(INVITE.father)}</b> · <b>${esc(INVITE.mother)}</b>`;
$('coverDate').innerHTML = esc(INVITE.when) + (INVITE.venue ? `<br>${esc(INVITE.venue.name)}` : '');
$('greeting').textContent = INVITE.greeting;
$('hosts').innerHTML = INVITE.hosts.map(family => `<div class="host-family">${
  family.map(h => `<div><span>${esc(h.role)}</span>${esc(h.name)}</div>`).join('')
}</div>`).join('') + '<div style="margin-top:6px">자녀 일동 올림</div>';
$('dateTitle').textContent = INVITE.when;
$('timeNote').textContent = INVITE.timeNote || '';
$('footerText').textContent = `${INVITE.father} · ${INVITE.mother} 칠순을 축하해 주셔서 감사합니다`;

// 달력
$('calHead').textContent = `${y}. ${String(m).padStart(2, '0')}`;
const first = new Date(y, m - 1, 1).getDay();
const last = new Date(y, m, 0).getDate();
let cells = DOW.map((w, i) => `<div class="dow${i === 0 ? ' sun' : ''}">${w}</div>`).join('');
cells += '<div></div>'.repeat(first);
for (let i = 1; i <= last; i++) {
  const sun = (first + i - 1) % 7 === 0;
  cells += `<div><div class="day${i === d ? ' on' : ''}${sun && i !== d ? ' sun' : ''}">${i}</div></div>`;
}
$('calGrid').innerHTML = cells;

// D-day (날짜 단위)
const today = new Date(); today.setHours(0, 0, 0, 0);
const diff = Math.round((new Date(y, m - 1, d) - today) / 86400000);
$('dday').innerHTML = diff > 0 ? `잔치까지 <b>${diff}일</b> 남았습니다`
  : diff === 0 ? '<b>오늘</b>이 잔치 날입니다' : '함께해 주셔서 감사했습니다';

// 사진
if (!INVITE.gallery.length) $('gallerySection').remove();
else {
  $('gallery').innerHTML = INVITE.gallery.map(src => `<img src="${esc(src)}" alt="" loading="lazy">`).join('');
  $('gallery').onclick = e => {
    if (e.target.tagName !== 'IMG') return;
    $('viewer').querySelector('img').src = e.target.src;
    $('viewer').classList.add('open');
  };
  $('viewer').onclick = () => $('viewer').classList.remove('open');
}

// 오시는 길
if (!INVITE.venue) {
  $('venueName').textContent = '장소와 정확한 시간은 정해지는 대로 알려드리겠습니다';
  $('mapButtons').remove();
} else {
  $('venueName').textContent = INVITE.venue.name;
  $('venueAddr').textContent = INVITE.venue.address;
  const q = encodeURIComponent(INVITE.venue.mapQuery || INVITE.venue.address);
  $('naverMap').href = `https://map.naver.com/p/search/${q}`;
  $('kakaoMap').href = `https://map.kakao.com/link/search/${q}`;
  $('copyAddr').onclick = () => copy(INVITE.venue.address, '주소를 복사했습니다');
}
$('guide').innerHTML = INVITE.guide.map(g => `<dt>${esc(g.title)}</dt><dd>${esc(g.text)}</dd>`).join('');

// 연락처
const telOnly = t => t.replace(/[^0-9+]/g, '');
$('contacts').innerHTML = INVITE.contacts.map(c => `
  <div class="item">
    <div><div class="item-label">${esc(c.label)}</div><div class="item-main">${esc(c.name)}</div></div>
    <div style="display:flex;gap:6px">
      <a class="btn" href="tel:${telOnly(c.tel)}">전화</a>
      <a class="btn" href="sms:${telOnly(c.tel)}">문자</a>
    </div>
  </div>`).join('');

// 공유: 휴대폰 공유 시트, 안 되면 링크 복사
$('share').onclick = async () => {
  const data = { title: document.title, url: location.href };
  if (navigator.share) { try { await navigator.share(data); return; } catch (e) { if (e.name === 'AbortError') return; } }
  copy(location.href, '초대장 링크를 복사했습니다');
};

// 클립보드 API는 https에서만 동작하므로 http 접속용 대체 경로를 둔다
async function copy(text, msg) {
  try {
    await navigator.clipboard.writeText(text);
  } catch {
    const ta = document.createElement('textarea');
    ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    const ok = document.execCommand('copy');
    ta.remove();
    if (!ok) { prompt('길게 눌러 복사해 주세요', text); return; }
  }
  toast(msg);
}

function toast(msg) {
  const t = $('toast'); t.textContent = msg; t.classList.add('show');
  clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 1800);
}

async function post(action, body) {
  const res = await fetch(`api.php?a=${action}`, { method: 'POST', body });
  const data = await res.json().catch(() => ({ error: '잠시 문제가 생겼습니다. 조금 뒤 다시 시도해 주세요.' }));
  if (!res.ok || data.error) throw new Error(data.error || '잠시 문제가 생겼습니다.');
  return data;
}

// 참석 여부
const rsvp = $('rsvpForm');
rsvp.addEventListener('change', () => {
  const no = rsvp.attend.value === '0';
  $('countRow').hidden = no;
  rsvp.headcount.disabled = no;
});
rsvp.onsubmit = async e => {
  e.preventDefault();
  const btn = rsvp.querySelector('button'), msg = rsvp.querySelector('.form-msg');
  btn.disabled = true; msg.textContent = '';
  try {
    await post('rsvp', new FormData(rsvp));
    rsvp.outerHTML = `<div class="done">${rsvp.attend.value === '1' ? '알려주셔서 감사합니다.<br>잔치에서 뵙겠습니다.' : '마음 전해주셔서 감사합니다.'}</div>`;
  } catch (err) {
    msg.textContent = err.message; btn.disabled = false;
  }
};

// 축하 메시지
const gb = $('gbForm');
let gbItems = new Map();
async function loadGuestbook() {
  try {
    const res = await fetch(`api.php?a=guestbook&e=${encodeURIComponent(INVITE.event)}`);
    const { items } = await res.json();
    gbItems = new Map(items.map(g => [String(g.id), g]));
    $('gbList').innerHTML = items.length ? items.map(g => `
      <div class="gb-item" data-id="${Number(g.id)}">
        <div class="gb-head"><span class="gb-name">${esc(g.name)}</span><span class="gb-date">${esc(g.created_at.slice(0, 10).replace(/-/g, '.'))}</span></div>
        <div class="gb-body">${esc(g.message)}</div>
        <div class="gb-actions"><button class="gb-del" data-act="edit">수정</button><button class="gb-del" data-act="del">삭제</button></div>
      </div>`).join('') : '<div class="gb-empty">첫 번째 축하 메시지를 남겨주세요</div>';
  } catch {
    $('gbList').innerHTML = '<div class="gb-empty">메시지를 불러오지 못했습니다</div>';
  }
}
gb.onsubmit = async e => {
  e.preventDefault();
  const btn = gb.querySelector('button'), msg = gb.querySelector('.form-msg');
  btn.disabled = true; msg.textContent = '';
  try {
    await post('guestbook', new FormData(gb));
    gb.reset();
    toast('축하 메시지를 남겼습니다');
    loadGuestbook();
  } catch (err) {
    msg.textContent = err.message;
  }
  btn.disabled = false;
};
$('gbList').onclick = async e => {
  const item = e.target.closest('.gb-item'), act = e.target.dataset.act;
  if (!item || !act) return;
  const id = item.dataset.id;
  if (act === 'cancel') return loadGuestbook();
  if (act === 'del') {
    const pw = prompt('글을 남길 때 정한 비밀번호를 입력해 주세요');
    if (!pw) return;
    const fd = new FormData(); fd.append('id', id); fd.append('password', pw);
    try { await post('guestbook_delete', fd); toast('삭제했습니다'); loadGuestbook(); }
    catch (err) { alert(err.message); }
    return;
  }
  // 수정: 그 자리에서 입력칸을 연다
  item.querySelector('.gb-body').remove();
  item.querySelector('.gb-actions').outerHTML = `
    <form class="form gb-edit">
      <textarea name="message" maxlength="500" required></textarea>
      <input name="password" type="password" minlength="4" maxlength="30" required autocomplete="current-password" placeholder="글 남길 때 정한 비밀번호">
      <div class="gb-actions"><button class="btn primary">저장</button><button type="button" class="btn" data-act="cancel">취소</button></div>
      <div class="form-msg" role="status"></div>
    </form>`;
  const form = item.querySelector('form');
  form.message.value = gbItems.get(id).message;
  form.message.focus();
  form.onsubmit = async ev => {
    ev.preventDefault();
    const fd = new FormData(form); fd.append('id', id);
    try { await post('guestbook_update', fd); toast('수정했습니다'); loadGuestbook(); }
    catch (err) { form.querySelector('.form-msg').textContent = err.message; }
  };
};
loadGuestbook();

// 스크롤 등장
const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: 0.15 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));
</script>
</body>
</html>
