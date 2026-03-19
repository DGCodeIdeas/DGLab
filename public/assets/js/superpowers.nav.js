/**
 * Superpowers Navigation Engine - Phase 2
 * Lightweight pure-JS navigation for SPA-style transitions.
 */
(function() {
    const cache = new Map();
    const prefetchRegistry = new Set();
    let isNavigating = false;

    const SuperpowersNav = {
        init() {
            document.body.addEventListener('click', e => this.handleLinkClick(e));
            window.addEventListener('popstate', e => this.handlePopState(e));
            this.initPrefetching();
            console.log('Superpowers Navigation Engine initialized');
        },

        async navigate(url, push = true) {
            if (isNavigating) return;
            isNavigating = true;

            const root = this.getNavRoot();
            const transition = root.getAttribute('data-transition') || 'fade';

            // Start transition
            root.classList.add(`sp-transition-${transition}-leave`);

            try {
                const html = await this.fetchFragment(url);

                if (push) {
                    window.history.pushState({ sp_nav: true }, '', url);
                }

                // Morph content
                if (window.Superpowers && window.Superpowers.morph) {
                    window.Superpowers.morph(root, html);
                } else {
                    root.innerHTML = html;
                }

                // Scroll to top
                window.scrollTo(0, 0);

                // End transition
                root.classList.remove(`sp-transition-${transition}-leave`);
                root.classList.add(`sp-transition-${transition}-enter`);

                setTimeout(() => {
                    root.classList.remove(`sp-transition-${transition}-enter`);
                }, 300);

                // Re-init prefetching for new content
                this.initPrefetching();

            } catch (err) {
                console.error('Navigation failed:', err);
                window.location.href = url; // Fallback to full reload
            } finally {
                isNavigating = false;
            }
        },

        async fetchFragment(url) {
            if (cache.has(url)) return cache.get(url);

            const response = await fetch(url, {
                headers: {
                    'X-Superpowers-Fragment': 'true',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const html = await response.text();
            cache.set(url, html);
            return html;
        },

        handleLinkClick(e) {
            const link = e.target.closest('a');
            if (!link) return;

            const url = new URL(link.href);
            if (url.origin !== window.location.origin) return; // External link
            if (link.hasAttribute('download') || link.target === '_blank') return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return; // Special clicks

            e.preventDefault();
            this.navigate(link.href);
        },

        handlePopState(e) {
            this.navigate(window.location.href, false);
        },

        getNavRoot() {
            return document.querySelector('[s-nav-root]') || document.querySelector('main') || document.body;
        },

        initPrefetching() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.prefetch(entry.target.href);
                        observer.unobserve(entry.target);
                    }
                });
            });

            document.querySelectorAll('a[data-prefetch="true"]').forEach(link => {
                if (prefetchRegistry.has(link.href)) return;

                // Prefetch on hover
                link.addEventListener('mouseenter', () => this.prefetch(link.href), { once: true });

                // Prefetch when visible
                observer.observe(link);

                prefetchRegistry.add(link.href);
            });
        },

        async prefetch(url) {
            if (cache.has(url)) return;
            try {
                const html = await this.fetchFragment(url);
                console.log(`Prefetched: ${url}`);
            } catch (err) {
                // Silently fail prefetch
            }
        }
    };

    window.SuperpowersNav = SuperpowersNav;

    document.addEventListener('DOMContentLoaded', () => {
        SuperpowersNav.init();
    });
})();
