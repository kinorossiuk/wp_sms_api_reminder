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
            $smsNotice = 'SMS 설정을 안전하게 저장했습니다.';
            $_POST = [];
        } elseif ($action === 'sms-schedule') {
            $readyConfig = rossi_sms_config();
            rossi_sms_schedule($readyConfig, (string) ($_POST['recipient'] ?? ''), (string) ($_POST['message'] ?? ''), (string) ($_POST['scheduled_at'] ?? ''));
            $smsNotice = 'SOLAPI 예약이 접수되었습니다.';
            $_POST = [];
        } elseif ($action === 'sms-send-now') {
            $readyConfig = rossi_sms_config();
            rossi_sms_send_now($readyConfig, (string) ($_POST['recipient'] ?? ''), (string) ($_POST['message'] ?? ''));
            $smsNotice = 'SOLAPI에 즉시 발송을 접수했습니다.';
            $_POST = [];
        } elseif ($action === 'sms-cancel') {
            $readyConfig = rossi_sms_config();
            rossi_sms_cancel($readyConfig, (string) ($_POST['public_id'] ?? ''));
            $smsNotice = '예약 취소 요청을 처리했습니다.';
            $_POST = [];
        } elseif ($action === 'sms-refresh-status') {
            $readyConfig = rossi_sms_config();
            $result = rossi_sms_sync_statuses($readyConfig, 10);
            $smsNotice = '발송 상태를 확인했습니다. (' . $result['checked'] . '건 조회)';
            $_POST = [];
        } else {
            throw new RuntimeException('알 수 없는 SMS 요청입니다.');
        }
    } catch (Throwable $error) {
        $smsError = $error->getMessage();
    }
}

