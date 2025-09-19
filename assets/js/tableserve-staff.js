// Staff-Seite: Login, Bestell-Board (kein Admin/CRUD mehr)
document.addEventListener('DOMContentLoaded', () => {
  // aktiver Tab markieren
  const active = document.querySelector('.tabs a[href="/staff.html"]') || document.querySelector('.tabs a[data-active="staff"]');
  if (active) active.classList.add('active');

  const y = document.getElementById('year'); if (y) y.textContent = new Date().getFullYear();

  let timer = null;

  // -------- Login --------
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault(); // kein ?key=... in URL
      const key = (document.getElementById('key')?.value || '').trim();

      const msg = document.getElementById('loginMsg');
      if (msg) msg.textContent = 'Anmeldung...';

      try {
        const r = await fetch('/api/staff_orders.php?login=1', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin', // Session-Cookie mitsenden
          body: JSON.stringify({ key })
        });

        if (!r.ok) {
          if (msg) msg.textContent = 'Falscher Key.';
          return;
        }

        if (msg) msg.textContent = 'Angemeldet.';
        start(); // Polling starten
      } catch {
        if (msg) msg.textContent = 'Netzwerkfehler.';
      }
    });
  }

  // -------- Bestell-Board --------
  async function fetchOrders(){
    const r = await fetch('/api/staff_orders.php', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    if (!r.ok) return [];
    const data = await r.json();
    return Array.isArray(data.orders) ? data.orders : [];
  }

  function renderOrders(orders){
    const box = document.getElementById('orders'); if (!box) return;
    box.innerHTML = '';
    if (orders.length === 0) {
      box.innerHTML = "<p class='muted'>Keine offenen Bestellungen.</p>";
      return;
    }

    for (const o of orders){
      const ul = (o.items || []).map(it =>
        `<li>${it.qty}× ${it.name}${it.notes ? ` <span class="muted">(${it.notes})</span>` : ''}</li>`
      ).join('');

      const div = document.createElement('div'); div.className = 'card';
      div.innerHTML = `
        <div class="row"><strong>${o.table_name || o.table_code || 'Tisch'}</strong><span class="pill">${o.status}</span></div>
        <div class="small muted">${o.created_at || ''}</div>
        <ul>${ul}</ul>
        <div class="row">
          <button data-status='in_prep'  data-id='${o.id}'>In Zubereitung</button>
          <button data-status='served'   data-id='${o.id}'>Serviert</button>
          <button data-status='cancelled' data-id='${o.id}'>Storno</button>
        </div>`;
      box.appendChild(div);
    }

    box.querySelectorAll('button[data-status]').forEach(btn => {
      btn.addEventListener('click', async () => {
        await fetch('/api/set_status.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id: btn.dataset.id, status: btn.dataset.status })
        });
        tick();
      });
    });
  }

  async function tick(){ renderOrders(await fetchOrders()); }
  function start(){ if (timer) clearInterval(timer); tick(); timer = setInterval(tick, 4000); }
});
// -------- PDF Auswertung (Heute) --------
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnReportToday');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    try {
      // Heutiges Datum (lokal) im Format YYYY-MM-DD
      const now = new Date();
      const yyyy = now.getFullYear();
      const mm = String(now.getMonth() + 1).padStart(2, '0');
      const dd = String(now.getDate()).padStart(2, '0');
      const from = `${yyyy}-${mm}-${dd}`;
      const to   = `${yyyy}-${mm}-${dd}`;

      // Daten vom Server holen
      const r = await fetch(`/api/report_data.php?from=${from}&to=${to}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      if (!r.ok) {
        alert(`Fehler beim Erzeugen der Auswertung (HTTP ${r.status}).`);
        return;
      }
      const data = await r.json();
      if (!data.ok) {
        alert('Fehler beim Erzeugen der Auswertung.');
        return;
      }

      // jsPDF nutzen
      const { jsPDF } = window.jspdf || {};
      if (!jsPDF) { alert('jsPDF konnte nicht geladen werden.'); return; }

      const doc = new jsPDF({ unit: 'pt', format: 'a4' }); // 595 x 842pt
      const margin = 48;
      let x = margin;
      let y = margin;

      const line = (txt, size = 12, dy = 18) => {
        doc.setFont('Helvetica', 'normal');
        doc.setFontSize(size);
        doc.text(txt, x, y);
        y += dy;
        if (y > 842 - margin) { doc.addPage(); y = margin; }
      };

      // Titel + Zeitraum
      doc.setFont('Helvetica', 'bold');
      doc.setFontSize(18);
      doc.text('TableServe – Auswertung', x, y);
      y += 10;
      doc.setFont('Helvetica', 'normal');
      doc.setFontSize(12);
      const period = data.period ? data.period : `${from} bis ${to}`;
      doc.text(`Zeitraum: ${period}`, x, y);
      y += 24;

      // Tabelle: Kopf
      doc.setFont('Helvetica', 'bold'); doc.setFontSize(12);
      doc.text('Getränk', x, y);
      doc.text('Kategorie', x + 320, y);
      doc.text('Menge', x + 500, y);
      y += 8;
      doc.setLineWidth(0.5);
      doc.line(x, y, 595 - margin, y);
      y += 14;

      // Zeilen
      doc.setFont('Helvetica', 'normal');
      const items = Array.isArray(data.items) ? data.items : [];

      // Sortierung: Kategorie, dann Name
      items.sort((a, b) => {
        const ca = (a.category || '').localeCompare(b.category || '');
        if (ca !== 0) return ca;
        return (a.name || '').localeCompare(b.name || '');
      });

      for (const it of items) {
        const name = it.name || `#${it.item_id}`;
        const cat  = it.category || '';
        const qty  = String(it.total_qty ?? 0);

        // Zeile zeichnen
        doc.text(name, x, y);
        doc.text(cat,  x + 320, y);
        doc.text(qty,  x + 500, y);
        y += 18;
        if (y > 842 - margin) { doc.addPage(); y = margin; }
      }

      // Summe
      const sum = items.reduce((s, it) => s + (it.total_qty || 0), 0);
      y += 6; doc.line(x, y, 595 - margin, y); y += 18;
      doc.setFont('Helvetica', 'bold');
      doc.text(`Gesamtmenge: ${sum}`, x, y);

      // Dateiname
      const fname = `tableserve-auswertung-${from}${from !== to ? '_'+to : ''}.pdf`;
      doc.save(fname);
    } catch (e) {
      console.error(e);
      alert('Unerwarteter Fehler bei der PDF-Erzeugung.');
    }
  });
});
