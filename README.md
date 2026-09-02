# celebration

축하 카드 HTML 모음 + 인스타 피드 자동 게시.

## 인스타 자동 게시 (`insta/`)

월·수·금 12:00 KST에 GitHub Actions가 AI 관련 카드를 만들어 자동 게시합니다.

```
Claude API로 문구 생성 → template.html 렌더 → JPEG → 커밋·푸시 → 인스타 API 게시
```

### 문구는 어디서 오나 — 큐 우선

`insta/queue.json`에 카드가 있으면 **거기서 하나씩 꺼내 씁니다.** API 호출도, 비용도 없습니다.
큐가 비면 그때 Claude API로 생성하고, 키도 없으면 실행이 실패합니다(게시가 조용히 멈추는 것보다 낫습니다).

큐가 4장 이하로 줄면 Actions 로그에 경고가 찍힙니다. 그때 채우면 됩니다.

```json
{
  "topic": "중복 방지용 주제 한 줄",
  "kicker": "SHORT LABEL",
  "title": "카드 제목 (18자 이내, 강조는 <em>)",
  "points": ["30자 이내", "3줄", "각 줄 완결"],
  "caption": "인스타 캡션",
  "hashtags": ["#AI활용"]
}
```

### 준비

1. **인스타 계정을 프로페셔널(비즈니스/크리에이터)로 전환** — 개인 계정은 API 사용 불가
2. [developers.facebook.com](https://developers.facebook.com)에서 앱 생성 → Instagram 제품 추가 (무료)
3. `instagram_business_content_publish` 권한으로 장기 토큰 발급 (**60일 만료**)
4. `insta/config.json`에서 `handle` 채우기
5. 레포 Settings → Secrets에 등록:

   | Secret | 값 | 필수 |
   |---|---|---|
   | `IG_USER_ID` | 인스타 비즈니스 계정 ID | ✅ |
   | `IG_TOKEN` | 장기 액세스 토큰 | ✅ |
   | `ANTHROPIC_API_KEY` | Claude API 키 | 큐를 직접 채우면 불필요 |

### 로컬 확인

게시 없이 문구 생성과 이미지 렌더까지만 실행합니다. `insta/out/`에 JPEG가 떨어집니다.

```bash
pip install anthropic playwright && playwright install chromium
python insta/post.py generate --dry-run
```

### 소재 수집 (`harvest`)

Obsidian 볼트에서 카드 소재를 뽑아옵니다. **수동 실행이고, 자동 게시 파이프라인은 볼트에 접근하지 않습니다.**

```bash
VAULT_PATH="<Obsidian 볼트 경로>" python insta/post.py harvest
```

볼트의 노트를 읽어 고유명사·인프라 정보·평가 내용을 걷어내고, 일반화된 교훈만
`insta/notes.md`에 **주석 처리된 초안**으로 append 합니다.

```markdown
<!-- harvest 2026-09-02 — 검토 후 아래 주석 기호를 지우면 소재로 쓰입니다 -->
<!--
- 완료조건을 정하지 않고 작업을 맡기면 "다 됐다"는 답만 돌아온다.
-->
```

읽어보고 괜찮은 것만 `<!--` / `-->` 를 지우면 그때부터 카드 소재가 됩니다.
**주석을 풀지 않는 한 어떤 것도 게시되지 않습니다.**

수집 범위는 `config.json`의 `vault.include` / `vault.exclude`로 조정합니다.
기본값은 업무프로세스·일지·정책 노트만 보고, 평가보고서·운영DB·인증·결제·배포소스는 제외합니다.

### 주의

- **토큰은 60일마다 직접 갱신해야 합니다.** 만료되면 Actions가 실패하고 게시가 멈춥니다.
- 레포가 public이어야 합니다 — 인스타 API가 `raw.githubusercontent.com`에서 이미지를 읽습니다.
- 게시 한도는 24시간 100건입니다.
