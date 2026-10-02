/**
 * PedalWorks Dynamics — Homepage Scroll Animation Engine
 * Built with Anime.js v3.2.2
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
        const heroTimeline = anime.timeline({
            easing: 'easeOutExpo',
            duration: 1000
        });

        heroTimeline
            .add({
                targets: '.pw-hero-badge',
                translateY: [-24, 0],
                opacity: [0, 1],
                scale: [0.94, 1],
                duration: 750
            })
            .add({
                targets: '.pw-hero-title',
                translateY: [35, 0],
                opacity: [0, 1],
                duration: 900
            }, '-=500')
            .add({
                targets: '.pw-hero-desc',
                translateY: [25, 0],
                opacity: [0, 1],
                duration: 850
            }, '-=650')
            .add({
                targets: '.pw-hero-actions .pw-btn-trail, .pw-hero-actions .pw-btn-glass',
                translateY: [20, 0],
                opacity: [0, 1],
                scale: [0.95, 1],
                delay: anime.stagger(120),
                duration: 750
            }, '-=600')
            .add({
                targets: '.pw-stat-pill',
                translateY: [20, 0],
                opacity: [0, 1],
                delay: anime.stagger(100),
                duration: 700
            }, '-=550')
            .add({
                targets: '.pw-hero-visual-card',
                translateY: [50, 0],
                opacity: [0, 1],
                scale: [0.92, 1],
                rotateX: [6, 0],
                duration: 1100,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            }, '-=850')
            .add({
                targets: '.pw-topographic-overlay path',
                strokeDashoffset: [anime.setDashoffset, 0],
                easing: 'easeInOutSine',
                duration: 1600,
                delay: anime.stagger(180)
            }, '-=700')
            .add({
                targets: '.pw-hero-badge-float',
                scale: [0.8, 1],
                opacity: [0, 1],
                duration: 650,
                easing: 'easeOutBack'
            }, '-=600')
            .add({
                targets: '.pw-scroll-cue',
                opacity: [0, 1],
                translateY: [15, 0],
                duration: 700
            }, '-=400');
    }

    /* ==========================================================================
       2. CONTINUOUS SCROLL PARALLAX ENGINE (60 FPS LERPed)
       ========================================================================== */
    let latestScrollY = window.scrollY || window.pageYOffset;
    let smoothScrollY = latestScrollY;
    let isTicking = false;

    // Mouse horizontal parallax sway tracking (Desktop)
    let mouseNormX = 0;
    let smoothMouseX = 0;
    window.addEventListener('mousemove', (e) => {
        mouseNormX = (e.clientX / window.innerWidth) - 0.5; // -0.5 to 0.5
    }, { passive: true });

    // Cache Mountain Parallax 7 Layers
    const layer0 = document.querySelector('.pw-mountain-layer-0');
    const layer1 = document.querySelector('.pw-mountain-layer-1');
    const layer2 = document.querySelector('.pw-mountain-layer-2');
    const layer3 = document.querySelector('.pw-mountain-layer-3');
    const layer4 = document.querySelector('.pw-mountain-layer-4');
    const layer5 = document.querySelector('.pw-mountain-layer-5');
    const layer6 = document.querySelector('.pw-mountain-layer-6');
    const compassRing = document.querySelector('.pw-parallax-compass');
    const contourSvg = document.querySelector('.pw-parallax-contours');
    const heroCard = document.querySelector('.pw-hero-visual-card');

    // Trail Progress Elements
    const trackFill = document.querySelector('.pw-trail-track-fill');
    const elevationValue = document.querySelector('.pw-elevation-value');
    const trailDots = document.querySelectorAll('.pw-trail-dot');

    // Sections for Waypoint Activation
    const waypoints = [
        { id: 'hero', element: document.querySelector('.pw-hero-section'), altitude: 1450 },
        { id: 'about', element: document.querySelector('#about'), altitude: 1120 },
        { id: 'products', element: document.querySelector('#products'), altitude: 780 },
        { id: 'services', element: document.querySelector('#services'), altitude: 420 },
        { id: 'workshop', element: document.querySelector('#workshop-basecamp'), altitude: 45 }
    ];

    function onScrollUpdate() {
        latestScrollY = window.scrollY || window.pageYOffset;
        if (!isTicking) {
            window.requestAnimationFrame(renderParallax);
            isTicking = true;
        }
    }

    function renderParallax() {
        // Linear interpolation for smooth velocity
        smoothScrollY += (latestScrollY - smoothScrollY) * 0.10;
        smoothMouseX += (mouseNormX - smoothMouseX) * 0.08;

        const maxScroll = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
        const scrollProgress = Math.min(Math.max(smoothScrollY / maxScroll, 0), 1);
        const vh = window.innerHeight;

        // Asymptotic curve: initially linear with gentle slope (rate), smoothly tapering as it approaches maxPx
        // Ensures the mountain layers never plummet or descend rapidly off the screen
        function getGentleDescent(scrollY, rate, maxPx) {
            if (maxPx <= 0) return 0;
            return maxPx * (1 - Math.exp(-(scrollY * rate) / maxPx));
        }

        const y0 = getGentleDescent(smoothScrollY, 0.005, vh * 0.025);
        const y1 = getGentleDescent(smoothScrollY, 0.012, vh * 0.05);
        const y2 = getGentleDescent(smoothScrollY, 0.020, vh * 0.08);
        const y3 = getGentleDescent(smoothScrollY, 0.032, vh * 0.11);
        const y4 = getGentleDescent(smoothScrollY, 0.046, vh * 0.14);
        const y5 = getGentleDescent(smoothScrollY, 0.062, vh * 0.17);
        const y6 = getGentleDescent(smoothScrollY, 0.080, vh * 0.20);

        // Established Multi-Layer Mountain Parallax (Scroll & Subtle Mouse Sway)
        // Layer 0: Sky
        if (layer0) {
            layer0.style.transform = `translate3d(0, ${y0.toFixed(2)}px, 0)`;
        }
        // Layer 1: Distant Peaks
        if (layer1) {
            layer1.style.transform = `translate3d(${(smoothMouseX * -6).toFixed(2)}px, ${y1.toFixed(2)}px, 0) scale(${1 + scrollProgress * 0.015})`;
        }
        // Layer 2: Secondary Mountain Ridge
        if (layer2) {
            layer2.style.transform = `translate3d(${(smoothMouseX * -12).toFixed(2)}px, ${y2.toFixed(2)}px, 0)`;
        }
        // Layer 3: Midground Mountain Range
        if (layer3) {
            layer3.style.transform = `translate3d(${(smoothMouseX * -18).toFixed(2)}px, ${y3.toFixed(2)}px, 0)`;
        }
        // Layer 4: Mountain Slope & Treeline
        if (layer4) {
            layer4.style.transform = `translate3d(${(smoothMouseX * -24).toFixed(2)}px, ${y4.toFixed(2)}px, 0)`;
        }
        // Layer 5: Near Pine Forest Ridge
        if (layer5) {
            layer5.style.transform = `translate3d(${(smoothMouseX * -32).toFixed(2)}px, ${y5.toFixed(2)}px, 0)`;
        }
        // Layer 6: Foreground Ridge & Trail
        if (layer6) {
            layer6.style.transform = `translate3d(${(smoothMouseX * -40).toFixed(2)}px, ${y6.toFixed(2)}px, 0)`;
        }

        // Topographic Contours & Compass
        if (compassRing) {
            compassRing.style.transform = `translate3d(0, ${(smoothScrollY * 0.06).toFixed(2)}px, 0) rotate(${(smoothScrollY * 0.03).toFixed(2)}deg)`;
        }
        if (contourSvg) {
            contourSvg.style.transform = `translate3d(${(smoothScrollY * -0.015).toFixed(2)}px, ${(smoothScrollY * 0.03).toFixed(2)}px, 0)`;
        }

        // 6. Hero Card Subtle Exit Lift
        if (heroCard && smoothScrollY < window.innerHeight * 1.3) {
            const heroOpacity = Math.max(0, 1 - (smoothScrollY / (window.innerHeight * 0.95)));
            heroCard.style.transform = `translate3d(0, ${(smoothScrollY * 0.08).toFixed(2)}px, 0)`;
            heroCard.style.opacity = heroOpacity;
        }

        // 5. Waypoint Elevation & Fill Bar
        if (trackFill) {
            trackFill.style.height = `${scrollProgress * 100}%`;
        }

        if (elevationValue) {
            const currentAltitude = Math.round(1450 - (scrollProgress * (1450 - 45)));
            elevationValue.textContent = `${currentAltitude.toLocaleString()}m`;
        }

        // Update active waypoint dot
        const midScreen = smoothScrollY + (window.innerHeight * 0.45);
        let activeIdx = 0;
        waypoints.forEach((wp, index) => {
            if (wp.element) {
                const rect = wp.element.getBoundingClientRect();
                const elementTop = rect.top + smoothScrollY;
                if (midScreen >= elementTop - 120) {
                    activeIdx = index;
                }
            }
        });

        trailDots.forEach((dot, idx) => {
            if (idx === activeIdx) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });

        // 6. Smoothly fade out trail rail when approaching the footer
        const rail = document.querySelector('.pw-trail-progress-rail');
        const footer = document.querySelector('.pw-glass-footer');
        if (rail && footer) {
            const footerRect = footer.getBoundingClientRect();
            if (footerRect.top < window.innerHeight * 0.7) {
                rail.style.opacity = '0';
                rail.style.pointerEvents = 'none';
            } else {
                rail.style.opacity = '1';
                rail.style.pointerEvents = 'auto';
            }
        }

        // Continue loop if still interpolating
        if (Math.abs(latestScrollY - smoothScrollY) > 0.3) {
            window.requestAnimationFrame(renderParallax);
        } else {
            isTicking = false;
        }
    }

    window.addEventListener('scroll', onScrollUpdate, { passive: true });

    /* ==========================================================================
       3. SCROLL-TRIGGERED SECTION REVEALS (Intersection Observer + Anime.js)
       ========================================================================== */
    function initScrollReveals() {
        const observerOptions = {
            root: null,
            rootMargin: '0px 0px -12% 0px',
            threshold: 0.15
        };

        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    const sectionId = target.getAttribute('id') || target.className;

                    // Trigger specific choreographed Anime.js timeline
                    animateSectionEntry(target, sectionId);
                    observer.unobserve(target);
                }
            });
        }, observerOptions);

        // Register Sections for Observation
        document.querySelectorAll('.pw-scroll-section').forEach(sec => {
            revealObserver.observe(sec);
        });
    }

    function animateSectionEntry(container, id) {
        // Section Head (Eyebrow + Title + Subtitle)
        const head = container.querySelector('.pw-section-head');
        if (head) {
            anime({
                targets: [
                    head.querySelector('.pw-section-eyebrow'),
                    head.querySelector('.pw-section-title'),
                    head.querySelector('.pw-section-sub')
                ],
                translateY: [35, 0],
                opacity: [0, 1],
                delay: anime.stagger(120),
                duration: 850,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });
        }

        // Feature Pillars (#about)
        if (id === 'about' || container.querySelector('.pw-feature-card')) {
            const cards = container.querySelectorAll('.pw-feature-card');
            anime({
                targets: cards,
                translateY: [55, 0],
                scale: [0.92, 1],
                opacity: [0, 1],
                delay: anime.stagger(110),
                duration: 850,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });

            anime({
                targets: container.querySelectorAll('.pw-feature-icon'),
                rotate: [-25, 0],
                scale: [0.75, 1],
                delay: anime.stagger(110, { start: 200 }),
                duration: 750,
                easing: 'easeOutBack'
            });
        }

        // Product Catalog (#products)
        if (id === 'products' || container.querySelector('#productList')) {
            // Category filter pills
            const pills = container.querySelectorAll('.pw-filter-pill');
            if (pills.length) {
                anime({
                    targets: pills,
                    translateY: [20, 0],
                    opacity: [0, 1],
                    scale: [0.9, 1],
                    delay: anime.stagger(60),
                    duration: 650,
                    easing: 'easeOutCubic'
                });
            }

            // Product Cards
            const items = container.querySelectorAll('.pw-product-item');
            anime({
                targets: items,
                translateY: [60, 0],
                scale: [0.93, 1],
                opacity: [0, 1],
                delay: anime.stagger(90, { grid: [4, 2], from: 'center' }),
                duration: 900,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });
        }

        // Workshop Services (#services)
        if (id === 'services' || container.querySelector('.pw-service-card')) {
            const serviceCards = container.querySelectorAll('.pw-service-card');
            anime({
                targets: serviceCards,
                translateY: [50, 0],
                opacity: [0, 1],
                scale: [0.94, 1],
                delay: anime.stagger(100),
                duration: 850,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });

            anime({
                targets: container.querySelectorAll('.pw-service-icon-box'),
                rotate: [-35, 0],
                scale: [0.8, 1],
                delay: anime.stagger(100, { start: 150 }),
                duration: 700,
                easing: 'easeOutBack'
            });
        }

        // Basecamp Workshop Banner
        if (id === 'workshop-basecamp' || container.querySelector('.pw-workshop-banner')) {
            const banner = container.querySelector('.pw-workshop-banner');
            anime({
                targets: banner,
                scale: [0.95, 1],
                opacity: [0, 1],
                translateY: [40, 0],
                duration: 950,
                easing: 'cubicBezier(0.16, 1, 0.3, 1)'
            });

            anime({
                targets: banner.querySelectorAll('.pw-store-badge, .pw-btn-trail'),
                translateY: [20, 0],
                opacity: [0, 1],
                delay: anime.stagger(120, { start: 300 }),
                duration: 700,
                easing: 'easeOutCubic'
            });
        }
    }

    /* ==========================================================================
       4. INTERACTIVE 3D MOUSE PARALLAX TILT ON CARDS
       ========================================================================== */
    function initCardMouseTilt() {
        const tiltCards = document.querySelectorAll('.pw-tilt-card, .pw-hero-visual-card');

        tiltCards.forEach(card => {
            let tiltRunning = false;

            card.addEventListener('mousemove', (e) => {
                if (tiltRunning) return;
                tiltRunning = true;

                requestAnimationFrame(() => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;

                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;

                    const rotateX = -((y - centerY) / centerY) * 5;
                    const rotateY = ((x - centerX) / centerX) * 5;

                    anime({
                        targets: card,
                        rotateX: rotateX,
                        rotateY: rotateY,
                        duration: 250,
                        easing: 'easeOutQuad'
                    });

                    tiltRunning = false;
                });
            });

            card.addEventListener('mouseleave', () => {
                anime({
                    targets: card,
                    rotateX: 0,
                    rotateY: 0,
                    duration: 600,
                    easing: 'easeOutElastic(1, 0.6)'
                });
            });
        });
    }

    /* ==========================================================================
       5. SMOOTH CATEGORY FILTERING TRANSITIONS
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

                // Animate Out hidden items
                if (itemsToHide.length > 0) {
                    anime({
                        targets: itemsToHide,
                        scale: [1, 0.85],
                        opacity: [1, 0],
                        duration: 300,
                        easing: 'easeInQuad',
                        complete: function () {
                            itemsToHide.forEach(el => el.style.display = 'none');
                        }
                    });
                }

                // Animate In shown items
                itemsToShow.forEach(el => el.style.display = 'block');
                anime({
                    targets: itemsToShow,
                    scale: [0.85, 1],
                    opacity: [0, 1],
                    translateY: [25, 0],
                    delay: anime.stagger(60),
                    duration: 500,
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
                    const targetPosition = targetEl.getBoundingClientRect().top + window.pageYOffset - 70;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Smooth scroll for hero cue
        const scrollCue = document.querySelector('.pw-scroll-cue');
        if (scrollCue) {
            scrollCue.addEventListener('click', function (e) {
                e.preventDefault();
                const aboutSec = document.querySelector('#about');
                if (aboutSec) {
                    const pos = aboutSec.getBoundingClientRect().top + window.pageYOffset - 70;
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
        initHeroIntro();
        initScrollReveals();
        initCardMouseTilt();
        initFilterTransitions();
        initWaypointClicks();

        // Initial parallax calculation
        renderParallax();
    });

})();