$config = rossi_sms_raw_config();
$configured = false;
$schedules = [];
$accountSummary = null;
if (rossi_sms_is_configured()) {
    try {
        $readyConfig = rossi_sms_config();
        rossi_sms_sync_statuses($readyConfig, 10);
        $schedules = rossi_sms_list($readyConfig);
        $configured = true;
    } catch (Throwable $error) {
        if ($smsError === null) {
            $smsError = $error->getMessage();
        }
    }
}
if ($configured) {
    try {
        $accountSummary = rossi_sms_account_summary($readyConfig);
    } catch (Throwable) {
        // 잔액 API가 일시적으로 실패해도 예약 기능은 계속 제공한다.
    }
}
$db = is_array($config['database'] ?? null) ? $config['database'] : [];
$solapi = is_array($config['solapi'] ?? null) ? $config['solapi'] : [];
$limits = is_array($config['limits'] ?? null) ? $config['limits'] : [];
$hasDbHost = trim((string) ($db['host'] ?? '')) !== '';
$hasDbPort = (int) ($db['port'] ?? 0) > 0;
$hasDbName = trim((string) ($db['name'] ?? '')) !== '';
$hasDbUser = trim((string) ($db['user'] ?? '')) !== '';
$allowed = is_array($limits['allowed_recipients'] ?? null) ? implode(', ', $limits['allowed_recipients']) : '';
$nowMin = (new DateTimeImmutable('now', new DateTimeZone(ROSSI_SMS_TIMEZONE)))->modify('+1 minute')->format('Y-m-d\\TH:i:s');
function rossi_sms_status_label(string $status): string { return match ($status) { 'SCHEDULED' => '예약됨', 'SENDING' => '발송 중', 'COMPLETE' => '완료', 'CANCELLED' => '취소됨', 'FAILED', 'PARTIAL_FAILED' => '실패', 'UNKNOWN' => '확인 필요', default => $status }; }
$smsCssVersion = (string) (filemtime(__DIR__ . '/../static/sms.css') ?: '1');
?>
<link rel="stylesheet" href="/static/sms.css?v=<?= htmlspecialchars($smsCssVersion, ENT_QUOTES, 'UTF-8') ?>">
<script defer src="/static/sms.js"></script>
<section class="tool-view sms-view">
  <a class="back-link" href="/">← 대시보드로</a>
  <div class="tool-view-icon" aria-hidden="true">✉</div>
  <p class="kicker">SOLAPI / PRIVATE REMINDER</p>
  <h1>SMS<br>예약 발송</h1>
  <p class="tool-intro">개인 알림용 예약 문자입니다. 등록한 발신번호와 허용 수신번호로만 발송하며, 예약 발송 자체는 SOLAPI가 처리합니다.</p>
  <details class="work-notice"><summary>업무 사용 전 주의사항</summary><ul><li>수신 동의, 발신번호 등록, 회사의 문자 발송 정책을 확인한 뒤 승인된 수신자에게만 발송하세요.</li><li>문자 내용과 수신번호는 SOLAPI로 전송되며 통신 비용이 발생할 수 있습니다. 비밀번호·인증코드·민감정보는 문자에 넣지 마세요.</li><li>API Key, API Secret, 데이터베이스 비밀번호는 관리자만 설정하고 회사의 비밀 관리·교체 정책을 따라야 합니다.</li></ul></details>
  <?php if ($smsNotice !== ''): ?><p class="sms-notice" role="status"><?= e($smsNotice) ?></p><?php endif; ?>
  <?php if ($smsError !== null): ?><p class="error sms-error" role="alert"><?= e($smsError) ?></p><?php endif; ?>

  <?php if ($configured): ?>
    <?php if ($accountSummary !== null): ?><section class="sms-balance" aria-label="SOLAPI 잔액 및 발송 가능 건수">
      <div><span>사용 가능 잔액</span><strong><?= number_format((float) $accountSummary['balance']) ?>원</strong><small>포인트 <?= number_format((float) $accountSummary['point']) ?>원 별도</small></div>
      <div><span>SMS 단가 / 예상 건수</span><strong><?= number_format((float) $accountSummary['sms_price']) ?>원 · <?= number_format((int) $accountSummary['sms_count']) ?>건</strong><small>현금·예치금 기준의 예상치</small></div>
      <div><span>LMS 단가 / 예상 건수</span><strong><?= number_format((float) $accountSummary['lms_price']) ?>원 · <?= number_format((int) $accountSummary['lms_count']) ?>건</strong><small>실제 과금은 SOLAPI 최종 처리 기준</small></div>
    </section><?php endif; ?>
    <form class="sms-form" method="post">
      <input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
      <label>수신번호 <input name="recipient" required inputmode="tel" placeholder="01012345678" value="<?= e((string) ($_POST['recipient'] ?? '')) ?>"></label>
      <label>예약 시각 (KST) <input name="scheduled_at" type="datetime-local" step="1" min="<?= e($nowMin) ?>" required value="<?= e((string) ($_POST['scheduled_at'] ?? '')) ?>"></label>
      <label class="sms-message">메시지 <textarea name="message" required maxlength="2000" placeholder="알림 내용을 입력하세요."><?= e((string) ($_POST['message'] ?? '')) ?></textarea><span>SMS 90바이트 초과 시 LMS로 자동 전환됩니다.</span></label>
      <p class="hint">발신번호: <?= e((string) $solapi['sender']) ?> · 허용 수신번호: <?= e($allowed) ?></p>
      <div class="sms-actions"><button class="primary" type="submit" name="action" value="sms-schedule">SOLAPI에 예약 접수</button><button class="ghost" type="submit" name="action" value="sms-send-now" formnovalidate>지금 즉시 발송</button></div>
    </form>
  <?php endif; ?>

  <details class="sms-settings" <?= $configured ? '' : 'open' ?>><summary>SOLAPI 및 환경 설정 <?= $configured ? '수정' : '시작' ?></summary>
    <form class="sms-form sms-config" method="post" autocomplete="off">
      <input type="hidden" name="action" value="sms-save-config"><input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>">
      <label>MySQL 호스트 <input name="db_host" <?= $hasDbHost ? '' : 'required' ?> value="<?= $hasDbHost ? '' : 'localhost' ?>" placeholder="<?= $hasDbHost ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>MySQL 포트 <input name="db_port" <?= $hasDbPort ? '' : 'required' ?> inputmode="numeric" value="<?= $hasDbPort ? '' : '3306' ?>" placeholder="<?= $hasDbPort ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>데이터베이스명 <input name="db_name" <?= $hasDbName ? '' : 'required' ?> value="" placeholder="<?= $hasDbName ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>데이터베이스 사용자 <input name="db_user" <?= $hasDbUser ? '' : 'required' ?> value="" placeholder="<?= $hasDbUser ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>데이터베이스 비밀번호 <input name="db_password" type="password" <?= $configured ? '' : 'required' ?> placeholder="<?= $configured ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>SOLAPI API Key <input name="solapi_api_key" <?= $configured ? '' : 'required' ?> placeholder="<?= $configured ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>SOLAPI API Secret <input name="solapi_api_secret" type="password" <?= $configured ? '' : 'required' ?> placeholder="<?= $configured ? '비워두면 현재 값 유지' : '' ?>"></label>
      <label>발신번호 <input name="solapi_sender" required inputmode="tel" value="<?= e((string) ($solapi['sender'] ?? '')) ?>" placeholder="01012345678"></label>
      <label>허용 수신번호 <input name="allowed_recipients" required value="<?= e($allowed) ?>" placeholder="01012345678, 01098765432"><span>쉼표로 구분합니다. 이 목록 밖 번호는 예약할 수 없습니다.</span></label>
      <label>하루 예약 한도 <input name="daily_max" required inputmode="numeric" value="<?= e((string) ($limits['daily_max'] ?? '20')) ?>"></label>
      <button class="primary" type="submit">설정 안전하게 저장</button>
      <p class="hint">저장된 MySQL 연결 정보와 API 비밀정보는 다시 화면에 표시되지 않으며, 비워두면 현재 값을 유지합니다. 웹 루트 밖 <code>~/.rossi-tools/sms.php</code>에 600 권한으로 저장됩니다.</p>
    </form>
  </details>

  <?php if ($configured): ?><section class="sms-history"><div class="sms-history-head"><h2>최근 예약</h2><form method="post" data-sms-status-refresh><input type="hidden" name="action" value="sms-refresh-status"><input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>"><button class="ghost" type="submit">상태 새로고침</button><span class="sms-auto-refresh">탭을 보는 동안 1분마다 자동 확인</span></form></div><?php if ($schedules === []): ?><p class="hint">아직 예약된 문자가 없습니다.</p><?php else: ?>
    <?php foreach ($schedules as $item): ?><article class="sms-row"><div><strong><?= e(rossi_sms_status_label((string) $item['status'])) ?></strong><span><?= e((string) $item['scheduled_at_kst']) ?> KST · <?= e((string) $item['recipient']) ?></span><p><?= nl2br(e((string) $item['message'])) ?></p></div><?php if ((string) $item['status'] === 'SCHEDULED'): ?><form method="post"><input type="hidden" name="action" value="sms-cancel"><input type="hidden" name="csrf" value="<?= e((string) $auth['csrf']) ?>"><input type="hidden" name="public_id" value="<?= e((string) $item['public_id']) ?>"><button class="ghost" type="submit">취소</button></form><?php endif; ?></article><?php endforeach; ?>
  <?php endif; ?></section><?php endif; ?>
</section>
