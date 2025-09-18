(function(){
  try {
    const pref = localStorage.getItem('ts-theme');
    const dark = pref ? (pref==='dark') : window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (dark) document.documentElement.classList.add('dark');
  } catch(e){}
})();

document.addEventListener('DOMContentLoaded', () => {
  // aktiver Tab
  const active = document.querySelector('.tabs a[href="/staff.html"]') || document.querySelector('.tabs a[data-active="staff"]');
  if (active) active.classList.add('active');

  // Theme toggle
  const tbtn = document.getElementById('theme-toggle');
  if (tbtn) tbtn.addEventListener('click', () => {
    const dark = !document.documentElement.classList.contains('dark');
    document.documentElement.classList.toggle('dark', dark);
    try { localStorage.setItem('ts-theme', dark ? 'dark' : 'light'); } catch(e){}
  });

  const y = document.getElementById('year'); if (y) y.textContent = new Date().getFullYear();

  // Staff Board
  let timer = null;

  document.getElementById('loginBtn').addEventListener('click', async ()=>{
    const key = document.getElementById('key').value.trim();
    const r = await fetch('/api/staff_orders.php?login=1', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({key})
    });
    document.getElementById('loginMsg').textContent = r.ok ? 'Angemeldet.' : 'Falscher Key.';
    if(r.ok){ start(); }
  });

  async function fetchOrders(){
    const r = await fetch('/api/staff_orders.php', {headers:{'Accept':'application/json'}});
    if(!r.ok) return [];
    const data = await r.json();
    return Array.isArray(data.orders) ? data.orders : [];
  }

  function render(orders){
    const box = document.getElementById('orders'); box.innerHTML='';
    if(orders.length===0){ box.innerHTML="<p class='muted'>Keine offenen Bestellungen.</p>"; return; }
    for(const o of orders){
      const ul = (o.items||[]).map(it => `<li>${it.qty}× ${it.name}${it.notes?` <span class="muted">(${it.notes})</span>`:''}</li>`).join('');
      const div = document.createElement('div'); div.className='card';
      div.innerHTML = `
        <div class="row"><strong>${o.table_name||o.table_code||'Tisch'}</strong><span class="pill">${o.status}</span></div>
        <div class="small muted">${o.created_at||''}</div>
        <ul>${ul}</ul>
        <div class="row">
          <button data-status='in_prep' data-id='${o.id}'>In Zubereitung</button>
          <button data-status='served'  data-id='${o.id}'>Serviert</button>
          <button data-status='cancelled' data-id='${o.id}'>Storno</button>
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

  async function tick(){ render(await fetchOrders()); }
  function start(){ if(timer) clearInterval(timer); tick(); timer=setInterval(tick, 4000); }
});
