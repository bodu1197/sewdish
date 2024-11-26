<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['email'])) {
    header('Location: https://ma-lab.co.kr/pages/login.php');
    exit;
}
$userEmail = $_SESSION['user']['email'];
$dataDir = __DIR__ . '/data';
$imageDir = $dataDir . '/images';
$jsonFile = $dataDir . '/shops.json';
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);
if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);
if (!file_exists($jsonFile)) file_put_contents($jsonFile, '{"stores":[]}');
$editMode = isset($_GET['edit']) ? $_GET['edit'] : null;
$storeData = null;
if ($editMode) {
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if ($data && isset($data['stores'])) {
            foreach ($data['stores'] as $store) {
                if ($store['id'] === $editMode) {
                    $storeData = $store;
                    break;
                }
            }
        }
    }
}
$data = json_decode(file_get_contents($jsonFile), true) ?? ['stores' => []];
$massage_types = [
    '1인샵', '중국', '스웨디시', '베트남&러시아', '태국', 
    '왁싱', '남성전용', '여성전용', '스파&세신', '홈케어'
];

function findGeocode($address) {
    $csvFile = $_SERVER['DOCUMENT_ROOT'] . '/KR.csv';
    if (!file_exists($csvFile)) {
        error_log("CSV 파일을 찾을 수 없음. 경로: " . $csvFile);
        throw new Exception("좌표 데이터 파일을 찾을 수 없습니다.");
    }
    
    $handle = fopen($csvFile, "r");
    if (!$handle) {
        throw new Exception("좌표 데이터 파일을 열 수 없습니다.");
    }
    
    // 지번 주소에서 시/구/동만 추출
    $addressParts = explode(' ', $address);
    $searchParts = [];
    
    // 첫 3개 부분만 사용 (시도, 시군구, 동읍면)
    for ($i = 0; $i < 3 && $i < count($addressParts); $i++) {
        if (!preg_match('/^\d+/', $addressParts[$i])) {
            $searchParts[] = $addressParts[$i];
        }
    }
    
    $found = null;
    $bestMatch = 0;
    
    // CSV 파일 읽기
    while (($line = fgets($handle)) !== false) {
        $data = explode("\t", $line); // 탭으로 구분된 데이터 읽기
        
        if (count($data) < 7) continue;
        
        // 시도, 시군구, 읍면동 데이터 추출
        $csvLocation = [
            trim($data[0]), // 시도
            trim($data[1]), // 시군구
            trim($data[2])  // 읍면동
        ];
        
        $matchCount = 0;
        foreach ($searchParts as $index => $part) {
            if (isset($csvLocation[$index]) && 
                mb_strpos($csvLocation[$index], trim($part)) !== false) {
                $matchCount++;
            }
        }
        
        if ($matchCount > $bestMatch) {
            $found = [
                'lat' => trim($data[5]),
                'lng' => trim($data[6])
            ];
            $bestMatch = $matchCount;
            
            // 정확히 3개 모두 일치하면 즉시 반환
            if ($matchCount === 3) {
                break;
            }
        }
    }
    
    fclose($handle);
    
    if (!$found || $bestMatch < 2) {
        return [
            'lat' => '37.5665',
            'lng' => '126.9780'
        ];
    }
    
    return $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $storeId = $editMode ?: uniqid();
        $coordinates = findGeocode($_POST['address']);
        
        $newStore = [
            'id' => $storeId,
            'name' => $_POST['storeName'],
            'address' => [
                'full' => $_POST['address'],
                'lat' => $coordinates['lat'],
                'lng' => $coordinates['lng']
            ],
            'phone' => $_POST['fullPhone'],
            'minPrice' => intval(str_replace(',', '', $_POST['minPrice'])),
            'types' => $_POST['types'] ?? [],
            'description' => $_POST['description'],
            'owner' => $userEmail,
            'created_at' => $editMode ? $storeData['created_at'] : date('Y-m-d H:i:s')
        ];

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $_FILES['photo']['tmp_name']);
            finfo_close($fileInfo);
            $allowedMimes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            ];
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('허용되지 않는 파일 형식입니다.');
            }
            if (!in_array($ext, $allowedTypes)) {
                throw new Exception('허용되지 않는 파일 형식입니다.');
            }
            $newFileName = $storeId . '.' . $ext;
            $uploadPath = $imageDir . '/' . $newFileName;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                throw new Exception('파일 업로드에 실패했습니다.');
            }
            $newStore['photo'] = '/pages/data/images/' . $newFileName;
        } elseif ($editMode && $storeData && isset($storeData['photo'])) {
            $newStore['photo'] = $storeData['photo'];
        }

        if ($editMode) {
            $updated = false;
            foreach ($data['stores'] as $key => $store) {
                if ($store['id'] === $editMode) {
                    $data['stores'][$key] = $newStore;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                throw new Exception('수정할 데이터를 찾을 수 없습니다.');
            }
        } else {
            $data['stores'][] = $newStore;
        }

        if (!file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new Exception('데이터 저장에 실패했습니다.');
        }

        header('Location: /pages/detail.html?id=' . $storeId);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>매장 <?php echo $editMode ? '수정' : '등록' ?> - MA-LAB</title>
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Arial,sans-serif;line-height:1.6;padding:20px;background:#f5f5f5}.container{max-width:800px;margin:0 auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.1)}h1{text-align:center;margin-bottom:20px;color:#333}.form-group{margin-bottom:15px}label{display:block;margin-bottom:5px;color:#666}input[type=text],input[type=number],textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:16px}.phone-group{display:flex;gap:10px}.phone-group input{width:80px;text-align:center}.types-group{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}.types-group label{display:flex;align-items:center;gap:5px}.image-preview{max-width:300px;margin-top:10px;display:none}button{background:#4CAF50;color:#fff;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;font-size:16px}button:hover{background:#45a049}.error{color:red;margin-bottom:10px}@media (max-width:600px){.container{padding:15px}.types-group{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))}}</style>
</head>
<body>
    <div class="container">
        <h1>매장 <?php echo $editMode ? '수정' : '등록' ?></h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form id="storeForm" method="POST" enctype="multipart/form-data">
            <?php if ($editMode): ?>
                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($editMode); ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="storeName">매장명</label>
                <input type="text" id="storeName" name="storeName" required>
            </div>
            <div class="form-group">
                <label for="address">주소</label>
                <input type="text" id="address" name="address" required readonly>
                <button type="button" id="addressSearch">주소 검색</button>
            </div>
            <div class="form-group">
                <label>전화번호</label>
                <div class="phone-group">
                    <input type="text" name="phone1" maxlength="3" required oninput="moveNext(this, 3)">
                    <input type="text" name="phone2" maxlength="4" required oninput="moveNext(this, 4)">
                    <input type="text" name="phone3" maxlength="4" required>
                    <input type="hidden" id="fullPhone" name="fullPhone">
                </div>
            </div>
            <div class="form-group">
                <label for="minPrice">최저가격</label>
                <input type="text" id="minPrice" name="minPrice" required oninput="formatPrice(this)">
            </div>
            <div class="form-group">
                <label>마사지 종류</label>
                <div class="types-group">
                    <?php foreach ($massage_types as $type): ?>
                        <label>
                            <input type="checkbox" name="types[]" value="<?php echo htmlspecialchars($type); ?>">
                            <?php echo htmlspecialchars($type); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="description">상세설명</label>
                <textarea id="description" name="description" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="photo">매장 사진</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" <?php echo $editMode ? '' : 'required'; ?>>
                <img id="imagePreview" class="image-preview" alt="미리보기">
            </div>
            <button type="submit"><?php echo $editMode ? '수정하기' : '등록하기' ?></button>
        </form>
    </div>
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
    <script>
        const editMode = <?php echo $editMode ? 'true' : 'false' ?>;
        const storeData = <?php echo $storeData ? json_encode($storeData) : 'null' ?>;
        if (editMode && storeData) {
            document.getElementById('storeName').value = storeData.name;
            document.getElementById('address').value = storeData.address.full;
            document.getElementById('minPrice').value = new Intl.NumberFormat('ko-KR').format(storeData.minPrice);
            document.getElementById('description').value = storeData.description;
            const phoneNumbers = storeData.phone.split('-');
            document.querySelector('input[name="phone1"]').value = phoneNumbers[0];
            document.querySelector('input[name="phone2"]').value = phoneNumbers[1];
            document.querySelector('input[name="phone3"]').value = phoneNumbers[2];
            storeData.types.forEach(type => {
                const checkbox = document.querySelector(`input[name="types[]"][value="${type}"]`);
                if (checkbox) checkbox.checked = true;
            });
            if (storeData.photo) {
                const preview = document.getElementById('imagePreview');
                preview.src = storeData.photo;
                preview.style.display = 'block';
            }
        }
        document.getElementById('addressSearch').addEventListener('click', function() {
            new daum.Postcode({
                oncomplete: function(data) {
                    let fullAddress = data.jibunAddress;
                    if (!fullAddress) {
                        fullAddress = data.autoJibunAddress;
                    }
                    if (!fullAddress) {
                        fullAddress = data.address;
                    }
                    if (data.buildingName) {
                        fullAddress += ` (${data.buildingName})`;
                    }
                    document.getElementById('address').value = fullAddress;
                }
            }).open();
        });
        function moveNext(input, length) {
            if (input.value.length >= length) {
                const next = input.nextElementSibling;
                if (next && next.tagName === 'INPUT') next.focus();
            }
        }
        function formatPrice(input) {
            let value = input.value.replace(/[^\d]/g, '');
            input.value = new Intl.NumberFormat('ko-KR').format(value);
        }
        document.getElementById('storeForm').addEventListener('submit', function(e) {
            const phone1 = document.querySelector('input[name="phone1"]').value;
            const phone2 = document.querySelector('input[name="phone2"]').value;
            const phone3 = document.querySelector('input[name="phone3"]').value;
            document.getElementById('fullPhone').value = `${phone1}-${phone2}-${phone3}`;
            const types = document.querySelectorAll('input[name="types[]"]:checked');
            if (types.length === 0) {
                e.preventDefault();
                alert('마사지 종류를 하나 이상 선택해주세요.');
                return false;
            }
        });
        document.getElementById('photo').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>