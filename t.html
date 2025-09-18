<?php
require_once __DIR__ . '/functions.php';
$code = $_GET['code'] ?? '';
$tables = csv_read_assoc(CSV_TABLES);
$tbl = null;
foreach ($tables as $t) if (($t['code'] ?? '') === $code) { $tbl = $t; break; }
if (!$tbl) { http_response_code(404); echo "Ungültiger Tisch."; exit; }
$menu = array_values(array_filter(csv_read_assoc(CSV_MENU), fn($m) => ($m['active'] ?? '') === '1'));
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bestellung – <?= htmlspecialchars($tbl['name']) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <style>
    /* Kleine Ergänzungen für TableServe */
    .container{max-width:1000px;margin:24px auto;padding:0 16px}
    .grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .card{border:1px solid #ddd;border-radius:var(--radius);padding:12px;background:var(--panel)}
    .row{display:flex;gap:8px;align-items:center;justify-content:space-between}
    .muted{opacity:.7}.badge{background:#eee;border-radius:8px;padding:2px 8px}
    button,input,textarea{padding:8px}
  </style>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <meta name="theme-color" content="#ffffff">
</head>
<body data-page="tableserve">
  <!-- Gemeinsamer Header -->
  <div data-include="/partials/header-tableserve.html"></div>
  <main class="container">
    <h1><?= htmlspecialchars($tbl['name']) ?></h1>
    <p class="muted">Tisch-Code: <?= htmlspecialchars($tbl['code']) ?></p>

    <?php
      $byCat = [];
      foreach ($menu as $m) { $byCat[$m['category']][] = $m; }
      foreach ($byCat as $cat => $items):
    ?>
      <h3><?= htmlspecialchars($cat) ?></h3>
      <div class="grid">
      <?php foreach ($items as $it): ?>
        <div class="card">
          <div class="row">
            <strong><?= htmlspecialchars($it['name']) ?></strong>
            <span class="badge"><?= number_format(((int)$it['price_cents'])/100, 2, ',', '.') ?> €</span>
          </div>
          <div class="row">
            <input type="number" min="1" value="1" id="qty-<?= (int)$it['id'] ?>" style="width:70px">
            <button onclick="add(<?= (int)$it['id'] ?>)">Hinzufügen</button>
          </div>
          <textarea id="note-<?= (int)$it['id'] ?>" placeholder="Optional: Hinweis (z. B. ohne Eis)" rows="2" style="width:100%;margin-top:6px"></textarea>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <h3>Warenkorb</h3>
    <div id="cart"><p class="muted">Noch leer.</p></div>
    <button id="send" disabled>Bestellung abschicken</button>
    <p id="msg" class="muted"></p>
  </main>

  <!-- Gemeinsamer Footer -->
  <div data-include="/partials/footer-tableserve.html"></div>
  <script src="/assets/js/include.js" defer></script>
  <script>
    const tableCode = <?= json_encode($tbl['code'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const cart = {};
    const prices = Object.fromEntries(<?=
      json_encode(array_map(fn($m)=>[(int)$m['id'], (int)$m['price_cents']], $menu), JSON_UNESCAPED_SLASHES)
    ?>.map(v=>v));
    const names  = Object.fromEntries(<?=
      json_encode(array_map(fn($m)=>[(int)$m['id'], $m['name']], $menu), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ?>.map(v=>v));

    function add(id){
      const qty = Math.max(1, parseInt(document.getElementById('qty-'+id).value || "1",10));
      const note = (document.getElementById('note-'+id).value || "").trim();
      const key = id + "|" + note;
      cart[key] = (cart[key]||0) + qty;
      render();
    }
    function delKey(key){ delete cart[key]; render(); }
    function euro(c){ return (c/100).toFixed(2).replace('.', ',') + " €"; }

    function render(){
      const cont = document.getElementById('cart');
      const entries = Object.entries(cart);
      if(entries.length===0){ cont.innerHTML = "<p class='muted'>Noch leer.</p>"; document.getElementById('send').disabled=true; return; }
      let sum = 0, html = "<ul>";
      for(const [key, qty] of entries){
        const [id, note] = key.split('|');
        sum += (prices[id]||0)*qty;
        html += `<li>${qty}× ${names[id]||('ID '+id)} ${note?`<span class="muted">(${note})</span>`:''}
          — ${euro((prices[id]||0)*qty)} <button onclick="delKey('${key.replaceAll("'","\\'")}')">−</button></li>`;
      }
      html += `</ul><p><strong>Summe:</strong> ${euro(sum)}</p>`;
      cont.innerHTML = html;
      document.getElementById('send').disabled=false;
    }

    document.getElementById('send').onclick = async ()=>{
      const items = Object.entries(cart).map(([key, qty])=>{
        const [id, note] = key.split('|');
        return { item_id: parseInt(id,10), qty, notes: note||null };
      });
      const res = await fetch('/api/order.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ table_code: tableCode, items })
      });
      if(res.ok){
        for(const k in cart) delete cart[k];
        render();
        document.getElementById('msg').textContent = "Bestellung gesendet. Die Bedienung kommt gleich!";
      } else {
        document.getElementById('msg').textContent = "Fehler beim Abschicken.";
      }
    };
  </script>
</body>
</html>
