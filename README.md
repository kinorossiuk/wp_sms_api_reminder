# ROSSI TOOLS

PHP 웹호스팅에서 실행하는 개인용 편의 도구 플랫폼입니다.

도구 목록은 `app/tools.php`에서 관리하며, 각 도구 화면은 `tools/{slug}.php`에
추가합니다. 현재 대시보드에는 QR 코드 생성기 진입 버튼이 등록되어 있습니다.

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
