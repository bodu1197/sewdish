// 전역 변수
let store = null;
let map = null;

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', async () => {
    await loadStoreData();
    initializeMap();
});

// URL에서 매장 ID 가져오기
function getStoreId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

// 매장 데이터 로드
async function loadStoreData() {
    try {
        const storeId = getStoreId();
        if (!storeId) {
            throw new Error('매장 ID가 없습니다.');
        }

        const response = await fetch('../data/stores.json');
        const data = await response.json();
        store = data.stores.find(s => s.id === storeId);

        if (!store) {
            throw new Error('매장을 찾을 수 없습니다.');
        }

        displayStoreInfo();
    } catch (error) {
        console.error('매장 데이터 로드 실패:', error);
        showError(error.message);
    }
}

// 매장 정보 표시
function displayStoreInfo() {
    // 기본 정보 표시
    document.getElementById('storeImage').src = store.photo;
    document.getElementById('storeName').textContent = store.name;
    document.getElementById('storeAddress').textContent = store.address.full;
    document.getElementById('storePhone').innerHTML = `
        <span>${store.phone}</span>
        <a href="tel:${store.phone}" class="call-btn">전화걸기</a>
    `;
    document.getElementById('storePrice').textContent = 
        `${formatPrice(store.minPrice)}원부터`;
    document.getElementById('storeDescription').textContent = store.description;

    // 마사지 종류 태그 표시
    const typesContainer = document.getElementById('storeTypes');
    typesContainer.innerHTML = store.types.map(type => 
        `<span class="type-tag">${type}</span>`
    ).join('');

    // 페이지 타이틀 업데이트
    document.title = `${store.name} - MA-LAB`;
}

// 지도 초기화
function initializeMap() {
    if (!store) return;

    const mapContainer = document.getElementById('map');
    const mapOption = {
        center: new kakao.maps.LatLng(store.address.lat, store.address.lng),
        level: 3
    };

    map = new kakao.maps.Map(mapContainer, mapOption);

    // 마커 추가
    const marker = new kakao.maps.Marker({
        position: new kakao.maps.LatLng(store.address.lat, store.address.lng)
    });
    marker.setMap(map);

    // 인포윈도우 추가
    const infowindow = new kakao.maps.InfoWindow({
        content: `<div style="padding:5px;">${store.name}</div>`
    });
    infowindow.open(map, marker);
}

// 매장 수정
async function editStore() {
    const currentUser = authManager.getCurrentUser();
    if (!currentUser) {
        alert('로그인이 필요합니다.');
        return;
    }

    // 수정 페이지로 이동
    window.location.href = `register.html?edit=${store.id}`;
}

// 매장 삭제
async function deleteStore() {
    const currentUser = authManager.getCurrentUser();
    if (!currentUser) {
        alert('로그인이 필요합니다.');
        return;
    }

    if (!confirm('정말 이 매장을 삭제하시겠습니까?')) {
        return;
    }

    try {
        // stores.json에서 해당 매장 삭제
        const response = await fetch('../data/stores.json');
        const data = await response.json();
        data.stores = data.stores.filter(s => s.id !== store.id);

        // 파일 다운로드
        const blob = new Blob([JSON.stringify(data, null, 2)], 
            { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'stores.json';
        a.click();
        window.URL.revokeObjectURL(url);

        alert('매장이 삭제되었습니다.');
        window.location.href = 'list.html';
    } catch (error) {
        console.error('매장 삭제 실패:', error);
        alert('매장 삭제에 실패했습니다.');
    }
}

// 가격 포맷팅
function formatPrice(price) {
    return new Intl.NumberFormat('ko-KR').format(price);
}

// 에러 메시지 표시
function showError(message) {
    const container = document.querySelector('.store-detail');
    container.innerHTML = `
        <div class="error-message">
            <p>${message}</p>
            <a href="list.html" class="back-btn">목록으로 돌아가기</a>
        </div>
    `;
}