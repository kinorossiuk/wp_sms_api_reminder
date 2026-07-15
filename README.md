# ROSSI TOOLS

PHP 웹호스팅에서 실행하는 개인용 편의 도구 플랫폼입니다.

도구 목록은 `app/tools.php`에서 관리하며, 각 도구 화면은 `tools/{slug}.php`에
추가합니다. 현재 QR 코드 생성기, SOLAPI SMS 예약 발송, 테스트 데이터 생성기, JSON 뷰어가 구현되어 있습니다.

## 업무 사용 주의사항

각 도구 화면에는 작업 전 확인할 안내가 표시됩니다. QR은 공유 가능한 내용만 담고, 테스트 데이터는 더미 정보만 사용하며, SMS는 수신 동의·발신번호·비용·외부 전송 정책을 확인해야 합니다. JSON은 브라우저에서만 처리하지만 회사 보안 솔루션의 별도 기록·제한 가능성을 고려하고, 민감정보는 회사 승인 없이 입력하지 않습니다.

## JSON 뷰어

- JSON 문법 검사와 오류 위치 안내
- 공백 2칸·4칸·탭 들여쓰기 및 한 줄 압축
- 객체·배열을 접고 펼치는 계층형 구조 보기
- 문자열·숫자·불리언·null 문법 강조
- 큰 숫자의 원문 표현을 바꾸지 않고 정리
- 결과 복사, JSON 다운로드, 로컬 JSON 파일 열기
- 모든 입력은 브라우저에서만 처리하고 서버로 전송하지 않음
- 도구 화면에 서버 미전송·미저장, 사용자 요청 동작, 회사 보안 솔루션의 별도 모니터링 가능성과 민감정보 주의사항을 명시

## 테스트 데이터 생성기

`/tools/test-data/`에서 테스트 업로드용 파일과 데이터 경계값을 만듭니다.

- PNG, TXT, PDF, DOCX, XLSX, MP4, WebM을 최대 300 MiB 목표 크기로 생성
- PNG, TXT, PDF, DOCX, XLSX는 생성 날짜·시간(KST)을 제목으로 넣고, 같은 파일명에서 10가지 콘텐츠·레이아웃 패턴을 중복 없이 한 번씩 무작위 선택
- MP4는 생성 날짜·시간(KST)을 화면 제목으로 녹화하고 10가지 합성 테스트 사운드 중 하나를 포함해 생성
- 파일은 실제 형식 구조를 유지하고, 목표 바이트에 맞추기 위한 비표시 패딩을 포함
- 499·500·501자 등 문자 수 경계값과 UTF-8 바이트 수 확인·복사
- 더미 연락처는 대한민국·미국·캐나다·영국·일본·호주·싱가포르·독일·프랑스·인도·인도네시아·튀르키예·투르크메니스탄 형식을 지원하며, 국내 표시 번호와 검증된 E.164 국제번호를 CSV로 생성
- 가상 이름·전화번호·이메일·회사 정보로 더미 연락처를 만들고 CSV로 내려받기
- 모든 결과물은 브라우저에서만 생성되며 서버에 저장·전송하지 않음

WebM 생성은 `MediaRecorder`를 사용하는 Chrome 또는 Edge에서 가장 안정적입니다.

## SOLAPI SMS 예약 발송

`/tools/sms/`의 설정 화면에서 MySQL 연결 정보, SOLAPI API Key/Secret, 활성 발신번호,
허용 수신번호를 입력합니다. API Secret과 DB 비밀번호는 저장 후 다시 표시하지 않으며,
웹 루트 밖 `~/.rossi-tools/sms.php`에 소유자 전용 권한으로 저장됩니다.

