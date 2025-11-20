
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div id="sidebar" class="d-flex flex-column">
    <div class="text-center mb-4">
        <img src="../assets/images/logo.jpg" alt="KakaiOne Logo" width="80" height="80" style="border-radius: 50%; margin-bottom:10px;">
        <h5 class="fw-bold text-light">KakaiOne</h5>
        <p class="small">Inventory Module</p>
    </div>

        <a href="../dashboard/admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
        <a href="#" class="nav-link active" data-target="inventory-overview"><i class="bi bi-box-seam me-2"></i>Overview</a>
        <a href="#" class="nav-link" data-target="inventory-manage"><i class="bi bi-pencil-square me-2"></i>Manage Inventory</a>
        <a href="#" class="nav-link" data-target="inventory-movements"><i class="bi bi-arrow-left-right me-2"></i>Movements</a>
        <a href="#" class="nav-link" data-target="inventory-analytics"><i class="bi bi-bar-chart-line me-2"></i>Analytics</a>

        <div class="mt-auto">
            <form action="../../backend/auth/logout.php" method="POST">
                <button class="btn btn-outline-light w-100 btn-sm mt-3">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </button>
            </form>
            <p class="text-center text-secondary small mt-3">© 2025 KakaiOne</p>
        </div>
</div>

<div id="sidebar" class="d-flex flex-column">
    <div class="text-center mb-4">
        <img src="../assets/images/logo.jpg" alt="KakaiOne Logo" width="80" height="80" style="border-radius: 50%; margin-bottom:10px;">
        <h5 class="fw-bold text-light">KakaiOne</h5>
        <p class="small">Inventory Module</p>
    </div>

    <a href="../dashboard/admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    
    <a href="#" class="nav-link active" data-target="inventory-overview"><i class="bi bi-box-seam me-2"></i>Overview</a>
    <a href="#" class="nav-link" data-target="inventory-manage"><i class="bi bi-pencil-square me-2"></i>Manage Inventory</a>
    <a href="#" class="nav-link" data-target="inventory-movements"><i class="bi bi-arrow-left-right me-2"></i>Movements</a>
    <a href="#" class="nav-link" data-target="inventory-analytics"><i class="bi bi-bar-chart-line me-2"></i>Analytics</a>

    <div class="mt-auto">
        <form action="../../backend/auth/logout.php" method="POST">
            <button class="btn btn-outline-light w-100 btn-sm mt-3">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </button>
        </form>
        <p class="text-center text-secondary small mt-3">© 2025 KakaiOne</p>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const internalLinks = document.querySelectorAll("#sidebar .nav-link[data-target]");
    
    function handleNavClick(e) {
        e.preventDefault();
        const targetId = e.currentTarget.getAttribute('data-target');
        
        internalLinks.forEach(link => link.classList.remove("active"));
        e.currentTarget.classList.add("active");

        window.dispatchEvent(new CustomEvent('inventory-nav-change', { detail: { targetId } }));
    }

    internalLinks.forEach(link => {
        link.addEventListener('click', handleNavClick);
    });
    
    const initialTarget = window.location.hash.substring(1) || 'inventory-overview';
    const initialLink = document.querySelector(`#sidebar .nav-link[data-target="${initialTarget}"]`);
    if(initialLink) {
        internalLinks.forEach(link => link.classList.remove("active"));
        initialLink.classList.add("active");
    }
});
</script>

<style>
/* Page-specific */
.module-card h6 {
    color: #4b2c06;
}
.btn-pri {
    background: linear-gradient(90deg, #4b2c06, #a8742a);
    color: #fff;
}
.btn-pri:hover {
    opacity: 0.9;
}
</style>