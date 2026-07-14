<?php
declare(strict_types=1);

require __DIR__ . '/app/auth.php';

$auth = rossi_auth_gate();
$status = (string) ($auth['status'] ?? 'setup');
$nonce = htmlspecialchars((string) ($auth['nonce'] ?? ''), ENT_QUOTES, 'UTF-8');
$tools = require __DIR__ . '/app/tools.php';
$requestedTool = trim((string) ($_GET['tool'] ?? ''));
$currentTool = $requestedTool !== '' && isset($tools[$requestedTool]) ? $requestedTool : '';

if ($status === 'authenticated' && $requestedTool !== '' && $currentTool === '') {
    http_response_code(404);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $status === 'authenticated' ? ($currentTool !== '' ? e($tools[$currentTool]['name']) . ' · ROSSI TOOLS' : 'ROSSI TOOLS') : 'Access · ROSSI TOOLS' ?></title>
  <style nonce="<?= $nonce ?>">
    :root { color-scheme:dark; --bg:#111310; --surface:#191c18; --surface-2:#20241f; --text:#f5f3eb; --muted:#a6aca1; --acid:#d7ff5f; --line:#363c33; --danger:#ff8c78; }
    * { box-sizing:border-box; }
    html { min-height:100%; }
    body { min-height:100vh; margin:0; background:var(--bg); color:var(--text); font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
    a { color:inherit; }
    button,input { font:inherit; }
    .shell { width:min(1180px,92vw); margin:0 auto; padding:0 0 5rem; }
    .topbar { min-height:82px; display:flex; align-items:center; gap:1rem; border-bottom:1px solid var(--line); }
    .brand { color:var(--text); text-decoration:none; font-size:1.1rem; font-weight:850; letter-spacing:-.04em; }
    .brand span { color:var(--acid); }
    .global-nav { display:flex; align-items:center; gap:.3rem; margin-left:auto; }
    .global-nav a { border:1px solid transparent; color:var(--muted); padding:.55rem .65rem; text-decoration:none; font:.7rem ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.04em; }
    .global-nav a:hover,.global-nav a[aria-current="page"] { border-color:var(--line); color:var(--acid); background:var(--surface); }
    .logout-form { margin:0; }
    .ghost { margin:0; border:1px solid var(--line); background:transparent; color:var(--muted); padding:.65rem .85rem; cursor:pointer; }
    .hero { padding:clamp(4rem,9vw,8rem) 0 4rem; display:grid; grid-template-columns:1fr auto; align-items:end; gap:2rem; }
    .kicker,.mark { color:var(--acid); font:700 .72rem/1.4 ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.13em; }
    .hero h1,.tool-view h1 { margin:.9rem 0 1rem; font-size:clamp(3.5rem,9vw,7.5rem); line-height:.87; letter-spacing:-.085em; }
    .hero-copy { max-width:34rem; color:var(--muted); font-size:1rem; line-height:1.7; }
    .tool-count { min-width:108px; padding:1rem; border:1px solid var(--line); color:var(--muted); font:.72rem/1.5 ui-monospace,SFMono-Regular,Menlo,monospace; }
    .tool-count strong { display:block; color:var(--text); font-size:2rem; line-height:1; margin-bottom:.45rem; }
    .section-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
    .section-head h2 { margin:0; font-size:.88rem; letter-spacing:-.02em; }
    .section-head span { color:var(--muted); font:.7rem ui-monospace,SFMono-Regular,Menlo,monospace; }
    .tool-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }
    .tool-card { min-height:260px; display:flex; flex-direction:column; padding:1.35rem; border:1px solid var(--line); background:var(--surface); text-decoration:none; transition:border-color .18s ease,transform .18s ease,background .18s ease; }
    .tool-card:hover { border-color:var(--acid); background:var(--surface-2); transform:translateY(-3px); }
    .tool-card-top { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; }
    .tool-icon { width:52px; height:52px; display:grid; place-items:center; background:var(--acid); color:#10120f; }
    .tool-icon svg { width:30px; height:30px; fill:currentColor; }
    .badge { padding:.35rem .5rem; border:1px solid var(--line); color:var(--muted); font:.62rem ui-monospace,SFMono-Regular,Menlo,monospace; }
    .tool-card-body { margin-top:auto; }
    .tool-category { color:var(--acid); font:.67rem ui-monospace,SFMono-Regular,Menlo,monospace; }
    .tool-card h3 { margin:.5rem 0 .45rem; font-size:1.45rem; letter-spacing:-.05em; }
    .tool-card p { margin:0; color:var(--muted); font-size:.85rem; line-height:1.55; }
    .tool-card-action { margin-top:1.25rem; display:flex; justify-content:space-between; color:var(--text); font-size:.78rem; font-weight:750; }
    .access-page { min-height:100vh; display:grid; place-items:center; }
    .access-main { width:min(92vw,680px); padding:3rem 0; }
    .access-main h1 { margin:1rem 0 .75rem; font-size:clamp(3rem,10vw,6.5rem); line-height:.9; letter-spacing:-.08em; }
    .access-main p { color:var(--muted); font-size:1rem; line-height:1.7; }
    .panel { margin-top:2rem; padding:1.4rem; border:1px solid var(--line); background:var(--surface); }
    .panel label { display:block; margin-bottom:.65rem; color:var(--muted); font-size:.8rem; }
    .panel input { width:100%; border:1px solid var(--line); border-radius:0; background:#0c0e0c; color:var(--text); padding:.9rem 1rem; outline:none; }
    .panel input:focus { border-color:var(--acid); }
    .primary { margin-top:.85rem; border:0; background:var(--acid); color:#10120f; padding:.85rem 1.1rem; font-weight:750; cursor:pointer; }
    .error { color:var(--danger)!important; font-size:.85rem!important; }
    .hint,.status-line { font:.75rem/1.6 ui-monospace,SFMono-Regular,Menlo,monospace; }
    .status-line { margin-top:2.5rem; padding-top:1rem; border-top:1px solid var(--line); color:var(--acid)!important; }
    .tool-view { padding:clamp(3rem,8vw,7rem) 0; max-width:820px; }
    .back-link { display:inline-block; margin-bottom:4rem; color:var(--muted); text-decoration:none; font-size:.8rem; }
    .back-link:hover { color:var(--acid); }
    .tool-view-icon { width:76px; height:76px; display:grid; place-items:center; margin-bottom:2rem; color:#10120f; background:var(--acid); }
    .tool-view-icon svg { width:44px; fill:currentColor; }
    .tool-intro { max-width:38rem; color:var(--muted); font-size:1rem; line-height:1.7; }
    .coming-soon { display:inline-block; margin-top:2rem; padding:.75rem 1rem; border:1px solid var(--line); color:var(--muted); font:.72rem ui-monospace,SFMono-Regular,Menlo,monospace; }
    .not-found { padding:8rem 0; }
    @media (max-width:760px) { .hero { grid-template-columns:1fr; } .tool-count { width:max-content; } .tool-grid { grid-template-columns:1fr; } .tool-card { min-height:230px; } .topbar { min-height:70px; gap:.45rem; } .global-nav { order:3; width:100%; margin:0; overflow-x:auto; padding-bottom:.65rem; } .global-nav a { flex:0 0 auto; } }
  </style>
</head>
<body class="<?= $status === 'authenticated' ? '' : 'access-page' ?>">
<?php if ($status === 'authenticated'): ?>
  <div class="shell">
    <header class="topbar">
      <a class="brand" href="/">ROSSI<span>•</span>TOOLS</a>
      <nav class="global-nav" aria-label="도구 바로가기">
        <a href="/tools/qr/"<?= $currentTool === 'qr' ? ' aria-current="page"' : '' ?>>QR</a>
        <a href="/tools/sms/"<?= $currentTool === 'sms' ? ' aria-current="page"' : '' ?>>SMS</a>
        <a href="/tools/test-data/"<?= $currentTool === 'test-data' ? ' aria-current="page"' : '' ?>>Test Data</a>
      </nav>
      <form class="logout-form" method="post" action="/">
        <input type="hidden" name="action" value="logout">
        <input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
        <button class="ghost" type="submit">로그아웃</button>
      </form>
    </header>

    <?php if ($requestedTool !== '' && $currentTool === ''): ?>
      <section class="not-found"><p class="kicker">404 / NOT FOUND</p><h1>도구를 찾을 수 없습니다.</h1><a class="back-link" href="/">← 대시보드로</a></section>
    <?php elseif ($currentTool !== ''): ?>
      <?php require __DIR__ . '/tools/' . $currentTool . '.php'; ?>
    <?php else: ?>
      <section class="hero">
        <div>
          <p class="kicker">PRIVATE UTILITY DASHBOARD</p>
          <h1>Simple tools,<br>ready to use.</h1>
          <p class="hero-copy">자주 필요한 작업을 빠르게 끝내는 개인용 편의 도구 모음입니다. 입력한 데이터는 가능한 한 브라우저 안에서만 처리합니다.</p>
        </div>
        <div class="tool-count"><strong><?= count($tools) ?></strong>TOOLS AVAILABLE</div>
      </section>

      <section aria-labelledby="all-tools-title">
        <div class="section-head"><h2 id="all-tools-title">모든 도구</h2><span>ALL TOOLS</span></div>
        <div class="tool-grid">
          <?php foreach ($tools as $slug => $tool): ?>
            <a class="tool-card" href="<?= e($tool['path']) ?>">
              <div class="tool-card-top">
                <div class="tool-icon" aria-hidden="true">
                  <?php if ($slug === 'qr'): ?><svg viewBox="0 0 32 32"><path d="M3 3h10v10H3V3Zm3 3v4h4V6H6Zm13-3h10v10H19V3Zm3 3v4h4V6h-4ZM3 19h10v10H3V19Zm3 3v4h4v-4H6Zm13-3h4v4h-4v-4Zm6 0h4v4h-4v-4Zm-6 6h4v4h-4v-4Zm6 0h4v4h-4v-4Z"/></svg><?php elseif ($slug === 'test-data'): ?><svg viewBox="0 0 32 32"><path d="M6 3h14l6 6v20H6V3Zm12 2.8V11h5.2L18 5.8ZM10 15h12v2H10v-2Zm0 5h12v2H10v-2Zm0 5h8v2h-8v-2Z"/></svg><?php endif; ?>
                </div>
                <span class="badge"><?= e($tool['status']) ?></span>
              </div>
              <div class="tool-card-body">
                <span class="tool-category"><?= e($tool['category']) ?></span>
                <h3><?= e($tool['name']) ?></h3>
                <p><?= e($tool['description']) ?></p>
                <div class="tool-card-action"><span>도구 열기</span><span>↗</span></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
<?php else: ?>
  <main class="access-main">
    <?php if ($status === 'login'): ?>
      <div class="mark">ROSSI TOOLS / RESTRICTED</div>
      <h1>Private<br>access.</h1>
      <p>현재 준비 중인 사이트입니다. 접근 비밀번호를 입력해 주세요.</p>
      <form class="panel" method="post" autocomplete="on">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
        <label for="password">PASSWORD</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="current-password" maxlength="4096">
        <?php if (!empty($auth['error'])): ?><p class="error" role="alert"><?= e((string) $auth['error']) ?></p><?php endif; ?>
        <button class="primary" type="submit">접속하기</button>
        <p class="hint">남은 시도: <?= (int) ($auth['remaining'] ?? 0) ?> / 5</p>
      </form>
    <?php elseif ($status === 'blocked'): ?>
      <div class="mark">ROSSI TOOLS / BLOCKED</div><h1>Access<br>blocked.</h1>
      <p>로그인 시도가 너무 많아 이 IP의 접속이 일시적으로 차단되었습니다.</p>
      <p class="status-line">재시도 가능: 약 <?= max(1, (int) ceil(((int) $auth['blocked_seconds']) / 60)) ?>분 후</p>
    <?php elseif ($status === 'permanently-blocked'): ?>
      <div class="mark">ROSSI TOOLS / FORBIDDEN</div><h1>Access<br>denied.</h1>
      <p>비정상적인 반복 로그인 시도로 이 IP의 접근이 차단되었습니다.</p>
    <?php else: ?>
      <div class="mark">ROSSI TOOLS / OFFLINE</div><h1>Setup<br>required.</h1>
      <p><?= $status === 'storage-error' ? '로그인 보안 저장소를 사용할 수 없습니다.' : '사이트 보안 설정이 아직 완료되지 않았습니다.' ?></p>
    <?php endif; ?>
  </main>
<?php endif; ?>
</body>
</html>
