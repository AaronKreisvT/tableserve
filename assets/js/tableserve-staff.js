// Staff-Seite: Login, Bestell-Board, Tischverwaltung
document.addEventListener('DOMContentLoaded', () => {
  // aktiver Tab
  const active = document.querySelector('.tabs a[href="/staff.html"]') || document.querySelector('.tabs a[data-active="staff"]');
  if (active) active.classList.add('active');

  const y = document.getElementById('year'); if (y) y.textContent = new Date().getFullYear();

  let timer = null;

  // -------- Login --------
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const key = (document.getElementById('key')?.value || '').trim();
      const r = await fetch('/api/staff_orders.php?login=1', {
        method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ key })
      });
      const msg = document.getElementById('loginMsg');
      if (msg) msg.textContent = r.ok ? 'Angemeldet.' : 'Falscher Key.';
      if (r.ok) { start(); loadTables(); }
    });
  }

  // -------- Bestell-Board --------
  async function fetchOrders(){
    const r = await fetch('/api/staff_orders.php', { headers:{ 'Accept':'application/json' } });
    if (!r.ok) return [];
    const data = await r.json();
    return Array.isArray(data.orders) ? data.orders : [];
  }

  function renderOrders(orders){
    const box = document.getElementById('orders'); if (!box) return;
    box.innerHTML='';
    if(orders.length===0){ box.innerHTML="<p class='muted'>Keine offenen Bestellungen.</p>"; return; }
    for(const o of orders){
      const ul = (o.items||[]).map(it => `<li>${it.qty}× ${it.name}${it.notes?` <span class="muted">(${it.notes})</span>`:''}</li>`).join('');
      const div = document.createElement('div'); div.className='card';
      div.innerHTML = `
        <div class="row"><strong>${o.table_name||o.table_code||'Tisch'}</strong><span class="pill">${o.status}</span></div>
        <div class="small muted">${o.created_at||''}</div>
        <ul>${ul}</ul>
        <div class="row">
          <button data-status='in_prep'  data-id='${o.id}'>In Zubereitung</button>
          <button data-status='served'   data-id='${o.id}'>Serviert</button>
          <button data-status='cancelled'data-id='${o.id}'>Storno</button>
        </div>`;
      box.appendChild(div);
    }
    box.querySelectorAll('button[data-status]').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        await fetch('/api/set_status.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({id: btn.dataset.id, status: btn.dataset.status})
        });
        tick();
      });
    });
  }

  async function tick(){ renderOrders(await fetchOrders()); }
  function start(){ if(timer) clearInterval(timer); tick(); timer=setInterval(tick, 4000); }

  // -------- Tischverwaltung --------
  async function loadTables(){
    const list = document.getElementById('tablesList'); if (!list) return;
    list.innerHTML = `<p class="muted">Lade Tische…</p>`;
    try{
      const r = await fetch('/api/tables.php', { headers:{ 'Accept':'application/json' } });
      if(!r.ok) throw 0;
      const data = await r.json();
      const tables = Array.isArray(data.tables) ? data.tables : [];

      if (tables.length === 0) {
        list.innerHTML = `<p class="muted">Noch keine Tische angelegt.</p>`;
        return;
      }

      let html = `<table><thead><tr><th style="width:50%">Name</th><th>Code</th><th style="text-align:right">Aktionen</th></tr></thead><tbody>`;
      for (const t of tables) {
        const name = (t.name||'').replace(/</g,'&lt;');
        const code = (t.code||'').replace(/</g,'&lt;');
        html += `
          <tr>
            <td>${name}</td>
            <td><code>${code}</code></td>
            <td style="text-align:right">
              <button class="btn-rename" data-code="${code}">Umbenennen</button>
              <button class="btn-delete" data-code="${code}">Löschen</button>
            </td>
          </tr>`;
      }
      html += `</tbody></table>`;
      list.innerHTML = html;

      // Aktionen binden
      list.querySelectorAll('.btn-rename').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          const code = btn.dataset.code;
          const currentName = btn.closest('tr')?.children?.[0]?.textContent?.trim() || '';
          const name = prompt(`Neuer Name für Tisch ${code}:`, currentName);
          if (!name) return;
          await fetch('/api/tables.php', {
            method:'PATCH',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ code, name })
          });
          loadTables();
        });
      });
      list.querySelectorAll('.btn-delete').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          const code = btn.dataset.code;
          if (!confirm(`Tisch ${code} wirklich löschen?`)) return;
          await fetch('/api/tables.php', {
            method:'DELETE',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ code })
          });
          loadTables();
        });
      });

    } catch(e){
      list.innerHTML = `<p class="muted">Fehler beim Laden der Tische.</p>`;
    }
  }

  const createForm = document.getElementById('tableCreateForm');
  if (createForm) {
    createForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const name = (document.getElementById('newTableName')?.value || '').trim();
      if (!name) return;
      await fetch('/api/tables.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ name })
      });
      (document.getElementById('newTableName')||{}).value = '';
      loadTables();
    });
  }
});
