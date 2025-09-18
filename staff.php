<?php require_once __DIR__ . '/config.php'; ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>TableServe – Tresen</title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <style>
    .container{max-width:1100px;margin:24px auto;padding:0 16px}
    .grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
    .card{border:1px solid #ddd;border-radius:var(--radius);padding:12px;background:var(--panel)}
    .row{display:flex;gap:8px;align-items:center;justify-content:space-between}
    .pill{background:#eee;border-radius:999px;padding:2px 8px}
    button{padding:8px}
    .muted{opacity:.7}.small{font-size:.85em}
  </style>
</head>
<body data-page="tresen">
  <!-- Gemeinsamer Header -->
  <div data-include="/partials/header-tableserve.html"></div>

  <main class="container">
    <h1>Tresen-Board</h1>
    <div id="login" class="card" style="margin-bottom:12px">
      <div class="row">
        <input id="key" placeholder="Staff-Key" type="password" style="width:260px">
        <button onclick="login()">Anmelden</button>
      </div>
      <span id="loginMsg" class="muted"></span>
    </div>

    <p class="muted small">Zeigt „offen“ & „in Zubereitung“. Aktualisiert automatisch.</p>
    <div id="orders" class="grid"></div>
  </main>

  <!-- Gemeinsamer Footer -->
  <div data-include="/partials/footer-tableserve.html"></div>

  <script src="/assets/js/include.js" defer></script>
  <script>
  let interval=null;

  async function login(){
    const key = document.getElementById('key').value.trim();
    const r = await fetch('/api/staff_orders.php?login=1', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({key})
    });
    document.getElementById('loginMsg').textContent = r.ok ? 'Angemeldet.' : 'Falscher Key.';
    if(r.ok){ start(); }
  }

  async function fetchOrders(){
    const r = await fetch('/api/staff_orders.php');
    if(!r.ok) return [];
    return (await r.json()).orders;
  }

  function render(orders){
    const box = document.getElementById('orders'); box.innerHTML='';
    if(orders.length===0){ box.innerHTML="<p class='muted'>Keine offenen Bestellungen.</p>"; return; }
    for(const o of orders){
      const ul = o.items.map(it => `<li>${it.qty}× ${it.name}${it.notes?` <span class="muted">(${it.notes})</span>`:''}</li>`).join('');
      const div = document.createElement('div'); div.className='card';
      div.innerHTML = `
        <div class="row"><strong>${o.table_name}</strong><span class="pill">${o.status}</span></div>
        <div class="small muted">${o.created_at}</div>
        <ul>${ul}</ul>
        <div class="row">
          <button onclick="setStatus('${o.id}','in_prep')">In Zubereitung</button>
          <button onclick="setStatus('${o.id}','served')">Serviert</button>
          <button onclick="setStatus('${o.id}','cancelled')">Storno</button>
        </div>`;
      box.appendChild(div);
    }
  }

  async function setStatus(id, status){
    await fetch('/api/set_status.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id, status})});
    tick();
  }
  async function tick(){ render(await fetchOrders()); }
  function start(){ if(interval) clearInterval(interval); tick(); interval=setInterval(tick, 4000); }
  </script>
</body>
</html>
