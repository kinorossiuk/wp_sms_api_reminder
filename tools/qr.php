<?php
declare(strict_types=1);

$qrCssVersion = (string) (filemtime(__DIR__ . '/../static/qr.css') ?: '1');
?>
<link rel="stylesheet" href="/static/qr.css?v=<?= htmlspecialchars($qrCssVersion, ENT_QUOTES, 'UTF-8') ?>">
<section class="tool-view qr-tool" aria-labelledby="tool-title" data-qr-tool>
  <a class="back-link" href="/">← 모든 도구</a>
  <div class="tool-view-icon" aria-hidden="true">
    <svg viewBox="0 0 32 32"><path d="M3 3h10v10H3V3Zm3 3v4h4V6H6Zm13-3h10v10H19V3Zm3 3v4h4V6h-4ZM3 19h10v10H3V19Zm3 3v4h4v-4H6Zm13-3h4v4h-4v-4Zm6 0h4v4h-4v-4Zm-6 6h4v4h-4v-4Zm6 0h4v4h-4v-4Z"/></svg>
  </div>
  <p class="kicker">GENERATOR / 01</p>
  <h1 id="tool-title">QR 코드<br>생성기</h1>
  <p class="tool-intro">링크나 텍스트를 QR 코드로 만듭니다. 입력값은 브라우저 안에서만 처리되며 서버로 전송되지 않습니다.</p>

  <details class="work-notice"><summary>업무 사용 전 주의사항</summary><ul><li>QR 이미지는 누구나 스캔해 내용을 확인할 수 있습니다. 비밀번호, 인증 토큰, 일회용 링크, 고객정보는 넣지 마세요.</li><li>배포 전에는 QR이 가리키는 링크와 공유 대상을 확인하고, 인쇄물·메신저에 잘못 공유되지 않도록 관리하세요.</li><li>회사 보안 정책과 브라우저 DLP는 생성·다운로드한 QR 파일을 별도로 기록하거나 제한할 수 있습니다.</li></ul></details>

  <div class="qr-layout">
    <form class="qr-controls" id="qr-form" novalidate>
      <div class="qr-field">
        <label for="qr-kind">내용 종류</label>
        <select id="qr-kind" name="kind">
          <option value="url">웹 링크 (URL)</option>
          <option value="text">일반 텍스트</option>
        </select>
      </div>

      <div class="qr-field">
        <label for="qr-data">내용</label>
        <textarea id="qr-data" name="data" rows="6" maxlength="2000" required placeholder="https://example.com"></textarea>
        <p class="qr-help" id="qr-data-help">http 또는 https 링크를 입력하세요. example.com처럼 입력하면 https://를 붙입니다.</p>
      </div>

      <div class="qr-options">
        <div class="qr-field">
          <label for="qr-level">오류 복원</label>
          <select id="qr-level" name="level">
            <option value="L">낮음 · 약 7%</option>
            <option value="M" selected>보통 · 약 15%</option>
            <option value="Q">높음 · 약 25%</option>
            <option value="H">최고 · 약 30%</option>
          </select>
        </div>
        <div class="qr-field">
          <label for="qr-size">다운로드 크기</label>
          <select id="qr-size" name="size">
            <option value="256">256 × 256</option>
            <option value="512" selected>512 × 512</option>
            <option value="1024">1024 × 1024</option>
          </select>
        </div>
        <div class="qr-field">
          <label for="qr-foreground">전경색</label>
          <input id="qr-foreground" name="foreground" type="color" value="#111310">
        </div>
        <div class="qr-field">
          <label for="qr-background">배경색</label>
          <input id="qr-background" name="background" type="color" value="#ffffff">
        </div>
      </div>

      <div class="qr-actions">
        <button class="primary" type="submit">QR 코드 만들기</button>
        <button class="ghost qr-reset" type="reset">초기화</button>
      </div>
      <p class="qr-status" id="qr-status" role="status" aria-live="polite">내용을 입력하면 미리보기가 생성됩니다.</p>
    </form>

    <aside class="qr-output" aria-label="QR 코드 미리보기">
      <div class="qr-preview" id="qr-preview" aria-live="polite"><span>QR</span></div>
      <div class="qr-downloads">
        <button class="ghost" id="qr-download-png" type="button" disabled>PNG 다운로드</button>
        <button class="ghost" id="qr-download-svg" type="button" disabled>SVG 다운로드</button>
      </div>
      <p class="qr-help">흰 여백(quiet zone) 4칸을 포함해 생성합니다. 인쇄·공유 전 휴대폰 카메라로 한 번 확인하세요.</p>
    </aside>
  </div>
</section>
<script src="/static/vendor/qrcode-generator.js" defer></script>
<script src="/static/qr.js" defer></script>
