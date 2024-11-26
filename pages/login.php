<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 에러 로깅 설정
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// 모든 에러와 워닝을 캐치하기 위한 핸들러
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr - $errfile:$errline");
});

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = 'data/users.json';
    
    try {
        if (!file_exists($file)) {
            throw new Exception("사용자 데이터를 찾을 수 없습니다.");
        }

        // 사용자 데이터 읽기
        $jsonContent = file_get_contents($file);
        $users = json_decode($jsonContent, true);

        if ($users === null) {
            throw new Exception("사용자 데이터를 읽을 수 없습니다.");
        }

        $userEmail = $_POST['email'] ?? '';
        $userPw = $_POST['password'] ?? '';
        $loginSuccess = false;

        // 사용자 검증
        if (isset($users['users']) && is_array($users['users'])) {
            foreach ($users['users'] as $user) {
                if (isset($user['userEmail']) && isset($user['userPw']) && 
                    $user['userEmail'] === $userEmail && 
                    password_verify($userPw, $user['userPw'])) {
                    
                    $_SESSION['user'] = [
                        'email' => $user['userEmail'],
                        'createdAt' => $user['createdAt'] ?? '',
                        'isAdmin' => (isset($user['isAdmin']) && $user['isAdmin'] === 'Y')
                    ];
                    
                    error_log("Login successful: " . print_r($_SESSION, true));
                    $loginSuccess = true;
                    break;
                }
            }
        }

        if ($loginSuccess) {
            $message = $_SESSION['user']['isAdmin'] ? '관리자로 로그인되었습니다!' : '로그인 성공!';
            echo "<script>
                alert('$message');
                window.location.href = '../';
            </script>";
            exit;
        } else {
            $error = "이메일 또는 비밀번호가 일치하지 않습니다.";
        }

    } catch (Exception $e) {
        $error = "로그인 처리 중 오류가 발생했습니다: " . $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - MA-LAB</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .auth-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .auth-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .auth-button {
            width: 100%;
            padding: 12px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        .auth-button:hover {
            background: #45a049;
        }

        .error-message {
            color: #ff0000;
            background-color: #ffe6e6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .auth-links {
            text-align: center;
            margin-top: 20px;
        }

        .auth-links a {
            color: #4CAF50;
            text-decoration: none;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }

        .admin-notice {
            text-align: center;
            margin-top: 15px;
            font-size: 0.9em;
            color: #666;
            padding: 10px;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>로그인</h1>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="email">이메일</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">비밀번호</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="auth-button">로그인</button>
            </form>
            <div class="auth-links">
                <p>아직 계정이 없으신가요? <a href="signup.html">회원가입</a></p>
            </div>
            <div class="admin-notice">
                * 관리자 계정으로 로그인하면 추가 기능이 활성화됩니다.
            </div>
        </div>
    </div>

    <script>
        function validateForm() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if (!email || !password) {
                alert('모든 필드를 입력해주세요.');
                return false;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('올바른 이메일 형식이 아닙니다.');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>