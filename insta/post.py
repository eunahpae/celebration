#!/usr/bin/env python3
"""AI 콘텐츠 인스타 피드 자동 게시.

    python insta/post.py generate [--dry-run]   # 문구 생성 + JPEG 렌더
    python insta/post.py publish                # 오늘자 카드를 인스타에 게시

generate 와 publish 사이에 반드시 git push 가 끼어야 한다 —
인스타 API는 이미지를 '공개 URL'로만 받기 때문에, 커밋되어 raw.githubusercontent.com
에 노출되기 전에는 게시할 수 없다.
"""
from __future__ import annotations

import argparse
import html
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "out"
HISTORY = ROOT / "history.json"
CONFIG = json.loads((ROOT / "config.json").read_text(encoding="utf-8"))

KST = timezone(timedelta(hours=9))
MODEL = "claude-opus-5"
WIDTH, HEIGHT = 1080, 1350  # 인스타 피드 4:5 — 타임라인에서 가장 크게 보이는 비율

SCHEMA = {
    "type": "object",
    "properties": {
        "topic": {"type": "string", "description": "이 게시물의 주제를 한 문장으로. 중복 방지용 내부 기록."},
        "type": {
            "type": "string",
            "enum": ["스킬", "플러그인", "MCP", "설정", "기본"],
            "description": "카드 최상단 타입 배지. 새 값을 만들지 말 것.",
        },
        "kicker": {"type": "string", "description": "타입 배지 옆 이름. 스킬·플러그인이면 그 이름, 아니면 영문 대문자 라벨 1~3단어."},
        "title": {"type": "string", "description": "카드 대제목. 한국어 18자 이내. 강조할 부분은 <em>로 감쌀 것."},
        "lead": {"type": "string", "description": "제목 아래 한 줄. 이게 없을 때 뭐가 괴로운지. 32자 이내."},
        "points": {
            "type": "array",
            "description": "카드 본문 3개. 각 30자 이내, 한 줄에 끝나게. 자세한 설명은 캡션이 맡으니 카드는 짧게.",
            "items": {"type": "string"},
        },
        "code": {
            "type": "array",
            "description": "카드 하단 명령어 블록. 설치 명령이나 사용 예시. 각 줄 46자 이내, 최대 4줄.",
            "items": {"type": "string"},
        },
        "caption": {"type": "string", "description": "인스타 캡션. config 의 caption_spec 에 있는 구조와 금지 목록을 그대로 따를 것."},
        "hashtags": {"type": "array", "items": {"type": "string"}, "description": "# 포함 해시태그 5~8개."},
    },
    "required": ["topic", "type", "kicker", "title", "lead", "points", "code", "caption", "hashtags"],
    "additionalProperties": False,
}


# ── 저장소 ────────────────────────────────────────────────────────────────

def load_history() -> list[dict]:
    if not HISTORY.exists():
        return []
    return json.loads(HISTORY.read_text(encoding="utf-8"))


def today_slug() -> str:
    return datetime.now(KST).strftime("%Y-%m-%d")


# ── 1. 문구 생성 ──────────────────────────────────────────────────────────

def read_notes() -> list[str]:
    """notes.md 의 '- ' 로 시작하는 줄만 소재로 읽는다 (안내문·주석은 무시)."""
    path = ROOT / "notes.md"
    if not path.exists():
        return []
    body = re.sub(r"<!--.*?-->", "", path.read_text(encoding="utf-8"), flags=re.S)
    return [ln.strip()[2:].strip() for ln in body.splitlines() if ln.strip().startswith("- ")]


