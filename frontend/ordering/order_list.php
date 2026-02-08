<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['user_id'])) header('Location: ../auth/login.php');
?>

<?php include '../includes/links.php'; ?>
<?php include 'o_sidebar.php'; ?>

<div id="dashboardContainer">
  <main id="main-content">
    <div class="container-fluid">
      <h3 class="mb-3">Orders</h3>
      <div class="module-card">
        <div class="table-responsive">
          <table class="table table-striped" id="ordersTable">
            <thead class="table-light"><tr><th>ID</th><th>Customer</th><th>Status</th><th>Payment</th><th>Total</th><th>Date</th><th>Action</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
async function loadOrders(){
  const res = await fetch('../../backend/orders/get_orders.php').then(r=>r.json());
  const tb = document.querySelector('#ordersTable tbody'); tb.innerHTML='';
  if(!res.success) return;
  res.data.forEach(o=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>#${o.order_id}</td>
      <td>${o.full_name??'Guest'}</td>
      <td>${o.status}</td>
      <td>${o.payment_status}</td>
      <td>₱${Number(o.total_amount).toFixed(2)}</td>
      <td>${o.order_date}</td>
      <td>
        <a class="btn btn-sm btn-primary" href="order_view.php?id=${o.order_id}">View</a>
        <button class="btn btn-sm btn-warning" onclick="changeStatus(${o.order_id}, 'Processing')">Mark Processing</button>
      </td>`;
    tb.appendChild(tr);
  });
}

async function changeStatus(id, status){
  const res = await fetch('../../backend/orders/update_status.php', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({order_id:id, status})
  }).then(r=>r.json());
  if(res.success){ loadOrders(); } else alert(res.message || 'Error');
}

loadOrders();
</script>
</body></html>
