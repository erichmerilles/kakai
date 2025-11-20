<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

?>
<?php include '../includes/links.php'; ?>
<?php include 'i_sidebar.php'; ?>

<div id="dashboardContainer">
    <main id="main-content">
        <div class="container-fluid">
            <div id="inventory-overview" class="inventory-section">
                
                <h3 class="fw-bold mb-4">
                    <i class="bi bi-box-seam me-2"></i>Inventory Overview
                </h3>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="module-card text-center">
                            <h6>Total Items</h6>
                            <h2 id="totalItems" class="fw-bold text-primary">0</h2>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="module-card text-center">
                            <h6>Total Stock Value</h6>
                            <h2 id="stockValue" class="fw-bold">₱0.00</h2>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="module-card text-center">
                            <h6 class="text-warning">Low Stock</h6>
                            <h2 id="lowStock" class="fw-bold text-warning">0</h2>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="module-card text-center">
                            <h6 class="text-danger">Out of Stock</h6>
                            <h2 id="outStock" class="fw-bold text-danger">0</h2>
                        </div>
                    </div>
                </div>

                <div class="module-card">
                    <h5 class="mb-3"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Stock Distribution</h5>
                    <canvas id="stockChartOverview" height="150"></canvas>
                </div>

                <div class="module-card mb-4">
                    <h5><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Item</h5>

                    <form id="addItemForm" class="row g-3 mt-2" method="POST" action="../../backend/inventory/add_item.php">
                        <div class="col-md-6">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id">
                                <option value="">Select Category</option>
                                </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Unit Price</label>
                            <input type="number" class="form-control" step="0.01" name="unit_price">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level" value="10">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Supplier (Optional)</label>
                            <select class="form-select" name="supplier_id">
                                <option value="">Select Supplier</option>
                                </select>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-pri w-100">
                                <i class="bi bi-save me-1"></i> Save Item
                            </button>
                        </div>
                    </form>

                    <div id="addItemMessage" class="mt-3"></div>
                </div>
            </div>

            <div id="inventory-manage" class="inventory-section" style="display:none;">
                <?php include 'inventory_manage.php'; ?>
            </div>

            <div id="inventory-movements" class="inventory-section" style="display:none;">
                <?php include 'inventory_movements.php'; ?>
            </div>
            
            <div id="inventory-analytics" class="inventory-section" style="display:none;">
                <?php include 'inventory_analytics.php'; ?>
            </div>

        </div>
    </main>
</div>

<script>
function switchInventorySection(targetId) {
    document.querySelectorAll('.inventory-section').forEach(section => {
        section.style.display = 'none';
    });

    const targetSection = document.getElementById(targetId);
    if (targetSection) {
        targetSection.style.display = 'block';
    }
    
    window.location.hash = targetId;

    if (targetId === 'inventory-manage' && typeof initializeInventoryManage === 'function') {
        initializeInventoryManage();
    } else if (targetId === 'inventory-movements' && typeof initializeInventoryMovements === 'function') {
        initializeInventoryMovements();
    } else if (targetId === 'inventory-analytics' && typeof initializeInventoryAnalytics === 'function') {
        initializeInventoryAnalytics();
    }
    
    if (targetId === 'inventory-overview' && typeof loadDashboard === 'function') {
        loadDashboard();
    }
}

window.addEventListener('inventory-nav-change', (event) => {
    switchInventorySection(event.detail.targetId);
});

window.addEventListener('load', () => {
    const initialTarget = window.location.hash.substring(1) || 'inventory-overview';
    switchInventorySection(initialTarget);
});


// Load main Overview dashboard section
async function loadDashboard() {
    const inv = await fetch('../../backend/inventory/get_inventory.php').then(r => r.json());
    if (!inv.success) return;

    const items = inv.data;

    const totalItems = items.length;
    const lowStock = items.filter(i => i.quantity <= i.reorder_level && i.quantity > 0).length;
    const outStock = items.filter(i => i.quantity <= 0).length;
    const stockValue = items.reduce((sum, i) => sum + (i.quantity * i.unit_price), 0);

    document.getElementById('totalItems').innerText = totalItems;
    document.getElementById('lowStock').innerText = lowStock;
    document.getElementById('outStock').innerText = outStock;
    document.getElementById('stockValue').innerText = '₱' + stockValue.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    // STOCK DISTRIBUTION CHART
    const ctx = document.getElementById('stockChartOverview').getContext('2d');
    const labels = items.map(i => i.item_name);
    const data = items.map(i => i.quantity);

    if (window.inventoryOverviewChart) { window.inventoryOverviewChart.destroy(); } // Destroy previous instance

    window.inventoryOverviewChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Quantity',
                data: data,
                borderWidth: 1,
                borderRadius: 5,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

// Handle Add Item Form Submission
document.getElementById('addItemForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);

    const res = await fetch(form.action, {
        method: 'POST',
        body: fd
    });
    const j = await res.json();
    const msg = document.getElementById('addItemMessage');

    if (j.success) {
        msg.innerHTML = '<div class="alert alert-success">Item added successfully!</div>';
        form.reset();
        loadDashboard();
    } else {
        msg.innerHTML = `<div class="alert alert-danger">${j.message || 'Error adding item.'}</div>`;
    }
});

</script>
