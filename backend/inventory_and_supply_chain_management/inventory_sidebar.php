<?php
include '../../SQL/config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT fname, lname, username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
} else {
    header('Location: login.php');
    exit();
}

$requestUri  = trim($_SERVER['REQUEST_URI'], '/');
$currentPage = basename(parse_url($requestUri, PHP_URL_PATH));

$userFullName = (isset($user['fname'], $user['lname']))
    ? htmlspecialchars($user['fname'] . ' ' . $user['lname'])
    : 'Guest';
$userLastName = isset($user['lname']) ? htmlspecialchars($user['lname']) : 'Guest';
$userInitials = (isset($user['fname'], $user['lname']))
    ? strtoupper(mb_substr($user['fname'], 0, 1) . mb_substr($user['lname'], 0, 1))
    : 'GU';

$stockPages = ['inventory_management.php','batch&expiry.php','batch_expiry.php','return_damage.php'];
$poPages    = ['purchase_order.php','admin_purchase_requests.php','department_request.php','purchase_request.php','order_receive.php','po_status_tracking.php'];
$assetPages = ['budget_request.php','asset_mapping.php','preventive_maintenance.php','maintenance.php','asset_transfer.php','audit_logs.php','vlogin.php'];

$isStockActive = in_array($currentPage, $stockPages);
$isPOActive    = in_array($currentPage, $poPages);
$isAssetActive = in_array($currentPage, $assetPages);

function navLink(string $href, string $label, string $currentPage): string {
    $active = ($currentPage === $href) ? 'nav-link--active' : '';
    return "<a href=\"{$href}\" class=\"nav-link {$active}\">{$label}</a>";
}
?>

<style>
/* ───────────────────────────────────────────────
   SIDEBAR — Professional & Fully Responsive
   ─────────────────────────────────────────────── */
:root {
    --sb-width:        250px;
    --sb-bg:           #ffffff;
    --sb-border:       #eef0f4;
    --sb-text:         #8892a4;
    --sb-text-dark:    #2d3748;
    --sb-accent:       #00acc1;
    --sb-accent-rgb:   0,172,193;
    --sb-accent-light: rgba(0,172,193,.08);
    --sb-accent-mid:   rgba(0,172,193,.15);
    --sb-danger:       #e05555;
    --sb-danger-light: rgba(224,85,85,.08);
    --sb-radius:       10px;
    --sb-shadow:       0 4px 24px rgba(0,0,0,.07);
    --sb-transition:   .22s cubic-bezier(.4,0,.2,1);
    --top-bar-h:       60px;
}

/* ── Reset for sidebar scope ── */
#sb-root *, #sb-root *::before, #sb-root *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Overlay (mobile) ── */
#sb-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(20,25,40,.45);
    z-index: 1100;
    backdrop-filter: blur(2px);
    animation: sbFadeIn .2s ease;
}
#sb-overlay.open { display: block; }
@keyframes sbFadeIn { from{opacity:0} to{opacity:1} }

/* ── Sidebar shell ── */
#sb-root .sidebar {
    position: fixed;
    top: 0; left: 0;
    width: var(--sb-width);
    height: 100vh;
    background: var(--sb-bg);
    border-right: 1px solid var(--sb-border);
    display: flex;
    flex-direction: column;
    z-index: 1200;
    transition: transform var(--sb-transition), box-shadow var(--sb-transition);
    overflow: hidden;
}

/* ── Logo area ── */
#sb-root .sb-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 22px 18px;
    border-bottom: 1px solid var(--sb-border);
    flex-shrink: 0;
}
#sb-root .sb-logo img {
    width: 38px;
    height: 38px;
    object-fit: contain;
    flex-shrink: 0;
}
#sb-root .sb-logo-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
#sb-root .sb-logo-name {
    font-family: 'Nunito', sans-serif;
    font-size: .82rem;
    font-weight: 800;
    color: var(--sb-text-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
#sb-root .sb-logo-sub {
    font-size: .68rem;
    color: var(--sb-accent);
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-top: 2px;
}

