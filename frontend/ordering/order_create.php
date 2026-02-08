<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['user_id'])) header('Location: ../auth/login.php');
?>

<?php include '../includes/links.php'; ?>
<?php include 'o_sidebar.php'; ?>

<div id="dashboardContainer">

  <main id="main-content">
    <div class="container">
      <h3 class="mb-3">Create Order</h3>

      <div class="row">
        <div class="col-md-7">
          <div class="card mb-3">
            <div class="card-header bg-dark text-white">Products</div>
            <div class="card-body">
              <table class="table table-hover" id="productsTable">
                <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th></th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-md-5">
          <div class="card mb-3">
            <div class="card-header bg-warning">Cart</div>
            <div class="card-body">
              <div class="mb-2">
                <label>Customer</label>
                <select id="customerSelect" class="form-control"></select>
                <button class="btn btn-sm btn-link mt-1" onclick="showCustomerModal()">+ Add new customer</button>
              </div>

              <ul class="list-group mb-2" id="cartList"></ul>

              <div class="mb-2">Total: <strong>₱<span id="cartTotal">0.00</span></strong></div>

              <div class="mb-2">
                <label>Payment Method</label>
                <select id="paymentMethod" class="form-control">
                  <option>Cash</option><option>GCash</option><option>PayMaya</option><option>Bank</option>
                </select>
              </div>

              <div>
                <button class="btn btn-success w-100" onclick="placeOrder()">Place Order</button>
              </div>
            </div>
          </div>

          <!-- Add Customer Modal (simple) -->
          <div class="modal fade" id="customerModal" tabindex="-1">
            <div class="modal-dialog">
              <form id="customerForm" class="modal-content p-3">
                <h5>Add Customer</h5>
                <label>Full Name</label><input name="full_name" class="form-control mb-2" required>
                <label>Phone</label><input name="phone" class="form-control mb-2">
                <label>Email</label><input name="email" class="form-control mb-2" type="email">
                <div class="text-end">
                  <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                  <button class="btn btn-primary">Save</button>
                </div>
              </form>
            </div>
          </div>

        </div>
      </div>

    </div>
  </main>
</div>

<script>
let cart = {}; // { product_id: { product_name, price, qty } }

async function loadProducts(){
  const res = await fetch('../../backend/orders/get_products.php').then(r=>r.json());
  const tbody = document.querySelector('#productsTable tbody');
  tbody.innerHTML = '';
  if(res.success){
    res.data.forEach(p=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${p.product_name}</td><td>₱${Number(p.price).toFixed(2)}</td><td>${p.stock}</td>
        <td><button class="btn btn-sm btn-primary" onclick='addToCart(${p.product_id},"${p.product_name}",${p.price},${p.stock})'>Add</button></td>`;
      tbody.appendChild(tr);
    });
  }
}

async function loadCustomers(){
  const res = await fetch('../../backend/orders/get_customers.php').then(r=>r.json());
  const sel = document.getElementById('customerSelect');
  sel.innerHTML = '<option value="">Walk-in / Guest</option>';
  if(res.success){
    res.data.forEach(c=>{
      const o = document.createElement('option');
      o.value = c.customer_id;
      o.innerText = c.full_name;
      sel.appendChild(o);
    });
  }
}

function addToCart(id,name,price,stock){
  if(cart[id]){
    if(cart[id].qty + 1 > stock) return alert('Not enough stock');
    cart[id].qty++;
  } else cart[id] = {product_name:name, price:price, qty:1};
  updateCart();
}

function updateCart(){
  const ul = document.getElementById('cartList'); ul.innerHTML = '';
  let total = 0;
  Object.keys(cart).forEach(k=>{
    const it = cart[k];
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';
    li.innerHTML = `${it.product_name} <span>₱${(it.price*it.qty).toFixed(2)} <button class="btn btn-sm btn-link" onclick="changeQty(${k}, -1)">-</button> ${it.qty} <button class="btn btn-sm btn-link" onclick="changeQty(${k},1)">+</button> <button class="btn btn-sm btn-danger" onclick="removeItem(${k})">Remove</button></span>`;
    ul.appendChild(li);
    total += it.price * it.qty;
  });
  document.getElementById('cartTotal').innerText = total.toFixed(2);
}

function changeQty(id, delta){
  if(!cart[id]) return;
  cart[id].qty += delta;
  if(cart[id].qty <= 0) delete cart[id];
  updateCart();
}

function removeItem(id){ delete cart[id]; updateCart(); }

async function placeOrder(){
  if(Object.keys(cart).length===0) return alert('Cart empty');
  const customer_id = document.getElementById('customerSelect').value || null;
  const payment_method = document.getElementById('paymentMethod').value;
  const payload = { customer_id, payment_method, items: cart };
  const res = await fetch('../../backend/orders/create_order.php', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
  }).then(r=>r.json());
  if(res.success){
    alert('Order placed (#' + res.order_id + ')');
    cart = {}; updateCart(); loadProducts(); loadCustomers();
  } else alert(res.message || 'Error');
}

function showCustomerModal(){ new bootstrap.Modal(document.getElementById('customerModal')).show(); }

document.getElementById('customerForm').addEventListener('submit', async e=>{
  e.preventDefault();
  const fd = Object.fromEntries(new FormData(e.target).entries());
  const res = await fetch('../../backend/customers/create_customer.php', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(fd)
  }).then(r=>r.json());
  if(res.success){ loadCustomers(); bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide(); }
  else alert(res.message || 'Error');
});

window.addEventListener('load', ()=>{ loadProducts(); loadCustomers(); });
</script>
</body>
</html>
