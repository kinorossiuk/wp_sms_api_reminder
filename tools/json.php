<?php
declare(strict_types=1);

$jsonCssVersion = (string) (filemtime(__DIR__ . '/../static/json.css') ?: '1');
$jsonJsVersion = (string) (filemtime(__DIR__ . '/../static/json.js') ?: '1');
?>
<link rel="stylesheet" href="/static/json.css?v=<?= htmlspecialchars($jsonCssVersion, ENT_QUOTES, 'UTF-8') ?>">
<section class="tool-view json-tool" aria-labelledby="tool-title" data-json-tool>
  <a class="back-link" href="/">← 모든 도구</a>
  <div class="tool-view-icon" aria-hidden="true">
    <svg viewBox="0 0 32 32"><path d="M11 3v4H8v7c0 1.4-.7 2.4-2 3 1.3.6 2 1.6 2 3v7h3v4H7c-2 0-3-1-3-3v-7c0-1.3-.7-2-2-2v-4c1.3 0 2-.7 2-2V6c0-2 1-3 3-3h4Zm10 0h4c2 0 3 1 3 3v7c0 1.3.7 2 2 2v4c-1.3 0-2 .7-2 2v7c0 2-1 3-3 3h-4v-4h3v-7c0-1.4.7-2.4 2-3-1.3-.6-2-1.6-2-3V7h-3V3Z"/></svg>
  </div>
  <p class="kicker">DEVELOPER / JSON</p>
  <h1 id="tool-title">JSON<br>뷰어</h1>
  <p class="tool-intro">JSON 문법을 검사하고 읽기 좋게 정리하거나 한 줄로 압축합니다. 입력 내용은 브라우저 안에서만 처리됩니다.</p>

  <details class="work-notice json-security-note">
    <summary id="json-security-title">업무 사용 전 주의사항 및 데이터 처리</summary>
    <ul>
      <li><strong>서버 미전송·미저장</strong><span>붙여 넣거나 파일로 연 JSON은 브라우저 메모리에서만 처리하며 서버, 외부 API, 분석 서비스로 보내지 않습니다.</span></li>
      <li><strong>사용자 요청만 실행</strong><span>클립보드 복사와 JSON 다운로드는 해당 버튼을 직접 눌렀을 때만 실행됩니다.</span></li>
      <li><strong>회사 보안 정책 우선</strong><span>Falcon, Ivanti, 사내 Wi-Fi, 브라우저 DLP 등 회사 보안 솔루션은 접속·파일·클립보드 활동을 별도로 기록하거나 제한할 수 있습니다.</span></li>
      <li><strong>민감정보 주의</strong><span>회사 승인 없이 비밀번호, API Key, 인증 토큰, 고객정보 또는 사내 기밀 데이터를 입력하지 마세요.</span></li>
    </ul>
  </details>

  <div class="json-toolbar" aria-label="JSON 보기 설정">
    <label for="json-indent">들여쓰기
      <select id="json-indent">
        <option value="2">공백 2칸</option>
        <option value="4">공백 4칸</option>
        <option value="tab">탭</option>
      </select>
    </label>
    <label class="json-check"><input id="json-wrap" type="checkbox" checked> 긴 줄 자동 줄바꿈</label>
    <label class="json-file" for="json-file">JSON 파일 열기</label>
    <input id="json-file" type="file" accept=".json,application/json">
  </div>

  <div class="json-layout">
    <section class="json-panel" aria-labelledby="json-input-title">
      <div class="json-panel-head">
        <h2 id="json-input-title">입력</h2>
        <button class="json-text-button" id="json-example" type="button">예시</button>
      </div>
      <textarea id="json-input" spellcheck="false" autocomplete="off" placeholder='{"name":"ROSSI TOOLS","enabled":true}'></textarea>
    </section>

    <section class="json-panel" aria-labelledby="json-output-title">
      <div class="json-panel-head">
        <h2 id="json-output-title">결과</h2>
        <span id="json-mode">PRETTY</span>
      </div>
      <div class="json-output is-wrapped" id="json-output" tabindex="0"><code>JSON을 입력하고 정리 버튼을 눌러 주세요.</code></div>
    </section>
  </div>

  <div class="json-actions">
    <button class="primary" id="json-format" type="button">보기 좋게 정리</button>
    <button class="ghost" id="json-tree" type="button">사람이 보기</button>
    <button class="ghost" id="json-minify" type="button">한 줄로 압축</button>
    <button class="ghost" id="json-copy" type="button" disabled>결과 복사</button>
    <button class="ghost" id="json-download" type="button" disabled>JSON 다운로드</button>
    <button class="json-text-button json-clear" id="json-clear" type="button">모두 지우기</button>
  </div>
  <p class="json-status" id="json-status" role="status" aria-live="polite">문자열·숫자·불리언·null 값을 색상으로 구분해 표시합니다.</p>
</section>
<script src="/static/json.js?v=<?= htmlspecialchars($jsonJsVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
