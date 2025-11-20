<?php ?>
<div id="inventory-manage-content" class="container mt-4">
  <h3>Inventory Management</h3>
  <a href="#inventory-analytics"
     onclick="window.dispatchEvent(new CustomEvent('inventory-nav-change', { detail: { targetId: 'inventory-analytics' } }));"
     class="btn btn-secondary mb-2">Analytics</a>
  <button class="btn btn-primary mb-2" onclick="showAdd()">Add Item</button>
  <table class="table table-bordered" id="invTable">
    <thead>
      <tr>
        <th>Name</th>
        <th>Qty</th>
        <th>Reorder</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<!-- Item Modal -->
<div class="modal fade" id="itemModal">
  <div class="modal-dialog">
    <form id="itemForm" class="modal-content p-3">
      <h5 id="modalTitle">Item</h5>
      <input type="hidden" name="item_id" />
      <label>Name</label>
      <input name="item_name" class="form-control mb-2" required />
      <label>Quantity</label>
      <input name="quantity" type="number" class="form-control mb-2" value="0" />
      <label>Unit Price</label>
      <input name="unit_price" type="number" step="0.01" class="form-control mb-2" value="0" />
      <label>Reorder Level</label>
      <input name="reorder_level" type="number" class="form-control mb-2" value="10" />
      <div class="text-end">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Movement Modal -->
<div class="modal fade" id="moveModal">
  <div class="modal-dialog">
    <form id="moveForm" class="modal-content p-3">
      <h5>Stock Movement</h5>
      <input type="hidden" name="item_id" />
      <label>Type</label>
      <select name="type" class="form-control mb-2">
        <option value="IN">IN</option>
        <option value="OUT">OUT</option>
      </select>
      <label>Quantity</label>
      <input name="quantity" type="number" class="form-control mb-2" value="1" />
      <label>Remarks</label>
      <input name="remarks" class="form-control mb-2" />
      <div class="text-end">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary">Apply</button>
      </div>
    </form>
  </div>
</div>

<script>
function initializeInventoryManage() {
  const base = '../../backend/inventory';

  async function load() {
    const response = await fetch(base + '/get_inventory.php');
    const data = await response.json();
    const tableBody = document.querySelector('#invTable tbody');
    tableBody.innerHTML = '';

    if (!data.success) return;

    data.data.forEach(item => {
      const safeName = item.item_name.replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      }[c]));

      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${safeName}</td>
        <td>${item.quantity}</td>
        <td>${item.reorder_level}</td>
        <td>
          <button class="btn btn-sm btn-primary" onclick='openEdit(${item.item_id}, ${JSON.stringify(item)})'>Edit</button>
          <button class="btn btn-sm btn-warning" onclick="openMove(${item.item_id})">Move</button>
          <button class="btn btn-sm btn-danger" onclick="del(${item.item_id})">Delete</button>
        </td>
      `;
      tableBody.appendChild(row);
    });
  }

  window.loadInventoryManage = load;

  window.showAdd = function () {
    const form = document.getElementById('itemForm');
    form.reset();
    form.item_id.value = '';
    document.getElementById('modalTitle').innerText = 'Add Item';
    new bootstrap.Modal(document.getElementById('itemModal')).show();
  };

  window.openEdit = function (id, obj) {
    const form = document.getElementById('itemForm');
    form.item_id.value = id;
    form.item_name.value = obj.item_name;
    form.quantity.value = obj.quantity;
    form.unit_price.value = obj.unit_price;
    form.reorder_level.value = obj.reorder_level;
    document.getElementById('modalTitle').innerText = 'Edit Item';
    new bootstrap.Modal(document.getElementById('itemModal')).show();
  };

  document.getElementById('itemForm').addEventListener('submit', async e => {
    e.preventDefault();
    const formData = Object.fromEntries(new FormData(e.target).entries());
    const url = formData.item_id ? base + '/update_item.php' : base + '/add_item.php';
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData)
    });
    const result = await res.json();
    if (result.success) {
      bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
      load();
    } else {
      alert(result.message);
    }
  });

  window.openMove = function (id) {
    const form = document.getElementById('moveForm');
    form.reset();
    form.item_id.value = id;
    new bootstrap.Modal(document.getElementById('moveModal')).show();
  };

  document.getElementById('moveForm').addEventListener('submit', async e => {
    e.preventDefault();
    const formData = Object.fromEntries(new FormData(e.target).entries());
    const res = await fetch(base + '/movement.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData)
    });
    const result = await res.json();
    if (result.success) {
      bootstrap.Modal.getInstance(document.getElementById('moveModal')).hide();
      load();
    } else {
      alert(result.message);
    }
  });

  window.del = async function (id) {
    if (!confirm('Delete?')) return;
    const res = await fetch(base + '/delete_item.php?item_id=' + id);
    const result = await res.json();
    if (result.success) load();
    else alert(result.message);
  };
}
</script>