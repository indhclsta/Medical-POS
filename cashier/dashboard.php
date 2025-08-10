<?php  
require_once '../service/connection.php';
session_start();
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../service/login.php");
    exit();
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Validate session
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../service/login.php");
    exit;
}

if (isset($_SESSION['ip_address']) && isset($_SESSION['user_agent'])) {
    if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        header("Location: ../service/login.php");
        exit;
    }
}

// Ambil data penjualan hari ini
$today_sales = 0;
$today_transactions = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total 
                           FROM transactions 
                           WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $today_transactions = $row['count'];
        $today_sales = $row['total'] ?? 0;
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Helper untuk menandai menu aktif
function is_active($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Kasir - MediPOS</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    :root{
        --purple-dark: #5B21B6; /* primary fresh purple */
        --purple-soft: #A78BFA; /* accent */
        --muted: #6B7280; 
        --bg: #F8FAFC; 
        --card: #FFFFFF;
    }

    html,body{height:100%;}

    /* Sidebar */
    .sidebar{
        background-color: var(--purple-dark);
        color: white;
        min-height:100vh;
        position:relative;
        overflow:hidden;
    }

    .sidebar::before{
        content: '';
        position:absolute;
        inset:0;
        background-image: linear-gradient(135deg, rgba(255,255,255,0.02) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.02) 50%, rgba(255,255,255,0.02) 75%, transparent 75%, transparent);
        background-size: 16px 16px;
        pointer-events: none;
    }

    .brand {
        padding: 20px 18px;
        display:flex;
        gap:10px;
        align-items:center;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .brand h1{font-size:1.25rem; font-weight:700; color: #FDF4FF; margin:0}
    .brand .logo-badge{background: rgba(255,255,255,0.06); padding:8px; border-radius:8px; display:flex; align-items:center; justify-content:center}

    /* profile box */
    .profile-box{margin:14px; background: rgba(255,255,255,0.04); padding:10px; border-radius:10px; display:flex; gap:12px; align-items:center}
    .profile-box img{width:44px;height:44px;border-radius:8px; object-fit:cover; border:2px solid rgba(255,255,255,0.06)}
    .profile-box .meta{font-size:0.92rem}
    .profile-box .meta p{margin:0}
    .profile-box .meta .role{color: rgba(255,255,255,0.75); font-size:0.8rem}

    /* menu items */
    .menu a{
        display:flex; align-items:center; gap:10px; padding:10px 14px; margin:6px 10px; border-radius:8px; color: #F8F7FF; text-decoration:none; font-weight:500; transition: all .18s ease;
        border-left: 3px solid transparent;
    }
    .menu a:hover{
        background-color: rgba(255,255,255,0.06);
        backdrop-filter: blur(2px);
        transform: translateX(4px);
        box-shadow: 0 6px 18px rgba(8,7,14,0.06);
    }
    .menu a.active{
        background-color: rgba(167,139,250,0.16);
        color: #FFF;
        border-left: 4px solid var(--purple-soft);
        transform:none;
    }

    /* collapse button */
    .collapse-btn{
        position:absolute;
        right: -10px; /* fixed supaya tidak terpotong */
        top:18px;
        background:var(--purple-dark);
        border-radius:12px;
        width:36px;
        height:36px;
        display:flex;
        align-items:center;
        justify-content:center;
        box-shadow:0 8px 18px rgba(12,11,20,0.12);
        border: 2px solid rgba(255,255,255,0.04);
        z-index: 10;
    }

    /* main area */
    .main-area{background:var(--bg); min-height:100vh}
    header.app-header{background:transparent; padding:18px; display:flex; align-items:center; justify-content:space-between; gap:12px}

    /* stat card style */
    .stat-card{background:var(--card); border-radius:12px; padding:18px; box-shadow: 0 6px 18px rgba(13,12,20,0.06); border:1px solid rgba(16,15,20,0.03)}
    .stat-card .title{color:var(--muted)}
    .stat-card .value{font-weight:700; font-size:1.5rem; color:#111827}
    .stat-card.accent-left{border-left:6px solid var(--purple-soft)}

    /* quick actions */
    .quick-action{background:var(--card); border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:8px; align-items:center; justify-content:center; transition: transform .12s ease, box-shadow .12s ease}
    .quick-action:hover{transform: translateY(-6px); box-shadow: 0 10px 30px rgba(12,11,20,0.08)}

    .table thead th{font-size:0.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.04em}
    .table tbody tr:hover{background: white}

    /* responsive tweak */
    .sidebar.collapsed{width:72px}
    .sidebar.collapsed .brand h1{display:none}
    .sidebar.collapsed .profile-box,.sidebar.collapsed .menu a span.label{display:none}
    .sidebar.collapsed .menu a{justify-content:center}

    @media (max-width:900px){
        .sidebar{position:fixed; z-index:40; width:260px}
        .sidebar.collapsed{transform: translateX(-120%)}
        .collapse-btn{display:none}
    }
</style>
</head>
<body class="font-sans text-sm">
<div id="app" class="flex">
    <!-- SIDEBAR -->
    <aside id="sidebar" class="sidebar w-64">
        <div class="brand">
            <div class="logo-badge"><i class="fas fa-clinic-medical text-white"></i></div>
            <div>
                <h1>MediPOS</h1>
                <div style="font-size:12px;color:rgba(255,255,255,0.85)">Kasir • Sistem</div>
            </div>
        </div>

        <div class="profile-box">
            <img src="https://avatars.dicebear.com/api/initials/<?= urlencode(substr($_SESSION['username'],0,2)) ?>.svg" alt="profile">
            <div class="meta">
                <p style="font-weight:600"><?= htmlspecialchars($_SESSION['username']) ?></p>
                <p class="role">Kasir • <?= htmlspecialchars($_SESSION['email']) ?></p>
            </div>
        </div>

        <nav class="menu px-2">
            <a href="dashboard.php" class="<?= is_active('dashboard.php') ?>"><span class="icon"><i class="fas fa-tachometer-alt"></i></span><span class="label">Dashboard</span></a>
            <a href="transaksi.php" class="<?= is_active('transaksi.php') ?>"><span class="icon"><i class="fas fa-cash-register"></i></span><span class="label">Transaksi Baru</span></a>
            <a href="daftar_transaksi.php" class="<?= is_active('daftar_transaksi.php') ?>"><span class="icon"><i class="fas fa-list"></i></span><span class="label">Daftar Transaksi</span></a>
            <a href="produk.php" class="<?= is_active('produk.php') ?>"><span class="icon"><i class="fas fa-boxes"></i></span><span class="label">Kelola Produk</span></a>
            <hr style="border-color: rgba(255,255,255,0.06); margin:12px 8px">
            <a href="laporan_harian.php" class="<?= is_active('laporan_harian.php') ?>"><span class="icon"><i class="fas fa-chart-line"></i></span><span class="label">Laporan</span></a>
            <a href="../service/logout.php"><span class="icon"><i class="fas fa-sign-out-alt"></i></span><span class="label">Logout</span></a>
        </nav>

        <button id="collapseBtn" class="collapse-btn" title="Toggle sidebar">
            <i class="fas fa-chevron-left" style="color: #fff"></i>
        </button>
    </aside>

    <!-- MAIN AREA -->
    <div class="main-area flex-1 min-h-screen">
        <header class="app-header">
            <div class="flex items-center gap-4">
                <button id="mobileOpen" class="md:hidden p-2 rounded bg-white shadow" title="Open menu"><i class="fas fa-bars"></i></button>
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Dashboard</h2>
                    <div style="font-size:12px;color:var(--muted)"><?= date('l, d F Y') ?></div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button id="focusBtn" class="p-2 rounded bg-white shadow text-sm">Focus mode</button>
                <div class="relative">
                    <button id="notifBtn" class="p-2 rounded bg-white shadow"><i class="fas fa-bell"></i></button>
                </div>
                <div class="flex items-center gap-3 p-2 rounded bg-white shadow">
                    <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,var(--purple-soft),#7C3AED); display:flex; align-items:center; justify-content:center; color:white"><i class="fas fa-user"></i></div>
                    <div style="font-size:13px">
                        <div style="font-weight:600"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div style="font-size:11px;color:var(--muted)">Kasir</div>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-6">
            <!-- stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stat-card accent-left">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="title">Penjualan Hari Ini</div>
                            <div class="value mt-2"><?= format_currency($today_sales) ?></div>
                        </div>
                        <div class="p-3 rounded-lg bg-purple-50 text-purple-700"><i class="fas fa-wallet fa-lg"></i></div>
                    </div>
                </div>
                <div class="stat-card accent-left" style="border-left-color:#60A5FA">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="title">Transaksi Hari Ini</div>
                            <div class="value mt-2"><?= $today_transactions ?></div>
                        </div>
                        <div class="p-3 rounded-lg bg-blue-50 text-blue-600"><i class="fas fa-receipt fa-lg"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="title">Produk Tersedia</div>
                            <div class="value mt-2">142</div>
                        </div>
                        <div class="p-3 rounded-lg bg-purple-50 text-purple-700"><i class="fas fa-box-open fa-lg"></i></div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <section class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-md font-semibold">Quick Actions</h3>
                    <div style="font-size:13px;color:var(--muted)">Shortcut: <kbd class="rounded px-2 py-1 bg-white shadow">/</kbd></div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <a href="transaksi_baru.php" class="quick-action">
                        <div class="p-3 rounded bg-purple-50 text-purple-700"><i class="fas fa-cash-register fa-2x"></i></div>
                        <div style="font-weight:600">Transaksi Baru</div>
                        <div style="font-size:12px;color:var(--muted)">Cepat buat transaksi</div>
                    </a>
                    <a href="produk.php" class="quick-action">
                        <div class="p-3 rounded bg-gray-100 text-gray-800"><i class="fas fa-search fa-2x"></i></div>
                        <div style="font-weight:600">Cari Produk</div>
                        <div style="font-size:12px;color:var(--muted)">Cari berdasarkan nama / barcode</div>
                    </a>
                    <a href="daftar_transaksi.php" class="quick-action">
                        <div class="p-3 rounded bg-gray-100 text-gray-800"><i class="fas fa-history fa-2x"></i></div>
                        <div style="font-weight:600">Riwayat</div>
                        <div style="font-size:12px;color:var(--muted)">Lihat transaksi terdahulu</div>
                    </a>
                    <a href="laporan_harian.php" class="quick-action">
                        <div class="p-3 rounded bg-gray-100 text-gray-800"><i class="fas fa-chart-line fa-2x"></i></div>
                        <div style="font-weight:600">Laporan</div>
                        <div style="font-size:12px;color:var(--muted)">Ringkasan penjualan</div>
                    </a>
                </div>
            </section>

            <!-- Recent Transactions -->
            <section class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold">Transaksi Terakhir</h4>
                    <a href="daftar_transaksi.php" class="text-sm text-purple-600">Lihat semua</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">Waktu</th>
                                <th class="px-4 py-3 text-left">Items</th>
                                <th class="px-4 py-3 text-left">Total</th>
                                <th class="px-4 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b">
                                <td class="px-4 py-3">#TRX-20230801-001</td>
                                <td class="px-4 py-3">10:25 AM</td>
                                <td class="px-4 py-3">3 Items</td>
                                <td class="px-4 py-3">Rp 125.000</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs" style="background:#ECFDF5;color:#065F46">Selesai</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>
<script>
(function(){
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseBtn');
    const focusBtn = document.getElementById('focusBtn');
    const mobileOpen = document.getElementById('mobileOpen');

    // helper: toggle icon on collapse button
    function setCollapseIcon(isCollapsed){
        if(!collapseBtn) return;
        const icon = collapseBtn.querySelector('i');
        if(!icon) return;
        // prefer chevrons pointing to the action: when collapsed (sidebar small) show chevron-right to open
        icon.classList.remove('fa-chevron-left', 'fa-chevron-right');
        icon.classList.add(isCollapsed ? 'fa-chevron-right' : 'fa-chevron-left');
        collapseBtn.setAttribute('aria-expanded', String(!isCollapsed));
        collapseBtn.title = isCollapsed ? 'Buka sidebar' : 'Tutup sidebar';
    }

    // safe guards
    if(collapseBtn){
        // initialize state from class
        setCollapseIcon(sidebar && sidebar.classList.contains('collapsed'));

        collapseBtn.addEventListener('click', ()=>{
            if(!sidebar) return;
            const isCollapsed = sidebar.classList.toggle('collapsed');
            // update icon accordingly (true -> collapsed)
            setCollapseIcon(isCollapsed);
            // ensure collapse button sits above (in case of stacking)
            collapseBtn.style.zIndex = 20;
        });
    }

    // focus mode toggler
    if(focusBtn){
        focusBtn.addEventListener('click', ()=>{
            const body = document.body;
            if(body.classList.contains('focus-mode')){
                body.classList.remove('focus-mode');
                focusBtn.textContent = 'Focus mode';
                focusBtn.setAttribute('aria-pressed', 'false');
            }else{
                body.classList.add('focus-mode');
                focusBtn.textContent = 'Exit focus';
                focusBtn.setAttribute('aria-pressed', 'true');
            }
        });
    }

    // Mobile menu toggle (small screens)
    if(mobileOpen && sidebar){
        mobileOpen.addEventListener('click', ()=>{
            // on mobile we use the same collapsed class to hide/show
            sidebar.classList.toggle('collapsed');
            // keep collapse icon in sync if visible
            setCollapseIcon(sidebar.classList.contains('collapsed'));
        });

        // close sidebar when clicking outside on mobile
        document.addEventListener('click', (e)=>{
            const isMobile = window.innerWidth <= 900;
            if(!isMobile) return;
            if(!sidebar.classList.contains('collapsed') && !sidebar.contains(e.target) && !mobileOpen.contains(e.target)){
                sidebar.classList.add('collapsed');
                setCollapseIcon(true);
            }
        });
    }

    // keyboard quick open (/) — skip if user typing in input or textarea
    document.addEventListener('keydown', (e)=>{
        if(e.key === '/'){
            const active = document.activeElement;
            const typing = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable);
            if(typing) return;
            e.preventDefault();
            // for now toggle quick search or focus first quick action; fallback: toggle sidebar on small screens
            // TODO: hook this to a real search modal
            if(window.innerWidth <= 900 && sidebar){
                sidebar.classList.toggle('collapsed');
                setCollapseIcon(sidebar.classList.contains('collapsed'));
            } else {
                // simple visual cue until search implemented
                alert('Shortcut: buka pencarian produk (belum diimplementasi)');
            }
        }
    });

    // keep icon state in sync on resize (so desktop/mobile transitions are OK)
    window.addEventListener('resize', ()=>{
        if(!sidebar || !collapseBtn) return;
        // if screen expanded to desktop and sidebar was hidden because of mobile, ensure proper classes (optional)
        // we won't force open/close, just sync icon
        setCollapseIcon(sidebar.classList.contains('collapsed'));
    });

})();
(function(){
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseBtn');
    const focusBtn = document.getElementById('focusBtn');
    const mobileOpen = document.getElementById('mobileOpen');

    // Collapse sidebar on desktop
    collapseBtn.addEventListener('click', () => {
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-20');
    });

    // Toggle focus mode
    focusBtn.addEventListener('click', () => {
        document.body.classList.toggle('focus-mode');
    });

    // Mobile sidebar open
    mobileOpen.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
    });
})();
</script>

</body>
</html>