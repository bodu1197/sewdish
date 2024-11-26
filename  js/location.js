class LocationManager {
    constructor() {
        this.currentLocation = null;
        this.geoData = null;
    }

    // KR.txt 파일에서 좌표 데이터 로드
    async loadGeoData() {
        if (this.geoData) return;

        try {
            const response = await fetch('/www/KR.txt');
            const text = await response.text();
            
            // TSV 파일 파싱
            this.geoData = text.split('\n')
                .filter(line => line.trim())
                .map(line => {
                    const [
                        geonameid, name, asciiname, alternatenames,
                        latitude, longitude, featureClass, featureCode,
                        countryCode, cc2, admin1Code, admin2Code,
                        admin3Code, admin4Code, population, elevation,
                        dem, timezone, modificationDate
                    ] = line.split('\t');

                    return {
                        geonameid,
                        name,
                        latitude: parseFloat(latitude),
                        longitude: parseFloat(longitude),
                        admin1Code,
                        admin2Code,
                        admin3Code,
                        admin4Code,
                        alternatenames: alternatenames.split(',')
                    };
                });

        } catch (error) {
            console.error('지역 데이터 로드 실패:', error);
            throw new Error('지역 데이터를 불러올 수 없습니다.');
        }
    }

    // 현재 위치 가져오기
    async getCurrentLocation() {
        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject);
            });

            this.currentLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            return this.currentLocation;
        } catch (error) {
            console.error('위치 정보 가져오기 실패:', error);
            throw new Error('위치 정보를 가져올 수 없습니다.');
        }
    }

    // 주소로 좌표 검색
    async getCoordinatesByAddress(address) {
        if (!this.geoData) {
            await this.loadGeoData();
        }

        // 주소에서 검색어 추출 (예: "서울 강남구" -> ["서울", "강남구"])
        const keywords = address.split(' ').filter(k => k);

        // 가장 일치하는 결과 찾기
        const matches = this.geoData.filter(location => {
            const searchText = [
                location.name,
                ...location.alternatenames
            ].join(' ').toLowerCase();

            return keywords.every(keyword => 
                searchText.includes(keyword.toLowerCase())
            );
        });

        if (matches.length > 0) {
            // 가장 적절한 결과 반환 (인구수나 행정구역 레벨 등으로 정렬 가능)
            const bestMatch = matches[0];
            return {
                lat: bestMatch.latitude,
                lng: bestMatch.longitude,
                name: bestMatch.name
            };
        }

        throw new Error('주소를 찾을 수 없습니다.');
    }

    // 두 지점 간의 거리 계산 (km)
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371; // 지구 반경 (km)
        const dLat = this.toRad(lat2 - lat1);
        const dLng = this.toRad(lng2 - lng1);
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) * 
            Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    // 도(degree)를 라디안(radian)으로 변환
    toRad(degree) {
        return degree * Math.PI / 180;
    }

    // 현재 위치 기준으로 가까운 매장 정렬
    async sortStoresByDistance(stores) {
        try {
            const currentLocation = await this.getCurrentLocation();
            
            return stores.map(store => ({
                ...store,
                distance: this.calculateDistance(
                    currentLocation.lat,
                    currentLocation.lng,
                    store.address.lat,
                    store.address.lng
                )
            })).sort((a, b) => a.distance - b.distance);
        } catch (error) {
            console.error('매장 정렬 실패:', error);
            return stores;
        }
    }
}

// 전역 인스턴스 생성
const locationManager = new LocationManager();