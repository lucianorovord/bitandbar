const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initRevealAnimations() {
    const revealTargets = Array.from(document.querySelectorAll(
        '.hero, .home-highlight, .home-feature, .kcal-box, .action-card, .home-footer'
    ));

    revealTargets.forEach((el, index) => {
        el.classList.add('js-reveal');
        el.style.setProperty('--reveal-delay', `${Math.min(index * 140, 840)}ms`);
    });

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        revealTargets.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        {
            threshold: 0.16,
            rootMargin: '0px 0px -10% 0px',
        }
    );

    revealTargets.forEach((el) => observer.observe(el));
}

function initHeroParallax() {
    const hero = document.querySelector('.hero');
    if (!hero || prefersReducedMotion) return;

    const heroTitle = hero.querySelector('.hero__title');
    const heroText = hero.querySelector('.hero__text');
    if (!heroTitle || !heroText) return;

    const maxOffset = 10;

    hero.addEventListener('mousemove', (event) => {
        const rect = hero.getBoundingClientRect();
        const px = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
        const py = ((event.clientY - rect.top) / rect.height - 0.5) * 2;

        heroTitle.style.transform = `translate3d(${(-px * maxOffset).toFixed(2)}px, ${(-py * maxOffset).toFixed(2)}px, 0)`;
        heroText.style.transform = `translate3d(${(-px * (maxOffset * 0.45)).toFixed(2)}px, ${(-py * (maxOffset * 0.45)).toFixed(2)}px, 0)`;
    });

    hero.addEventListener('mouseleave', () => {
        heroTitle.style.transform = 'translate3d(0, 0, 0)';
        heroText.style.transform = 'translate3d(0, 0, 0)';
    });
}

function initCounterAnimation() {
    const counters = Array.from(document.querySelectorAll('.kcal-box__total strong, .kcal-box__item strong'));
    if (!counters.length) return;

    const animate = (node) => {
        if (node.dataset.counted === '1') return;

        const raw = node.textContent ?? '';
        const match = raw.match(/-?\d+/);
        if (!match) return;

        const target = Number.parseInt(match[0], 10);
        if (Number.isNaN(target)) return;

        node.dataset.counted = '1';
        const startTime = performance.now();
        const duration = 900;

        const step = (time) => {
            const progress = Math.min((time - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = Math.round(target * eased);
            node.textContent = raw.replace(match[0], String(value));

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    };

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        counters.forEach(animate);
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.35 }
    );

    counters.forEach((node) => observer.observe(node));
}

function init() {
    document.body.classList.add('js-enabled');
    initRevealAnimations();
    initHeroParallax();
    initCounterAnimation();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}
