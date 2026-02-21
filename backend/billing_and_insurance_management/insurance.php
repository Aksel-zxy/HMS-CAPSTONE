<?php
include '../../SQL/config.php';

/* COLOR MAP PER INSURANCE COMPANY */
function cardGradient($company) {
    return match ($company) {
        'PhilHealth'  => ['#0d2b6e', '#1a56c4', '#4a90d9'],
        'Maxicare'    => ['#0a6b5e', '#0f9b8e', '#38ef7d'],
        'Medicard'    => ['#4a0080', '#8e2de2', '#c470f0'],
        'Intellicare' => ['#b35a00', '#f7971e', '#ffd200'],
        default       => ['#1a1a2e', '#16213e', '#0f3460'],
    };
}

function cardAccent($company) {
    return match ($company) {
        'PhilHealth'  => 'rgba(74,144,217,.35)',
        'Maxicare'    => 'rgba(56,239,125,.3)',
        'Medicard'    => 'rgba(196,112,240,.35)',
        'Intellicare' => 'rgba(255,210,0,.35)',
        default       => 'rgba(255,255,255,.12)',
    };
}

$list = $conn->query("SELECT * FROM patient_insurance ORDER BY created_at DESC");
$rows = [];
while ($row = $list->fetch_assoc()) $rows[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Patient Insurance — HMS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
/* ── Tokens ── */
:root {
  --sidebar-w:  250px;
  --sidebar-w-sm: 200px;
  --navy:       #0b1d3a;
  --ink:        #1e293b;
  --ink-light:  #64748b;
  --border:     #e2e8f0;
  --surface:    #f1f5f9;
  --card:       #ffffff;
  --accent:     #2563eb;
  --accent-2:   #0ea5e9;
  --radius:     14px;
  --shadow:     0 2px 20px rgba(11,29,58,.08);
  --shadow-lg:  0 8px 40px rgba(11,29,58,.16);
  --ff-head:    'DM Serif Display', serif;
  --ff-body:    'DM Sans', sans-serif;
  --transition: .3s ease-in-out;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--ff-body);
  background: var(--surface);
  color: var(--ink);
  /* billing_sidebar.php sets body { margin:0 } — we keep it zero here too.
     The sidebar is position:fixed, so we shift only the content wrapper. */
  margin: 0;
  min-height: 100vh;
}

/* ── Content Wrapper ── */
/* This is what shifts right to avoid the fixed sidebar */
.cw {
  margin-left: var(--sidebar-w);
  padding: 60px 32px 60px;
  max-width: 1400px;
  transition: margin-left var(--transition);
}

/* Mirror the sidebar .closed state → shrink the content offset */
.cw.sidebar-collapsed {
  margin-left: 0;
}

/* ── Page Header ── */
.page-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}
.page-head-icon {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1.4rem;
  box-shadow: 0 6px 18px rgba(37,99,235,.35);
  flex-shrink: 0;
}
.page-head h1 {
  font-family: var(--ff-head);
  font-size: clamp(1.3rem, 3vw, 1.9rem);
  color: var(--navy);
  margin: 0;
  line-height: 1.1;
}
.page-head p {
  font-size: .83rem;
  color: var(--ink-light);
  margin: 3px 0 0;
}
.count-chip {
  margin-left: auto;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 6px 16px;
  font-size: .8rem;
  font-weight: 600;
  color: var(--ink-light);
  box-shadow: var(--shadow);
  white-space: nowrap;
}

/* ── Search Bar ── */
.search-bar-wrap {
  margin-bottom: 20px;
  position: relative;
  max-width: 360px;
}
.search-bar-wrap i {
  position: absolute;
  left: 14px; top: 50%;
  transform: translateY(-50%);
  color: var(--ink-light);
  font-size: .95rem;
  pointer-events: none;
}
.search-bar {
  width: 100%;
  padding: 10px 14px 10px 38px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-family: var(--ff-body);
  font-size: .88rem;
  color: var(--ink);
  background: var(--card);
  transition: border-color .2s, box-shadow .2s;
  outline: none;
}
.search-bar:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

/* ── Table Card ── */
.table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}