/* ── Nav scroll area ── */
#sb-root .sb-nav {
    flex: 1;
    overflow-y: auto;
    padding: 14px 0 20px;
}
#sb-root .sb-nav::-webkit-scrollbar { width: 4px; }
#sb-root .sb-nav::-webkit-scrollbar-thumb { background: #dde0e8; border-radius: 4px; }
#sb-root .sb-nav::-webkit-scrollbar-thumb:hover { background: #c5c9d4; }

/* ── Section label ── */
#sb-root .sb-section-label {
    font-size: .62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: #b0b8ca;
    padding: 12px 22px 6px;
}

/* ── Direct nav link (Dashboard) ── */
#sb-root .nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 22px;
    font-family: 'Nunito', sans-serif;
    font-size: .9rem;
    font-weight: 600;
    color: var(--sb-text);
    text-decoration: none;
    border-radius: 0;
    transition: color var(--sb-transition), background var(--sb-transition);
    position: relative;
}
#sb-root .nav-link:hover {
    color: var(--sb-accent);
    background: var(--sb-accent-light);
}
#sb-root .nav-link--active {
    color: var(--sb-accent);
    background: var(--sb-accent-light);
    font-weight: 700;
}
#sb-root .nav-link--active::before {
    content: '';
    position: absolute;
    left: 0; top: 6px; bottom: 6px;
    width: 3px;
    background: var(--sb-accent);
    border-radius: 0 3px 3px 0;
}

/* ── Dropdown group ── */
#sb-root .sb-group { margin-bottom: 2px; }

#sb-root .sb-group-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 22px;
    background: none;
    border: none;
    font-family: 'Nunito', sans-serif;
    font-size: .9rem;
    font-weight: 600;
    color: var(--sb-text);
    cursor: pointer;
    text-align: left;
    transition: color var(--sb-transition), background var(--sb-transition);
    position: relative;
}
#sb-root .sb-group-btn:hover {
    color: var(--sb-accent);
    background: var(--sb-accent-light);
}
#sb-root .sb-group-btn.open {
    color: var(--sb-accent);
    background: var(--sb-accent-light);
}
#sb-root .sb-group-btn .sb-caret {
    margin-left: auto;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform var(--sb-transition);
}
#sb-root .sb-group-btn .sb-caret svg {
    width: 12px; height: 12px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}
#sb-root .sb-group-btn.open .sb-caret { transform: rotate(180deg); }

/* ── Active indicator on group button ── */
#sb-root .sb-group-btn.group-active {
    color: var(--sb-accent);
}
#sb-root .sb-group-btn.group-active::before {
    content: '';
    position: absolute;
    left: 0; top: 6px; bottom: 6px;
    width: 3px;
    background: var(--sb-accent);
    border-radius: 0 3px 3px 0;
}

/* ── Dropdown items ── */
#sb-root .sb-dropdown {
    overflow: hidden;
    max-height: 0;
    transition: max-height .3s cubic-bezier(.4,0,.2,1), opacity .25s ease;
    opacity: 0;
}
#sb-root .sb-dropdown.open {
    max-height: 400px;
    opacity: 1;
}
#sb-root .sb-dropdown a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 22px 8px 46px;
    font-family: 'Nunito', sans-serif;
    font-size: .85rem;
    font-weight: 600;
    color: var(--sb-text);
    text-decoration: none;
    transition: color var(--sb-transition), background var(--sb-transition);
    position: relative;
}
#sb-root .sb-dropdown a::before {
    content: '';
    position: absolute;
    left: 31px;
    width: 5px; height: 5px;
    border-radius: 50%;
    background: #d0d5df;
    transition: background var(--sb-transition), transform var(--sb-transition);
}
#sb-root .sb-dropdown a:hover {
    color: var(--sb-accent);
    background: var(--sb-accent-light);
}
#sb-root .sb-dropdown a:hover::before { background: var(--sb-accent); transform: scale(1.4); }
#sb-root .sb-dropdown a.nav-link--active {
    color: var(--sb-accent);
    background: var(--sb-accent-light);
    font-weight: 700;
}
#sb-root .sb-dropdown a.nav-link--active::before {
    background: var(--sb-accent);
    transform: scale(1.4);
}

