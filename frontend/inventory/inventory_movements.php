<?php ?>
<div id="inventory-movements-content" class="container mt-4">
  <h3>Inventory Movements</h3>
  <table class="table table-striped" id="movTable">
    <thead>
      <tr>
        <th>ID</th>
        <th>Item</th>
        <th>Type</th>
        <th>Qty</th>
        <th>Remarks</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<script>
function initializeInventoryMovements() {
  async function loadMov() {
    const response = await fetch('../../backend/inventory/get_movements.php'); // Adjusted path
    const json = await response.json();
    const tableBody = document.querySelector('#movTable tbody');
    tableBody.innerHTML = '';

    if (!json.success) return;

    json.data.forEach(movement => {
      const escapeHTML = str =>
        str.replace(/[&<>"']/g, c => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        }[c]));

      const safeItemName = escapeHTML(movement.item_name);
      const safeType = escapeHTML(movement.type);
      const safeRemarks = escapeHTML(movement.remarks);

      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${movement.movement_id}</td>
        <td>${safeItemName}</td>
        <td>${safeType}</td>
        <td>${movement.quantity}</td>
        <td>${safeRemarks}</td>
        <td>${movement.created_at}</td>
      `;
      tableBody.appendChild(row);
    });
  }

  window.loadInventoryMovements = loadMov; // Load function globally
  loadMov();
}
</script>