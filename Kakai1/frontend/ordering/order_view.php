<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Set active module for sidebar highlighting
$activeModule = 'ordering';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);

requirePermission('order_view');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail #<?= $id ?> | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .invoice-card {
            border: none;
            border-radius: 12px;
        }

        .invoice-header {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .invoice-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .invoice-value {
            font-weight: 700;
            color: #212529;
        }

        @media print {

            #sidebar,
            .btn,
            .breadcrumb,
            footer {
                display: none !important;
            }

            #main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .container-fluid {
                padding: 0 !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .invoice-card {
                padding: 0 !important;
            }

            body {
                background: white !important;
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1">
                        <i class="bi bi-file-earmark-text me-2 text-warning"></i>Order Details
                    </h3>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="ordering_module.php" class="text-decoration-none">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="order_list.php" class="text-decoration-none">Order List</a></li>
                            <li class="breadcrumb-item active">Order #<?= $id ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="order_list.php" class="btn btn-secondary shadow-sm">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                    <button class="btn btn-warning fw-bold shadow-sm" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i> Print Invoice
                    </button>
                </div>
            </div>

            <div id="orderRoot">
                <div class="card shadow-sm invoice-card p-4 text-center">
                    <div class="spinner-border text-warning my-5" role="status"></div>
                    <p class="text-muted">Fetching order details...</p>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (async function() {
            const id = <?= $id ?>;
            const root = document.getElementById('orderRoot');

            try {
                const res = await fetch('../../backend/orders/get_order.php?order_id=' + id)
                    .then(r => r.json());

                if (!res.success) {
                    root.innerHTML = `
                        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
                            <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
                            <div><strong>Error:</strong> Order not found or has been removed.</div>
                        </div>
                    `;
                    return;
                }

                const o = res.data;

                // Status Badge Logic
                let statusClass = 'bg-secondary';
                if (o.status === 'Pending') statusClass = 'bg-warning text-dark';
                if (o.status === 'Processing') statusClass = 'bg-info text-dark';
                if (o.status === 'Delivered' || o.status === 'Completed') statusClass = 'bg-success';
                if (o.status === 'Cancelled') statusClass = 'bg-danger';

                // Payment Status Badge
                let payClass = o.payment_status === 'Paid' ? 'bg-success' : 'bg-danger';

                let html = `
                    <div class="card shadow-sm border-0 invoice-card">
                        <div class="card-body p-lg-5">
                            <div class="invoice-header d-flex justify-content-between align-items-start">
                                <div>
                                    <img src="../assets/images/logo.png" alt="Logo" class="mb-3" style="max-height: 60px;">
                                    <h4 class="fw-bold text-dark mb-0">ORDER #${o.order_id}</h4>
                                    <p class="text-muted mb-0">Date: ${new Date(o.order_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                                </div>
                                <div class="text-end">
                                    <span class="badge ${statusClass} rounded-pill px-3 py-2 mb-2 fs-6">${o.status}</span><br>
                                    <span class="badge ${payClass} rounded-pill px-3 py-1 fs-small">Payment: ${o.payment_status}</span>
                                </div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-sm-4">
                                    <div class="invoice-label">Customer Info</div>
                                    <div class="invoice-value fs-5">${o.full_name ?? '<span class="text-muted fw-normal">Walk-in Customer</span>'}</div>
                                    <div class="text-muted small">${o.email || ''}</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="invoice-label">Payment Method</div>
                                    <div class="invoice-value">${o.payment_method ?? 'Cash'}</div>
                                </div>
                                <div class="col-sm-4 text-sm-end">
                                    <div class="invoice-label">Processed By</div>
                                    <div class="invoice-value">System Administrator</div>
                                </div>
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 py-3 border-0">Product Details</th>
                                            <th class="py-3 border-0 text-center">Qty</th>
                                            <th class="py-3 border-0 text-end">Unit Price</th>
                                            <th class="pe-3 py-3 border-0 text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;

                o.items.forEach(it => {
                    html += `
                        <tr>
                            <td class="ps-3 py-3 fw-bold text-dark">${it.product_name}</td>
                            <td class="text-center">${it.quantity}</td>
                            <td class="text-end">₱${Number(it.price).toFixed(2)}</td>
                            <td class="pe-3 text-end fw-bold">₱${Number(it.subtotal).toFixed(2)}</td>
                        </tr>
                    `;
                });

                html += `
                                    </tbody>
                                </table>
                            </div>

                            <div class="row justify-content-end">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded border border-warning border-opacity-25">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted small">Items Count:</span>
                                            <span class="fw-bold">${o.items.length}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted small">Subtotal:</span>
                                            <span class="fw-bold">₱${Number(o.total_amount).toFixed(2)}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-25">
                                            <span class="h5 fw-bold mb-0">Grand Total:</span>
                                            <span class="h4 fw-bold mb-0 text-primary">₱${Number(o.total_amount).toFixed(2)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-top text-center text-muted small">
                                <p class="mb-1">Thank you for your business!</p>
                                <p class="mb-0">This is an electronically generated invoice from <strong>KakaiOne Management System</strong>.</p>
                            </div>
                        </div>
                    </div>
                `;

                root.innerHTML = html;
            } catch (error) {
                console.error("Fetch error:", error);
                root.innerHTML = `<div class="alert alert-danger">Error loading order data. Please check connection.</div>`;
            }
        })();
    </script>

</body>

</html>