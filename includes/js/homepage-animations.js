/**
 * PedalWorks Dynamics — High-Performance Homepage Animation Engine
 * Optimized for 60 FPS, Apple HIG fluidity, zero layout thrashing
 * Scope: Strictly restricted to index.php (Homepage)
 */

(function () {
    'use strict';

    // Verify Anime.js is loaded
    if (typeof anime === 'undefined') {
        console.warn('[PedalWorks Dynamics] Anime.js not detected. Scroll animations bypassed.');
        return;
    }

    // Check for reduced motion preference (Accessibility First)
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        console.info('[PedalWorks Dynamics] prefers-reduced-motion is active. Simplified static display applied.');
        document.documentElement.classList.add('pw-reduced-motion');
        return;
    }

    // Mark document as ready for animations
    document.body.classList.add('pw-anim-ready');

    /* ==========================================================================
       1. HERO INTRO ENTRANCE TIMELINE
       ========================================================================== */
    function initHeroIntro() {
        requestAnimationFrame(() => {
            const heroTimeline = anime.timeline({
                easing: 'easeOutExpo',
                duration: 950
            });

            heroTimeline
                .add({
                    targets: '.pw-hero-title',
                    translateY: [35, 0],
                    opacity: [0, 1],
                    duration: 850
                })
                .add({
                    targets: '.pw-hero-desc',
                    translateY: [25, 0],
                    opacity: [0, 1],
                    duration: 800
                }, '-=600')
                .add({
                    targets: '.pw-hero-actions .pw-btn-trail, .pw-hero-actions .pw-btn-glass',
                    translateY: [20, 0],
                    opacity: [0, 1],
                    scale: [0.95, 1],
                    delay: anime.stagger(100),
                    duration: 700
                }, '-=550')
                .add({
                    targets: '.pw-hero-visual-card',
                    translateY: [40, 0],
                    opacity: [0, 1],
                    scale: [0.94, 1],
                    rotateX: [5, 0],
                    duration: 1000,
                    easing: 'cubicBezier(0.16, 1, 0.3, 1)'
                }, '-=750')
                .add({
                    targets: '.pw-topographic-overlay path',
                    strokeDashoffset: [anime.setDashoffset, 0],
                    easing: 'easeInOutSine',
                    duration: 1400,
                    delay: anime.stagger(150)
                }, '-=650')
                .add({
                    targets: '.pw-hero-badge-float',
                    scale: [0.85, 1],
                    opacity: [0, 1],
                    duration: 600,
                    easing: 'easeOutBack'
                }, '-=550')
                .add({
                    targets: '.pw-scroll-cue',
                    opacity: [0, 1],
                    translateY: [15, 0],
                    duration: 650
                }, '-=400');
        });
    }

    /* ==========================================================================
       2. HARDWARE-ACCELERATED PARALLAX ENGINE (Zero Layout Thrashing)
       ========================================================================== */
    let latestScrollY = window.scrollY || window.pageYOffset;
    let smoothScrollY = latestScrollY;
    let isLoopRunning = false;
    let rafId = null;

    // Mouse horizontal parallax sway tracking (Desktop)
    let mouseNormX = 0;
    let smoothMouseX = 0;
    let mouseTicking = false;

    window.addEventListener('mousemove', (e) => {
        if (!mouseTicking) {
            mouseTicking = true;
            requestAnimationFrame(() => {
                mouseNormX = (e.clientX / window.innerWidth) - 0.5;
                mouseTicking = false;
                wakeAnimationLoop();
            });
        }
    }, { passive: true });

    // Cache Mountain Parallax 7 Layers & Atmospheric Overlays
    const layer0 = document.querySelector('.pw-mountain-layer-0');
    const layer1 = document.querySelector('.pw-mountain-layer-1');
    const layer2 = document.querySelector('.pw-mountain-layer-2');
    const layer3 = document.querySelector('.pw-mountain-layer-3');
    const layer4 = document.querySelector('.pw-mountain-layer-4');
    const layer5 = document.querySelector('.pw-mountain-layer-5');
    const layer6 = document.querySelector('.pw-mountain-layer-6');
    const mistFar = document.querySelector('.pw-mountain-mist-far');
    const mistNear = document.querySelector('.pw-mountain-mist-near');
    const mountainTint = document.querySelector('.pw-mountain-tint');
    const compassRing = document.querySelector('.pw-parallax-compass');
    const contourSvg = document.querySelector('.pw-parallax-contours');
    const contour1 = document.querySelector('.pw-contour-1');
    const contour2 = document.querySelector('.pw-contour-2');
    const contour3 = document.querySelector('.pw-contour-3');
    const heroCard = document.querySelector('.pw-hero-visual-card');

    // Trail Progress Elements
    const trackFill = document.querySelector('.pw-trail-track-fill');
    const elevationValue = document.querySelector('.pw-elevation-value');
    const trailDots = document.querySelectorAll('.pw-trail-dot');
    const rail = document.querySelector('.pw-trail-progress-rail');
    const footer = document.querySelector('.pw-glass-footer');

    // Sections for Waypoint Activation
    const waypoints = [
        { id: 'hero', element: document.querySelector('.pw-hero-section'), altitude: 1450 },
        { id: 'about', element: document.querySelector('#about'), altitude: 1120 },
        { id: 'products', element: document.querySelector('#products'), altitude: 780 },
        { id: 'featured', element: document.querySelector('#featured-products'), altitude: 620 },
        { id: 'services', element: document.querySelector('#services'), altitude: 420 },
        { id: 'workshop', element: document.querySelector('#workshop-basecamp'), altitude: 45 }
    ];

    // Cached layout coordinates to eliminate forced reflows inside requestAnimationFrame
    let cachedWaypoints = [];
    let cachedFooterTop = 0;
    let cachedMaxScroll = 1;
    let cachedVh = window.innerHeight;
    let lastActiveIdx = -1;
    let lastAltitude = -1;
    let lastRailVisible = null;

    function refreshLayoutMetrics() {
        const curScroll = window.scrollY || window.pageYOffset;
        cachedVh = window.innerHeight;
        cachedMaxScroll = Math.max(1, document.documentElement.scrollHeight - cachedVh);

        cachedWaypoints = waypoints.map(wp => {
            if (!wp.element) return 0;
            const r = wp.element.getBoundingClientRect();
            return r.top + curScroll;
        });

        if (footer) {
            const fRect = footer.getBoundingClientRect();
            cachedFooterTop = fRect.top + curScroll;
        }
    }

    // Debounced resize metric recalculation
    let resizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            refreshLayoutMetrics();
            wakeAnimationLoop();
        }, 150);
    }, { passive: true });

    function onScrollUpdate() {
        latestScrollY = window.scrollY || window.pageYOffset;
    }

    function wakeAnimationLoop() {
        if (!isLoopRunning) {
            isLoopRunning = true;
            rafId = window.requestAnimationFrame(renderParallax);
        }
    }

    window.addEventListener('scroll', () => {
        onScrollUpdate();
        wakeAnimationLoop();
    }, { passive: true });

    // Asymptotic curve: responsive initial slope that smoothly tapers
    function getGentleDescent(scrollY, rate, maxPx) {
        if (maxPx <= 0) return 0;
        return maxPx * (1 - Math.exp(-(scrollY * rate) / maxPx));
    }

    function renderParallax(timestamp) {
        const timeSec = timestamp ? timestamp * 0.001 : performance.now() * 0.001;

        // Linear interpolation with responsive tracking
        smoothScrollY += (latestScrollY - smoothScrollY) * 0.14;
        smoothMouseX += (mouseNormX - smoothMouseX) * 0.08;

        const scrollProgress = Math.min(Math.max(smoothScrollY / cachedMaxScroll, 0), 1);
        const vh = cachedVh;
        const isNearHero = smoothScrollY < (vh * 1.3);

        // -------------------------------------------------------------
        // A. MOUNTAIN PARALLAX (Active only when near top of viewport)
        // -------------------------------------------------------------
        if (isNearHero) {
            const windFar = Math.sin(timeSec * 0.65) * 3;
            const windNear = Math.cos(timeSec * 0.95) * 5;

            const y0 = getGentleDescent(smoothScrollY, 0.012, vh * 0.04);
            const y1 = getGentleDescent(smoothScrollY, 0.030, vh * 0.09);
            const y2 = getGentleDescent(smoothScrollY, 0.055, vh * 0.14);
            const y3 = getGentleDescent(smoothScrollY, 0.085, vh * 0.19);
            const y4 = getGentleDescent(smoothScrollY, 0.120, vh * 0.24);
            const y5 = getGentleDescent(smoothScrollY, 0.160, vh * 0.29);
            const y6 = getGentleDescent(smoothScrollY, 0.200, vh * 0.35);

            if (layer0) layer0.style.transform = `translate3d(0, ${y0.toFixed(1)}px, 0)`;

            if (layer1) {
                const l1Scale = (1 + scrollProgress * 0.012).toFixed(3);
                const l1X = (smoothMouseX * -8 + windFar * 0.35).toFixed(1);
                layer1.style.transform = `translate3d(${l1X}px, ${y1.toFixed(1)}px, 0) scale(${l1Scale})`;
            }

            if (layer2) {
                const l2Scale = (1 + scrollProgress * 0.018).toFixed(3);
                const l2X = (smoothMouseX * -15 + windFar * 0.65).toFixed(1);
                layer2.style.transform = `translate3d(${l2X}px, ${y2.toFixed(1)}px, 0) scale(${l2Scale})`;
            }

            if (mistFar) {
                const mistFarY = (y2 * 0.9).toFixed(1);
                mistFar.style.transform = `translate3d(${(windFar * 1.5).toFixed(1)}px, ${mistFarY}px, 0)`;
                mistFar.style.opacity = (0.75 + Math.sin(timeSec * 0.5) * 0.1 + scrollProgress * 0.15).toFixed(2);
            }

            if (layer3) {
                const l3Scale = (1 + scrollProgress * 0.026).toFixed(3);
                const l3X = (smoothMouseX * -22 + windFar).toFixed(1);
                layer3.style.transform = `translate3d(${l3X}px, ${y3.toFixed(1)}px, 0) scale(${l3Scale})`;
            }

            if (layer4) {
                const l4Scale = (1 + scrollProgress * 0.036).toFixed(3);
                const l4X = (smoothMouseX * -30 + windNear * 0.6).toFixed(1);
                layer4.style.transform = `translate3d(${l4X}px, ${y4.toFixed(1)}px, 0) scale(${l4Scale})`;
            }

            if (mistNear) {
                const mistNearY = (y5 * 0.85).toFixed(1);
                mistNear.style.transform = `translate3d(${(windNear * 1.2).toFixed(1)}px, ${mistNearY}px, 0)`;
                mistNear.style.opacity = (0.80 + scrollProgress * 0.18).toFixed(2);
            }

            if (layer5) {
                const l5Scale = (1 + scrollProgress * 0.046).toFixed(3);
                const l5X = (smoothMouseX * -40 + windNear * 0.85).toFixed(1);
                layer5.style.transform = `translate3d(${l5X}px, ${y5.toFixed(1)}px, 0) scale(${l5Scale})`;
            }

            if (layer6) {
                const l6Scale = (1 + scrollProgress * 0.058).toFixed(3);
                const l6X = (smoothMouseX * -50 + windNear).toFixed(1);
                layer6.style.transform = `translate3d(${l6X}px, ${y6.toFixed(1)}px, 0) scale(${l6Scale})`;
            }

            if (mountainTint) {
                mountainTint.style.opacity = (0.75 + scrollProgress * 0.22).toFixed(2);
            }

            if (contourSvg) {
                contourSvg.style.transform = `translate3d(${(smoothScrollY * -0.025).toFixed(1)}px, ${(smoothScrollY * 0.05).toFixed(1)}px, 0)`;
            }
            if (contour1) {
                contour1.style.strokeDashoffset = (smoothScrollY * 0.35 + timeSec * 6).toFixed(1);
            }
            if (contour2) {
                contour2.style.strokeDashoffset = (smoothScrollY * -0.45 - timeSec * 5).toFixed(1);
            }
            if (contour3) {
                contour3.style.strokeDashoffset = (smoothScrollY * 0.55 + timeSec * 8).toFixed(1);
            }

            if (compassRing) {
                const compassRot = (smoothScrollY * 0.05 + smoothMouseX * 14 + timeSec * 1.5).toFixed(1);
                compassRing.style.transform = `translate3d(0, ${(smoothScrollY * 0.10).toFixed(1)}px, 0) rotate(${compassRot}deg)`;
            }

            if (heroCard) {
                const heroOpacity = Math.max(0, 1 - (smoothScrollY / (vh * 0.95)));
                heroCard.style.transform = `translate3d(0, ${(smoothScrollY * 0.12).toFixed(1)}px, 0)`;
                heroCard.style.opacity = heroOpacity.toFixed(2);
            }
        }

        // -------------------------------------------------------------
        // B. TRAIL WAYPOINT PROGRESS (Pure Arithmetic, Zero Reflows)
        // -------------------------------------------------------------
        if (trackFill) {
            trackFill.style.height = `${(scrollProgress * 100).toFixed(1)}%`;
        }

        if (elevationValue) {
            const currentAltitude = Math.round(1450 - (scrollProgress * (1450 - 45)));
            if (currentAltitude !== lastAltitude) {
                lastAltitude = currentAltitude;
                elevationValue.textContent = `${currentAltitude.toLocaleString()}m`;
            }
        }

        const midScreen = smoothScrollY + (vh * 0.45);
        let activeIdx = 0;
        for (let i = 0; i < cachedWaypoints.length; i++) {
            if (midScreen >= cachedWaypoints[i] - 120) {
                activeIdx = i;
            }
        }

        if (activeIdx !== lastActiveIdx) {
            lastActiveIdx = activeIdx;
            trailDots.forEach((dot, idx) => {
                dot.classList.toggle('active', idx === activeIdx);
            });
        }

        if (rail && cachedFooterTop > 0) {
            const isNearFooter = (cachedFooterTop - smoothScrollY) < (vh * 0.7);
            if (isNearFooter !== lastRailVisible) {
                lastRailVisible = isNearFooter;
                rail.style.opacity = isNearFooter ? '0' : '1';
                rail.style.pointerEvents = isNearFooter ? 'none' : 'auto';
            }
        }

        // -------------------------------------------------------------
        // C. INTELLIGENT SLEEP (Zero CPU utilization when idle past hero)
        // -------------------------------------------------------------
        const isPastHero = smoothScrollY >= (vh * 1.25);
        const isScrollSettled = Math.abs(latestScrollY - smoothScrollY) < 0.2;
        const isMouseSettled = Math.abs(mouseNormX - smoothMouseX) < 0.002;

        if (isPastHero && isScrollSettled && isMouseSettled) {
            isLoopRunning = false;
            return;
        }

        rafId = window.requestAnimationFrame(renderParallax);
    }

    /* ==========================================================================
       3. SCROLL-TRIGGERED SECTION REVEALS (Intersection Observer + Anime.js)
       ========================================================================== */
    function initScrollReveals() {
        const observerOptions = {
            root: null,
            rootMargin: '0px 0px -10% 0px',
            threshold: 0.12
        };

        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    const sectionId = target.getAttribute('id') || target.className;

                    animateSectionEntry(target, sectionId);
                    observer.unobserve(target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.pw-scroll-section').forEach(sec => {
            revealObserver.observe(sec);
        });
    }

    function animateSectionEntry(container, id) {
        // Section Head
        const head = container.querySelector('.pw-section-head');
        if (head) {
            anime({
                targets: [
                    head.querySelector('.pw-section-eyebrow'),
                    head.querySelector('.pw-section-title'),
                    head.querySelector('.pw-section-sub')
                ].filter(Boolean),
                translateY: [30, 0],
                opacity: [0, 1],
                delay: anime.stagger(100),
                duration: 750,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });
        }

        // Feature Pillars (#about)
        if (id === 'about' || container.querySelector('.pw-feature-card')) {
            const cards = container.querySelectorAll('.pw-feature-card');
            anime({
                targets: cards,
                translateY: [40, 0],
                scale: [0.94, 1],
                opacity: [0, 1],
                delay: anime.stagger(90),
                duration: 750,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });

            anime({
                targets: container.querySelectorAll('.pw-feature-icon'),
                rotate: [-20, 0],
                scale: [0.8, 1],
                delay: anime.stagger(90, { start: 150 }),
                duration: 650,
                easing: 'easeOutBack'
            });
        }

        // Product Categories
        if (id === 'products' || container.querySelector('.pw-category-card')) {
            const catCards = container.querySelectorAll('.pw-category-card');
            if (catCards.length) {
                anime({
                    targets: catCards,
                    translateY: [35, 0],
                    opacity: [0, 1],
                    scale: [0.95, 1],
                    delay: anime.stagger(80),
                    duration: 700,
                    easing: 'cubicBezier(0.16, 1, 0.3, 1)'
                });
            }
        }

        // Featured Catalog
        if (id === 'featured-products' || container.querySelector('.pw-product-item')) {
            const items = container.querySelectorAll('.pw-product-item');
            anime({
                targets: items,
                translateY: [45, 0],
                scale: [0.95, 1],
                opacity: [0, 1],
                delay: anime.stagger(70, { grid: [4, 2], from: 'center' }),
                duration: 750,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });
        }

        // Workshop Services (#services)
        if (id === 'services' || container.querySelector('.pw-service-card')) {
            const serviceCards = container.querySelectorAll('.pw-service-card');
            anime({
                targets: serviceCards,
                translateY: [40, 0],
                opacity: [0, 1],
                scale: [0.95, 1],
                delay: anime.stagger(80),
                duration: 750,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });

            anime({
                targets: container.querySelectorAll('.pw-service-icon-box'),
                rotate: [-25, 0],
                scale: [0.85, 1],
                delay: anime.stagger(80, { start: 120 }),
                duration: 600,
                easing: 'easeOutBack'
            });
        }

        // Basecamp Workshop Banner
        if (id === 'workshop-basecamp' || container.querySelector('.pw-workshop-banner')) {
            const banner = container.querySelector('.pw-workshop-banner');
            anime({
                targets: banner,
                scale: [0.96, 1],
                opacity: [0, 1],
                translateY: [30, 0],
                duration: 800,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });
        }
    }

    /* ==========================================================================
       4. INTERACTIVE 3D MOUSE PARALLAX TILT (Native GPU Acceleration)
       ========================================================================== */
    function initCardMouseTilt() {
        const tiltCards = document.querySelectorAll('.pw-tilt-card, .pw-hero-visual-card');

        tiltCards.forEach(card => {
            let ticking = false;

            card.addEventListener('mousemove', (e) => {
                if (ticking) return;
                ticking = true;

                requestAnimationFrame(() => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;

                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;

                    const rotateX = -((y - centerY) / centerY) * 4;
                    const rotateY = ((x - centerX) / centerX) * 4;

                    card.style.transform = `perspective(800px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) translateZ(4px)`;
                    ticking = false;
                });
            }, { passive: true });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) translateZ(0)';
            });
        });
    }

    /* ==========================================================================
       5. CATEGORY FILTERING TRANSITIONS
       ========================================================================== */
    function initFilterTransitions() {
        const filterPills = document.querySelectorAll('.pw-filter-pill');
        const productItems = document.querySelectorAll('.pw-product-item');

        filterPills.forEach(pill => {
            pill.addEventListener('click', function (e) {
                e.preventDefault();
                filterPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                const selectedCategory = this.getAttribute('data-category');
                const itemsToShow = [];
                const itemsToHide = [];

                productItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category');
                    if (selectedCategory === 'all' || itemCategory === selectedCategory) {
                        itemsToShow.push(item);
                    } else {
                        itemsToHide.push(item);
                    }
                });

                if (itemsToHide.length > 0) {
                    anime({
                        targets: itemsToHide,
                        scale: [1, 0.85],
                        opacity: [1, 0],
                        duration: 250,
                        easing: 'easeInQuad',
                        complete: function () {
                            itemsToHide.forEach(el => el.style.display = 'none');
                        }
                    });
                }

                itemsToShow.forEach(el => el.style.display = 'block');
                anime({
                    targets: itemsToShow,
                    scale: [0.85, 1],
                    opacity: [0, 1],
                    translateY: [20, 0],
                    delay: anime.stagger(50),
                    duration: 450,
                    easing: 'easeOutBack'
                });
            });
        });
    }

    /* ==========================================================================
       6. WAYPOINT DOT CLICK TO SMOOTH SCROLL
       ========================================================================== */
    function initWaypointClicks() {
        trailDots.forEach(dot => {
            dot.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.querySelector(targetId);

                if (targetEl) {
                    const targetPosition = targetEl.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) - 70;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        const scrollCue = document.querySelector('.pw-scroll-cue');
        if (scrollCue) {
            scrollCue.addEventListener('click', function (e) {
                e.preventDefault();
                const aboutSec = document.querySelector('#about');
                if (aboutSec) {
                    const pos = aboutSec.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) - 70;
                    window.scrollTo({
                        top: pos,
                        behavior: 'smooth'
                    });
                }
            });
        }
    }

    /* ==========================================================================
       INITIALIZATION BOOTSTRAPPER
       ========================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        refreshLayoutMetrics();
        initHeroIntro();
        initScrollReveals();
        initCardMouseTilt();
        initFilterTransitions();
        initWaypointClicks();

        // Launch animation loop
        wakeAnimationLoop();
    });

    // Re-verify layout metrics once all images and stylesheets are fully loaded
    window.addEventListener('load', function () {
        refreshLayoutMetrics();
    }, { passive: true });

})();
