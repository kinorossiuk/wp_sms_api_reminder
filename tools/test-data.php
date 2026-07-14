<?php
declare(strict_types=1);
?>
<?php
$testDataCssVersion = (string) (filemtime(__DIR__ . '/../static/test-data.css') ?: '1');
$testDataJsVersion = (string) (filemtime(__DIR__ . '/../static/test-data.js') ?: '1');
?>
<link rel="stylesheet" href="/static/test-data.css?v=<?= htmlspecialchars($testDataCssVersion, ENT_QUOTES, 'UTF-8') ?>">
<section class="tool-view test-data-view" aria-labelledby="tool-title" data-test-data-tool>
  <a class="back-link" href="/">← 모든 도구</a>
  <div class="tool-view-icon" aria-hidden="true">▣</div>
  <p class="kicker">LOCAL / TEST DATA</p>
  <h1 id="tool-title">테스트 데이터<br>생성기</h1>
  <p class="tool-intro">파일과 데이터는 브라우저에서만 만들고 바로 내려받습니다. 서버에 저장하거나 전송하지 않습니다.</p>

  <div class="test-tabs" role="tablist" aria-label="생성기 종류">
    <button class="is-active" type="button" role="tab" aria-selected="true" aria-controls="file-panel" id="file-tab" data-tab="file">용량별 파일</button>
    <button type="button" role="tab" aria-selected="false" aria-controls="message-panel" id="message-tab" data-tab="message">문자 길이</button>
    <button type="button" role="tab" aria-selected="false" aria-controls="contact-panel" id="contact-tab" data-tab="contact">더미 연락처</button>
  </div>

  <section class="test-panel" id="file-panel" role="tabpanel" aria-labelledby="file-tab" data-panel="file">
    <form class="test-form" id="test-file-form">
      <label>파일 종류
        <select id="file-kind" name="kind">
          <option value="png">PNG 이미지</option>
          <option value="txt">TXT 문서</option>
          <option value="pdf">PDF 문서</option>
          <option value="docx">DOCX 문서</option>
          <option value="xlsx">XLSX 스프레드시트</option>
          <option value="mp4">MP4 동영상 (H.264)</option>
          <option value="webm">WebM 동영상</option>
        </select>
      </label>
      <label>목표 용량
        <div class="size-input"><input id="file-size" name="size" type="number" min="1" max="300" value="1" required inputmode="decimal"><select id="file-unit" name="unit"><option value="MiB">MiB</option><option value="MB">MB</option></select></div>
      </label>
      <label>파일명
        <input id="file-name" name="name" maxlength="60" value="test-file" required>
      </label>
      <label class="file-image-option">이미지 크기
        <select id="image-dimension" name="dimension"><option value="800x600">800 × 600</option><option value="1280x720">1280 × 720</option><option value="1920x1080">1920 × 1080</option></select>
      </label>
      <p class="test-hint" id="file-hint">PNG, TXT, PDF, DOCX, XLSX는 목표 바이트에 맞춰 생성합니다. WebM은 최신 Chrome·Edge에서 지원됩니다.</p>
      <button class="primary" type="submit">파일 생성 및 다운로드</button>
      <p class="test-status" id="file-status" role="status" aria-live="polite">모든 파일은 최대 300 MiB까지 만들 수 있습니다. 큰 파일은 충분한 브라우저 메모리가 필요합니다.</p>
    </form>
  </section>

  <section class="test-panel" id="message-panel" role="tabpanel" aria-labelledby="message-tab" data-panel="message" hidden>
    <form class="test-form" id="test-message-form">
      <label>문자 수
        <div class="size-input"><input id="message-count" type="number" min="1" max="2000" value="500" required inputmode="numeric"><select id="message-preset"><option value="custom">직접 입력</option><option value="499">499자 이하</option><option value="500">정확히 500자</option><option value="501">501자 이상</option></select></div>
      </label>
      <label>앞에 붙일 문구 <input id="message-prefix" maxlength="80" value="테스트 문자 "></label>
      <label class="wide-field">생성 결과 <textarea id="message-output" rows="8" readonly></textarea></label>
      <div class="test-actions"><button class="primary" type="submit">문자 만들기</button><button class="ghost" id="message-copy" type="button">복사</button></div>
      <p class="test-status" id="message-status" role="status" aria-live="polite">한글·이모지를 한 글자로 계산하며 UTF-8 바이트도 함께 표시합니다.</p>
    </form>
  </section>

  <section class="test-panel" id="contact-panel" role="tabpanel" aria-labelledby="contact-tab" data-panel="contact" hidden>
    <form class="test-form" id="test-contact-form">
      <label>생성 개수 <input id="contact-count" type="number" min="1" max="1000" value="10" required inputmode="numeric"></label>
      <label>전화번호 시작값 <input id="contact-prefix" value="010" maxlength="3" inputmode="numeric"></label>
      <label class="wide-field">미리보기</label>
      <div class="contact-preview wide-field" id="contact-preview" aria-live="polite"></div>
      <div class="test-actions"><button class="primary" type="submit">연락처 만들기</button><button class="ghost" id="contact-csv" type="button" disabled>CSV 다운로드</button></div>
      <p class="test-status" id="contact-status" role="status" aria-live="polite">가상의 이름·전화번호·이메일만 생성합니다. 실제 인물 정보는 사용하지 않습니다.</p>
    </form>
  </section>
</section>
<script defer src="/static/test-data.js?v=<?= htmlspecialchars($testDataJsVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
