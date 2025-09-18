// Startseite: Code prüfen, dann auf /order.html?code=... weiterleiten
document.addEventListener('DOMContentLoaded', () => {
  // aktiver Tab
  const link = document.querySelector('.tabs a[href="/"]') || document.querySelector('.tabs a[data-active="index"]');
  if (link) link.classList.add('active');

  const y = document.getElementById('year'); if (y) y.textContent = new Date().getFullYear();

  // Falls schon ?code=… in der URL steht -> direkt validieren und weiterleiten
  const params = new URLSearchParams(location.search);
  const preCode = (params.get('code') || '').trim();
  if (preCode) { submitCode(preCode); }

  const form = document.getElementById('startForm');
  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const code = (document.getElementById('code')?.value || '').trim();
    if (!code) return;
    submitCode(code);
  });

  async function submitCode(code){
    setErr('');
    try {
      const r = await fetch('/api/check_table.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ code })
      });
      if (!r.ok) throw 0;
      const data = await r.json();
      if (data && data.ok) {
        // success: auf Bestellseite weiter
        const url = new URL('/order.html', location.origin);
        url.searchParams.set('code', code);
        location.href = url.toString();
      } else {
        setErr('Ungültiger Tischcode.');
      }
    } catch(e) {
      setErr('Ungültiger Tischcode.');
    }
  }

  function setErr(msg){
    const el = document.getElementById('err');
    if (el) el.textContent = msg || '';
  }
});
