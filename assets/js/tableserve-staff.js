// /assets/js/tableserve-staff.js
// Staff-Seite: Login, Bestell-Board, Status setzen, PDF-Auswertung

(() => {
  // ---------- Helpers ----------
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
  function escapeAttr(s) { return escapeHtml(s).replaceAll('"', '&quot;'); }

  function setReportButtonVisible(visible) {
    const btn = $('#btnReportToday');
    if (btn) btn.style.display = visible ? 'inline-block' : 'none';
  }

  async function checkLoginStatus() {
    try {
      const r = await fetch('/api/staff_orders.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (!r.ok) return false;
      const j = await r.json();
      return j && j.ok === true;
    } catch { return false; }
  }

  async function apiLoginStaff(key) {
    const r = await fetch('/api/staff_orders.php?login=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ key })
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    if (!j.ok) throw new Error('invalid');
    return true;
  }

  async function loadOrders() {
    const cont = $('#orders');
    if (!cont) return;
    cont.innerHTML = '<p class="muted">Lade…</p>';
    try {
      const r = await fetch('/api/staff_orders.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const data = await r.json();
      if (!data.ok) throw new Error('not ok');

      const orders = Array.isArray(data.orders) ? data.orders : [];
      orders.sort((a, b) => (a.created_at || '').localeCompare(b.created_at || ''));
      cont.innerHTML = orders.length ? renderOrders(orders) : '<p class="muted">Aktuell keine Bestellungen.</p>';

      $$('.js-set-status').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const id = e.currentTarget.dataset.id;
          const st = e.currentTarget.dataset.status;
          try {
            await setStatus(id, st);
            await loadOrders();
          } catch (err) {
            console.error(err);
            alert('Konnte Status nicht setzen.');
          }
        });
      });
    } catch (err) {
      console.error(err);
      cont.innerHTML = '<p class="muted">Fehler beim Laden.</p>';
    }
  }

  function renderOrders(orders) {
    return orders.map(o => {
      const items = Array.isArray(o.items) ? o.items : [];
      const lines = items.map(it => {
        const note = it.notes ? ` <span class="muted">(${escapeHtml(it.notes)})</span>` : '';
        return `<li>${Number(it.qty) || 0}× ${escapeHtml(it.name || '')}${note}</li>`;
      }).join('');
      return `
        <div class="card">
          <div class="row" style="justify-content:space-between">
            <div>
              <strong>${escapeHtml(o.table_name || '')}</strong>
              <span class="muted"> • ${escapeHtml(o.created_at || '')}</span>
            </div>
            <span class="badge">${escapeHtml(o.status || '')}</span>
          </div>
          <ul style="margin:10px 0 12px 18px">${lines}</ul>
          <div class="row" style="gap:6px; flex-wrap:wrap">
            <button class="js-set-status" data-id="${escapeAttr(o.id)}" data-status="in_prep">In Vorbereitung</button>
            <button class="js-set-status" data-id="${escapeAttr(o.id)}" data-status="served">Serviert</button>
            <button class="js-set-status" data-id="${escapeAttr(o.id)}" data-status="cancelled">Storniert</button>
          </div>
        </div>
      `;
    }).join('');
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

  // ---------- PDF Auswertung (Heute) ----------
  async function handleReportToday() {
    const { jsPDF } = window.jspdf || {};
    if (!jsPDF) { alert('jsPDF konnte nicht geladen werden.'); return; }

    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const from = `${yyyy}-${mm}-${dd}`;

    try {
      const r = await fetch(`/api/report_data.php?from=${from}&to=${from}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      if (!r.ok) { alert(`Fehler beim Erzeugen der Auswertung (HTTP ${r.status}).`); return; }
      const data = await r.json();
      if (!data.ok) { alert('Fehler beim Erzeugen der Auswertung.'); return; }

      const doc = new jsPDF({ unit: 'pt', format: 'a4' });
      const margin = 48;
      let y = margin;

      doc.setFont('Helvetica','bold').setFontSize(18);
      doc.text('TableServe – Auswertung', margin, y);
      y += 24;
      doc.setFont('Helvetica','normal').setFontSize(12);
      doc.text(`Zeitraum: ${data.period}`, margin, y);
      y += 24;

      doc.setFont('Helvetica','bold');
      doc.text('Getränk', margin, y);
      doc.text('Kategorie', margin+320, y);
      doc.text('Menge', margin+500, y);
      y += 14;

      doc.setFont('Helvetica','normal');
      const items = data.items || [];
      for (const it of items) {
        doc.text(it.name || `#${it.item_id}`, margin, y);
        doc.text(it.category || '', margin+320, y);
        doc.text(String(it.total_qty||0), margin+500, y);
        y += 18;
      }

      const sum = items.reduce((s,it)=>s+(+it.total_qty||0),0);
      y += 18;
      doc.setFont('Helvetica','bold');
      doc.text(`Gesamtmenge: ${sum}`, margin, y);

      doc.save(`tableserve-auswertung-${from}.pdf`);
    } catch (err) {
      console.error(err);
      alert('Fehler bei PDF-Erstellung.');
    }
  }

  // ---------- Init ----------
  document.addEventListener('DOMContentLoaded', async () => {
    const loginForm = $('#loginForm');
    const keyInput  = $('#key');
    const loginMsg  = $('#loginMsg');
    const btnReport = $('#btnReportToday');

    // Button erstmal verstecken
    setReportButtonVisible(false);

    // Prüfen ob eingeloggt
    if (await checkLoginStatus()) {
      setReportButtonVisible(true);
      loadOrders();
    }

    // Login-Form submit
    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const key = (keyInput?.value || '').trim();
        if (!key) return;
        try {
          await apiLoginStaff(key);
          if (loginMsg) loginMsg.textContent = 'Login erfolgreich.';
          setReportButtonVisible(true);
          loadOrders();
        } catch (err) {
          console.error(err);
          if (loginMsg) loginMsg.textContent = 'Falscher Schlüssel!';
          setReportButtonVisible(false);
        }
      });
    }

    // PDF-Button
    if (btnReport) {
      btnReport.addEventListener('click', async () => {
        if (!(await checkLoginStatus())) {
          alert('Bitte zuerst einloggen!');
          setReportButtonVisible(false);
          return;
        }
        await handleReportToday();
      });
    }

    // Auto-Refresh alle 15s
    setInterval(async () => {
      if (await checkLoginStatus()) loadOrders();
    }, 15000);
  });
})();
