(function () {
  // Early theme apply (vor Paint): liest localStorage 'ts-theme' oder prefers-color-scheme
  const applyInitialTheme = () => {
    try {
      const saved = localStorage.getItem('ts-theme');
      const dark = saved ? (saved === 'dark') : window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.classList.toggle('dark', dark);
    } catch (e) { /* ignore */ }
  };
  applyInitialTheme();

  // Nach dem ersten Render sanfte Transitionen erlauben
  window.requestAnimationFrame(() => {
    document.documentElement.classList.add('theme-anim');
  });

  const loadPartials = async () => {
    const nodes = Array.from(document.querySelectorAll('[data-include]'));
    await Promise.all(nodes.map(async (el) => {
      const url = el.getAttribute('data-include');
      try {
        const r = await fetch(url, { cache: 'no-cache' });
        if (r.ok) el.innerHTML = await r.text();
      } catch (e) { /* ignore */ }
    }));
  };

  const wireThemeToggle = () => {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;

    const setLogoForTheme = () => {
      const dark = document.documentElement.classList.contains('dark');
      document.querySelectorAll('[data-logo-light]').forEach(img => {
        const light = img.getAttribute('data-logo-light');
        const darkSrc = img.getAttribute('data-logo-dark') || light;
        img.src = dark ? darkSrc : light;
      });
    };

    const apply = (dark) => {
      document.documentElement.classList.toggle('dark', dark);
      try { localStorage.setItem('ts-theme', dark ? 'dark' : 'light'); } catch(e){}
      setLogoForTheme();
    };

    // initial logo state
    setLogoForTheme();

    btn.addEventListener('click', () => {
      const dark = !document.documentElement.classList.contains('dark');
      apply(dark);
    });
  };

  // in include.js
  const markActiveTab = () => {
    const page = document.body?.dataset?.page;
    const map = { index: '/', order: '/', staff: '/staff.html' }; // <-- order ergänzt
    const href = map[page];
    if (!href) return;
    const link = document.querySelector(`.tabs a[href="${href}"], .tabs a[data-active="${page}"]`);
    if (link) link.classList.add('active');
  };

  const setYear = () => {
    const y = document.getElementById('year');
    if (y) y.textContent = new Date().getFullYear();
  };

  document.addEventListener('DOMContentLoaded', async () => {
    await loadPartials();
    wireThemeToggle();
    markActiveTab();
    setYear();
  });
})();