/* ── Icon wrapper ── */
#sb-root .sb-icon {
    width: 20px; height: 20px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    line-height: 1;
}

/* ── Divider ── */
#sb-root .sb-divider {
    height: 1px;
    background: var(--sb-border);
    margin: 10px 18px;
}

/* ── User profile footer ── */
#sb-root .sb-footer {
    border-top: 1px solid var(--sb-border);
    padding: 14px 18px;
    flex-shrink: 0;
}
#sb-root .sb-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: var(--sb-radius);
    cursor: pointer;
    transition: background var(--sb-transition);
    position: relative;
}
#sb-root .sb-user:hover { background: var(--sb-accent-light); }
#sb-root .sb-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--sb-accent), #0097a7);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: .03em;
}
#sb-root .sb-user-info { flex: 1; min-width: 0; }
#sb-root .sb-user-name {
    font-size: .85rem;
    font-weight: 700;
    color: var(--sb-text-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#sb-root .sb-user-role {
    font-size: .7rem;
    color: var(--sb-text);
    margin-top: 1px;
}
#sb-root .sb-user-actions {
    display: none;
    position: absolute;
    bottom: calc(100% + 8px);
    left: 0; right: 0;
    background: #fff;
    border: 1px solid var(--sb-border);
    border-radius: var(--sb-radius);
    box-shadow: 0 -4px 20px rgba(0,0,0,.1);
    overflow: hidden;
    z-index: 10;
    animation: sbPopUp .15s ease;
}
@keyframes sbPopUp { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
#sb-root .sb-user.open .sb-user-actions { display: block; }
#sb-root .sb-user-actions a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    font-size: .87rem;
    font-weight: 600;
    color: var(--sb-text);
    text-decoration: none;
    transition: background var(--sb-transition), color var(--sb-transition);
}
#sb-root .sb-user-actions a:hover { background: var(--sb-accent-light); color: var(--sb-accent); }
#sb-root .sb-user-actions .logout-link { color: var(--sb-danger); }
#sb-root .sb-user-actions .logout-link:hover { background: var(--sb-danger-light); color: #c0392b; }

/* ──────────────────────────────────────────────
   TOP BAR — Mobile hamburger + account button
   ────────────────────────────────────────────── */
#sb-topbar {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--top-bar-h);
    background: #fff;
    border-bottom: 1px solid var(--sb-border);
    z-index: 1050;
    align-items: center;
    padding: 0 16px;
    gap: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}
#sb-hamburger {
    width: 38px; height: 38px;
    border: 1.5px solid var(--sb-border);
    border-radius: 8px;
    background: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 5px;
    padding: 8px;
    transition: border-color var(--sb-transition), background var(--sb-transition);
    flex-shrink: 0;
}
#sb-hamburger:hover { border-color: var(--sb-accent); background: var(--sb-accent-light); }
#sb-hamburger span {
    display: block;
    width: 18px; height: 2px;
    background: var(--sb-text);
    border-radius: 2px;
    transition: all .25s ease;
}
#sb-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
#sb-hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
#sb-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

