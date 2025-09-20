// Bestellseite: Code validieren -> Menü laden -> Warenkorb/Bestellen
document.addEventListener('DOMContentLoaded', () => {
  // aktiven Tab markieren (Bestellen zeigt auf Startseite '/')
  const active = document.querySelector('.tabs a[href="/"]') || document.querySelector('.tabs a[data-active="index"]');
  if (active) active.classList.add('active');

  const y = document.getElementById('year'); if (y) y.textContent = new Date().getFullYear();

  const main = document.querySelector('main.container');
  const params = new URLSearchParams(location.search);
  const tableCode = (params.get('code') || '').trim();

  const tableInfo = document.getElementById('tableInfo');
  if (tableInfo) tableInfo.textContent = tableCode ? ("Tisch-Code: " + tableCode) : "";

  const state = { items: [], cart: {} };
  const euro = (c) => (c/100).toFixed(2).replace('.', ',') + ' €';

  // ---------- Fehleranzeige ----------
  function showInvalid(reason) {
    if (!main) return;
    main.innerHTML = `
      <h1>Ungültiger Tischcode</h1>
      <p class="muted">${reason || "Der eingegebene Code ist nicht gültig."}</p>
      <p>Bitte scanne den <strong>QR-Code am Tisch</strong> oder gib den Code auf der Startseite ein.</p>
      <p><a href="/">Zur Startseite</a></p>
    `;
  }

  // ---------- Code prüfen (Server) ----------
  async function validateTableCode(code) {
    try {
      const r = await fetch('/api/check_table.php', {
        method:'POST',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ code })
      });
      if (!r.ok) return false;
      const data = await r.json();
      return !!(data && data.ok);
    } catch(e){
      return false;
    }
  }

  // ---------- Menü laden ----------
  async function loadMenu(){
    try {
      const r = await fetch('/api/menu.php', {
        headers:{ 'Accept':'application/json' },
        credentials: 'same-origin'
      });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const data = await r.json();
      if (!Array.isArray(data.items)) throw new Error('bad format');
      state.items = data.items;
      renderMenu();
    } catch (e) {
      console.error(e);
      alert('Menü konnte nicht geladen werden.');
    }
  }

  function renderMenu(){
    const byCat = {};
    for (const it of state.items) (byCat[it.category] ||= []).push(it);

    let html = "";
    for (const [cat, arr] of Object.entries(byCat)){
      html += `<h3>${cat}</h3><div class="grid">`;
      for (const it of arr){
        html += `
          <div class="card">
            <div class="row"><strong>${it.name}</strong><span class="badge">${euro(it.price_cents)}</span></div>
            <div class="row">
              <input type="number" min="1" value="1" id="qty-${it.id}" style="width:70px">
              <button data-add="${it.id}">Hinzufügen</button>
            </div>
            <textarea id="note-${it.id}" placeholder="Optional: Hinweis (z. B. ohne Eis)" rows="2" style="width:100%;margin-top:6px"></textarea>
          </div>`;
      }
      html += `</div>`;
    }

    const menu = document.getElementById('menu');
    if (!menu) return;
    menu.innerHTML = html;

    // Buttons binden
    menu.querySelectorAll('button[data-add]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.dataset.add,10);
        const qty = Math.max(1, parseInt(document.getElementById(`qty-${id}`).value || "1", 10));
        const notes = (document.getElementById(`note-${id}`).value || "").trim();
        const key = id + "|" + (notes || "");
        state.cart[key] = (state.cart[key] || 0) + qty;
        renderCart();
      });
    });
  }

  function renderCart(){
    const box = document.getElementById('cart');
    if (!box) return;
    const lines = Object.entries(state.cart);
    if (lines.length===0){ box.innerHTML = "<p class='muted'>Noch leer.</p>"; document.getElementById('send').disabled = true; return; }

    let html = "<ul>", sum = 0;
    for (const [key, qty] of lines){
      const [id, note] = key.split("|");
      const it = state.items.find(x => x.id == id);
      sum += (it?.price_cents || 0) * qty;
      html += `<li>${qty}× ${it?.name || ("ID "+id)} ${note?`<span class="muted">(${note})</span>`:""} — ${euro((it?.price_cents||0)*qty)}
        <button data-del="${key.replaceAll("'","\\'")}">−</button></li>`;
    }
    html += `</ul><p><strong>Summe:</strong> ${euro(sum)}</p>`;
    box.innerHTML = html;

    box.querySelectorAll('button[data-del]').forEach(b => {
      b.addEventListener('click', () => { delete state.cart[b.dataset.del]; renderCart(); });
    });

    const send = document.getElementById('send');
    if (send) send.disabled = false;
  }

  const sendBtn = document.getElementById('send');
  if (sendBtn) {
    sendBtn.addEventListener('click', async () => {
      const msg = document.getElementById('msg');
      const items = Object.entries(state.cart).map(([key, qty])=>{
        const [id, note] = key.split("|");
        return { item_id: parseInt(id,10), qty, notes: note || null };
      });

      try {
        const res = await fetch('/api/order.php', {
          method:'POST',
          headers:{ 'Content-Type':'application/json' },
          body: JSON.stringify({ table_code: tableCode, items })
        });
        if(!res.ok) throw 0;
        state.cart = {}; renderCart();
        if(msg) msg.textContent = "Bestellung gesendet. Die Bedienung kommt gleich!";
      } catch(e){
        if(msg) msg.textContent = "Fehler beim Abschicken.";
      }
    });
  }

  // ---- Ablauf starten ----
  (async () => {
    if (!tableCode) { showInvalid("Es wurde kein Tischcode übermittelt."); return; }
    const ok = await validateTableCode(tableCode);
    if (!ok) { showInvalid("Der eingegebene Code ist falsch. Bitte scanne den QR-Code am Tisch oder gib den Code auf der Startseite ein."); return; }
    // gültig -> Menü laden
    loadMenu();
  })();
});
