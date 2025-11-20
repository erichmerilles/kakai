<?php
?>

<div id="inventory-analytics-content" class="container mt-4">
  <h3>Inventory Analytics</h3>
  <div class="row">
    <div class="col-md-6">
      <h5>Low Stock</h5>
      <ul id="lowStock" class="list-group"></ul>
    </div>
    <div class="col-md-6">
      <h5>Stock Levels</h5>
      <canvas id="stockChart" height="200"></canvas>
    </div>
  </div>
  <hr>
  <h5>Forecast (avg monthly out)</h5>
  <div id="forecastArea"></div>
</div>

<script>
function initializeInventoryAnalytics() {
  const base = '../../backend/inventory';
  const escapeHTML = str => str.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

  async function loadLow() {
    const r = await fetch(base + '/low_stock_alerts.php');
    const j = await r.json();
    const ul = document.getElementById('lowStock');
    if(!ul) return;
    ul.innerHTML = '';
    if (j.success) {
      j.data.forEach(it=>{
        const safeItemName = escapeHTML(it.item_name);
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `<div><strong>${safeItemName}</strong><br/><small>Cat ID: ${it.category_id}</small></div><span class="badge bg-danger rounded-pill">${it.quantity}</span>`;
        ul.appendChild(li);
      });
    }
  }

  async function loadChart() {
    const r = await fetch(base + '/get_inventory.php');
    const j = await r.json();
    if (!j.success) return;
    const labels = j.data.map(x=>x.item_name);
    const data = j.data.map(x=>x.quantity);
    const ctx = document.getElementById('stockChart').getContext('2d');
    if (window.inventoryStockChart) { window.inventoryStockChart.destroy(); } // Destroy previous instance
    window.inventoryStockChart = new Chart(ctx, {type:'bar', data:{labels, datasets:[{label:'Quantity', data}]}, options:{responsive:true}});
  }

  async function loadForecast() {
    const r = await fetch(base + '/forecast_inventory.php');
    const j = await r.json();
    const area = document.getElementById('forecastArea');
    if(!area) return;
    if (!j.success) { area.innerText = 'No forecast data'; return; }
    area.innerHTML = '';
    
    // Fetch inventory data
    const invRes = await fetch(base + '/get_inventory.php').then(r => r.json());
    const itemMap = invRes.success ? invRes.data.reduce((map, item) => { map[item.item_id] = item.item_name; return map; }, {}) : {};

    for (const [itemId, info] of Object.entries(j.forecast)) {
      const itemName = itemMap[itemId] || `Item ID ${itemId}`;
      const div = document.createElement('div');
      div.className = 'card p-2 mb-2';
      div.innerHTML = `<strong>${escapeHTML(itemName)}</strong>
        <div>Avg monthly out: ${info.avg_monthly_out}</div>
        <div>Forecast next 3 months: ${info.forecast_next_3_months.map(escapeHTML).join(', ')}</div>`;
      area.appendChild(div);
    }
  }

  loadLow(); loadChart(); loadForecast();
}
</script>