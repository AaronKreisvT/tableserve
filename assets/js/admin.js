// Admin-UI: Login -> Panels anzeigen -> Tische & Menü CRUD via APIs
document.addEventListener('DOMContentLoaded', () => {
  // aktiven Tab nicht markieren (optional könntest du im Header einen Admin-Link ergänzen)
  const y = document.getElementById('year'); if (y) y.textContent = new Date().getFullYear();

  const loginCard   = document.getElementById('loginCard');
  const adminPanels = document.getElementById('adminPanels');
  const loginMsg    = document.getElementById('loginMsg');

  // --- Login ---
  const loginForm = document.getElementById('adminLoginForm');
  loginForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const password = (document.getElementById('adminPass')?.value || '').trim();
    if (loginMsg) loginMsg.textContent = 'Anmeldung...';
    try {
      const r = await fetch('/api/admin_login.php?login=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ password })
      });
      if (!r.ok) {
        if (loginMsg) loginMsg.textContent = 'Falsches Passwort.';
        return;
      }
      if (loginMsg) loginMsg.textContent = 'Angemeldet.';
      showPanels();
    } catch {
      if (loginMsg) loginMsg.textContent = 'Netzwerkfehler.';
    }
  });

  async function showPanels() {
    loginCard?.classList.add('hidden');
    adminPanels?.classList.remove('hidden');
    await Promise.all([loadTables(), loadMenu()]);
  }

  // --- Logout ---
  document.getElementById('adminLogout')?.addEventListener('click', async () => {
    await fetch('/api/admin_login.php?logout=1', {
      method: 'POST',
      credentials: 'same-origin'
    });
    location.reload();
  });

  // --- Tische ---
  const tablesList = document.getElementById('tablesList');

  async function loadTables(){
    if (tablesList) tablesList.innerHTML = `<p class="muted">Lade Tische…</p>`;
    try{
      const r = await fetch('/api/tables.php', {
        headers: { 'Accept':'application/json' },
        credentials: 'same-origin'
      });
      if (!r.ok) {
        tablesList.innerHTML = `<p class="muted">Fehler beim Laden der Tische (HTTP ${r.status}).</p>`;
        return;
      }
      const data = await r.json();
      const tables = Array.isArray(data.tables) ? data.tables : [];
      if (tables.length === 0) {
        tablesList.innerHTML = `<p class="muted">Noch keine Tische angelegt.</p>`;
        return;
      }
      let html = `<table><thead><tr><th style="width:50%">Name</th><th>Code</th><th class="right">Aktionen</th></tr></thead><tbody>`;
      for (const t of tables) {
        const name = (t.name||'').replace(/</g,'&lt;');
        const code = (t.code||'').replace(/</g,'&lt;');
        html += `
          <tr>
            <td>
              <form class="inline form-rename" data-code="${code}">
                <input type="text" name="name" value="${name}" required style="min-width:220px">
                <button type="submit">Umbenennen</button>
              </form>
            </td>
            <td><code>${code}</code></td>
            <td class="right">
              <button class="btn-delete" data-code="${code}">Löschen</button>
              <a href="/order.html?code=${encodeURIComponent(code)}" style="margin-left:6px">Öffnen</a>
            </td>
          </tr>`;
      }
      html += `</tbody></table>`;
      tablesList.innerHTML = html;

      tablesList.querySelectorAll('.form-rename').forEach(form=>{
        form.addEventListener('submit', async (e)=>{
          e.preventDefault();
          const code = form.dataset.code;
          const name = form.querySelector('input[name="name"]').value.trim();
          if (!name) return;
          await fetch('/api/tables.php', {
            method: 'PATCH',
            headers: { 'Content-Type':'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ code, name })
          });
          loadTables();
        });
      });
      tablesList.querySelectorAll('.btn-delete').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          const code = btn.dataset.code;
          if (!confirm(`Tisch ${code} wirklich löschen?`)) return;
          await fetch('/api/tables.php', {
            method: 'DELETE',
            headers: { 'Content-Type':'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ code })
          });
          loadTables();
        });
      });

    } catch {
      tablesList.innerHTML = `<p class="muted">Netzwerkfehler beim Laden der Tische.</p>`;
    }
  }

  document.getElementById('tableCreateForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const name = (document.getElementById('newTableName')?.value || '').trim();
    if (!name) return;
    await fetch('/api/tables.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ name })
    });
    document.getElementById('newTableName').value = '';
    loadTables();
  });

  // --- Menü ---
  const menuList = document.getElementById('menuList');

  async function loadMenu(){
    if (menuList) menuList.innerHTML = `<p class="muted">Lade Menü…</p>`;
    try{
      const r = await fetch('/api/menu_admin.php', {
        headers: { 'Accept':'application/json' },
        credentials: 'same-origin'
      });
      if (!r.ok) {
        menuList.innerHTML = `<p class="muted">Fehler beim Laden des Menüs (HTTP ${r.status}).</p>`;
        return;
      }
      const data = await r.json();
      const items = Array.isArray(data.items) ? data.items : [];

      if (items.length === 0) {
        menuList.innerHTML = `<p class="muted">Noch keine Menüeinträge.</p>`;
        return;
      }

      let html = `<table>
        <thead><tr><th>ID</th><th style="width:40%">Name</th><th>Preis</th><th>Kategorie</th><th>Aktiv</th><th class="right">Aktionen</th></tr></thead>
        <tbody>`;
      for (const m of items) {
        const id  = m.id;
        const nm  = (m.name||'').replace(/</g,'&lt;');
        const eur = (m.price_cents/100).toFixed(2).replace('.', ',');
        const cat = (m.category||'').replace(/</g,'&lt;');
        const act = !!m.active;
        html += `<tr>
          <td>${id}</td>
          <td>
            <form class="inline form-menu" data-id="${id}">
              <input type="text" name="name" value="${nm}" required style="min-width:220px">
              <input type="text" name="price" value="${eur}" required style="width:120px">
              <input type="text" name="category" value="${cat}" required style="width:160px">
              <label><input type="checkbox" name="active" ${act?'checked':''}> aktiv</label>
              <button type="submit">Speichern</button>
            </form>
          </td>
          <td>${eur} €</td>
          <td>${cat}</td>
          <td>${act ? 'ja':'nein'}</td>
          <td class="right"><button class="btn-del-item" data-id="${id}">Löschen</button></td>
        </tr>`;
      }
      html += `</tbody></table>`;
      menuList.innerHTML = html;

      menuList.querySelectorAll('.form-menu').forEach(form=>{
        form.addEventListener('submit', async (e)=>{
          e.preventDefault();
          const id = parseInt(form.dataset.id,10);
          const name = form.querySelector('input[name="name"]').value.trim();
          const price = form.querySelector('input[name="price"]').value.trim();
          const category = form.querySelector('input[name="category"]').value.trim();
          const active = form.querySelector('input[name="active"]').checked;
          await fetch('/api/menu_admin.php', {
            method:'PATCH',
            headers:{'Content-Type':'application/json'},
            credentials:'same-origin',
            body: JSON.stringify({ id, name, price, category, active })
          });
          loadMenu();
        });
      });
      menuList.querySelectorAll('.btn-del-item').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          const id = parseInt(btn.dataset.id,10);
          if (!confirm(`Eintrag #${id} wirklich löschen?`)) return;
          await fetch('/api/menu_admin.php', {
            method:'DELETE',
            headers:{'Content-Type':'application/json'},
            credentials:'same-origin',
            body: JSON.stringify({ id })
          });
          loadMenu();
        });
      });

    } catch {
      menuList.innerHTML = `<p class="muted">Netzwerkfehler beim Laden des Menüs.</p>`;
    }
  }

  document.getElementById('menuCreateForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const name = document.getElementById('menuNewName').value.trim();
    const price = document.getElementById('menuNewPrice').value.trim();
    const category = document.getElementById('menuNewCat').value.trim();
    const active = document.getElementById('menuNewActive').checked;

    await fetch('/api/menu_admin.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ name, price, category, active })
    });

    document.getElementById('menuNewName').value = '';
    document.getElementById('menuNewPrice').value = '';
    document.getElementById('menuNewCat').value = '';
    document.getElementById('menuNewActive').checked = true;

    loadMenu();
  });
});
