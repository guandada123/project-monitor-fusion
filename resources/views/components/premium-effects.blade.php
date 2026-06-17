<script>
/**
 * Project Monitor — Premium Interaction Effects
 * Magnetic hover, count-up animation, stagger entrance, skeleton loader.
 */
;(function() {
  'use strict';

  // ── 1. Magnetic Hover Effect ────────────────────────────────────────
  function initMagnetic() {
    document.querySelectorAll('[data-magnetic]').forEach(function(card) {
      card.addEventListener('mousemove', function(e) {
        var rect = card.getBoundingClientRect();
        var x = e.clientX - rect.left - rect.width / 2;
        var y = e.clientY - rect.top - rect.height / 2;
        var strength = parseFloat(card.dataset.magnetic) || 0.015;
        var rotateStrength = strength * 0.6;

        card.style.transform =
          'translate(' + (x * strength).toFixed(2) + 'px, ' + (y * strength).toFixed(2) + 'px) ' +
          'rotateX(' + (-y * rotateStrength).toFixed(2) + 'deg) ' +
          'rotateY(' + (x * rotateStrength).toFixed(2) + 'deg)';
        card.style.transition = 'transform 0.15s ease-out';
        card.style.boxShadow = 'var(--shadow-hover)';
      });

      card.addEventListener('mouseleave', function() {
        card.style.transform = 'translate(0, 0) rotateX(0) rotateY(0)';
        card.style.transition = 'transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.5s ease';
        card.style.boxShadow = 'var(--shadow)';
      });
    });
  }

  // ── 2. Count-Up Animation ──────────────────────────────────────────
  function initCountUp() {
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseFloat(el.dataset.count);
        var suffix = el.dataset.countSuffix || '';
        var duration = parseInt(el.dataset.countDuration) || 1200;
        var decimals = el.dataset.countDecimals ? parseInt(el.dataset.countDecimals) : (target % 1 !== 0 ? 1 : 0);
        var start = performance.now();

        function tick(now) {
          var elapsed = now - start;
          var progress = Math.min(elapsed / duration, 1);
          // Ease-out expo
          var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
          var current = target * eased;
          el.textContent = current.toFixed(decimals) + suffix;
          if (progress < 1) requestAnimationFrame(tick);
        }

        requestAnimationFrame(tick);
        observer.unobserve(el);
      });
    }, { threshold: 0.4 });

    document.querySelectorAll('[data-count]').forEach(function(el) {
      observer.observe(el);
    });
  }

  // ── 3. Stagger Entrance Animation ───────────────────────────────────
  function initStaggerEntrance() {
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (!entry.isIntersecting) return;
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -20px 0px' });

    document.querySelectorAll('[data-animate]').forEach(function(el, index) {
      var delay = el.dataset.animateDelay || (index * 60);
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'opacity 0.5s ease ' + delay + 'ms, transform 0.5s cubic-bezier(0.16, 1, 0.3, 1) ' + delay + 'ms';
      observer.observe(el);
    });
  }

  // ── 4. Skeleton Loader ──────────────────────────────────────────────
  function initSkeleton() {
    document.querySelectorAll('[data-skeleton]').forEach(function(skel) {
      var target = document.querySelector(skel.dataset.skeleton);
      if (target) {
        skel.style.display = 'none';
        target.style.opacity = '1';
      }
    });
  }

  // ── 5. Progress bar fill animation ──────────────────────────────────
  function initProgressBars() {
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (!entry.isIntersecting) return;
        var bar = entry.target;
        bar.style.width = bar.dataset.progress + '%';
        observer.unobserve(bar);
      });
    }, { threshold: 0.3 });

    document.querySelectorAll('[data-progress]').forEach(function(bar) {
      bar.style.width = '0%';
      bar.style.transition = 'width 1s cubic-bezier(0.16, 1, 0.3, 1) 0.2s';
      observer.observe(bar);
    });
  }

  // ── Initialize all effects ─────────────────────────────────────────
  function initAll() {
    initMagnetic();
    initCountUp();
    initStaggerEntrance();
    initSkeleton();
    initProgressBars();
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  // Re-init on Livewire navigation (Turbo-style SPA)
  document.addEventListener('livewire:navigated', initAll);
})();
</script>

{{-- Skeleton CSS --}}
<style>
  /* Skeleton shimmer animation */
  @keyframes pm-shimmer {
    0%   { background-position: -200% 0; }
    100% { background-position: 200% 0; }
  }

  .skeleton {
    background: linear-gradient(
      90deg,
      var(--stroke) 25%,
      rgba(128,128,128,0.08) 50%,
      var(--stroke) 75%
    );
    background-size: 200% 100%;
    animation: pm-shimmer 1.8s ease-in-out infinite;
    border-radius: 8px;
  }

  .skeleton-text { height: 14px; margin-bottom: 8px; width: 80%; }
  .skeleton-text.short { width: 50%; }
  .skeleton-heading { height: 24px; margin-bottom: 12px; width: 60%; }
  .skeleton-kpi { height: 36px; margin-bottom: 6px; width: 70%; }
  .skeleton-label { height: 10px; width: 40%; }
  .skeleton-chart { height: 200px; width: 100%; border-radius: 12px; }
</style>
