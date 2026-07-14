# ROSSI TOOLS

PHP 웹호스팅에서 실행하는 개인용 편의 도구 플랫폼입니다.

도구 목록은 `app/tools.php`에서 관리하며, 각 도구 화면은 `tools/{slug}.php`에
추가합니다. 현재 QR 코드 생성기까지 구현되어 있습니다.

## QR 코드 생성기

- URL 또는 일반 텍스트를 브라우저에서만 QR 코드로 변환
- 오류 복원 수준(L/M/Q/H), 전경·배경색, PNG 크기 선택
- SVG 및 PNG 내려받기
- 입력값을 서버에 저장하거나 외부 QR API로 전송하지 않음

QR 인코딩에는 `qrcode-generator` v2.0.4(MIT)를 `static/vendor/`에 포함합니다.
자세한 고지 사항은 `THIRD_PARTY_NOTICES.md`를 확인하세요.

## 예약 작업(Crunz)

Crunz 런타임은 Git 저장소와 공개 웹 루트 밖의
`~/.rossi-tools/crunz-runtime/`에 설치합니다. 예약 작업 정의는 `tasks/`, 설정은
`crunz.yml`에서 관리합니다.

현재 등록된 작업은 다음과 같습니다.

- 5분마다 `~/.rossi-tools/cron-status.json`을 원자적으로 갱신
- 매일 03:20에 1 MiB를 넘은 Crunz 로그를 최근 256 KiB로 정리
- 두 작업 모두 파일 잠금으로 중복 실행 방지

배포 후 작업 목록과 강제 실행을 확인합니다.

```bash
cd /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder
/usr/local/bin/php /home/hkz3dtrsnk2pyzow/.rossi-tools/crunz-runtime/vendor/bin/crunz schedule:list
/usr/local/bin/php /home/hkz3dtrsnk2pyzow/.rossi-tools/crunz-runtime/vendor/bin/crunz schedule:run --force
cat /home/hkz3dtrsnk2pyzow/.rossi-tools/cron-status.json
```

cPanel Cron Jobs에는 매분 실행하도록 아래 명령을 하나만 등록합니다.

```bash
cd /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder && /usr/local/bin/php /home/hkz3dtrsnk2pyzow/.rossi-tools/crunz-runtime/vendor/bin/crunz schedule:run >> /home/hkz3dtrsnk2pyzow/.rossi-tools/crunz-runner.log 2>&1
```

## 보안 정책

- 사이트 전체 비밀번호 인증
- 15분 내 로그인 5회 실패 시 IP별 30분 차단
- 7일 내 일시 차단이 3회 발생하면 해당 IP를 영구 차단
- 민감 파일 및 알려진 공격 경로 탐색을 10분 내 2회 시도하면 해당 IP를 영구 차단
- 디렉터리 목록, 불필요한 HTTP 메서드, 대용량 요청 차단
- 12시간 동안 활동이 없으면 세션 만료
- CSRF 방지, 보안 쿠키, CSP 및 주요 보안 헤더 적용
- 비밀번호 해시는 공개 저장소가 아닌 계정 홈의 `.rossi-tools/security.php`에 저장

## 최초 비밀번호 설정

cPanel 터미널에서 Git 저장소의 설정 스크립트를 실행합니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/set-password.php
```

12자 이상의 비밀번호를 두 번 입력합니다. 입력값은 터미널 화면에 표시되지 않습니다.

관리자가 차단됐을 때는 아래 명령으로 모든 IP 잠금 기록만 초기화할 수 있습니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/clear-lockouts.php
```

특정 IP만 해제하려면 아래처럼 실행합니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/unblock-ip.php 203.0.113.12
```

## 배포

cPanel Git Version Control에서 `Update from Remote` 실행 후 `Deploy HEAD Commit`을
실행합니다. `.cpanel.yml`이 필요한 공개 파일만
`/home/hkz3dtrsnk2pyzow/public_html/rossiuk.xyz/`로 복사합니다.
