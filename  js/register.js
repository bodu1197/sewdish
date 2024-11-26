// 이미지 미리보기
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-image';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// 다음 주소 검색
function searchAddress() {
    new daum.Postcode({
        oncomplete: function(data) {
            document.getElementById('address').value = data.address;
        }
    }).open();
}

// 전화번호 자동 이동
function moveNext(input, length) {
    if (input.value.length >= length) {
        const next = input.nextElementSibling;
        if (next && next.tagName === 'INPUT') {
            next.focus();
        }
    }
}

// 가격 포맷팅 (콤마 추가)
function formatPrice(input) {
    let value = input.value.replace(/[^\d]/g, '');
    input.value = new Intl.NumberFormat('ko-KR').format(value);
}

// 이미지를 Base64로 변환
function convertToBase64(file) {
    return new Promise((resolve, reject) => {
        if (!file) {
            reject(new Error('이미지 파일을 선택해주세요.'));
            return;
        }

        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(new Error('이미지 파일 읽기 실패'));
        reader.readAsDataURL(file);
    });
}

// JSON 파일 저장
async function saveToJson(storeData) {
    try {
        // 기존 데이터 로드
        const response = await fetch('/data/stores.json');
        let data = await response.json();
        
        // stores 배열이 없으면 생성
        if (!Array.isArray(data.stores)) {
            data = { stores: [] };
        }
        
        // 새 데이터 추가
        data.stores.push(storeData);
        
        // JSON 파일로 저장
        const saveResponse = await fetch('/api/save-store.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        if (!saveResponse.ok) {
            throw new Error('데이터 저장 실패');
        }
        
    } catch (error) {
        throw new Error('데이터 저장 중 오류 발생: ' + error.message);
    }
}

// 폼 제출 처리
document.getElementById('storeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    try {
        // 이미지 처리
        const imageFile = document.getElementById('storePhoto').files[0];
        const imageBase64 = await convertToBase64(imageFile);
        
        // 전화번호 조합
        const phoneInputs = document.querySelectorAll('.phone-input');
        const phone = Array.from(phoneInputs).map(input => input.value).join('-');
        
        // 마사지 종류 체크
        const types = Array.from(document.querySelectorAll('input[name="types"]:checked'))
            .map(input => input.value);
        if (types.length === 0) {
            throw new Error('마사지 종류를 하나 이상 선택해주세요.');
        }

        // 데이터 수집
        const storeData = {
            id: Date.now().toString(),
            name: document.getElementById('storeName').value,
            photo: imageBase64,
            address: document.getElementById('address').value,
            types: types,
            phone: phone,
            price: parseInt(document.getElementById('price').value.replace(/,/g, '')),
            description: document.getElementById('description').value,
            createdAt: new Date().toISOString()
        };

        // JSON 파일로 저장
        await saveToJson(storeData);
        
        alert('매장이 성공적으로 등록되었습니다.');
        window.location.href = '/'; // 홈으로 이동
        
    } catch (error) {
        alert(error.message);
    }
});