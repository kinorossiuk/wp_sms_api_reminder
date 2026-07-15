<?php
declare(strict_types=1);

$temporaryNoteCssVersion = (string) (filemtime(__DIR__ . '/../static/temporary-note.css') ?: '1');
$temporaryNoteJsVersion = (string) (filemtime(__DIR__ . '/../static/temporary-note.js') ?: '1');
?>
<link rel="stylesheet" href="/static/temporary-note.css?v=<?= htmlspecialchars($temporaryNoteCssVersion, ENT_QUOTES, 'UTF-8') ?>">
<section class="tool-view temporary-note-tool" aria-labelledby="tool-title" data-temporary-note-tool>
  <a class="back-link" href="/">← 모든 도구</a>
  <div class="tool-view-icon" aria-hidden="true">✎</div>
  <p class="kicker">PRIVATE / LOCAL ONLY</p>
  <h1 id="tool-title">임시<br>메모</h1>
  <p class="tool-intro">짧은 메모를 현재 브라우저에만 저장합니다. 서버로 전송되지 않으며, 저장한 메모는 다음 자정(00:00 KST)에 자동 삭제됩니다.</p>
  <section class="temporary-note-notice" aria-label="저장 및 삭제 안내">
    <div><strong>로컬 저장</strong><span>이 브라우저의 저장 공간에만 보관됩니다. 다른 기기·브라우저에서는 볼 수 없습니다.</span></div>
    <div><strong>매일 자정 삭제</strong><span>메모는 다음 날 00:00 KST에 자동 삭제됩니다. 삭제된 메모는 복구할 수 없습니다.</span></div>
  </section>
  <div class="temporary-note-editor">
    <label for="temporary-note-input">새 메모</label>
    <textarea id="temporary-note-input" maxlength="5000" spellcheck="true" placeholder="잠깐 보관할 내용을 입력하세요. 이모지도 사용할 수 있어요. ✨"></textarea>
    <div class="temporary-note-actions"><button class="primary" id="temporary-note-save" type="button">메모 저장</button><span id="temporary-note-count">0 / 5,000</span></div>
  </div>
  <section class="temporary-note-list" aria-labelledby="temporary-note-list-title">
    <div class="temporary-note-list-head"><div><h2 id="temporary-note-list-title">오늘의 메모</h2><p id="temporary-note-expiry">다음 자정에 자동 삭제됩니다.</p></div><button class="ghost" id="temporary-note-clear" type="button" disabled>모두 삭제</button></div>
    <div id="temporary-note-items" aria-live="polite"></div>
  </section>
  <p class="temporary-note-status" id="temporary-note-status" role="status" aria-live="polite">메모는 현재 브라우저에만 저장됩니다.</p>
</section>
<script src="/static/temporary-note.js?v=<?= htmlspecialchars($temporaryNoteJsVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