/* ── Table ── */
.ins-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .87rem;
}
.ins-table thead th {
  background: var(--navy);
  color: rgba(255,255,255,.75);
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .65px;
  padding: 13px 16px;
  white-space: nowrap;
  text-align: left;
}
.ins-table thead th:last-child { text-align: center; }

.ins-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .13s;
}
.ins-table tbody tr:last-child { border-bottom: none; }
.ins-table tbody tr:hover { background: #f7faff; }

.ins-table tbody td {
  padding: 13px 16px;
  vertical-align: middle;
  color: var(--ink);
}
.ins-table tbody td:last-child { text-align: center; }

/* Name cell */
.name-cell { display: flex; align-items: center; gap: 10px; }
.avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .78rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}
.name-main  { font-weight: 600; color: var(--navy); font-size: .88rem; }
.name-sub   { font-size: .73rem; color: var(--ink-light); }

/* Company badge */
.co-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: .74rem;
  font-weight: 700;
  white-space: nowrap;
}
.co-philhealth  { background: #dbeafe; color: #1d4ed8; }
.co-maxicare    { background: #d1fae5; color: #065f46; }
.co-medicard    { background: #ede9fe; color: #5b21b6; }
.co-intellicare { background: #fef3c7; color: #92400e; }
.co-default     { background: #f1f5f9; color: #475569; }

/* Status badge */
.status-active   { background: #d1fae5; color: #065f46; border-radius: 999px; padding: 3px 10px; font-size: .73rem; font-weight: 700; }
.status-inactive { background: #fee2e2; color: #991b1b; border-radius: 999px; padding: 3px 10px; font-size: .73rem; font-weight: 700; }
.status-pending  { background: #fef3c7; color: #92400e; border-radius: 999px; padding: 3px 10px; font-size: .73rem; font-weight: 700; }

/* Mono text */
.mono { font-family: 'Courier New', monospace; font-size: .82rem; color: var(--ink-light); letter-spacing: .3px; }

/* Discount */
.discount-val { font-weight: 600; color: #059669; }

/* View button */
.btn-view {
  background: #eff6ff;
  color: var(--accent);
  border: 1.5px solid #bfdbfe;
  border-radius: 8px;
  padding: 5px 14px;
  font-size: .8rem;
  font-weight: 700;
  font-family: var(--ff-body);
  cursor: pointer;
  transition: all .15s;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
}
.btn-view:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

/* Empty state */
.empty-row td {
  text-align: center;
  padding: 48px 16px;
  color: var(--ink-light);
}
.empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: .4; }

/* ── Mobile Card View ── */
.mobile-cards { display: none; }
.m-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px;
  margin-bottom: 12px;
  animation: fadeUp .3s ease both;
}
.m-card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
  gap: 10px;
}
.m-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
  font-size: .83rem;
  gap: 8px;
}
.m-row:last-child { border-bottom: none; }
.m-label { color: var(--ink-light); font-weight: 600; font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.m-val   { font-weight: 500; color: var(--ink); text-align: right; }
.m-actions { margin-top: 12px; }

/* ── Insurance Card (Modal) ── */
.ins-card-wrap {
  perspective: 1000px;
  display: flex;
  justify-content: center;
  padding: 8px 0 16px;
}
.ins-card {
  width: 340px;
  max-width: 100%;
  height: 210px;
  border-radius: 20px;
  padding: 20px 22px;
  color: #fff;
  position: relative;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.4);
  transform-style: preserve-3d;
  transition: transform .4s ease;
}
.ins-card:hover { transform: rotateY(4deg) rotateX(-2deg) scale(1.02); }

.ins-card::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(255,255,255,.08);
}
.ins-card::after {
  content: '';
  position: absolute;
  bottom: -80px; left: -40px;
  width: 220px; height: 220px;
  border-radius: 50%;
  background: rgba(255,255,255,.05);
}

.ins-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 14px;
  position: relative;
  z-index: 1;
}
.ins-company {
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  opacity: .8;
}
.ins-logo-dot { display: flex; gap: 4px; }
.ins-logo-dot span {
  width: 20px; height: 20px;
  border-radius: 50%;
  opacity: .7;
}

.chip-svg {
  width: 42px; height: 32px;
  background: linear-gradient(135deg, #d4a017, #f5d060, #c8860e);
  border-radius: 6px;
  margin-bottom: 12px;
  position: relative;
  z-index: 1;
  box-shadow: 0 2px 8px rgba(0,0,0,.3);
  display: flex;
  align-items: center;
  justify-content: center;
}
.chip-svg::after {
  content: '';
  position: absolute;
  width: 60%; height: 60%;
  border: 1.5px solid rgba(0,0,0,.2);
  border-radius: 3px;
}

.ins-number {
  font-family: 'Courier New', monospace;
  font-size: 1rem;
  letter-spacing: 2.5px;
  margin-bottom: 14px;
  position: relative;
  z-index: 1;
  text-shadow: 0 1px 4px rgba(0,0,0,.3);
}

.ins-card-bottom {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}
.ins-holder-label {
  font-size: .6rem;
  letter-spacing: .8px;
  text-transform: uppercase;
  opacity: .6;
  margin-bottom: 3px;
}
.ins-holder-name {
  font-family: var(--ff-head);
  font-size: 1rem;
  letter-spacing: .5px;
  line-height: 1.1;
}
.ins-promo { text-align: right; font-size: .72rem; opacity: .7; }
.ins-discount { font-size: .88rem; font-weight: 700; opacity: 1; }

/* Card detail pills */
.card-pills {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 16px;
}
.card-pill {
  background: #f1f5f9;
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 5px 14px;
  font-size: .78rem;
  color: var(--ink-light);
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 5px;
}
.card-pill strong { color: var(--ink); }

/* ── Modal ── */
.modal-content { border-radius: var(--radius); border: none; box-shadow: var(--shadow-lg); overflow: hidden; }
.modal-header  {
  background: var(--navy);
  color: #fff;
  padding: 16px 22px;
  border-bottom: none;
}
.modal-header .modal-title { font-family: var(--ff-head); font-size: 1.05rem; }
.modal-header .btn-close   { filter: invert(1); }
.modal-body { padding: 24px 20px; background: #f8fafc; }

/* ── Animations ── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Top spacing so content doesn't hide under sidebar toggle button ── */
.cw { padding-top: 56px; }

/* ── Responsive: match billing_sidebar.php breakpoints ── */

/* Medium screens — sidebar narrows to 200px */
@media (max-width: 768px) {
  body { margin-left: var(--sidebar-w-sm); }
  body.sidebar-closed { margin-left: 0; }
  .cw { padding: 56px 16px 50px; }
  .table-card  { display: none; }
  .mobile-cards { display: block; }
  .search-bar-wrap { max-width: 100%; }
  .count-chip { display: none; }
  .page-head h1 { font-size: 1.3rem; }
  .page-head-icon { width: 44px; height: 44px; font-size: 1.2rem; border-radius: 11px; }
}

/* Small screens — sidebar overlays (no margin needed) */
@media (max-width: 480px) {
  body { margin-left: 0; }
  body.sidebar-closed { margin-left: 0; }
  .cw { padding: 52px 10px 40px; }
  .ins-card { height: auto; min-height: 200px; }
  .page-head { gap: 10px; }
  .page-head-icon { width: 40px; height: 40px; font-size: 1.1rem; border-radius: 10px; }
}

/* Landscape mobile */
@media (max-width: 812px) and (orientation: landscape) {
  .cw { padding: 52px 20px 40px; }
  .ins-card { height: 190px; }
}

/* Safe area for notched devices */
@supports (padding: env(safe-area-inset-bottom)) {
  .cw { padding-bottom: calc(60px + env(safe-area-inset-bottom)); }
}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<div class="cw">

  <!-- Page Header -->
  <div class="page-head">
    <div class="page-head-icon"><i class="bi bi-shield-check"></i></div>
    <div>
      <h1>Patient Insurance</h1>
      <p>Manage and view all patient insurance records</p>
    </div>
    <span class="count-chip">
      <i class="bi bi-people me-1"></i><?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?>
    </span>
  </div>

  <!-- Search -->
  <div class="search-bar-wrap">
    <i class="bi bi-search"></i>
    <input type="text" class="search-bar" id="searchInput" placeholder="Search name, company, insurance #…">
  </div>

  <!-- ── Desktop Table ── -->
  <div class="table-card">
    <div style="overflow-x:auto;">
      <table class="ins-table" id="insTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Patient</th>
            <th>Company</th>
            <th>Insurance #</th>
            <th>Promo</th>
            <th>Discount</th>
            <th>Relation</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr class="empty-row">
              <td colspan="9"><i class="bi bi-inbox"></i>No insurance records found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $i => $row):
              $colors  = cardGradient($row['insurance_company']);
              $initials = strtoupper(substr($row['full_name'], 0, 1));
              $coClass = match($row['insurance_company']) {
                'PhilHealth'  => 'co-philhealth',
                'Maxicare'    => 'co-maxicare',
                'Medicard'    => 'co-medicard',
                'Intellicare' => 'co-intellicare',
                default       => 'co-default',
              };
              $statusClass = match(strtolower($row['status'] ?? 'active')) {
                'active'   => 'status-active',
                'inactive' => 'status-inactive',
                default    => 'status-pending',
              };
              $discount = $row['discount_type'] === 'Percentage'
                ? htmlspecialchars($row['discount_value']) . '%'
                : '₱' . number_format($row['discount_value'], 2);
              $modalId = 'modal_' . intval($row['patient_insurance_id']);
            ?>
            <tr class="ins-row">
              <td style="color:var(--ink-light);font-size:.8rem;"><?= $i + 1 ?></td>
              <td>
                <div class="name-cell">
                  <div class="avatar" style="background:linear-gradient(135deg,<?= $colors[0] ?>,<?= $colors[1] ?>);">
                    <?= $initials ?>
                  </div>
                  <div>
                    <div class="name-main"><?= htmlspecialchars($row['full_name']) ?></div>
                    <div class="name-sub"><?= htmlspecialchars($row['relationship_to_insured']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="co-badge <?= $coClass ?>">
                  <?= htmlspecialchars($row['insurance_company']) ?>
                </span>
              </td>
              <td><span class="mono"><?= htmlspecialchars($row['insurance_number']) ?></span></td>
              <td><?= htmlspecialchars($row['promo_name']) ?></td>
              <td><span class="discount-val"><?= $discount ?></span></td>
              <td style="color:var(--ink-light);font-size:.83rem;"><?= htmlspecialchars($row['relationship_to_insured']) ?></td>
              <td><span class="<?= $statusClass ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span></td>
              <td>
                <button class="btn-view" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
                  <i class="bi bi-credit-card-2-front"></i> View Card
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Mobile Cards ── -->
  <div class="mobile-cards" id="mobileCards">
    <?php foreach ($rows as $i => $row):
      $colors      = cardGradient($row['insurance_company']);
      $coClass     = match($row['insurance_company']) {
        'PhilHealth'  => 'co-philhealth',
        'Maxicare'    => 'co-maxicare',
        'Medicard'    => 'co-medicard',
        'Intellicare' => 'co-intellicare',
        default       => 'co-default',
      };
      $statusClass = match(strtolower($row['status'] ?? 'active')) {
        'active'   => 'status-active',
        'inactive' => 'status-inactive',
        default    => 'status-pending',
      };
      $discount = $row['discount_type'] === 'Percentage'
        ? htmlspecialchars($row['discount_value']) . '%'
        : '₱' . number_format($row['discount_value'], 2);
      $modalId  = 'modal_' . intval($row['patient_insurance_id']);
    ?>
    <div class="m-card mobile-row" style="animation-delay:<?= $i * 0.05 ?>s">
      <div class="m-card-head">
        <div>
          <div class="name-main" style="font-size:.95rem;"><?= htmlspecialchars($row['full_name']) ?></div>
          <span class="co-badge <?= $coClass ?>" style="margin-top:5px;display:inline-flex;">
            <?= htmlspecialchars($row['insurance_company']) ?>
          </span>
        </div>
        <span class="<?= $statusClass ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span>
      </div>
      <div class="m-row">
        <span class="m-label">Insurance #</span>
        <span class="m-val mono"><?= htmlspecialchars($row['insurance_number']) ?></span>
      </div>
      <div class="m-row">
        <span class="m-label">Promo</span>
        <span class="m-val"><?= htmlspecialchars($row['promo_name']) ?></span>
      </div>
      <div class="m-row">
        <span class="m-label">Discount</span>
        <span class="m-val discount-val"><?= $discount ?></span>
      </div>
      <div class="m-row">
        <span class="m-label">Relation</span>
        <span class="m-val"><?= htmlspecialchars($row['relationship_to_insured']) ?></span>
      </div>
      <div class="m-actions">
        <button class="btn-view w-100 justify-content-center" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
          <i class="bi bi-credit-card-2-front"></i> View Insurance Card
        </button>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($rows)): ?>
      <div style="text-align:center;padding:40px;color:var(--ink-light);">
        <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.4;"></i>
        No insurance records found.
      </div>
    <?php endif; ?>
  </div>

</div><!-- /cw -->

<!-- ── Modals ── -->
<?php foreach ($rows as $row):
  $colors  = cardGradient($row['insurance_company']);
  $accent  = cardAccent($row['insurance_company']);
  $modalId = 'modal_' . intval($row['patient_insurance_id']);
  $discount = $row['discount_type'] === 'Percentage'
    ? htmlspecialchars($row['discount_value']) . '%'
    : '₱' . number_format($row['discount_value'], 2);
?>
<div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-shield-check me-2"></i><?= htmlspecialchars($row['full_name']) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- The Card -->
        <div class="ins-card-wrap">
          <div class="ins-card" style="background:linear-gradient(145deg, <?= $colors[0] ?>, <?= $colors[1] ?> 55%, <?= $colors[2] ?>);">

            <div class="ins-card-top">
              <div class="ins-company"><?= htmlspecialchars($row['insurance_company']) ?></div>
              <div class="ins-logo-dot">
                <span style="background:<?= $accent ?>;border:1px solid rgba(255,255,255,.3);"></span>
                <span style="background:rgba(255,255,255,.25);border:1px solid rgba(255,255,255,.3);"></span>
              </div>
            </div>

            <div class="chip-svg"></div>

            <div class="ins-number">
              <?= implode(' ', str_split(preg_replace('/\s+/', '', $row['insurance_number']), 4)) ?>
            </div>

            <div class="ins-card-bottom">
              <div>
                <div class="ins-holder-label">Card Holder</div>
                <div class="ins-holder-name"><?= htmlspecialchars(strtoupper($row['full_name'])) ?></div>
              </div>
              <div class="ins-promo">
                <div><?= htmlspecialchars($row['promo_name']) ?></div>
                <div class="ins-discount"><?= $discount ?></div>
              </div>
            </div>

          </div>
        </div>

        <!-- Pills -->
        <div class="card-pills">
          <div class="card-pill">
            <i class="bi bi-people"></i>
            <strong>Relation:</strong> <?= htmlspecialchars($row['relationship_to_insured']) ?>
          </div>
          <div class="card-pill">
            <i class="bi bi-tag"></i>
            <strong>Type:</strong> <?= htmlspecialchars($row['discount_type']) ?>
          </div>
          <div class="card-pill">
            <?php $st = strtolower($row['status'] ?? 'active'); ?>
            <i class="bi bi-<?= $st === 'active' ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
            <strong>Status:</strong> <?= ucfirst(htmlspecialchars($row['status'])) ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
/* ─────────────────────────────────────────────────────────────
   Sync body margin with billing_sidebar.php sidebar state.
   billing_sidebar.php toggles .closed on #mySidebar and
   changes the toggle button icon. We watch for that class change
   and mirror it on <body> so our margin transitions stay correct.
───────────────────────────────────────────────────────────── */
(function () {
  const sidebar = document.getElementById('mySidebar');
  if (!sidebar) return;

  function syncBody() {
    if (sidebar.classList.contains('closed')) {
      document.body.classList.add('sidebar-closed');
    } else {
      document.body.classList.remove('sidebar-closed');
    }
  }

  // Watch for class changes on the sidebar element
  const observer = new MutationObserver(syncBody);
  observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });

  // Initial sync
  syncBody();

  // Also re-sync on resize so viewport changes re-evaluate correctly
  window.addEventListener('resize', syncBody);
})();

/* ─────────────────────────────────────────────────────────────
   Live search — desktop table + mobile cards
───────────────────────────────────────────────────────────── */
document.getElementById('searchInput').addEventListener('input', function () {
  const q = this.value.toLowerCase().trim();

  document.querySelectorAll('#insTable tbody .ins-row').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });

  document.querySelectorAll('#mobileCards .mobile-row').forEach(card => {
    card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>