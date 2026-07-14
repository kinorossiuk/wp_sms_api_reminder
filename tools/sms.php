<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/sms.php';

$smsError = null;
$smsNotice = (string) ($_SESSION['sms_notice'] ?? '');
unset($_SESSION['sms_notice']);
$config = rossi_sms_raw_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals((string) $auth['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('요청을 확인할 수 없습니다. 페이지를 새로고침해 주세요.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'sms-save-config') {
            rossi_sms_update_config($_POST);
            $_SESSION['sms_notice'] = 'SMS 설정을 안전하게 저장했습니다.';
            header('Location: /tools/sms/', true, 303);
            exit;
        }
        $readyConfig = rossi_sms_config();
        if ($action === 'sms-schedule') {
            rossi_sms_schedule($readyConfig, (string) ($_POST['recipient'] ?? ''), (string) ($_POST['message'] ?? ''), (string) ($_POST['scheduled_at'] ?? ''));
            $_SESSION['sms_notice'] = 'SOLAPI 예약이 접수되었습니다.';
            header('Location: /tools/sms/', true, 303);
            exit;
        }
        if ($action === 'sms-cancel') {
            rossi_sms_cancel($readyConfig, (string) ($_POST['public_id'] ?? ''));
            $_SESSION['sms_notice'] = '예약 취소 요청을 처리했습니다.';
            header('Location: /tools/sms/', true, 303);
            exit;
        }
        throw new RuntimeException('알 수 없는 SMS 요청입니다.');
    } catch (Throwable $error) {
        $smsError = $error->getMessage();
    }
}