먼저 cPanel의 **MySQL Databases**에서 데이터베이스와 사용자를 만들고 모든 권한을
부여한 뒤, 웹 설정 화면에서 연결 정보를 저장하세요. 설정을 저장한 다음 cPanel
터미널에서 테이블을 한 번 생성합니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/setup-sms-db.php
```

SOLAPI 콘솔에서 발신번호를 사전에 등록·활성화해야 합니다. 예약 발송은 SOLAPI가 직접
처리하고, 외부 heartbeat는 5분마다 예약 상태만 동기화합니다. 수신번호는 설정한
허용 목록으로 제한되고, 기본 하루 예약 한도는 20건입니다.

## QR 코드 생성기

- URL 또는 일반 텍스트를 브라우저에서만 QR 코드로 변환
- 오류 복원 수준(L/M/Q/H), 전경·배경색, PNG 크기 선택
- SVG 및 PNG 내려받기
- 입력값을 서버에 저장하거나 외부 QR API로 전송하지 않음

QR 인코딩에는 `qrcode-generator` v2.0.4(MIT)를 `static/vendor/`에 포함합니다.
자세한 고지 사항은 `THIRD_PARTY_NOTICES.md`를 확인하세요.

## 예약 작업

호스팅 계정에는 `crontab` 권한이 없으므로 cron-job.org가 `cron.php`를 매분 POST로
호출합니다. 서버의 경량 스케줄러는 외부 명령을 실행하지 않고 등록된 PHP 함수만
실행합니다. 일정 판단은 마지막 성공 시각을 기준으로 하므로 외부 호출이 조금
늦어져도 작업을 놓치지 않습니다.

- 5분 간격으로 `~/.rossi-tools/cron-status.json` 갱신
- 매일 03:20 이후 처음 호출될 때 1 MiB 초과 로그를 최근 256 KiB로 정리
- 전역 파일 잠금으로 중복 요청 차단
- 작업별 실패 기록 및 재시도 간격 적용
- 토큰 해시, 실행 상태와 로그는 모두 `~/.rossi-tools`에 저장

배포 후 전용 토큰을 생성합니다. 출력된 원본 토큰은 서버에 저장되지 않으며 다시
표시할 수 없습니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/set-cron-token.php
```

cron-job.org 작업 설정:

- URL: `https://rossiuk.xyz/cron.php`
- 실행 간격: 매분
- 요청 방식: `POST`
- 요청 헤더 이름: `X-Rossi-Cron-Token`
- 요청 헤더 값: 위 명령이 출력한 토큰
- 응답 저장: 사용 안 함

서버에서 작업을 강제로 시험하려면 다음 명령을 사용합니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/run-cron.php --force
cat /home/hkz3dtrsnk2pyzow/.rossi-tools/cron-status.json
```

토큰이 노출되면 `set-cron-token.php`를 다시 실행하고 cron-job.org의 헤더 값을 새
토큰으로 교체합니다.

## 보안 정책

- 사이트 전체 비밀번호 인증
- 15분 내 로그인 5회 실패 시 IP별 30분 차단
- 7일 내 일시 차단이 3회 발생하면 해당 IP를 영구 차단
- 민감 파일 및 알려진 공격 경로 탐색을 10분 내 2회 시도하면 해당 IP를 영구 차단
- 디렉터리 목록, 불필요한 HTTP 메서드, 대용량 요청 차단
- 12시간 동안 활동이 없으면 세션 만료
- CSRF 방지, 보안 쿠키, CSP 및 주요 보안 헤더 적용
- 예약 작업 엔드포인트는 별도 256비트 토큰과 POST 요청으로만 실행
- 비밀번호 해시는 공개 저장소가 아닌 계정 홈의 `.rossi-tools/security.php`에 저장

## 최초 비밀번호 설정

cPanel 터미널에서 Git 저장소의 설정 스크립트를 실행합니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/set-password.php
```

12자 이상의 비밀번호를 두 번 입력합니다. 입력값은 터미널 화면에 표시되지 않습니다.

## 추가 비밀번호 설정

기존 비밀번호를 유지한 채 한 개를 더 허용하려면 cPanel 터미널에서 아래 명령을 실행합니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/add-password.php
```

추가 비밀번호도 12자 이상이어야 하며, 실행 후 기존·추가 비밀번호 모두 로그인에 사용할 수 있습니다. 비밀번호 해시는 계정 홈의 `.rossi-tools/security.php`에만 저장됩니다.

관리자가 차단됐을 때는 아래 명령으로 모든 IP 잠금 기록만 초기화할 수 있습니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/clear-lockouts.php
```

특정 IP만 해제하려면 아래처럼 실행합니다.

```bash
php /home/hkz3dtrsnk2pyzow/repositories/wp_sms_api_reminder/bin/unblock-ip.php 203.0.113.12
```

## 배포

배포는 다음 순서로 완료합니다.

1. 검사한 변경만 커밋하고 `origin/main`에 푸시합니다.
2. 변경 성격에 맞는 주석 태그를 생성하고 원격에 푸시합니다.
3. 원격 브랜치와 태그가 새 커밋을 가리키는지 확인합니다.
4. cPanel Git Version Control에서 `Update from Remote`를 실행합니다.
5. 이어서 `Deploy HEAD Commit`을 실행합니다.

GitHub 푸시만으로는 운영 사이트 배포가 완료되지 않습니다. `.cpanel.yml`이 필요한
공개 파일만 `/home/hkz3dtrsnk2pyzow/public_html/rossiuk.xyz/`로 복사합니다.
Codex 작업 시 상세 완료 기준은 저장소 루트의 `AGENTS.md`를 따릅니다.