def write_copy(recent_topics: list[str], index: int) -> dict:
    import anthropic

    system = "\n".join([
        f"당신은 인스타그램 계정 {CONFIG['handle']} 의 콘텐츠 작가입니다.",
        f"계정 컨셉: {CONFIG['concept']}",
        f"독자: {CONFIG['audience']}",
        "",
        "말투:",
        *(f"- {t}" for t in CONFIG["tone"]),
        "",
        "반드시 지킬 것:",
        *(f"- {c}" for c in CONFIG["constraints"]),
    ])

    notes = read_notes()
    if notes:
        # 본인이 실제로 겪은 소재가 있으면 그것부터. 이 계정의 차별점이 여기서 나온다.
        source = "\n".join([
            "아래는 계정 주인이 직접 겪은 실무 메모입니다.",
            "이 중 최근에 다루지 않은 것 하나를 골라 카드로 만드세요.",
            "메모에 없는 경험을 지어내지 마세요.",
            "",
            *(f"- {n}" for n in notes),
        ])
    else:
        angle = CONFIG["angles"][(index - 1) % len(CONFIG["angles"])]
        source = f"이번 카드의 앵글입니다:\n{angle}"

    avoid = "\n".join(f"- {t}" for t in recent_topics) or "(아직 없음)"
    prompt = (
        f"{index}번째 카드를 만들어 주세요.\n\n"
        f"{source}\n\n"
        f"최근에 다룬 주제입니다. 겹치거나 비슷한 주제는 피해 주세요:\n{avoid}\n\n"
        f"kicker 는 '{CONFIG['series_prefix']}' 로 시작하지 않아도 됩니다. 내용에 맞는 짧은 영문 라벨이면 됩니다."
    )

    response = anthropic.Anthropic().messages.create(
        model=MODEL,
        max_tokens=16000,
        system=system,
        messages=[{"role": "user", "content": prompt}],
        output_config={"format": {"type": "json_schema", "schema": SCHEMA}},
    )
    if response.stop_reason == "refusal":
        raise SystemExit(f"모델이 생성을 거부했습니다: {response.stop_details}")

    text = next(b.text for b in response.content if b.type == "text")
    return json.loads(text)


# ── 2. JPEG 렌더 ──────────────────────────────────────────────────────────

def fill_template(copy: dict, index: int) -> str:
    points = "".join(f"<li>{html.escape(p)}</li>" for p in copy["points"])
    # title 만 <em> 태그를 허용 — 나머지는 이스케이프
    title = html.escape(copy["title"]).replace("&lt;em&gt;", "<em>").replace("&lt;/em&gt;", "</em>")
    # 명령어 블록: 슬래시로 시작하는 줄의 명령 부분만 액센트 색
    code = "\n".join(
        re.sub(r"^(/\S+)", r'<span class="p">\1</span>', html.escape(line))
        for line in copy.get("code", [])
    )

    page_html = (ROOT / "template.html").read_text(encoding="utf-8")
    for key, value in {
        "{{ACCENT}}": CONFIG["accent"],
        "{{ACCENT2}}": CONFIG["accent2"],
        "{{TYPE}}": html.escape(copy.get("type", "기본")),
        "{{KICKER}}": html.escape(copy["kicker"]),
        "{{TITLE}}": title,
        "{{TITLE_CLASS}}": "long" if len(copy["title"].replace("<em>", "").replace("</em>", "")) > 18 else "",
        "{{LEAD}}": html.escape(copy.get("lead", "")),
        "{{POINTS}}": points,
        "{{CODE}}": code,
        "{{HANDLE}}": html.escape(CONFIG["handle"]),
        "{{SLOGAN}}": html.escape(CONFIG["slogan"]),
        "{{INDEX}}": f"{index:03d}",
    }.items():
        page_html = page_html.replace(key, value)
    return page_html


def render(copy: dict, index: int, dest: Path) -> Path:
    from playwright.sync_api import sync_playwright

    page_html = fill_template(copy, index)
    dest.parent.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(viewport={"width": WIDTH, "height": HEIGHT}, device_scale_factor=1)
        page.set_content(page_html, wait_until="networkidle")
        page.evaluate("document.fonts.ready")  # 웹폰트가 적용되기 전에 찍히는 것 방지
        page.screenshot(path=str(dest), type="jpeg", quality=92)
        browser.close()

    # 인스타는 JPEG만 받는다. 렌더가 조용히 실패하면 몇백 바이트짜리 빈 이미지가 남는다.
    assert dest.stat().st_size > 20_000, f"렌더 결과가 비정상적으로 작습니다: {dest.stat().st_size}B"
    return dest


# ── 3. 인스타 게시 ────────────────────────────────────────────────────────

