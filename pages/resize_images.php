<?php
function resizeAndSaveImage($sourceImage, $targetWidth) {
    list($width, $height) = getimagesize($sourceImage);
    $ratio = $height / $width;
    $targetHeight = round($targetWidth * $ratio);
    
    $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
    $source = imagecreatefromjpeg($sourceImage);
    
    if (!$thumb || !$source) {
        return false;
    }
    
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    
    if (!imagecopyresampled($thumb, $source, 0, 0, 0, 0, 
        $targetWidth, $targetHeight, $width, $height)) {
        return false;
    }
    
    imagedestroy($source);
    return $thumb;
}

function processUploadedImage($originalImage, $filename) {
    $baseImagePath = $_SERVER['DOCUMENT_ROOT'] . '/pages/data/images';
    
    try {
        if (!copy($originalImage, "$baseImagePath/$filename")) {
            throw new Exception("원본 이미지 저장 실패");
        }
        return true;
    } catch (Exception $e) {
        error_log("이미지 처리 오류: " . $e->getMessage());
        return false;
    }
}
?>