$config = rossi_sms_raw_config();
$configured = false;
$schedules = [];
if ($smsError === null && rossi_sms_is_configured()) {
    try {
        $readyConfig = rossi_sms_config();
        $schedules = rossi_sms_list($readyConfig);
        $configured = true;
    } catch (Throwable $error) {
        $smsError = $error->getMessage();
    }
}
$db = is_array($config['database'] ?? null) ? $config['database'] : [];
$solapi = is_array($config['solapi'] ?? null) ? $config['solapi'] : [];
$limits = is_array($config['limits'] ?? null) ? $config['limits'] : [];
$allowed = is_array($limits['allowed_recipients'] ?? null) ? implode(', ', $limits['allowed_recipients']) : '';
$nowMin = (new DateTimeImmutable('now', new DateTimeZone(ROSSI_SMS_TIMEZONE)))->modify('+2 minutes')->format('Y-m-d\\TH:i');
function rossi_sms_status_label(string $status): string { return match ($status) { 'SCHEDULED' => '예약됨', 'SENDING' => '발송 중', 'COMPLETE' => '완료', 'CANCELLED' => '취소됨', 'FAILED', 'PARTIAL_FAILED' => '실패', 'UNKNOWN' => '확인 필요', default => $status }; }
?>
<link rel="stylesheet" href="/static/sms.css">
<section class="tool-view sms-view">
  <a class="back-link" href="/">← 대시보드로</a>
  <div class="tool-view-icon" aria-hidden="true">✉</div>
  <p class="kicker">SOLAPI / PRIVATE REMINDER</p>
  <h1>SMS<br>예약 발송</h1>
  <p class="tool-intro">개인 알림용 예약 문자입니다. 등록한 발신번호와 허용 수신번호로만 발송하며, 예약 발송 자체는 SOLAPI가 처리합니다.</p>
  <?php if ($smsNotice !== ''): ?><p class="sms-notice" role="status"><?= e($smsNotice) ?></p><?php endif; ?>
  <?php if ($smsError !== null): ?><p class="error sms-error" role="alert"><?= e($smsError) ?></p><?php endif; ?>

  <?php if ($configured): ?>
    <form class="sms-form" method="post">
      <input type="hidden" name="action" value="sms-schedule"><input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
      <label>수신번호 <input name="recipient" required inputmode="tel" placeholder="01012345678" value="<?= e((string) ($_POST['recipient'] ?? '')) ?>"></label>
      <label>예약 시각 (KST) <input name="scheduled_at" type="datetime-local" min="<?= e($nowMin) ?>" required value="<?= e((string) ($_POST['scheduled_at'] ?? '')) ?>"></label>
      <label class="sms-message">메시지 <textarea name="message" required maxlength="2000" placeholder="알림 내용을 입력하세요."><?= e((string) ($_POST['message'] ?? '')) ?></textarea><span>SMS 90바이트 초과 시 LMS로 자동 전환됩니다.</span></label>
      <p class="hint">발신번호: <?= e((string) $solapi['sender']) ?> · 허용 수신번호: <?= e($allowed) ?></p>
      <button class="primary" type="submit">SOLAPI에 예약 접수</button>
    </form>
  <?php endif; ?>

  <details class="sms-settings" <?= $configured ? '' : 'open' ?>><summary>SOLAPI 및 환경 설정 <?= $configured ? '수정' : '시작' ?></summary>
    <form class="sms-form sms-config" method="post" autocomplete="off">
      <input type="hidden" name="action" value="sms-save-config"><input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
      <label>MySQL 호스트 <input name="db_host" required value="<?= e((string) ($db['host'] ?? 'localhost')) ?>"></label>
      <label>MySQL 포트 <input name="db_port" required inputmode="numeric" value="<?= e((string) ($db['port'] ?? '3306')) ?>"></label>
      <label>데이터베이스명 <input name="db_name" required value="<?= e((string) ($db['name'] ?? '')) ?>"></label>
      <label>데이터베이스 사용자 <input name="db_user" required value="<?= e((string) ($db['user'] ?? '')) ?>"></label>
      <label>데이터베이스 비밀번호 <input name="db_password" type="password" <?= $configured ? '' : 'required' ?> placeholder="<?= $configured ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>SOLAPI API Key <input name="solapi_api_key" <?= $configured ? '' : 'required' ?> placeholder="<?= $configured ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>SOLAPI API Secret <input name="solapi_api_secret" type="password" <?= $configured ? '' : 'required' ?> placeholder="<?= $configured ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>발신번호 <input name="solapi_sender" required inputmode="tel" value="<?= e((string) ($solapi['sender'] ?? '')) ?>" placeholder="01012345678"></label>
      <label>허용 수신번호 <input name="allowed_recipients" required value="<?= e($allowed) ?>" placeholder="01012345678, 01098765432"><span>쉼표로 구분합니다. 이 목록 밖 번호는 예약할 수 없습니다.</span></label>
      <label>하루 예약 한도 <input name="daily_max" required inputmode="numeric" value="<?= e((string) ($limits['daily_max'] ?? '20')) ?>"></label>
      <button class="primary" type="submit">설정 안전하게 저장</button>
      <p class="hint">API Secret과 DB 비밀번호는 다시 화면에 표시되지 않습니다. 웹 루트 밖 <code>~/.rossi-tools/sms.php</code>에 600 권한으로 저장됩니다.</p>
    </form>
  </details>

  <?php if ($configured): ?><section class="sms-history"><h2>최근 예약</h2><?php if ($schedules === []): ?><p class="hint">아직 예약된 문자가 없습니다.</p><?php else: ?>
    <?php foreach ($schedules as $item): ?><article class="sms-row"><div><strong><?= e(rossi_sms_status_label((string) $item['status'])) ?></strong><span><?= e((string) $item['scheduled_at_kst']) ?> KST · <?= e((string) $item['recipient']) ?></span><p><?= nl2br(e((string) $item['message'])) ?></p></div><?php if ((string) $item['status'] === 'SCHEDULED'): ?><form method="post"><input type="hidden" name="action" value="sms-cancel"><input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>"><input type="hidden" name="public_id" value="<?= e((string) $item['public_id']) ?>"><button class="ghost" type="submit">취소</button></form><?php endif; ?></article><?php endforeach; ?>
  <?php endif; ?></section><?php endif; ?>
</section>
