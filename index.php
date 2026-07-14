<?php
declare(strict_types=1);

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Hello, World!</title>
  <style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
      min-height: 100vh;
      margin: 0;
      display: grid;
      place-items: center;
      background: #151714;
      color: #f4f2eb;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    main { width: min(92vw, 680px); padding: 3rem 0; }
    .mark { color: #d7ff5f; font: 700 .75rem/1 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .14em; }
    h1 { margin: 1rem 0 .75rem; font-size: clamp(3rem, 10vw, 6.5rem); line-height: .9; letter-spacing: -.08em; }
    p { color: #b8bbb2; font-size: 1rem; line-height: 1.7; }
    .status { margin-top: 2.5rem; padding-top: 1rem; border-top: 1px solid #454840; color: #d7ff5f; font: .75rem ui-monospace, SFMono-Regular, Menlo, monospace; }
  </style>
</head>
<body>
  <main>
    <div class="mark">ROSSIUK.XYZ / PHP HOSTING</div>
    <h1>Hello,<br>World!</h1>
    <p>PHP 웹호스팅 배포 확인용 첫 화면입니다.</p>
    <div class="status">● ONLINE · <?= htmlspecialchars($now->format('Y-m-d H:i KST'), ENT_QUOTES, 'UTF-8') ?></div>
  </main>
</body>
</html>
