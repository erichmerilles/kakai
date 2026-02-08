<div id="sidebar" class="d-flex flex-column">
    <div class="text-center mb-4">
        <img src="../assets/images/logo.jpg" alt="KakaiOne Logo" width="80" height="80" style="border-radius: 50%; margin-bottom:10px;">
        <h5 class="fw-bold text-light">KakaiOne</h5>
        <p class="small">Inventory Module</p>
    </div>
    
        <a href="../dashboard/admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
        <a href="ordering_module.php" class="nav-link link"><i class="bi bi-cart me-2"></i>Overview</a>
        <a href="order_create.php" class="nav-link"><i class="bi bi-plus-circle me-2"></i>Create Order</a>
        <a href="order_list.php" class="nav-link"><i class="bi bi-list-ul me-2"></i>Order List</a>

    <div class="mt-auto">
        <form action="../../backend/auth/logout.php" method="POST">
            <button class="btn btn-outline-light w-100 btn-sm mt-3">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </button>
        </form>
        <p class="text-center text-secondary small mt-3">© 2025 KakaiOne</p>
    </div>
</div>

<style>
  .nav-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: #fff !important;
    font-weight: bold;
    border-radius: 5px;
}

</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const currentPage = location.pathname.split('/').pop();

    document.querySelectorAll("#sidebar .nav-link").forEach(link => {
        const linkPage = link.getAttribute("href").split('/').pop();

        if (linkPage === currentPage) {
            link.classList.add("active");
        }
    });
});
</script>
