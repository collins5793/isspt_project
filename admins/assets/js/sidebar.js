class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('adminSidebar');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.mobileToggle = document.getElementById('mobileToggle');
        this.sidebarClose = document.getElementById('sidebarClose');
        this.sidebarOverlay = document.getElementById('sidebarOverlay');
        this.navTitles = document.querySelectorAll('.nav-title');
        this.activeLink = document.querySelector('.nav-link.active, .submenu-link.active');
        
        this.init();
    }

    init() {
        this.checkScreenSize();
        this.bindEvents();
        this.setupClock();
        this.setupKeyboardNavigation();
        this.setupTouchGestures();
        
        // Auto-open submenu containing active link
        if (this.activeLink) {
            this.openActiveSubmenu();
        }
        
        // Listen for window resize
        window.addEventListener('resize', this.debounce(this.handleResize.bind(this), 250));
    }

    bindEvents() {
        // Toggle sidebar state (desktop)
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }

        // Mobile menu toggle
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => this.toggleMobileMenu());
        }

        // Close sidebar on mobile
        if (this.sidebarClose) {
            this.sidebarClose.addEventListener('click', () => this.closeMobileSidebar());
        }

        if (this.sidebarOverlay) {
            this.sidebarOverlay.addEventListener('click', () => this.closeMobileSidebar());
        }

        // Handle collapsible menus
        this.navTitles.forEach(title => {
            title.addEventListener('click', (e) => this.handleNavTitleClick(e, title));
        });

        // Close sidebar when clicking outside (mobile)
        document.addEventListener('click', (e) => this.handleOutsideClick(e));

        // Close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeMobileSidebar();
        });

        // Smooth navigation
        document.querySelectorAll('.nav-link, .submenu-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1200) {
                    this.closeMobileSidebar();
                }
            });
        });
    }

    toggleSidebar() {
        if (window.innerWidth >= 1200) {
            this.sidebar.classList.toggle('collapsed');
            const icon = this.sidebarToggle.querySelector('i');
            
            if (this.sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
                this.sidebarToggle.title = 'Développer le menu';
                this.sidebar.dataset.state = 'collapsed';
            } else {
                icon.className = 'fas fa-chevron-left';
                this.sidebarToggle.title = 'Réduire le menu';
                this.sidebar.dataset.state = 'expanded';
            }
            
            this.saveState();
            this.dispatchResizeEvent();
        }
    }

    toggleMobileMenu() {
        this.sidebar.classList.toggle('mobile-open');
        this.sidebarOverlay.classList.toggle('active');
        this.mobileToggle.classList.toggle('active');
        this.mobileToggle.setAttribute('aria-expanded', 
            this.sidebar.classList.contains('mobile-open'));
        
        // Block body scroll
        document.body.style.overflow = this.sidebar.classList.contains('mobile-open') ? 'hidden' : '';
    }

    closeMobileSidebar() {
        this.sidebar.classList.remove('mobile-open');
        this.sidebarOverlay.classList.remove('active');
        this.mobileToggle.classList.remove('active');
        this.mobileToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    handleNavTitleClick(e, title) {
        // Prevent click on mobile if it's a link
        if (window.innerWidth < 1200 && title.closest('.nav-link')) {
            return;
        }
        
        const parent = title.parentElement;
const submenu = parent.querySelector('.submenu');
const isActive = parent.classList.contains('active');
const arrow = title.querySelector('.nav-arrow');

if (!isActive) {
    parent.classList.add('active');
    arrow.className = 'nav-arrow fas fa-chevron-up';
    title.setAttribute('aria-expanded', 'true');

    // ouvrir le sous-menu avec sa hauteur réelle
    if (submenu) {
        submenu.style.maxHeight = submenu.scrollHeight + "px";
    }
} else {
    parent.classList.remove('active');
    arrow.className = 'nav-arrow fas fa-chevron-down';
    title.setAttribute('aria-expanded', 'false');

    // fermer le sous-menu
    if (submenu) {
        submenu.style.maxHeight = "0";
    }
}

        
        // Close other submenus on mobile
        if (window.innerWidth < 1200) {
            document.querySelectorAll('.nav-section.active').forEach(section => {
                if (section !== parent) {
                    section.classList.remove('active');
                    section.querySelector('.nav-arrow').className = 'nav-arrow fas fa-chevron-down';
                }
            });
        }
        
        // Toggle active state
        if (!isActive) {
            parent.classList.add('active');
            arrow.className = 'nav-arrow fas fa-chevron-up';
            title.setAttribute('aria-expanded', 'true');
        } else {
            parent.classList.remove('active');
            arrow.className = 'nav-arrow fas fa-chevron-down';
            title.setAttribute('aria-expanded', 'false');
        }
        
        // Save state for desktop
        if (window.innerWidth >= 1200) {
            this.saveMenuState(parent.dataset.menu, !isActive);
        }
    }

    handleOutsideClick(e) {
        if (window.innerWidth < 1200 && 
            this.sidebar.classList.contains('mobile-open') &&
            !this.sidebar.contains(e.target) && 
            !this.mobileToggle.contains(e.target)) {
            this.closeMobileSidebar();
        }
    }

    openActiveSubmenu() {
    const submenu = this.activeLink.closest('.submenu');
    if (submenu) {
        const navSection = submenu.parentElement;
        navSection.classList.add('active');
        const title = navSection.querySelector('.nav-title');
        const arrow = title.querySelector('.nav-arrow');

        if (title) title.setAttribute('aria-expanded', 'true');
        if (arrow) arrow.className = 'nav-arrow fas fa-chevron-up';

        // définir la hauteur du sous-menu pour qu’il s’affiche
        submenu.style.maxHeight = submenu.scrollHeight + "px";
    }
}


    checkScreenSize() {
        if (window.innerWidth < 1200) {
            this.sidebar.classList.remove('collapsed');
        }
    }

    handleResize() {
        this.checkScreenSize();
        
        if (window.innerWidth >= 1200) {
            // Close mobile menu when switching to desktop
            this.closeMobileSidebar();
            
            // Restore saved state
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                this.sidebar.classList.add('collapsed');
                const icon = this.sidebarToggle.querySelector('i');
                icon.className = 'fas fa-chevron-right';
                this.sidebarToggle.title = 'Développer le menu';
                this.sidebar.dataset.state = 'collapsed';
            }
        } else {
            // Force sidebar to not be collapsed on mobile
            this.sidebar.classList.remove('collapsed');
        }
    }

    saveState() {
        if (window.innerWidth >= 1200) {
            const isCollapsed = this.sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        }
    }

    saveMenuState(menuId, isOpen) {
        const menuStates = JSON.parse(localStorage.getItem('menuStates') || '{}');
        menuStates[menuId] = isOpen;
        localStorage.setItem('menuStates', JSON.stringify(menuStates));
    }

   

    setupClock() {
        function updateClock() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const clockElement = document.getElementById('sidebarClock');
            if (clockElement) {
                clockElement.textContent = `${hours}:${minutes}`;
                clockElement.setAttribute('aria-label', `Heure actuelle: ${hours}h${minutes}`);
            }
        }
        
        setInterval(updateClock, 60000);
        updateClock();
    }

    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            if (!this.sidebar.contains(document.activeElement)) return;
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.focusNextItem();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.focusPreviousItem();
                    break;
                case 'Enter':
                case ' ':
                    if (document.activeElement.classList.contains('nav-title')) {
                        e.preventDefault();
                        document.activeElement.click();
                    }
                    break;
            }
        });
    }

    focusNextItem() {
        const focusable = this.getFocusableElements();
        const currentIndex = focusable.indexOf(document.activeElement);
        const nextIndex = (currentIndex + 1) % focusable.length;
        focusable[nextIndex].focus();
    }

    focusPreviousItem() {
        const focusable = this.getFocusableElements();
        const currentIndex = focusable.indexOf(document.activeElement);
        const prevIndex = currentIndex > 0 ? currentIndex - 1 : focusable.length - 1;
        focusable[prevIndex].focus();
    }

    getFocusableElements() {
        return Array.from(this.sidebar.querySelectorAll(
            'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )).filter(el => !el.disabled && el.offsetParent !== null);
    }

    setupTouchGestures() {
        let startX = 0;
        let startY = 0;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const diffX = endX - startX;
            const diffY = endY - startY;
            
            // Only consider horizontal swipes with minimal vertical movement
            if (Math.abs(diffX) > 50 && Math.abs(diffY) < 30) {
                if (diffX > 0 && window.innerWidth < 1200) {
                    // Swipe right - open menu if near edge
                    if (startX < 50) {
                        this.toggleMobileMenu();
                    }
                } else if (diffX < 0) {
                    // Swipe left - close menu if open
                    if (this.sidebar.classList.contains('mobile-open')) {
                        this.closeMobileSidebar();
                    }
                }
            }
        }, { passive: true });
    }

    dispatchResizeEvent() {
        window.dispatchEvent(new Event('resize'));
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new SidebarManager();
});

// Export for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SidebarManager;
}