def _post(url: str, params: dict) -> dict:
    body = urllib.parse.urlencode(params).encode()
    request = urllib.request.Request(url, data=body, method="POST")
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return json.load(response)
    except urllib.error.HTTPError as e:
        raise SystemExit(f"인스타 API 오류 {e.code}: {e.read().decode('utf-8', 'replace')}")


def publish(image_url: str, caption: str) -> str:
    ig_id = os.environ["IG_USER_ID"]
    token = os.environ["IG_TOKEN"]
    base = f"{CONFIG['graph_base']}/{ig_id}"

    # 방금 푸시한 이미지를 인스타 서버가 아직 못 읽을 수 있다 (raw URL 전파 지연).
    for attempt in range(3):
        try:
            container = _post(f"{base}/media", {
                "image_url": image_url,
                "caption": caption,
                "access_token": token,
            })
            break
        except SystemExit as e:
            if attempt == 2:
                raise
            print(f"컨테이너 생성 실패, 15초 후 재시도 ({attempt + 1}/3): {e}", file=sys.stderr)
            time.sleep(15)

    published = _post(f"{base}/media_publish", {
        "creation_id": container["id"],
        "access_token": token,
    })
    return published["id"]


# ── 커맨드 ────────────────────────────────────────────────────────────────

def load_queue() -> list[dict]:
    path = ROOT / "queue.json"
    return json.loads(path.read_text(encoding="utf-8")) if path.exists() else []


def cmd_generate(dry_run: bool) -> None:
    history = load_history()
    index = len(history) + 1
    slug = today_slug()

    # 큐에 미리 써둔 문구가 있으면 그것부터 쓴다 (API 호출 없음).
    queue = load_queue()
    if queue:
        copy = queue[0]
        print(f"큐에서 꺼냈습니다. 남은 카드: {len(queue) - 1}장")
        if len(queue) <= 4:
            print("⚠ 큐가 얼마 안 남았습니다. insta/queue.json 을 채워주세요.", file=sys.stderr)
    elif os.environ.get("ANTHROPIC_API_KEY") or os.environ.get("ANTHROPIC_AUTH_TOKEN"):
        copy = write_copy([entry["topic"] for entry in history[-20:]], index)
    else:
        raise SystemExit(
            "queue.json 이 비었고 ANTHROPIC_API_KEY 도 없습니다.\n"
            "insta/queue.json 에 카드를 채우거나, API 키를 설정하세요."
        )

    image = render(copy, index, OUT / f"{slug}.jpg")

    hashtags = list(dict.fromkeys(copy["hashtags"] + CONFIG["fixed_hashtags"]))
    payload = {
        "date": slug,
        "index": index,
        "topic": copy["topic"],
        "title": copy["title"],
        "image": image.name,
        "caption": copy["caption"] + "\n\n" + " ".join(hashtags),
    }

    print(f"렌더 완료: {image}  ({image.stat().st_size // 1024}KB)")
    print(f"제목: {copy['title']}")
    print(f"캡션:\n{payload['caption']}")

    if dry_run:
        print("\n--dry-run: history 기록과 게시를 건너뜁니다.")
        return

    (OUT / f"{slug}.json").write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    # 게시 전에 기록한다 — 게시가 실패해도 같은 주제를 다시 뽑지 않는 편이 낫다.
    HISTORY.write_text(
        json.dumps(history + [payload], ensure_ascii=False, indent=2), encoding="utf-8"
    )
    if queue:
        (ROOT / "queue.json").write_text(
            json.dumps(queue[1:], ensure_ascii=False, indent=2), encoding="utf-8"
        )


HARVEST_SCHEMA = {
    "type": "object",
    "properties": {
        "lessons": {
            "type": "array",
            "items": {"type": "string", "description": "일반화된 교훈 1~2문장. 고유명사 없이."},
        }
    },
    "required": ["lessons"],
    "additionalProperties": False,
}