#sb-topbar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}
#sb-topbar-logo img { width: 30px; height: 30px; object-fit: contain; }
#sb-topbar-logo span {
    font-size: .88rem;
    font-weight: 800;
    color: var(--sb-text-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Floating account button — desktop only */
#sb-account-float {
    position: fixed;
    top: 12px; right: 20px;
    z-index: 1300;
}
.sb-account-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1px solid var(--sb-border);
    border-radius: 8px;
    padding: 8px 16px;
    cursor: pointer;
    font-family: 'Nunito', sans-serif;
    font-size: .88rem;
    font-weight: 600;
    color: var(--sb-text);
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    transition: all var(--sb-transition);
}
.sb-account-btn:hover {
    border-color: var(--sb-accent);
    color: var(--sb-accent);
    box-shadow: 0 4px 16px rgba(var(--sb-accent-rgb),.15);
}
.sb-account-btn .sb-btn-avatar {
    width: 26px; height: 26px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--sb-accent), #0097a7);
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; font-weight: 800; color: #fff;
    flex-shrink: 0;
}
.sb-account-btn .sb-caret2 {
    width: 10px; height: 10px;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg);
    margin-top: -3px;
    transition: transform var(--sb-transition);
}
.sb-account-btn.open .sb-caret2 { transform: rotate(-135deg); margin-top: 3px; }

.sb-account-menu {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 210px;
    background: #fff;
    border: 1px solid var(--sb-border);
    border-radius: var(--sb-radius);
    box-shadow: 0 8px 28px rgba(0,0,0,.12);
    overflow: hidden;
    z-index: 1400;
    animation: sbFadeIn .15s ease;
}
.sb-account-menu.open { display: block; }
.sb-account-menu .sb-menu-header {
    padding: 14px 18px;
    font-size: .83rem;
    color: var(--sb-text);
    border-bottom: 1px solid var(--sb-border);
    background: #fafbfc;
}
.sb-account-menu .sb-menu-header strong { color: var(--sb-accent); }
.sb-account-menu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 18px;
    font-size: .88rem;
    font-weight: 600;
    color: var(--sb-text);
    text-decoration: none;
    transition: background var(--sb-transition), color var(--sb-transition);
}
.sb-account-menu a:hover { background: var(--sb-accent-light); color: var(--sb-accent); }
.sb-account-menu a.sb-logout { border-top: 1px solid var(--sb-border); color: var(--sb-danger); }
.sb-account-menu a.sb-logout:hover { background: var(--sb-danger-light); color: #c0392b; }

/* ── Mobile account btn in topbar ── */
#sb-topbar-account {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    background: var(--sb-accent-light);
    border: 1.5px solid var(--sb-accent-mid);
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Nunito', sans-serif;
    font-size: .82rem;
    font-weight: 700;
    color: var(--sb-accent);
    position: relative;
    transition: background var(--sb-transition);
}
#sb-topbar-account:hover { background: var(--sb-accent-mid); }
#sb-topbar-account .tb-avatar {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--sb-accent), #0097a7);
    color: #fff;
    font-size: .65rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ── Responsive breakpoints ── */
@media (max-width: 1024px) {
    /* Sidebar starts hidden on tablet and mobile */
    #sb-root .sidebar {
        transform: translateX(-100%);
        box-shadow: none;
    }
    #sb-root .sidebar.open {
        transform: translateX(0);
        box-shadow: 4px 0 32px rgba(0,0,0,.15);
    }
    #sb-topbar { display: flex; }
    #sb-account-float { display: none; }
}

@media (min-width: 1025px) {
    /* Desktop: sidebar always visible, topbar hidden */
    #sb-root .sidebar { transform: translateX(0) !important; box-shadow: none; }
    #sb-overlay { display: none !important; }
    #sb-topbar { display: none; }
    #sb-account-float { display: block; }
}

/* NOTE: Individual pages handle their own margin-left: 250px on desktop.
   On mobile/tablet, pages should use margin-left: 0 and padding-top: 60px.
   Sidebar does NOT set body padding to avoid conflicts with existing pages. */
</style>

<!-- ═══════════════════════════════════════════
     MOBILE / TABLET TOP BAR
