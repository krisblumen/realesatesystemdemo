/**
 * Hero carousel controls (Épica 12.1 §9.3, §10).
 *
 * The cross-fade itself is pure CSS. This module only adds what CSS cannot do:
 * an operable pause, and honouring `prefers-reduced-motion`.
 *
 * WCAG 2.2.2 — anything that moves automatically for more than five seconds
 * needs a way to stop it. The slides are decorative backdrops, but the movement
 * is still movement, so mode A always ships a real <button>: keyboard operable,
 * with an accessible pressed state. Hover and focus pause too, so reading the
 * hero text does not fight the rotation.
 *
 * Compiled by Vite and imported from app.js: no inline script, no inline style,
 * nothing interpolated from content — the page stays CSP-safe without
 * `unsafe-inline`.
 */

const PAUSED = 'is-paused';

function setUp(hero) {
    const slides = hero.querySelector('[data-nh-hero-slides]');
    const toggle = hero.querySelector('[data-nh-hero-toggle]');

    if (!slides || !toggle) {
        return;
    }

    // Reduced motion: the CSS already froze the first slide, so there is no
    // movement left to control. Offering a "pause" that pauses nothing would be
    // noise, not an affordance.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        toggle.hidden = true;

        return;
    }

    const labels = {
        pause: toggle.dataset.labelPause || 'Pausar',
        resume: toggle.dataset.labelResume || 'Reanudar',
    };

    // The explicit choice of the user; hover/focus pause on top of it without
    // overwriting it, so moving the mouse away does not resume what the user
    // deliberately stopped.
    let pausedByUser = false;

    const apply = (paused) => {
        slides.classList.toggle(PAUSED, paused);
    };

    const render = () => {
        toggle.textContent = pausedByUser ? labels.resume : labels.pause;
        toggle.setAttribute('aria-pressed', pausedByUser ? 'true' : 'false');
        apply(pausedByUser);
    };

    toggle.addEventListener('click', () => {
        pausedByUser = !pausedByUser;
        render();
    });

    const transient = (paused) => {
        if (!pausedByUser) {
            apply(paused);
        }
    };

    hero.addEventListener('mouseenter', () => transient(true));
    hero.addEventListener('mouseleave', () => transient(false));
    hero.addEventListener('focusin', () => transient(true));
    hero.addEventListener('focusout', () => transient(false));

    render();
}

export function initHeroCarousels(root = document) {
    root.querySelectorAll('[data-nh-hero]').forEach(setUp);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initHeroCarousels());
} else {
    initHeroCarousels();
}