HARVEST_RULES = """당신은 계정 주인의 업무 노트에서 '공개해도 되는 방법론'만 뽑아내는 편집자입니다.

절대 포함하면 안 되는 것 (하나라도 걸리면 그 교훈은 버리세요):
- 회사명, 고객사명, 프로젝트명, 사람 이름, 팀 이름 등 모든 고유명사
- 서버·DB·URL·파일경로·계정·토큰 등 인프라나 자격증명에 관한 정보
- 보안 취약점, 접근 권한, 인증 우회에 관한 구체적 내용
- 인사평가, 급여, 근태, 타인에 대한 평가

뽑아야 하는 것:
- 도구나 회사가 바뀌어도 통하는, 재현 가능한 작업 방식
- "이렇게 했더니 이렇더라"는 관찰. 성공만이 아니라 실패와 헛수고도 좋습니다.
- 남들도 똑같이 겪을 법한 문제와 그 대응

각 교훈은 그 자체로 읽히는 1~2문장이어야 합니다. 원문을 요약하지 말고,
원문에서 배운 것을 일반화해 다시 쓰세요. 안전하게 일반화할 수 없으면 버리세요.
억지로 개수를 채우지 마세요."""


def cmd_harvest() -> None:
    import anthropic

    vault = Path(os.environ.get("VAULT_PATH", ""))
    if not vault.is_dir():
        raise SystemExit("VAULT_PATH 환경변수에 Obsidian 볼트 경로를 지정하세요.")

    rules = CONFIG["vault"]
    sources: list[str] = []
    for pattern in rules["include"]:
        for path in sorted(vault.glob(pattern)):
            rel = path.relative_to(vault).as_posix()
            if any(word in rel for word in rules["exclude"]):
                continue
            sources.append(f"### {rel}\n\n{path.read_text(encoding='utf-8')}")

    if not sources:
        raise SystemExit("수집 대상이 없습니다. config.json 의 vault.include 를 확인하세요.")
    print(f"{len(sources)}개 노트를 읽었습니다. 추출 중...")

    notes_path = ROOT / "notes.md"
    existing = notes_path.read_text(encoding="utf-8") if notes_path.exists() else ""

    response = anthropic.Anthropic().messages.create(
        model=MODEL,
        max_tokens=16000,
        system=HARVEST_RULES,
        messages=[{"role": "user", "content": (
            "아래는 이미 뽑아둔 소재입니다. 여기와 겹치는 것은 다시 뽑지 마세요.\n\n"
            f"{existing}\n\n---\n\n다음은 원본 노트입니다.\n\n" + "\n\n---\n\n".join(sources)
        )}],
        output_config={"format": {"type": "json_schema", "schema": HARVEST_SCHEMA}},
    )
    if response.stop_reason == "refusal":
        raise SystemExit(f"모델이 추출을 거부했습니다: {response.stop_details}")

    lessons = json.loads(next(b.text for b in response.content if b.type == "text"))["lessons"]
    if not lessons:
        print("새로 뽑을 만한 소재가 없습니다.")
        return

    # 주석 안에 넣는다 — 사람이 읽고 주석을 풀어야 비로소 카드 소재가 된다.
    block = "\n".join([
        "",
        f"<!-- harvest {today_slug()} — 검토 후 아래 주석 기호(<!-- , -->)를 지우면 소재로 쓰입니다 -->",
        "<!--",
        *(f"- {lesson}" for lesson in lessons),
        "-->",
    ])
    with notes_path.open("a", encoding="utf-8") as f:
        f.write(block + "\n")

    print(f"\n{len(lessons)}개를 notes.md 에 초안으로 추가했습니다. 검토 후 주석을 푸세요:\n")
    for lesson in lessons:
        print(f"  - {lesson}")


def cmd_publish() -> None:
    payload = json.loads((OUT / f"{today_slug()}.json").read_text(encoding="utf-8"))
    media_id = publish(CONFIG["image_url_base"] + payload["image"], payload["caption"])
    print(f"게시 완료: {media_id}  ({payload['title']})")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="command", required=True)
    gen = sub.add_parser("generate", help="문구 생성 + JPEG 렌더")
    gen.add_argument("--dry-run", action="store_true", help="history 기록 없이 결과만 확인")
    sub.add_parser("publish", help="오늘자 카드 게시")
    sub.add_parser("harvest", help="Obsidian 볼트에서 소재 초안 뽑기 (수동 실행)")

    args = parser.parse_args()
    if args.command == "generate":
        cmd_generate(args.dry_run)
    elif args.command == "harvest":
        cmd_harvest()
    else:
        cmd_publish()