══════════════════════════════════════════════ -->
<div id="sb-topbar">
    <button id="sb-hamburger" aria-label="Toggle sidebar">
        <span></span><span></span><span></span>
    </button>

    <div id="sb-topbar-logo">
        <img src="assets/image/logo-dark.png" alt="Logo">
        <span>Inventory & Supply</span>
    </div>

    <!-- Mobile account button -->
    <div style="position:relative;">
        <button id="sb-tb-account" class="" aria-label="Account">
            <div class="tb-avatar" style="
                width:32px;height:32px;border-radius:50%;
                background:linear-gradient(135deg,#00acc1,#0097a7);
                color:#fff;font-size:.72rem;font-weight:800;
                display:flex;align-items:center;justify-content:center;
                border:none;cursor:pointer;font-family:Nunito,sans-serif;
            "><?= $userInitials ?></div>
        </button>
        <div id="sb-tb-menu" class="sb-account-menu" style="right:0;">
            <div class="sb-menu-header">Welcome, <strong><?= $userLastName ?>!</strong></div>
            <a href="../logout.php" class="sb-logout"
               onclick="return confirm('Are you sure you want to log out?');">
               🚪 Logout
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     OVERLAY
══════════════════════════════════════════════ -->
<div id="sb-overlay"></div>

<!-- ═══════════════════════════════════════════
     DESKTOP FLOATING ACCOUNT BUTTON
══════════════════════════════════════════════ -->
<div id="sb-account-float">
    <div style="position:relative;">
        <button class="sb-account-btn" id="sb-desk-btn">
            <span class="sb-btn-avatar"><?= $userInitials ?></span>
            <?= $userFullName ?>
            <span class="sb-caret2"></span>
        </button>
        <div class="sb-account-menu" id="sb-desk-menu">
            <div class="sb-menu-header">Welcome, <strong><?= $userLastName ?>!</strong></div>
            <a href="../logout.php" class="sb-logout"
               onclick="return confirm('Are you sure you want to log out?');">
               🚪 Logout
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════ -->
<div id="sb-root">
<aside class="sidebar" id="sb-sidebar">

    <!-- Logo -->
    <div class="sb-logo">
        <img src="assets/image/logo-dark.png" alt="Logo">
        <div class="sb-logo-text">
            <span class="sb-logo-name">HMS Inventory</span>
            <span class="sb-logo-sub">Supply Chain</span>
        </div>
    </div>

    <!-- Nav -->
    <nav class="sb-nav">

        <p class="sb-section-label">Main Menu</p>

        <!-- Dashboard -->
        <?= navLink('inventory_dashboard.php',
            '<span class="sb-icon">📊</span> Dashboard',
            $currentPage) ?>

        <div class="sb-divider"></div>
        <p class="sb-section-label">Management</p>

        <!-- Equipment & Medicine Stock -->
        <div class="sb-group">
            <button class="sb-group-btn <?= $isStockActive ? 'group-active open' : '' ?>"
                    data-target="sb-drop-stock">
                <span class="sb-icon">💊</span>
                Equipment & Medicine
                <span class="sb-caret">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </button>
            <div class="sb-dropdown <?= $isStockActive ? 'open' : '' ?>" id="sb-drop-stock">
                <?= navLink('inventory_management.php', 'Inventory & Stock Tracking', $currentPage) ?>
                <?= navLink('batch&expiry.php',         'Batch & Expiry Tracking',   $currentPage) ?>
                <?= navLink('return_damage.php',        'Return & Damage Handling',  $currentPage) ?>
            </div>
        </div>

        <!-- Purchase Order Processing -->
        <div class="sb-group">
            <button class="sb-group-btn <?= $isPOActive ? 'group-active open' : '' ?>"
                    data-target="sb-drop-po">
                <span class="sb-icon">🛒</span>
                Purchase Orders
                <span class="sb-caret">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </button>
            <div class="sb-dropdown <?= $isPOActive ? 'open' : '' ?>" id="sb-drop-po">
                <?= navLink('department_request.php', 'Department Request',          $currentPage) ?>
                <?= navLink('purchase_request.php',   'Purchase Request',            $currentPage) ?>
                <?= navLink('order_receive.php',      'Goods Receipt & Verification',$currentPage) ?>
            </div>
        </div>

        <!-- Asset Tracking -->
        <div class="sb-group">
            <button class="sb-group-btn <?= $isAssetActive ? 'group-active open' : '' ?>"
                    data-target="sb-drop-asset">
                <span class="sb-icon">🏥</span>
                Asset Tracking
                <span class="sb-caret">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </button>
            <div class="sb-dropdown <?= $isAssetActive ? 'open' : '' ?>" id="sb-drop-asset">
                <?= navLink('asset_mapping.php',   'Department Asset Mapping',  $currentPage) ?>
                <?= navLink('maintenance.php',     'Repair & Maintenance',      $currentPage) ?>
                <?= navLink('asset_transfer.php',  'Asset Transfer & Disposal', $currentPage) ?>
            </div>
        </div>

    </nav>

    <!-- Footer / User -->
    <div class="sb-footer">
        <div class="sb-user" id="sb-user-trigger">
            <div class="sb-avatar"><?= $userInitials ?></div>
            <div class="sb-user-info">
                <div class="sb-user-name"><?= $userFullName ?></div>
                <div class="sb-user-role">Inventory Staff</div>
            </div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="#b0b8ca" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
            </svg>
            <div class="sb-user-actions">
                <a href="../logout.php" class="logout-link"
                   onclick="return confirm('Are you sure you want to log out?');">
                   🚪 Logout
                </a>
            </div>
        </div>
    </div>

</aside>
</div><!-- /sb-root -->

<script>
(function() {
    const sidebar   = document.getElementById('sb-sidebar');
    const overlay   = document.getElementById('sb-overlay');
    const hamburger = document.getElementById('sb-hamburger');

    // ── Hamburger toggle ──
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        hamburger.classList.add('open');
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        hamburger.classList.remove('open');
    }
    hamburger.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);

    // ── Close sidebar on nav link click (mobile) ──
    sidebar.querySelectorAll('.nav-link, .sb-dropdown a').forEach(function(a) {
        a.addEventListener('click', function() {
            if (window.innerWidth <= 1024) closeSidebar();
        });
    });

    // ── Dropdown accordion ──
    document.querySelectorAll('.sb-group-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = btn.getAttribute('data-target');
            const dropdown = document.getElementById(targetId);
            const isOpen   = btn.classList.contains('open');

            // Close all
            document.querySelectorAll('.sb-group-btn').forEach(function(b) {
                b.classList.remove('open');
                const d = document.getElementById(b.getAttribute('data-target'));
                if (d) d.classList.remove('open');
            });

            // Toggle clicked
            if (!isOpen) {
                btn.classList.add('open');
                if (dropdown) dropdown.classList.add('open');
            }
        });
    });

    // ── Desktop account dropdown ──
    const deskBtn  = document.getElementById('sb-desk-btn');
    const deskMenu = document.getElementById('sb-desk-menu');
    if (deskBtn && deskMenu) {
        deskBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            deskBtn.classList.toggle('open');
            deskMenu.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            deskBtn.classList.remove('open');
            deskMenu.classList.remove('open');
        });
    }

    // ── Mobile topbar account dropdown ──
    const tbAccount = document.getElementById('sb-tb-account');
    const tbMenu    = document.getElementById('sb-tb-menu');
    if (tbAccount && tbMenu) {
        tbAccount.addEventListener('click', function(e) {
            e.stopPropagation();
            tbMenu.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            tbMenu.classList.remove('open');
        });
    }

    // ── Sidebar user footer popup ──
    const userTrigger = document.getElementById('sb-user-trigger');
    if (userTrigger) {
        userTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            userTrigger.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            userTrigger.classList.remove('open');
        });
    }
})();
</script>