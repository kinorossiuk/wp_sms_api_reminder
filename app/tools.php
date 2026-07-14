<?php
declare(strict_types=1);

return [
    'qr' => [
        'name' => 'QR 코드 생성기',
        'description' => '링크와 텍스트를 빠르게 QR 코드로 변환합니다.',
        'category' => '생성',
        'path' => '/tools/qr/',
        'status' => '사용 가능',
    ],
    'sms' => [
        'name' => 'SMS 예약 발송',
        'description' => 'SOLAPI로 개인 알림을 예약하고 발송 상태를 확인합니다.',
        'category' => '알림',
        'path' => '/tools/sms/',
        'status' => '설정 필요',
    ],
    'test-data' => [
        'name' => '테스트 데이터 생성기',
        'description' => '용량별 파일, 문자 경계값, 더미 연락처를 브라우저에서 만듭니다.',
        'category' => '테스트',
        'path' => '/tools/test-data/',
        'status' => '사용 가능',
    ],
    'json' => [
        'name' => 'JSON 뷰어',
        'description' => 'JSON 문법을 검사하고 보기 좋게 정리하거나 한 줄로 압축합니다.',
        'category' => '개발',
        'path' => '/tools/json/',
        'status' => '사용 가능',
    ],
];
