<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// 로그인 체크
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['email'])) {
    http_response_code(401);
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit;
}

$userEmail = $_SESSION['user']['email'];
$isAdmin = $_SESSION['user']['isAdmin'] ?? false;

// 절대 경로로 수정
$dataDir = $_SERVER['DOCUMENT_ROOT'] . '/pages/data';
$jsonFile = $dataDir . '/shops.json';

try {
    // POST 데이터 확인
    $storeId = $_POST['id'] ?? null;
    
    if (!$storeId) {
        throw new Exception('매장 ID가 필요합니다.');
    }

    // 파일 존재 확인
    if (!file_exists($jsonFile)) {
        throw new Exception('데이터 파일을 찾을 수 없습니다. 경로: ' . $jsonFile);
    }

    // 데이터 읽기
    $stores = json_decode(file_get_contents($jsonFile), true);
    if (!isset($stores['stores'])) {
        throw new Exception('잘못된 데이터 형식입니다.');
    }

    $storeIndex = null;
    $store = null;

    // 매장 찾기
    foreach ($stores['stores'] as $index => $s) {
        if ($s['id'] === $storeId) {
            $storeIndex = $index;
            $store = $s;
            break;
        }
    }

    if ($store === null) {
        throw new Exception('매장을 찾을 수 없습니다.');
    }

    // 권한 체크
    if (!$isAdmin && $store['owner'] !== $userEmail) {
        throw new Exception('삭제 권한이 없습니다.');
    }

    // 이미지 삭제
    if (isset($store['photo'])) {
        $imagePath = $_SERVER['DOCUMENT_ROOT'] . $store['photo'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    // 매장 데이터 삭제
    array_splice($stores['stores'], $storeIndex, 1);

    // 파일 저장
    if (!file_put_contents($jsonFile, json_encode($stores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        throw new Exception('데이터 저장에 실패했습니다.');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Delete Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}