class AuthManager {
    constructor() {
        this.currentUser = null;
        this.usersKey = 'users';
        this.currentUserKey = 'currentUser';
    }

    // 회원가입
    async register(email, password) {
        try {
            const users = await this.getUsers();
            
            // 이메일 중복 체크
            if (users.some(user => user.email === email)) {
                throw new Error('이미 등록된 이메일입니다.');
            }

            // 새 사용자 생성
            const newUser = {
                id: Date.now().toString(),
                email,
                password: await this.hashPassword(password),
                createdAt: new Date().toISOString()
            };

            users.push(newUser);
            await this.saveUsers(users);

            // 회원가입 후 로그인 페이지로 이동
            alert('회원가입이 완료되었습니다. 로그인해주세요.');
            window.location.href = '/pages/login.html';
        } catch (error) {
            throw error;
        }
    }

    // 로그인
    async login(email, password) {
        try {
            const users = await this.getUsers();
            const user = users.find(u => u.email === email);

            if (!user) {
                throw new Error('사용자를 찾을 수 없습니다.');
            }

            const passwordMatch = await this.verifyPassword(password, user.password);
            if (!passwordMatch) {
                throw new Error('비밀번호가 일치하지 않습니다.');
            }

            // 로그인 상태 저장
            this.setCurrentUser(user);
            
            // 메인 페이지로 이동
            window.location.href = '/';
            return user;
        } catch (error) {
            throw error;
        }
    }

    // 로그아웃
    logout() {
        localStorage.removeItem(this.currentUserKey);
        this.currentUser = null;
        window.location.href = '/pages/login.html';
    }

    // 비밀번호 해싱
    async hashPassword(password) {
        // 실제 서비스에서는 더 안전한 해싱 방식을 사용해야 합니다
        return btoa(password);
    }

    // 비밀번호 검증
    async verifyPassword(password, hashedPassword) {
        return btoa(password) === hashedPassword;
    }

    // 사용자 목록 가져오기
    async getUsers() {
        try {
            const response = await fetch('/data/users.json');
            const data = await response.json();
            return data.users;
        } catch (error) {
            console.error('사용자 데이터 로드 실패:', error);
            return [];
        }
    }

    // 사용자 목록 저장
    async saveUsers(users) {
        const data = { users };
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'users.json';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // 현재 사용자 설정
    setCurrentUser(user) {
        this.currentUser = user;
        localStorage.setItem(this.currentUserKey, JSON.stringify(user));
    }

    // 현재 사용자 가져오기
    getCurrentUser() {
        if (!this.currentUser) {
            const savedUser = localStorage.getItem(this.currentUserKey);
            if (savedUser) {
                this.currentUser = JSON.parse(savedUser);
            }
        }
        return this.currentUser;
    }

    // 로그인 상태 확인
    checkAuth() {
        const currentUser = this.getCurrentUser();
        if (!currentUser) {
            window.location.href = '/pages/login.html';
            return false;
        }
        return true;
    }
}

// 전역 인스턴스 생성
const authManager = new AuthManager();

// 로그인 폼 제출 처리
if (document.getElementById('loginForm')) {
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            await authManager.login(email, password);
        } catch (error) {
            alert(error.message);
        }
    });
}

// 회원가입 폼 제출 처리
if (document.getElementById('signupForm')) {
    document.getElementById('signupForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('passwordConfirm').value;

            if (password !== passwordConfirm) {
                throw new Error('비밀번호가 일치하지 않습니다.');
            }

            await authManager.register(email, password);
        } catch (error) {
            alert(error.message);
        }
    });
}

// 페이지 로드 시 인증 체크 (보호된 페이지에서 사용)
document.addEventListener('DOMContentLoaded', () => {
    // register.html, list.html, detail.html에서만 인증 체크
    const protectedPages = ['/pages/register.html', '/pages/list.html', '/pages/detail.html'];
    if (protectedPages.some(page => window.location.pathname.endsWith(page))) {
        authManager.checkAuth();
    }
});