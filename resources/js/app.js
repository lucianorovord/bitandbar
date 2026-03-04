import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const LOADER_TRANSITION_MS = 1500;
const LOADER_EXIT_HOLD_MS = 220;
const LOADER_STATE_KEY = 'bb:pending-loader-transition';
const LOADER_VARIANT_KEY = 'bb:pending-loader-variant';

function createPageLoader() {
    const overlay = document.createElement('div');
    overlay.className = 'page-loader';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
        <div class="page-loader__box">
            <div class="page-loader__spinner" aria-hidden="true"></div>
            <div class="page-loader__book" aria-hidden="true">
                <span class="page-loader__book-page page-loader__book-page--1"></span>
                <span class="page-loader__book-page page-loader__book-page--2"></span>
                <span class="page-loader__book-page page-loader__book-page--3"></span>
            </div>
            <div class="page-loader__food" aria-hidden="true">
                <div class="page-loader__food-plate"></div>
                <span class="page-loader__ingredient page-loader__ingredient--1"></span>
                <span class="page-loader__ingredient page-loader__ingredient--2"></span>
                <span class="page-loader__ingredient page-loader__ingredient--3"></span>
                <span class="page-loader__ingredient page-loader__ingredient--4"></span>
            </div>
            <div class="page-loader__arm" aria-hidden="true">
                <span class="page-loader__roll-track"></span>
                <span class="page-loader__roll-shadow"></span>
                <span class="page-loader__roll-dumbbell">
                    <span class="page-loader__roll-handle"></span>
                    <span class="page-loader__roll-plate page-loader__roll-plate--left"></span>
                    <span class="page-loader__roll-plate page-loader__roll-plate--right"></span>
                </span>
            </div>
            <p class="page-loader__text">Cargando...</p>
        </div>
    `;

    const style = document.createElement('style');
    style.textContent = `
        .page-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            background: rgba(8, 8, 8, 0.94);
            backdrop-filter: blur(3px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: scale(1.015);
            transition: opacity 320ms ease, visibility 320ms ease, transform 320ms ease;
        }

        .page-loader.is-visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: scale(1);
        }

        .page-loader__box {
            display: grid;
            justify-items: center;
            gap: 0.75rem;
            opacity: 0;
            transform: translateY(10px) scale(0.98);
            transition: opacity 360ms ease, transform 360ms ease;
        }

        .page-loader.is-visible .page-loader__box {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .page-loader__spinner {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            border: 3px solid rgba(255, 209, 0, 0.25);
            border-top-color: #ffd100;
            animation: pageLoaderSpin 900ms linear infinite;
        }

        .page-loader__book {
            position: relative;
            width: 64px;
            height: 46px;
            display: none;
            perspective: 220px;
        }

        .page-loader__book::before {
            content: "";
            position: absolute;
            left: 0;
            top: 1px;
            bottom: 1px;
            width: 8px;
            border-radius: 4px 0 0 4px;
            background: linear-gradient(180deg, #d9a400, #a87400);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, 0.25);
            z-index: 4;
        }

        .page-loader__book::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 6px;
            border: 1px solid rgba(255, 209, 0, 0.45);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.25);
            z-index: 1;
            pointer-events: none;
        }

        .page-loader__book-page {
            position: absolute;
            inset: 1px 1px 1px 7px;
            border-radius: 2px 6px 6px 2px;
            transform-origin: 0% 50%;
            background: linear-gradient(145deg, #fff7db, #ffe59c);
            border: 1px solid rgba(255, 209, 0, 0.5);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2), inset -6px 0 8px rgba(0, 0, 0, 0.06);
            animation: pageLoaderFlip 1900ms cubic-bezier(.33, .01, .22, .99) infinite;
        }

        .page-loader__book-page--1 { animation-delay: 0ms; z-index: 3; }
        .page-loader__book-page--2 { animation-delay: 360ms; z-index: 2; }
        .page-loader__book-page--3 { animation-delay: 720ms; z-index: 2; }

        .page-loader.is-book .page-loader__spinner {
            display: none;
        }

        .page-loader.is-book .page-loader__book {
            display: block;
        }

        .page-loader__food {
            position: relative;
            width: 90px;
            height: 68px;
            display: none;
        }

        .page-loader__food-plate {
            position: absolute;
            left: 50%;
            bottom: 6px;
            width: 74px;
            height: 20px;
            transform: translateX(-50%);
            border-radius: 50%;
            background: radial-gradient(ellipse at center, #fff6db 0%, #ffe9ac 58%, #e1c46f 100%);
            box-shadow: inset 0 2px 0 rgba(255, 255, 255, 0.45), 0 6px 10px rgba(0, 0, 0, 0.28);
        }

        .page-loader__food-plate::before {
            content: "";
            position: absolute;
            left: 50%;
            top: 5px;
            width: 50px;
            height: 9px;
            transform: translateX(-50%);
            border-radius: 50%;
            background: rgba(255, 250, 235, 0.92);
        }

        .page-loader__ingredient {
            position: absolute;
            top: 0;
            left: 50%;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            opacity: 0;
            transform: translate(-50%, -14px) scale(0.85);
            animation: ingredientDrop 2200ms cubic-bezier(.22, .61, .36, 1) infinite;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .page-loader__ingredient--1 {
            margin-left: -18px;
            background: #9ad56b;
            animation-delay: 0ms;
        }

        .page-loader__ingredient--2 {
            margin-left: -5px;
            background: #f1705f;
            width: 10px;
            height: 10px;
            animation-delay: 420ms;
        }

        .page-loader__ingredient--3 {
            margin-left: 8px;
            background: #ffd156;
            border-radius: 3px;
            animation-delay: 840ms;
        }

        .page-loader__ingredient--4 {
            margin-left: 21px;
            background: #73c7ef;
            width: 9px;
            height: 9px;
            animation-delay: 1260ms;
        }

        .page-loader.is-food .page-loader__spinner,
        .page-loader.is-food .page-loader__book {
            display: none;
        }

        .page-loader.is-food .page-loader__food {
            display: block;
        }

        .page-loader__arm {
            position: relative;
            width: 130px;
            height: 76px;
            display: none;
        }

        .page-loader__roll-track {
            position: absolute;
            left: 4px;
            right: 4px;
            bottom: 18px;
            height: 3px;
            border-radius: 999px;
            background: rgba(255, 209, 0, 0.28);
        }

        .page-loader__roll-dumbbell {
            position: absolute;
            left: 12px;
            bottom: 19px;
            width: 56px;
            height: 26px;
            animation: dumbbellRoll 2600ms cubic-bezier(.4, 0, .2, 1) infinite;
            transform-origin: 50% 50%;
        }

        .page-loader__roll-handle {
            position: absolute;
            left: 11px;
            top: 50%;
            width: 34px;
            height: 8px;
            transform: translateY(-50%);
            border-radius: 999px;
            background: linear-gradient(180deg, #dbe2eb, #9fa9b6);
            box-shadow: inset 0 -1px 1px rgba(0, 0, 0, 0.3);
        }

        .page-loader__roll-plate {
            position: absolute;
            width: 12px;
            height: 24px;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 3px;
            background: linear-gradient(180deg, #3a4250, #181d26);
            box-shadow: inset 0 -1px 1px rgba(255, 255, 255, 0.08);
        }

        .page-loader__roll-plate::before {
            content: "";
            position: absolute;
            inset: 2px;
            border-radius: 2px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .page-loader__roll-plate--left {
            left: 3px;
        }

        .page-loader__roll-plate--right {
            right: 3px;
        }

        .page-loader__roll-shadow {
            position: absolute;
            left: 20px;
            bottom: 10px;
            width: 34px;
            height: 8px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.45);
            filter: blur(2px);
            animation: dumbbellShadow 2600ms cubic-bezier(.4, 0, .2, 1) infinite;
        }

        .page-loader.is-arm .page-loader__spinner,
        .page-loader.is-arm .page-loader__book,
        .page-loader.is-arm .page-loader__food {
            display: none;
        }

        .page-loader.is-arm .page-loader__arm {
            display: block;
        }

        .page-loader__text {
            margin: 0;
            color: #f5f5f5;
            font-size: 0.95rem;
            letter-spacing: 0.03em;
        }

        @keyframes pageLoaderSpin {
            to { transform: rotate(360deg); }
        }

        @keyframes pageLoaderFlip {
            0%   { transform: rotateY(0deg); opacity: 0.98; }
            35%  { transform: rotateY(-30deg); opacity: 0.98; }
            68%  { transform: rotateY(-155deg); opacity: 0.8; }
            100% { transform: rotateY(-175deg); opacity: 0.2; }
        }

        @keyframes ingredientDrop {
            0% {
                opacity: 0;
                transform: translate(-50%, -14px) scale(0.85);
            }
            12% {
                opacity: 1;
            }
            56% {
                opacity: 1;
                transform: translate(-50%, 37px) scale(1);
            }
            68% {
                opacity: 1;
                transform: translate(-50%, 33px) scale(0.95);
            }
            100% {
                opacity: 0;
                transform: translate(-50%, 36px) scale(0.9);
            }
        }

        @keyframes dumbbellRoll {
            0% {
                transform: translateX(-24px) rotate(0deg);
            }
            34% {
                transform: translateX(30px) rotate(660deg);
            }
            58% {
                transform: translateX(30px) rotate(660deg);
            }
            100% {
                transform: translateX(84px) rotate(1210deg);
            }
        }

        @keyframes dumbbellShadow {
            0% {
                transform: translateX(-24px) scaleX(0.88);
                opacity: 0.4;
            }
            34% {
                transform: translateX(30px) scaleX(1.02);
                opacity: 0.55;
            }
            58% {
                transform: translateX(30px) scaleX(1.02);
                opacity: 0.55;
            }
            100% {
                transform: translateX(84px) scaleX(0.9);
                opacity: 0.38;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .page-loader,
            .page-loader__spinner,
            .page-loader__book-page,
            .page-loader__ingredient,
            .page-loader__roll-dumbbell,
            .page-loader__roll-shadow {
                transition: none;
                animation: none;
            }
        }
    `;

    document.head.appendChild(style);
    document.body.appendChild(overlay);
    return overlay;
}

function randomLoaderDelay() {
    return LOADER_TRANSITION_MS;
}

function setPendingLoaderTransition(variant = 'spinner') {
    try {
        sessionStorage.setItem(LOADER_STATE_KEY, '1');
        sessionStorage.setItem(LOADER_VARIANT_KEY, variant);
    } catch (_) {
        // noop: storage can be unavailable in private contexts
    }
}

function consumePendingLoaderTransition() {
    try {
        const value = sessionStorage.getItem(LOADER_STATE_KEY);
        if (value === '1') {
            sessionStorage.removeItem(LOADER_STATE_KEY);
            const variant = sessionStorage.getItem(LOADER_VARIANT_KEY) || 'spinner';
            sessionStorage.removeItem(LOADER_VARIANT_KEY);
            return variant;
        }
    } catch (_) {
        // noop: treat as not pending
    }
    return null;
}

function resolveLoaderVariant(url) {
    const isRecetas = /^\/recetas(?:\/|$)/.test(url.pathname);
    const isComidaRegistrar = /^\/comida\/registrar(?:\/|$)/.test(url.pathname);
    const isEntrenamientoRegistrar = /^\/entrenamiento\/registrar(?:\/|$)/.test(url.pathname);
    if (isRecetas) return 'book';
    if (isComidaRegistrar) return 'food';
    if (isEntrenamientoRegistrar) return 'arm';
    return 'spinner';
}

function installPageTransitionLoader() {
    const loader = createPageLoader();
    let isNavigating = false;
    const arrivedVariant = consumePendingLoaderTransition();

    const showLoader = (variant = 'spinner') => {
        loader.classList.toggle('is-book', variant === 'book');
        loader.classList.toggle('is-food', variant === 'food');
        loader.classList.toggle('is-arm', variant === 'arm');
        loader.classList.add('is-visible');
    };

    const hideLoaderSmoothly = () => {
        window.setTimeout(() => {
            loader.classList.remove('is-visible');
        }, LOADER_EXIT_HOLD_MS);
    };

    if (arrivedVariant) {
        showLoader(arrivedVariant);

        if (document.readyState === 'complete') {
            requestAnimationFrame(hideLoaderSmoothly);
        } else {
            window.addEventListener('load', hideLoaderSmoothly, { once: true });
        }
    }

    const isPlainLeftClick = (event) =>
        event.button === 0 &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.shiftKey &&
        !event.altKey;

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[href]');
        if (!anchor || isNavigating || !isPlainLeftClick(event)) return;

        const href = anchor.getAttribute('href');
        if (!href || href.startsWith('#') || anchor.target === '_blank' || anchor.hasAttribute('download')) return;

        const url = new URL(anchor.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        if (url.href === window.location.href) return;

        event.preventDefault();
        isNavigating = true;
        const variant = resolveLoaderVariant(url);
        showLoader(variant);
        setPendingLoaderTransition(variant);

        setTimeout(() => {
            window.location.assign(url.href);
        }, randomLoaderDelay());
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || isNavigating) return;
        if (event.defaultPrevented) return;
        if (form.dataset.loaderIgnore === '1') return;

        event.preventDefault();
        isNavigating = true;
        showLoader();
        setPendingLoaderTransition('spinner');

        setTimeout(() => {
            form.submit();
        }, randomLoaderDelay());
    });

    window.addEventListener('pageshow', () => {
        isNavigating = false;
        hideLoaderSmoothly();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', installPageTransitionLoader, { once: true });
} else {
    installPageTransitionLoader();
}
