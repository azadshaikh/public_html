/**
 * Default Theme JavaScript
 * Version: 2.0.0
 *
 * This file contains theme-specific JavaScript functionality
 * that enhances the user experience.
 */

(function () {
    'use strict';

    // Theme configuration
    const ThemeConfig = {
        animations: {
            enabled: true,
            duration: 300,
            easing: 'ease-in-out',
        },
        smoothScroll: {
            enabled: true,
            duration: 800,
        },
        lazyLoading: {
            enabled: true,
            threshold: 0.1,
        },
    };

    // Initialize theme when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
    });

    /**
     * Initialize all theme functionality
     */
    function initTheme() {
        initSmoothScroll();
        initLazyLoading();
        initAnimations();
        initResponsiveNavigation();
        initBackToTop();
        initTooltips();
        initSearchEnhancements();
        initFormEnhancements();
        initThemeColorScheme();

        console.log('Default Theme v2.0.0 initialized');
    }

    /**
     * Smooth scrolling for anchor links
     */
    function initSmoothScroll() {
        if (!ThemeConfig.smoothScroll.enabled) return;

        const links = document.querySelectorAll('a[href^="#"]');

        links.forEach((link) => {
            link.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#') return;

                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                }
            });
        });
    }

    /**
     * Lazy loading for images
     */
    function initLazyLoading() {
        if (!ThemeConfig.lazyLoading.enabled || !('IntersectionObserver' in window)) return;

        const images = document.querySelectorAll('img[data-src]');

        const imageObserver = new IntersectionObserver(
            (entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            },
            {
                threshold: ThemeConfig.lazyLoading.threshold,
            }
        );

        images.forEach((img) => imageObserver.observe(img));
    }

    /**
     * Initialize scroll-triggered animations
     */
    function initAnimations() {
        if (!ThemeConfig.animations.enabled || !('IntersectionObserver' in window)) return;

        const animatedElements = document.querySelectorAll('.theme-fade-in, .theme-slide-up');

        const animationObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = `${entry.target.classList.contains('theme-fade-in') ? 'themeOpenIn' : 'themeSlideUp'} ${ThemeConfig.animations.duration}ms ${ThemeConfig.animations.easing}`;
                        animationObserver.unobserve(entry.target);
                    }
                });
            },
            {
                threshold: 0.1,
            }
        );

        animatedElements.forEach((el) => animationObserver.observe(el));
    }

    /**
     * Responsive navigation enhancements
     */
    function initResponsiveNavigation() {
        const navToggle = document.querySelector('.navbar-toggler');
        const navCollapse = document.querySelector('.navbar-collapse');

        if (navToggle && navCollapse) {
            // Close nav when clicking outside
            document.addEventListener('click', function (e) {
                if (!navToggle.contains(e.target) && !navCollapse.contains(e.target)) {
                    const bsCollapse = bootstrap.Collapse.getInstance(navCollapse);
                    if (bsCollapse && navCollapse.classList.contains('show')) {
                        bsCollapse.hide();
                    }
                }
            });

            // Close nav when clicking on nav links
            const navLinks = navCollapse.querySelectorAll('.nav-link');
            navLinks.forEach((link) => {
                link.addEventListener('click', function () {
                    const bsCollapse = bootstrap.Collapse.getInstance(navCollapse);
                    if (bsCollapse && navCollapse.classList.contains('show')) {
                        bsCollapse.hide();
                    }
                });
            });
        }
    }

    /**
     * Back to top button
     */
    function initBackToTop() {
        // Create back to top button
        const backToTop = document.createElement('button');
        backToTop.innerHTML = '<i class="ri-arrow-up-line"></i>';
        backToTop.className = 'btn btn-primary position-fixed bottom-0 end-0 m-3 rounded-circle';
        backToTop.style.cssText =
            'z-index: 1050; width: 50px; height: 50px; opacity: 0; visibility: hidden; transition: all 0.3s ease;';
        backToTop.setAttribute('aria-label', 'Back to top');

        document.body.appendChild(backToTop);

        // Show/hide based on scroll position
        window.addEventListener('scroll', function () {
            if (window.pageYOffset > 300) {
                backToTop.style.opacity = '1';
                backToTop.style.visibility = 'visible';
            } else {
                backToTop.style.opacity = '0';
                backToTop.style.visibility = 'hidden';
            }
        });

        // Scroll to top when clicked
        backToTop.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth',
            });
        });
    }

    /**
     * Initialize Bootstrap tooltips
     */
    function initTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        if (tooltipTriggerList.length > 0 && typeof bootstrap !== 'undefined') {
            tooltipTriggerList.forEach((tooltipTriggerEl) => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    /**
     * Search enhancements
     */
    function initSearchEnhancements() {
        const searchForm = document.querySelector('.search-form');
        const searchInput = document.querySelector('.search-input');

        if (searchForm && searchInput) {
            // Auto-focus search input when search page loads
            if (window.location.pathname.includes('/search')) {
                searchInput.focus();
            }

            // Add loading state to search
            searchForm.addEventListener('submit', function () {
                const submitBtn = searchForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Searching...';
                }
            });
        }
    }

    /**
     * Form enhancements
     */
    function initFormEnhancements() {
        // Add floating labels behavior
        const floatingInputs = document.querySelectorAll('.form-floating input, .form-floating textarea');

        floatingInputs.forEach((input) => {
            input.addEventListener('blur', function () {
                if (this.value) {
                    this.classList.add('has-value');
                } else {
                    this.classList.remove('has-value');
                }
            });

            // Check initial value
            if (input.value) {
                input.classList.add('has-value');
            }
        });

        // Enhanced form validation feedback
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach((form) => {
            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Focus first invalid field
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                }
                form.classList.add('was-validated');
            });
        });
    }

    /**
     * Theme color scheme handling
     */
    function initThemeColorScheme() {
        // Check for saved theme preference or default to auto
        const savedTheme = localStorage.getItem('theme') || 'auto';
        setTheme(savedTheme);

        // Listen for theme toggle button
        const themeToggle = document.querySelector('[data-theme-toggle]');
        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                setTheme(newTheme);
                localStorage.setItem('theme', newTheme);
            });
        }
    }

    /**
     * Set theme
     */
    function setTheme(theme) {
        if (theme === 'auto') {
            document.documentElement.setAttribute(
                'data-theme',
                window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
            );
        } else {
            document.documentElement.setAttribute('data-theme', theme);
        }
    }

    /**
     * Utility function to debounce function calls
     */
    function debounce(func, wait) {
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

    /**
     * Utility function to throttle function calls
     */
    function throttle(func, limit) {
        let inThrottle;
        return function () {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => (inThrottle = false), limit);
            }
        };
    }

    // Expose theme utilities globally
    window.ThemeUtils = {
        config: ThemeConfig,
        debounce: debounce,
        throttle: throttle,
        setTheme: setTheme,
    };

    // Handle window resize
    window.addEventListener(
        'resize',
        debounce(function () {
            // Recalculate any dynamic elements on resize
            console.log('Window resized - recalculating layout');
        }, 250)
    );

    // Handle page visibility changes
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            // Page became visible - resume any paused functionality
            console.log('Page visible - resuming theme functionality');
        } else {
            // Page hidden - pause unnecessary functionality
            console.log('Page hidden - pausing non-essential functionality');
        }
    });
})();
