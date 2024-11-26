// 전역 변수
let stores = [];
let userLocation = null;
let selectedTypes = new Set();

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', async () => {
    await loadStores();
    await getUserLocation();
    setupFilterListeners();
});

// 매장 데이터 로드
async function loadStores() {
    try {
        const response = await fetch('../data/stores.json');
        const data = await response.json();
        stores = data.stores;
        sortAndDisplayStores();
    } catch (error) {
        console.error('매장 데이터 로드 실패:', error);
        showError('매장 데이터를 불러오는데 실패했습니다.');
    }
}

// 사용자 위치 가져오기
async function getUserLocation() {
    try {
        const position = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject);
        });

        userLocation = {
            lat: position.coords.latitude,
            lng: position.coords.longitude
        };

        sortAndDisplayStores();
    } catch (error) {
        console.error('위치 정보 가져오기 실패:', error);
        document.getElementById('locationError').style.display = 'block';
    }
}

// 거리 계산
function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371; // 지구 반경 (km)
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// 매장 정렬 및 표시
function sortAndDisplayStores() {
    let filteredStores = [...stores];

    // 선택된 타입으로 필터링
    if (selectedTypes.size > 0) {
        filteredStores = filteredStores.filter(store => 
            store.types.some(type => selectedTypes.has(type))
        );
    }

    // 거리 계산 및 정렬
    if (userLocation) {
        filteredStores.forEach(store => {
            store.distance = calculateDistance(
                userLocation.lat,
                userLocation.lng,
                store.address.lat,
                store.address.lng
            );
        });

        filteredStores.sort((a, b) => a.distance - b.distance);
    }

    displayStores(filteredStores);
}

// 매장 목록 표시
function displayStores(stores) {
    const container = document.getElementById('storeList');
    
    if (stores.length === 0) {
        container.innerHTML = '<p class="no-results">검색 결과가 없습니다.</p>';
        return;
    }

    container.innerHTML = stores.map(store => `
        <div class="store-card">
            <img src="${store.photo}" alt="${store.name}" onerror="this.src='../assets/images/default/store-placeholder.jpg'">
            <div class="store-info">
                <h3>${store.name}</h3>
                ${store.distance ? 
                    `<p class="distance">${store.distance.toFixed(1)}km</p>` : 
                    ''}
                <div class="store-types">
                    ${store.types.map(type => 
                        `<span class="type-tag">${type}</span>`
                    ).join('')}
                </div>
                <p class="store-price">최저 ${formatPrice(store.minPrice)}원</p>
                <div class="store-contact">
                    <span>${store.phone}</span>
                    <a href="tel:${store.phone}" class="call-btn">전화걸기</a>
                </div>
                <a href="detail.html?id=${store.id}" class="detail-btn">상세보기</a>
            </div>
        </div>
    `).join('');
}

// 필터 이벤트 설정
function setupFilterListeners() {
    const checkboxes = document.querySelectorAll('.massage-types-filter input');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            if (e.target.checked) {
                selectedTypes.add(e.target.value);
            } else {
                selectedTypes.delete(e.target.value);
            }
            sortAndDisplayStores();
        });
    });
}

// 가격 포맷팅
function formatPrice(price) {
    return new Intl.NumberFormat('ko-KR').format(price);
}

// 에러 메시지 표시
function showError(message) {
    const container = document.getElementById('storeList');
    container.innerHTML = `<p class="error-message">${message}</p>`;
}

// 위치 정보 재요청
function requestLocation() {
    document.getElementById('locationError').style.display = 'none';
    getUserLocation();
}