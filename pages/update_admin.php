<?php
$file = 'data/users.json';

if (!file_exists($file)) {
    die("users.json 파일이 없습니다.");
}

$jsonContent = file_get_contents($file);
$data = json_decode($jsonContent, true);

// 관리자로 지정할 이메일
$adminEmail = 'ohddyus@gmail.com';

foreach ($data['users'] as &$user) {
    if ($user['userEmail'] === $adminEmail) {
        $user['isAdmin'] = 'Y';
    } else {
        $user['isAdmin'] = $user['isAdmin'] ?? 'N';
    }
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
echo "관리자 권한이 업데이트되었습니다.";
?>