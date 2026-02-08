<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
}

$id = intval($_GET['id'] ?? 0);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order #<?= $id ?> | KakaiOne</title>
    <?php include __DIR__ . '/../includes/links.php'; ?>
</head>
<body>

<div class="container mt-4">
    <a href="order_list.php" class="btn btn-secondary mb-3">Back</a>
    <div id="orderRoot">Loading…</div>
</div>

<script>
(async function() {
    const id = <?= $id ?>;

    const res = await fetch('../../backend/orders/get_order.php?order_id=' + id)
        .then(r => r.json());

    const root = document.getElementById('orderRoot');

    if (!res.success) {
        root.innerHTML = `
            <div class="alert alert-danger">
                Order not found
            </div>
        `;
        return;
    }

    const o = res.data;

    let html = `
        <h3>Order #${o.order_id}</h3>
        <p><strong>Customer:</strong> ${o.full_name ?? 'Guest'}</p>
        <p>
            <strong>Status:</strong> ${o.status} |
            <strong>Payment:</strong> ${o.payment_status}
        </p>

        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
    `;

    o.items.forEach(it => {
        html += `
            <tr>
                <td>${it.product_name}</td>
                <td>${it.quantity}</td>
                <td>₱${Number(it.price).toFixed(2)}</td>
                <td>₱${Number(it.subtotal).toFixed(2)}</td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>

        <h5>Total: ₱${Number(o.total_amount).toFixed(2)}</h5>

        <div class="mt-3">
            <button class="btn btn-success" onclick="print()">
                Print
            </button>
        </div>
    `;

    root.innerHTML = html;
})();
</script>

</body>
</html>
