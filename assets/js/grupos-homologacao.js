(function() {
  'use strict';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const carousels = Array.from(document.querySelectorAll('[data-bvgn-carousel]'));
  if (!carousels.length) return;

  function createCarousel(root) {
    const slides = Array.from(root.querySelectorAll('[data-bvgn-slide]'));
    const dots = Array.from(root.querySelectorAll('[data-bvgn-dot]'));
    if (slides.length <= 1) return null;

    let activeIndex = 0;
    let timerId = null;
    let started = false;
    let paused = false;
    let touchStartX = 0;
    let touchDeltaX = 0;
    const baseInterval = Math.max(3500, Number(root.getAttribute('data-interval')) || 4800);
    const interval = window.matchMedia('(max-width: 767px)').matches ? baseInterval + 1200 : baseInterval;
    const stagger = (Number(root.getAttribute('data-card-index')) || 0) * 220;

    function render(index) {
      activeIndex = (index + slides.length) % slides.length;
      slides.forEach((slide, slideIndex) => {
        slide.classList.toggle('is-active', slideIndex === activeIndex);
      });
      dots.forEach((dot, dotIndex) => {
        dot.classList.toggle('is-active', dotIndex === activeIndex);
      });
    }

    function clearTimer() {
      if (timerId) {
        window.clearTimeout(timerId);
        timerId = null;
      }
    }

    function queueNext() {
      clearTimer();
      if (paused || prefersReducedMotion) return;
      timerId = window.setTimeout(() => {
        render(activeIndex + 1);
        queueNext();
      }, interval);
    }

    function start() {
      if (started) return;
      started = true;
      window.setTimeout(() => {
        queueNext();
      }, Math.min(stagger, 1800));
    }

    function pause() {
      paused = true;
      clearTimer();
    }

    function resume() {
      paused = false;
      if (!started) return;
      queueNext();
    }

    dots.forEach((dot, dotIndex) => {
      dot.addEventListener('click', () => {
        render(dotIndex);
        queueNext();
      });
    });

    root.addEventListener('mouseenter', pause);
    root.addEventListener('mouseleave', resume);

    root.addEventListener('touchstart', (event) => {
      if (!event.touches || !event.touches.length) return;
      touchStartX = event.touches[0].clientX;
      touchDeltaX = 0;
      pause();
    }, { passive: true });

    root.addEventListener('touchmove', (event) => {
      if (!event.touches || !event.touches.length) return;
      touchDeltaX = event.touches[0].clientX - touchStartX;
    }, { passive: true });

    root.addEventListener('touchend', () => {
      if (Math.abs(touchDeltaX) > 40) {
        render(activeIndex + (touchDeltaX < 0 ? 1 : -1));
      }
      touchDeltaX = 0;
      resume();
    }, { passive: true });

    render(activeIndex);
    return { start, pause };
  }

  const instances = carousels
    .map((root) => ({ root, instance: createCarousel(root) }))
    .filter((entry) => entry.instance);

  if (!instances.length || typeof IntersectionObserver === 'undefined') {
    instances.forEach((entry) => entry.instance.start());
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const instance = entry.target.__bvgnCarousel;
      if (!instance) return;
      if (entry.isIntersecting) {
        instance.start();
      } else {
        instance.pause();
      }
    });
  }, {
    threshold: 0.35,
    rootMargin: '120px 0px',
  });

  instances.forEach((entry) => {
    entry.root.__bvgnCarousel = entry.instance;
    observer.observe(entry.root);
  });
})();
