<?php
header('Content-Type: application/json');

class ImageUploader {
    private $uploadDir = '../uploads/stores/';
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    private $maxFileSize = 5242880; // 5MB
    private $maxFiles = 5;

    public function __construct() {
        // 업로드 기본 디렉토리 생성
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function upload() {
        try {
            // 파일 업로드 확인
            if (!isset($_FILES['images'])) {
                throw new Exception('업로드된 파일이 없습니다.');
            }

            $files = $this->reArrayFiles($_FILES['images']);
            
            if (count($files) > $this->maxFiles) {
                throw new Exception("최대 {$this->maxFiles}개의 파일만 업로드 가능합니다.");
            }

            $uploadedFiles = [];

            foreach ($files as $file) {
                // 파일 유효성 검사
                $this->validateFile($file);

                // 저장할 디렉토리 생성
                $yearMonth = $this->createYearMonthDirs();
                
                // 파일명 생성
                $filename = $this->generateFilename($file['name']);
                $filepath = $yearMonth . '/' . $filename;

                // 이미지 업로드 및 리사이징
                $this->processAndSaveImage($file['tmp_name'], $this->uploadDir . $filepath);

                $uploadedFiles[] = 'uploads/stores/' . $filepath;
            }

            $this->sendResponse(true, '파일 업로드 성공', $uploadedFiles);

        } catch (Exception $e) {
            $this->sendResponse(false, $e->getMessage());
        }
    }

    private function reArrayFiles($files) {
        $fileArray = [];
        $fileCount = count($files['name']);
        $fileKeys = array_keys($files);

        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $fileArray[$i][$key] = $files[$key][$i];
            }
        }

        return $fileArray;
    }

    private function validateFile($file) {
        // 에러 체크
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('파일 업로드 실패: ' . $this->getUploadError($file['error']));
        }

        // 파일 크기 체크
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('파일 크기는 5MB를 초과할 수 없습니다.');
        }

        // 파일 타입 체크
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception('JPG, PNG 파일만 업로드 가능합니다.');
        }
    }

    private function createYearMonthDirs() {
        $year = date('Y');
        $month = date('m');
        $path = $year . '/' . $month;
        
        if (!file_exists($this->uploadDir . $path)) {
            mkdir($this->uploadDir . $path, 0755, true);
        }

        return $path;
    }

    private function generateFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }

    private function processAndSaveImage($tmpPath, $destPath) {
        // 원본 이미지 정보 가져오기
        list($width, $height, $type) = getimagesize($tmpPath);
        
        // 최대 크기 설정
        $maxWidth = 1200;
        $maxHeight = 1200;
        
        // 새 크기 계산
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // 새 이미지 생성
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // 원본 이미지 로드
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($tmpPath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($tmpPath);
                // PNG 투명도 유지
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                break;
            default:
                throw new Exception('지원하지 않는 이미지 형식입니다.');
        }

        // 이미지 리사이징
        imagecopyresampled(
            $newImage, $source,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // 이미지 저장
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $destPath, 85); // 85% 품질
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $destPath, 8); // 압축레벨 8
                break;
        }

        // 메모리 해제
        imagedestroy($newImage);
        imagedestroy($source);
    }

    private function getUploadError($code) {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return '파일이 PHP.ini에 설정된 최대 크기를 초과했습니다.';
            case UPLOAD_ERR_FORM_SIZE:
                return '파일이 HTML 폼에 설정된 최대 크기를 초과했습니다.';
            case UPLOAD_ERR_PARTIAL:
                return '파일이 일부만 업로드되었습니다.';
            case UPLOAD_ERR_NO_FILE:
                return '파일이 업로드되지 않았습니다.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '임시 폴더가 없습니다.';
            case UPLOAD_ERR_CANT_WRITE:
                return '디스크에 파일을 쓸 수 없습니다.';
            case UPLOAD_ERR_EXTENSION:
                return 'PHP 확장에 의해 업로드가 중지되었습니다.';
            default:
                return '알 수 없는 오류가 발생했습니다.';
        }
    }

    private function sendResponse($success, $message, $data = null) {
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// 업로드 처리
$uploader = new ImageUploader();
$uploader->upload();