(function() {
  'use strict';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isMobile = window.matchMedia('(max-width: 767px)').matches;

  function parseISODateLocal(str) {
    if (!str) return null;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
    if (!match) return null;
    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 0, 0, 0, 0);
  }

  function toISO(dateStr) {
    const parts = String(dateStr || '').split('-');
    if (parts.length !== 3) return '';
    if (parts[0].length === 4) return dateStr;
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }

  function getSelectedBooking() {
    const raw = localStorage.getItem('bvgn_agendamento');
    if (!raw) return { days: 0, start: '', end: '' };

    try {
      const data = JSON.parse(raw);
      const start = toISO(data.inicio || '');
      const end = toISO(data.fim || '');
      const startDate = parseISODateLocal(start);
      const endDate = parseISODateLocal(end);
      if (!startDate || !endDate) return { days: 0, start, end };

      const startMid = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate(), 12, 0, 0, 0);
      const endMid = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate(), 12, 0, 0, 0);
      const diff = Math.round((endMid - startMid) / 86400000);
      return {
        days: diff >= 1 ? diff : 0,
        start,
        end,
      };
    } catch (_) {
      return { days: 0, start: '', end: '' };
    }
  }

  function formatPriceBR(value) {
    const amount = Number(value) || 0;
    return amount.toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 2,
    });
  }

  function pickRuleForDays(rules, days) {
    if (!Array.isArray(rules) || !rules.length || days <= 0) return null;
    if (days > 30) return null;

    let exact = null;
    let fallback = null;
    rules.forEach((rule) => {
      const min = Number(rule.min_days || 1);
      const max = Number(rule.max_days || min);
      if (days >= min && days <= max && !exact) {
        exact = rule;
      }
      if (!fallback || max > Number(fallback.max_days || 0)) {
        fallback = rule;
      }
    });

    return exact || fallback;
  }

  function applyDynamicTariff(basePrice, booking, groupLetter) {
    const base = Number(basePrice) || 0;
    if (base <= 0) return base;
    if (!booking || booking.days <= 0 || !booking.start || !booking.end) return base;

    if (
      typeof window.BVGN_Dynamic === 'undefined' ||
      !window.BVGN_Dynamic ||
      typeof window.BVGN_Dynamic.calcularTarifaDinamica !== 'function'
    ) {
      return base;
    }

    const dynamic = window.BVGN_Dynamic.calcularTarifaDinamica(
      base,
      booking.start,
      booking.end,
      booking.days,
      groupLetter || ''
    );

    const extra = dynamic && Number(dynamic.extra || 0) > 0 ? Number(dynamic.extra || 0) : 0;
    if (extra <= 0 || booking.days <= 0) return base;

    return base + (extra / booking.days);
  }

  function updateCardPrices() {
    const booking = getSelectedBooking();
    const cards = Array.from(document.querySelectorAll('[data-bvgn-carousel]'));

    cards.forEach((card) => {
      const priceBox = card.parentElement ? card.parentElement.querySelector('[data-bvgn-price]') : null;
      const priceValue = priceBox ? priceBox.querySelector('[data-bvgn-price-value]') : null;
      if (!priceBox || !priceValue) return;

      const planType = card.getAttribute('data-plan-type') || 'diario';
      const staticPrice = Number(card.getAttribute('data-static-price') || 0);

      if (planType === 'mensal') {
        if (staticPrice > 0) {
          priceValue.textContent = formatPriceBR(staticPrice);
          priceBox.hidden = false;
        } else {
          priceValue.textContent = '';
          priceBox.hidden = true;
        }
        return;
      }

      const rulesRaw = card.getAttribute('data-price-rules') || '[]';
      let rules = [];
      try {
        rules = JSON.parse(rulesRaw);
      } catch (_) {
        rules = [];
      }

      const selectedRule = pickRuleForDays(rules, booking.days);
      if (!selectedRule) {
        priceBox.hidden = true;
        priceValue.textContent = '';
        return;
      }

      const groupLetter = card.getAttribute('data-group-letter') || '';
      const finalUnitPrice = applyDynamicTariff(selectedRule.price || 0, booking, groupLetter);
      priceValue.textContent = formatPriceBR(finalUnitPrice);
      priceBox.hidden = false;
    });
  }

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
    const interval = isMobile ? baseInterval + 1200 : baseInterval;
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

  function initCarousels() {
    const carousels = Array.from(document.querySelectorAll('[data-bvgn-carousel]'));
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
  }

  updateCardPrices();
  initCarousels();

  window.addEventListener('storage', (event) => {
    if (event.key === 'bvgn_agendamento') {
      updateCardPrices();
    }
  });
})();
