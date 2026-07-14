<?php
declare(strict_types=1);

require __DIR__ . '/app/auth.php';

$auth = rossi_auth_gate();
$status = (string) ($auth['status'] ?? 'setup');
$nonce = htmlspecialchars((string) ($auth['nonce'] ?? ''), ENT_QUOTES, 'UTF-8');
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));

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
  <title><?= $status === 'authenticated' ? 'ROSSI TOOLS' : 'Access · ROSSI TOOLS' ?></title>
  <style nonce="<?= $nonce ?>">
    :root { color-scheme: dark; --bg:#151714; --card:#1e211d; --text:#f4f2eb; --muted:#a9aea4; --acid:#d7ff5f; --line:#3b4038; --danger:#ff8c78; }
    * { box-sizing: border-box; }
    body { min-height:100vh; margin:0; display:grid; place-items:center; background:var(--bg); color:var(--text); font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
    main { width:min(92vw,680px); padding:3rem 0; }
    .mark { color:var(--acid); font:700 .75rem/1 ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; }
    h1 { margin:1rem 0 .75rem; font-size:clamp(3rem,10vw,6.5rem); line-height:.9; letter-spacing:-.08em; }
    p { color:var(--muted); font-size:1rem; line-height:1.7; }
    .panel { margin-top:2rem; padding:1.4rem; border:1px solid var(--line); background:var(--card); }
    label { display:block; margin-bottom:.65rem; color:var(--muted); font-size:.8rem; }
    input { width:100%; border:1px solid var(--line); border-radius:0; background:#10120f; color:var(--text); padding:.9rem 1rem; font:1rem inherit; outline:none; }
    input:focus { border-color:var(--acid); }
    button { margin-top:.85rem; border:0; background:var(--acid); color:#10120f; padding:.85rem 1.1rem; font:700 .85rem inherit; cursor:pointer; }
    .ghost { margin:0; border:1px solid var(--line); background:transparent; color:var(--muted); }
    .error { color:var(--danger); font-size:.85rem; }
    .hint,.status { font:.75rem/1.6 ui-monospace,SFMono-Regular,Menlo,monospace; }
    .status { margin-top:2.5rem; padding-top:1rem; border-top:1px solid var(--line); color:var(--acid); }
    .top { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
  </style>
</head>
<body>
  <main>
    <?php if ($status === 'authenticated'): ?>
      <div class="top">
        <div class="mark">ROSSI TOOLS / PRIVATE BETA</div>
        <form method="post">
          <input type="hidden" name="action" value="logout">
          <input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
          <button class="ghost" type="submit">로그아웃</button>
        </form>
      </div>
      <h1>Hello,<br>World!</h1>
      <p>보호된 편의 도구 플랫폼의 첫 화면입니다.</p>
      <div class="status">● AUTHENTICATED · <?= e($now->format('Y-m-d H:i KST')) ?></div>
    <?php elseif ($status === 'login'): ?>
      <div class="mark">ROSSI TOOLS / RESTRICTED</div>
      <h1>Private<br>access.</h1>
      <p>현재 준비 중인 사이트입니다. 접근 비밀번호를 입력해 주세요.</p>
      <form class="panel" method="post" autocomplete="on">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
        <label for="password">PASSWORD</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="current-password" maxlength="4096">
        <?php if (!empty($auth['error'])): ?><p class="error" role="alert"><?= e((string) $auth['error']) ?></p><?php endif; ?>
        <button type="submit">접속하기</button>
        <p class="hint">남은 시도: <?= (int) ($auth['remaining'] ?? 0) ?> / 5</p>
      </form>
    <?php elseif ($status === 'blocked'): ?>
      <div class="mark">ROSSI TOOLS / BLOCKED</div>
      <h1>Access<br>blocked.</h1>
      <p>로그인 시도가 너무 많아 이 IP의 접속이 일시적으로 차단되었습니다.</p>
      <div class="status">재시도 가능: 약 <?= max(1, (int) ceil(((int) $auth['blocked_seconds']) / 60)) ?>분 후</div>
    <?php else: ?>
      <div class="mark">ROSSI TOOLS / OFFLINE</div>
      <h1>Setup<br>required.</h1>
      <p><?= $status === 'storage-error' ? '로그인 보안 저장소를 사용할 수 없습니다.' : '사이트 보안 설정이 아직 완료되지 않았습니다.' ?></p>
    <?php endif; ?>
  </main>
</body>
</html>
