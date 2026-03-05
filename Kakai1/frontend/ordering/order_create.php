<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'ordering';

// role validation
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit;
}

requirePermission('order_create');

// Fetch categories for the filter dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// NEW: Check if we are in EDIT MODE
$editId = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Order | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
  <style>
    .product-scroll {
      max-height: 600px;
      overflow-y: auto;
    }

    .cart-container {
      position: sticky;
      top: 20px;
    }

    .qty-control {
      width: 40px;
      text-align: center;
      font-weight: bold;
      border: none;
      background: transparent;
    }

    .table-v-align td {
      vertical-align: middle;
    }

    /* Highlight for edit mode */
    .edit-mode-alert {
      border-left: 5px solid #ffc107;
      background-color: #fff3cd;
      color: #856404;
    }
  </style>
</head>

<body class="bg-light">

  <?php include '../includes/sidebar.php'; ?>

  <main id="main-content" style="margin-left: 260px; padding: 25px; transition: margin-left 0.3s;">
    <div class="container-fluid">

      <?php if ($editId): ?>
        <div class="alert edit-mode-alert shadow-sm d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Mode: Order #<?= $editId ?></h4>
            <p class="mb-0 small opacity-75">Adjusting this order will recalculate inventory automatically upon saving.</p>
          </div>
          <a href="order_list.php" class="btn btn-sm btn-outline-dark">Exit Edit Mode</a>
        </div>
      <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h3 class="fw-bold text-dark mb-1">
              <i class="bi bi-cart-plus-fill me-2 text-warning"></i>Create New Order
            </h3>
            <p class="text-muted small mb-0">Select products from inventory to build a customer order.</p>
          </div>
          <div class="d-flex gap-2">
            <a href="ordering_module.php" class="btn btn-secondary shadow-sm">
              <i class="bi bi-arrow-left"></i> Dashboard
            </a>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white py-3">
              <div class="row g-2 align-items-center">
                <div class="col-md-4">
                  <span class="fw-bold"><i class="bi bi-box-seam me-2"></i>Inventory Items</span>
                </div>
                <div class="col-md-4">
                  <select id="categoryFilter" class="form-select form-select-sm" onchange="filterProducts()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <div class="input-group input-group-sm">
                    <input type="text" id="productSearch" class="form-control" placeholder="Search..." onkeyup="filterProducts()">
                    <span class="input-group-text bg-white border-start-0"><i class="bi bi-search"></i></span>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-body p-0 product-scroll">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-v-align" id="productsTable">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-4">Product Details</th>
                      <th>Price</th>
                      <th>Availability</th>
                      <th class="text-end pe-4">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="cart-container">
            <div class="card border-0 shadow-sm border-top border-warning border-4">
              <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bag-check-fill me-2 text-warning"></i>Order Summary</h6>
                <button class="btn btn-sm btn-outline-danger border-0" onclick="clearCart()" title="Clear Cart">
                  <i class="bi bi-trash3"></i>
                </button>
              </div>
              <div class="card-body p-4">
                <div class="mb-3">
                  <label class="form-label small fw-bold">Customer Selection</label>
                  <div class="input-group mb-1">
                    <select id="customerSelect" class="form-select"></select>
                    <button class="btn btn-dark" onclick="showCustomerModal()" title="Add New Customer">
                      <i class="bi bi-person-plus-fill"></i>
                    </button>
                  </div>
                </div>

                <div id="cartList" class="mb-4" style="min-height: 150px;">
                  <div class="text-center text-muted py-5 small" id="emptyCartMsg">
                    <i class="bi bi-cart3 display-6 d-block mb-2 opacity-25"></i>
                    Your cart is empty.
                  </div>
                </div>

                <div class="p-3 bg-light rounded border mb-4">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Subtotal:</span>
                    <span class="fw-bold" id="cartSubtotal">₱0.00</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center border-top pt-2">
                    <span class="h5 fw-bold mb-0">Order Total:</span>
                    <span class="h4 fw-bold mb-0 text-primary">₱<span id="cartTotal">0.00</span></span>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label small fw-bold">Payment Method</label>
                  <select id="paymentMethod" class="form-select border-primary-subtle">
                    <option value="Cash">Cash (Manual)</option>
                    <option value="GCash">GCash / E-Wallet</option>
                    <option value="PayMaya">PayMaya</option>
                    <option value="Bank">Bank Transfer</option>
                  </select>
                </div>

                <button class="btn btn-warning btn-lg w-100 fw-bold shadow-sm py-3" onclick="placeOrder()">
                  <?= $editId ? 'UPDATE ORDER' : 'PROCESS CHECKOUT' ?> <i class="bi bi-arrow-right-circle ms-2"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <form id="customerForm" class="modal-content shadow border-0">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>New Customer Entry</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
            <input name="full_name" class="form-control" placeholder="Juan Dela Cruz" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label small fw-bold">Contact Number</label>
              <input name="phone" class="form-control" placeholder="09xxxxxxxxx">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label small fw-bold">Email Address</label>
              <input name="email" type="email" class="form-control" placeholder="juan@mail.com">
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning fw-bold px-4">Register Customer</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    let cart = {};
    let allProducts = [];
    // NEW: Capture Edit ID from PHP
    const editId = <?= json_encode($editId) ?>;

    function filterProducts() {
      const search = document.getElementById('productSearch').value.toLowerCase();
      const category = document.getElementById('categoryFilter').value;
      const tbody = document.querySelector('#productsTable tbody');
      tbody.innerHTML = '';

      const filtered = allProducts.filter(p => {
        const matchesSearch = p.product_name.toLowerCase().includes(search);
        const matchesCategory = category === "" || p.category_name === category;
        return matchesSearch && matchesCategory;
      });

      renderProducts(filtered);
    }

    async function loadProducts() {
      const res = await fetch('../../backend/orders/get_products.php').then(r => r.json());
      if (res.success) {
        allProducts = res.data;
        renderProducts(allProducts);
      }
    }

    function renderProducts(products) {
      const tbody = document.querySelector('#productsTable tbody');
      tbody.innerHTML = '';
      if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No products found.</td></tr>';
        return;
      }
      products.forEach(p => {
        const tr = document.createElement('tr');
        const stockBadge = p.stock <= 10 ? 'bg-warning text-dark' : 'bg-success-subtle text-success border border-success';
        tr.innerHTML = `
          <td class="ps-4">
            <div class="fw-bold text-dark">${p.product_name}</div>
            <div class="text-muted small">${p.category_name || 'No Category'}</div>
          </td>
          <td class="fw-bold text-dark">₱${Number(p.price).toFixed(2)}</td>
          <td><span class="badge rounded-pill ${stockBadge}">${p.stock} units</span></td>
          <td class="text-end pe-4">
            <button class="btn btn-sm btn-primary px-3 shadow-sm" onclick='addToCart(${p.product_id},"${p.product_name}",${p.price},${p.stock})'>
              <i class="bi bi-cart-plus"></i> Add
            </button>
          </td>`;
        tbody.appendChild(tr);
      });
    }

    async function loadCustomers() {
      const res = await fetch('../../backend/orders/get_customers.php').then(r => r.json());
      const sel = document.getElementById('customerSelect');
      sel.innerHTML = '<option value="">Walk-in / Guest Customer</option>';
      if (res.success) {
        res.data.forEach(c => {
          const o = document.createElement('option');
          o.value = c.customer_id;
          o.innerText = c.full_name;
          sel.appendChild(o);
        });
      }
    }

    // NEW: Load Existing Order Data if Editing
    async function loadOrderDataForEditing(id) {
      Swal.fire({
        title: 'Fetching order details...',
        didOpen: () => {
          Swal.showLoading();
        }
      });

      const res = await fetch(`../../backend/orders/get_order.php?order_id=${id}`).then(r => r.json());
      if (res.success) {
        const o = res.data;
        document.getElementById('customerSelect').value = o.customer_id || "";
        document.getElementById('paymentMethod').value = o.payment_method || "Cash";

        cart = {};
        o.items.forEach(it => {
          // We add the current quantity back to the "available" stock for editing logic
          const pInfo = allProducts.find(p => p.product_id == it.product_id);
          const currentStock = pInfo ? parseInt(pInfo.stock) : 0;

          cart[it.product_id] = {
            product_name: it.product_name,
            price: parseFloat(it.price),
            qty: parseInt(it.quantity),
            stock: currentStock + parseInt(it.quantity)
          };
        });
        updateCart();
        Swal.close();
      } else {
        Swal.fire('Error', 'Could not load order data', 'error');
      }
    }

    function addToCart(id, name, price, stock) {
      if (cart[id]) {
        if (cart[id].qty + 1 > cart[id].stock) {
          Swal.fire({
            icon: 'error',
            title: 'Stock Limit',
            text: 'Insufficient stock available.'
          });
          return;
        }
        cart[id].qty++;
      } else {
        if (stock <= 0) return Swal.fire('Unavailable', 'This item is currently out of stock.', 'warning');
        cart[id] = {
          product_name: name,
          price: price,
          qty: 1,
          stock: stock
        };
      }
      updateCart();
    }

    function updateCart() {
      const list = document.getElementById('cartList');
      const emptyMsg = document.getElementById('emptyCartMsg');
      list.innerHTML = '';
      let total = 0;

      const keys = Object.keys(cart);
      if (keys.length === 0) {
        list.appendChild(emptyMsg);
      } else {
        keys.forEach(k => {
          const it = cart[k];
          const itemDiv = document.createElement('div');
          itemDiv.className = 'd-flex justify-content-between align-items-center mb-3 p-3 bg-white border rounded shadow-sm';
          itemDiv.innerHTML = `
            <div style="max-width: 60%;">
              <div class="fw-bold text-dark text-truncate">${it.product_name}</div>
              <div class="text-muted small">₱${it.price.toFixed(2)} ea</div>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="btn-group btn-group-sm border rounded">
                <button class="btn btn-link text-dark text-decoration-none" onclick="changeQty(${k}, -1)"><i class="bi bi-dash"></i></button>
                <input type="text" class="qty-control" value="${it.qty}" readonly>
                <button class="btn btn-link text-dark text-decoration-none" onclick="changeQty(${k}, 1)"><i class="bi bi-plus"></i></button>
              </div>
              <button class="btn btn-sm btn-outline-danger border-0" onclick="removeItem(${k})"><i class="bi bi-trash"></i></button>
            </div>`;
          list.appendChild(itemDiv);
          total += it.price * it.qty;
        });
      }
      document.getElementById('cartSubtotal').innerText = '₱' + total.toFixed(2);
      document.getElementById('cartTotal').innerText = total.toFixed(2);
    }

    function changeQty(id, delta) {
      if (!cart[id]) return;
      const newQty = cart[id].qty + delta;
      if (newQty > cart[id].stock) return Swal.fire('Limit Reached', 'Only ' + cart[id].stock + ' units available.', 'warning');
      if (newQty <= 0) return removeItem(id);
      cart[id].qty = newQty;
      updateCart();
    }

    function removeItem(id) {
      delete cart[id];
      updateCart();
    }

    function clearCart() {
      if (Object.keys(cart).length === 0) return;
      Swal.fire({
        title: 'Clear Cart?',
        text: "Remove all items from this order?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, clear it'
      }).then((result) => {
        if (result.isConfirmed) {
          cart = {};
          updateCart();
        }
      });
    }

    async function placeOrder() {
      if (Object.keys(cart).length === 0) return Swal.fire('No Items', 'Please add products to the cart first.', 'info');

      // NEW: Dynamic endpoint based on Mode
      const endpoint = editId ? '../../backend/orders/update_order.php' : '../../backend/orders/create_order.php';

      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          order_id: editId, // Will be null if creating new
          customer_id: document.getElementById('customerSelect').value || null,
          payment_method: document.getElementById('paymentMethod').value,
          items: cart
        })
      }).then(r => r.json());

      if (res.success) {
        Swal.fire({
          icon: 'success',
          title: editId ? 'Order Updated!' : 'Order Complete!',
          text: 'Transaction ID: #' + (res.order_id || editId),
          timer: 2500,
          showConfirmButton: false
        }).then(() => window.location.href = 'order_list.php');
      } else {
        Swal.fire('Error', res.message || 'The transaction could not be processed.', 'error');
      }
    }

    function showCustomerModal() {
      new bootstrap.Modal(document.getElementById('customerModal')).show();
    }

    document.getElementById('customerForm').addEventListener('submit', async e => {
      e.preventDefault();
      const fd = Object.fromEntries(new FormData(e.target).entries());
      const res = await fetch('../../backend/customers/create_customer.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(fd)
      }).then(r => r.json());
      if (res.success) {
        Swal.fire('Registered!', 'New customer has been added.', 'success');
        loadCustomers();
        bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
        e.target.reset();
      } else Swal.fire('Failed', res.message || 'Error saving customer.', 'error');
    });

    window.addEventListener('load', async () => {
      await loadProducts();
      await loadCustomers();
      // Trigger edit loading if ID exists
      if (editId) loadOrderForEdit(editId);
    });

    // Separate function to ensure clean async handling
    async function loadOrderForEdit(id) {
      await loadOrderDataForEditing(id);
    }
  </script>
</body>

</html>