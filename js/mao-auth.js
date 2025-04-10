// js/mao-auth.js
const AuthControl = {
    user: null,
    protectedAdminPaths: [
        '/mao-users-control-panel_v1.html',
        '/mao-products-R-control-panel_v1.html',
        '/mao-orders-control-panel_v1.html',
        '/mao-chart-control-panel_v1.html'
    ],
    memberPaths: [
        '/20250213-taipeiHotel.html',
        '/SPA-member-control-panel-maskmap_v1.html',
        '/SPA-member-control-panel-youbikemap_v1.html',
        '/SPA-member-control-panel-hotelmap_v1.html',
        '/20250213-taipeiHotel-map.html',
        '/20250211-control-panel.html',
        '/20250214-taipeiHotel-chart.html',
        '/SPA-member-control-panel-hotel_C_v1.html',
        '/20250218-taichung-parking-chart.html'
    ],

    init() {
        this.checkLoginStatus();
        this.controlContentVisibility();
        this.updateNavigation();
        this.handleProtectedRoutes();
    },

    checkLoginStatus() {
        const uid = this.getCookie('Uid01');
        const storedUser = localStorage.getItem('user');
        if (uid && storedUser) {
            const userData = JSON.parse(storedUser);
            // 檢查會員狀態是否為 "N"
            if (userData.Status === 'N') {
                this.user = null;
                localStorage.removeItem('user');
                this.setCookie('Uid01', '', -1); // 清除 Cookie
                Swal.fire({
                    title: "帳號被停權",
                    text: "此帳號已被停權，請聯繫客服以了解詳情。",
                    icon: "error",
                    allowOutsideClick: false,
                    confirmButtonText: "確定"
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "Mao-index_v1.html?timestamp=" + new Date().getTime();
                    }
                });
            } else {
                this.user = userData;
                console.log('已登入用戶:', this.user);
            }
        } else {
            this.user = null;
            localStorage.removeItem('user');
            console.log('未登入');
        }
    },

    controlContentVisibility() {
        const protectedSections = ['s05', 's07', 's06']; // 受保護的區塊：租賃商品、毛孩租賃體驗、常見問題
        const publicSections = ['s04', 'footer']; // 公開區塊：關於毛毛、頁尾

        // 未登入時隱藏受保護區塊
        protectedSections.forEach(sectionId => {
            const section = document.getElementById(sectionId);
            if (section) {
                section.style.display = this.user ? 'block' : 'none';
            }
        });

        // 公開區塊始終顯示（可根據需求調整）
        publicSections.forEach(sectionId => {
            const section = document.getElementById(sectionId);
            if (section) {
                section.style.display = 'block'; // 始終顯示
            }
        });
    },

    updateNavigation() {
        const adminPanel = document.getElementById('control_panel_btn');
        const memberArea = document.getElementById('member_area_btn');
        const usernameShowText = document.getElementById('s02_username_showtext');
        const levelText = document.getElementById('s02_level_text');
        const usernameText = document.getElementById('s02_username_text');

        // 強制初始化隱藏
        if (adminPanel) {
            adminPanel.classList.add('d-none');
            console.log('初始化: 隱藏 control_panel_btn');
        }
        if (memberArea) memberArea.classList.add('d-none');
        if (usernameShowText) usernameShowText.classList.add('d-none');

        if (!this.user) {
            console.log('未登入: control_panel_btn 應保持隱藏');
            return;
        }

        // 設置歡迎訊息
        if (usernameShowText && levelText && usernameText) {
            usernameShowText.classList.remove('d-none');
            levelText.classList.remove('d-none');
            usernameText.classList.remove('d-none');

            // 根據等級調整顯示格式
            if (this.user.Level === 10) {
                // 一般會員：單行顯示
                levelText.textContent = `HI! ${this.user.Username}`;
                usernameText.style.display = 'none'; // 隱藏用戶名欄位，只顯示 levelText
            } else {
                // VIP、VVIP、管理員：分行顯示
                const levelMessages = {
                    20: 'HI! VIP',
                    30: 'HI! VVIP',
                    100: 'HI! 管理員'
                };
                levelText.textContent = levelMessages[this.user.Level] || 'HI!';
                usernameText.textContent = this.user.Username;
                usernameText.style.display = 'block'; // 顯示用戶名欄位
            }
        }

        // 根據等級控制導航欄
        if (this.user.Level === 100) {
            if (adminPanel) {
                adminPanel.classList.remove('d-none');
                console.log('管理員登入: 顯示 control_panel_btn');
            }
            if (memberArea) {
                memberArea.classList.remove('d-none');
                this.showAllMemberLinks();
            }
        } else {
            if (adminPanel) {
                adminPanel.classList.add('d-none');
                console.log(`等級 ${this.user.Level}: 隱藏 control_panel_btn`);
            }
            if (memberArea) {
                memberArea.classList.remove('d-none');
                if (this.user.Level === 30) {
                    this.showAllMemberLinks();
                } else if (this.user.Level === 20) {
                    this.showPartialMemberLinks();
                } else if (this.user.Level === 10) {
                    this.showBasicMemberLinks();
                }
            }
        }
    },

    showAllMemberLinks() {
        const memberMenu = document.querySelector('#navbarDropdownMenuMemberArea + .dropdown-menu');
        if (memberMenu) {
            const items = memberMenu.querySelectorAll('.dropdown-item');
            items.forEach(item => item.parentElement.style.display = 'block');
        }
    },

    showPartialMemberLinks() {
        const memberMenu = document.querySelector('#navbarDropdownMenuMemberArea + .dropdown-menu');
        if (memberMenu) {
            const allowedLinks = [
                '20250213-taipeiHotel.html',
                'SPA-member-control-panel-maskmap_v1.html',
                'SPA-member-control-panel-youbikemap_v1.html',
                'SPA-member-control-panel-hotelmap_v1.html',
                '20250213-taipeiHotel-map.html'
            ];
            const items = memberMenu.querySelectorAll('.dropdown-item');
            items.forEach(item => {
                const href = item.getAttribute('href');
                item.parentElement.style.display = allowedLinks.some(link => href.includes(link)) ? 'block' : 'none';
            });
        }
    },

    showBasicMemberLinks() {
        const memberMenu = document.querySelector('#navbarDropdownMenuMemberArea + .dropdown-menu');
        if (memberMenu) {
            const allowedLink = '20250213-taipeiHotel.html';
            const items = memberMenu.querySelectorAll('.dropdown-item');
            items.forEach(item => {
                const href = item.getAttribute('href');
                item.parentElement.style.display = href.includes(allowedLink) ? 'block' : 'none';
            });
        }
    },

    handleProtectedRoutes() {
        const currentPath = window.location.pathname;
        const isAdminProtected = this.protectedAdminPaths.some(path => currentPath.includes(path));
        if (isAdminProtected && (!this.user || this.user.Level < 100)) {
            this.showAccessDenied('無權訪問', '此頁面僅限管理員訪問。');
            return;
        }

        const isMemberPath = this.memberPaths.some(path => currentPath.includes(path));
        if (isMemberPath && !this.user) {
            this.showAccessDenied('請先登入', '此頁面需要登入才能訪問。');
            return;
        }

        if (isMemberPath && this.user) {
            if (this.user.Level === 10 && !currentPath.includes('20250213-taipeiHotel.html')) {
                this.showAccessDenied('權限不足', '您的會員等級無法訪問此頁面。');
            } else if (this.user.Level === 20) {
                const vipAllowed = [
                    '20250213-taipeiHotel.html',
                    'SPA-member-control-panel-maskmap_v1.html',
                    'SPA-member-control-panel-youbikemap_v1.html',
                    'SPA-member-control-panel-hotelmap_v1.html',
                    '20250213-taipeiHotel-map.html'
                ];
                if (!vipAllowed.some(path => currentPath.includes(path))) {
                    this.showAccessDenied('權限不足', '您的會員等級無法訪問此頁面。');
                }
            }
        }
    },

    showAccessDenied(title, message) {
        document.body.innerHTML = `
            <div style="text-align: center; padding: 50px;">
                <h1>${title}</h1>
                <p>${message}</p>
                <a href="Mao-index_v1.html" class="btn bg-02 tx-05">返回首頁</a>
            </div>
        `;
    },

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return '';
    },

    setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = "expires=" + d.toUTCString();
        document.cookie = `${name}=${value};${expires};path=/`;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AuthControl.init();
});

window.AuthControl = AuthControl;