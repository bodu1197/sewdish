// 전역 변수
let currentUser = null;
let recentStores = [];

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', async () => {
    await checkAuthStatus();
    await loadRecentStores();
});

// 최근 등록된 매장 로드
async function loadRecentStores() {
    try {
        const response = await fetch('/data/stores.json');
        const data = await response.json();
        
        // 최근 등록순으로 정렬하고 최대 6개만 표시
        recentStores = data.stores
            .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
            .slice(0, 6);
        
        displayRecentStores();
    } catch (error) {
        console.error('매장 데이터 로드 실패:', error);
    }
}

// 최근 매장 표시
function displayRecentStores() {
    const container = document.getElementById('recentStoresList');
    
    container.innerHTML = recentStores.map(store => `
        <div class="store-card">
            <img src="${store.photo}" alt="${store.name}">
            <div class="store-info">
                <h3>${store.name}</h3>
                <p class="store-types">${store.types.join(', ')}</p>
                <p class="store-price">최저 ${formatPrice(store.minPrice)}원</p>
                <div class="store-actions">
                    <a href="tel:${store.phone}" class="call-btn">전화하기</a>
                    <a href="/pages/detail.html?id=${store.id}" class="detail-btn">상세보기</a>
                </div>
            </div>
        </div>
    `).join('');
}

// 지역 검색
function searchByLocation() {
    const searchInput = document.getElementById('locationSearch').value;
    if (searchInput.trim()) {
        window.location.href = `/pages/list.html?location=${encodeURIComponent(searchInput)}`;
    }
}

// 마사지 종류별 필터
function filterByType(type) {
    window.location.href = `/pages/list.html?type=${encodeURIComponent(type)}`;
}

// 가격 포맷팅
function formatPrice(price) {
    return new Intl.NumberFormat('ko-KR').format(price);
}