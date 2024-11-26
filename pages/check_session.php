<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// 디버깅을 위한 세션 로그
error_log('Current SESSION: ' . print_r($_SESSION, true));

// 세션 키 확인
$response = [
    'loggedIn' => isset($_SESSION['user']) && isset($_SESSION['user']['email']),
    'userEmail' => $_SESSION['user']['email'] ?? null,
    'isAdmin' => isset($_SESSION['admin']) ? $_SESSION['admin'] : false,
    'debug' => [
        'session' => $_SESSION,
        'cookies' => $_COOKIE
    ]
];

echo json_encode($response);