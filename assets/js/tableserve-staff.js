// /assets/js/tableserve-staff.js
// Staff-Seite: Login, Bestell-Board, Status setzen, PDF-Auswertung (nur eingeloggt)

(() => {
  // ---------- Helpers ----------
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const fmt = {
    euro(cents) {
      const n = (Number(cents) || 0) / 100;
      return n.toFixed(2).replace('.', ',') + ' €';
    }
  };

  function setVisible(el, visible) {
    if (!el) return;
    el.style.display = visible ? '' : 'none';
  }

  function setReportButtonVisible(visible) {
    const btn = $('#btnReportToday');
    if (!btn) return;
    setVisible(btn, visible);
  }

  async function checkLoginStatus() {
    try {
      // GET liefert bei Erfolg eine Orderliste (ok:true)
      const r = await fetch('/api/staff_orders.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (!r.ok) return false;
      const j = await r.json();
      return j && j.ok === true;
    } catch {
      return false;
    }
  }

  async function apiLoginStaff(key) {
    const r = await fetch('/api/staff_orders.php?login=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ key })
    });
    if (!r.ok) throw new Error('login failed ' + r.status);
    const j = await r.json();
    if (!j.ok) throw new Error('invalid');
    return true;
  }

  async function loadOrders() {
    const cont = $('#ordersList') || $('#orders'); // fallback id
    const msg = $('#ordersMsg');
    if (msg) msg.textContent = 'Lade…';
    try {
      const r = await fetch('/api/staff_orders.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const data = await r.json();
      if (!data.ok) throw new Error('not ok');

      const orders = Array.isArray(data.orders) ? data.orders : [];
      // Sortierung: älteste zuerst
      orders.sort((a, b) => (a.created_at || '').localeCompare(b.created_at || ''));

      // Render
      if (cont) cont.innerHTML = orders.length ? renderOrders(orders) : '<p class="muted">Aktuell keine offenen Bestellungen.</p>';
      if (msg) msg.textContent = '';

      // Buttons binden
      $$('.js-set-status').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const id = e.currentTarget.dataset.id;
          const st = e.currentTarget.dataset.status;
          e.currentTarget.disabled = true;
          try {
            await setStatus(id, st);
            await loadOrders();
          } catch (err) {
            console.error(err);
            alert('Konnte Status nicht setzen.');
          } finally {
            e.currentTarget.disabled = false;
          }
        });
      });
    } catch (err) {
      console.error(err);
      if (cont) cont.innerHTML = '';
      if (msg) msg.textContent = 'Fehler beim Laden.';
    }
  }

  function renderOrders(orders) {
    // einfache Karten
    return orders.map(o => {
      const items = Array.isArray(o.items) ? o.items : [];
      const lines = items.map(it => {
        const note = it.notes ? ` <span class="muted">(${escapeHtml(it.notes)})</span>` : '';
        return `<li>${Number(it.qty) || 0}× ${escapeHtml(it.name || '')}${note}</li>`;
      }).join('');
      const badge = statusBadge(o.status);
      return `
        <div class="card" data-order="${escapeAttr(o.id)}">
          <div class="row" style="justify-content:space-between;align-items:center">
            <div>
              <strong>${escapeHtml(o.table_name || '')}</strong>
              <span class="muted"> • ${escapeHtml(o.created_at || '')}</span>
              <div class="muted" style="margin-top:2px">ID: ${escapeHtml(o.id || '')}</div>
            </div>
            <div>${badge}</div>
          </div>
          <ul style="margin:10px 0 12px 18px">${lines}</ul>
          <div class="row" style="gap:6px; flex-wrap: wrap">
            <button class="js-set-status" data-id="${escapeAttr(o.id || '')}" data-status="in_prep">In Vorbereitung</button>
            <button class="js-set-status" data-id="${escapeAttr(o.id || '')}" data-status="served">Serviert</button>
            <button class="js-set-status" data-id="${escapeAttr(o.id || '')}" data-status="cancelled">Storniert</button>
          </div>
        </div>
      `;
    }).join('');
  }

  function statusBadge(st) {
    const label = (st || '').toLowerCase();
    const colors = {
      open: '#e0f2fe',
      in_prep: '#fef9c3',
      served: '#dcfce7',
      cancelled: '#fee2e2'
    };
    const txt = {
      open: 'Offen',
      in_prep: 'In Vorbereitung',
      served: 'Serviert',
      cancelled: 'Storniert'
    }[label] || label;
    const bg = colors[label] || '#eee';
    return `<span class="badge" style="background:${bg}; padding:3px 8px; border-radius:8px">${escapeHtml(txt)}</span>`;
  }

  async function setStatus(id, status) {
    const r = await fetch('/api/set_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ id, status })
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    if (!j.ok) throw new Error('not ok');
    return true;
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
  function escapeAttr(s) { return escapeHtml(s).replaceAll('"', '&quot;'); }

  // ---------- PDF Auswertung (Heute) ----------
  async function handleReportToday() {
    // Nur wenn jsPDF geladen ist
    const { jsPDF } = window.jspdf || {};
    if (!jsPDF) { alert('jsPDF konnte nicht geladen werden.'); return; }

    // Zeitraum: heute
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const from = `${yyyy}-${mm}-${dd}`;
    const to = from;

    try {
      const r = await fetch(`/api/report_data.php?from=${from}&to=${to}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      if (!r.ok) { alert(`Fehler beim Erzeugen der Auswertung (HTTP ${r.status}).`); return; }
      const data = await r.json();
      if (!data.ok) { alert('Fehler beim Erzeugen der Auswertung.'); return; }

      const doc = new jsPDF({ unit: 'pt', format: 'a4' }); // 595x842pt
      const margin = 48;
      let x = margin, y = margin;

      const line = (txt, size = 12, dy = 18, bold = false) => {
        doc.setFont('Helvetica', bold ? 'bold' : 'normal');
        doc.setFontSize(size);
        doc.text(txt, x, y);
        y += dy;
        if (y > 842 - margin) { doc.addPage(); y = margin; }
      };

      // Titel
      line('TableServe – Auswertung', 18, 24, true);
      line(`Zeitraum: ${data.period || (from + ' bis ' + to)}`, 12, 24);

      // Kopfzeile
      doc.setFont('Helvetica', 'bold'); doc.setFontSize(12);
      doc.text('Getränk', x, y);
      doc.text('Kategorie', x + 320, y);
      doc.text('Menge', x + 500, y);
      y += 8; doc.setLineWidth(0.5); doc.line(x, y, 595 - margin, y); y += 14;

      // Zeilen
      doc.setFont('Helvetica', 'normal'); doc.setFontSize(12);
      const items = Array.isArray(data.items) ? data.items : [];
      items.sort((a, b) => {
        const ca = (a.category || '').localeCompare(b.category || '');
        if (ca !== 0) return ca;
        return (a.name || '').localeCompare(b.name || '');
      });

      for (const it of items) {
        const name = it.name || `#${it.item_id}`;
        const cat  = it.category || '';
        const qty  = String(it.total_qty ?? 0);
        doc.text(name, x, y);
        doc.text(cat,  x + 320, y);
        doc.text(qty,  x + 500, y);
        y += 18;
        if (y > 842 - margin) { doc.addPage(); y = margin; }
      }

      // Summe
      const sum = items.reduce((s, it) => s + (Number(it.total_qty) || 0), 0);
      y += 6; doc.line(x, y, 595 - margin, y); y += 18;
      doc.setFont('Helvetica', 'bold');
      doc.text(`Gesamtmenge: ${sum}`, x, y);

      const fname = `tableserve-auswertung-${from}.pdf`;
      doc.save(fname);
    } catch (err) {
      console.error(err);
      alert('Unerwarteter Fehler bei der PDF-Erzeugung.');
    }
  }

  // ---------- Init ----------
  document.addEventListener('DOMContentLoaded', async () => {
    const loginForm = $('#staffLoginForm');       // <form id="staffLoginForm">
    const keyInput  = $('#staffKey');             //   <input id="staffKey" ...>
    const loginBox  = $('#staffLoginBox') || $('#loginSection');
    const boardBox  = $('#staffBoard')   || $('#boardSection');
    const btnReport = $('#btnReportToday');

    // Standard: Button verstecken
    setReportButtonVisible(false);

    // Bereits eingeloggt?
    const logged = await checkLoginStatus();
    if (logged) {
      setVisible(loginBox, false);
      setVisible(boardBox, true);
      setReportButtonVisible(true);
      await loadOrders();
    } else {
      setVisible(loginBox, true);
      setVisible(boardBox, false);
    }

    // Login-Form: Enter + Submit
    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const key = (keyInput?.value || '').trim();
        if (!key) { keyInput?.focus(); return; }
        // disable während Login
        const btn = loginForm.querySelector('button[type="submit"]') || loginForm.querySelector('button');
        if (btn) btn.disabled = true;
        try {
          await apiLoginStaff(key);
          // UI sofort updaten
          setVisible(loginBox, false);
          setVisible(boardBox, true);
          setReportButtonVisible(true);
          await loadOrders();
        } catch (err) {
          console.error(err);
          alert('Anmeldung fehlgeschlagen. Bitte Schlüssel prüfen.');
          setReportButtonVisible(false);
        } finally {
          if (btn) btn.disabled = false;
        }
      });
    }

    // Report-Button
    if (btnReport) {
      btnReport.addEventListener('click', async () => {
        // Sicherheit: nur bei aktiver Session
        const ok = await checkLoginStatus();
        if (!ok) {
          alert('Bitte zuerst einloggen!');
          setReportButtonVisible(false);
          setVisible(loginBox, true);
          setVisible(boardBox, false);
          return;
        }
        await handleReportToday();
      });
    }

    // Optional: Manuell reloaden (falls du einen Button mit id="btnReload" hast)
    $('#btnReload')?.addEventListener('click', loadOrders);

    // Optional: kleines Auto-Refresh Intervall (z. B. alle 15s)
    // Deaktiviere, wenn unerwünscht
    // setInterval(async () => {
    //   const ok = await checkLoginStatus();
    //   if (ok) await loadOrders();
    // }, 15000);
  });
})();
