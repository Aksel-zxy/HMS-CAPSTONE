<?php
include '../../SQL/config.php';

// ================= FILTERS =================
$filterType = $_GET['filter'] ?? 'month';
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year']  ?? date('Y');

// ================= QUERY =================
if ($filterType === 'month') {
    $stmt = $pdo->prepare("
        SELECT * FROM department_request
        WHERE status='Approved'
        AND YEAR(month)=:year AND MONTH(month)=:month
        ORDER BY month ASC
    ");
    $stmt->execute([':year'=>$year, ':month'=>$month]);
    $periodLabel = date('F', mktime(0,0,0,$month,1))." ".$year;
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM department_request
        WHERE status='Approved'
        AND YEAR(month)=:year
        ORDER BY month ASC
    ");
    $stmt->execute([':year'=>$year]);
    $periodLabel = "Year ".$year;
}

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================= PREPARE DATA =================
$allItems = [];
$grandRequested = 0;
$grandApproved  = 0;
$grandTotal     = 0;

foreach ($requests as $r) {
    $department = $r['department'] ?? '';
    $items = json_decode($r['items'], true);
    if (!is_array($items)) continue;

    foreach ($items as $item) {
        $requested = (int)($item['quantity'] ?? 0);
        $approved  = (int)($item['approved_quantity'] ?? $requested);
        $price     = (float)($item['price'] ?? 0);
        $total     = $approved * $price;

        $grandRequested += $requested;
        $grandApproved  += $approved;
        $grandTotal     += $total;

        $allItems[] = [
            'department' => $department,
            'name' => $item['name'] ?? '',
            'description' => $item['description'] ?? '',
            'requested' => $requested,
            'approved' => $approved,
            'total' => $total
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Approved Item Requests Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family:'Segoe UI',sans-serif; background:#f8fafc; padding:20px; }
.card { border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,.08); }
.table th { background:#0d6efd; color:#fff; }
</style>
</head>
<body>

<div class="container">
<div class="card p-4">

<h2 class="text-center text-primary mb-4">
📊 Approved Item Requests – <?= htmlspecialchars($periodLabel) ?>
</h2>

<!-- FILTER FORM -->
<form method="GET" class="row g-3 mb-4 align-items-end">
    <div class="col-md-3">
        <label class="form-label">Filter Type</label>
        <select name="filter" class="form-select">
            <option value="month" <?= $filterType==='month'?'selected':'' ?>>Monthly</option>
            <option value="year" <?= $filterType==='year'?'selected':'' ?>>Yearly</option>
        </select>
    </div>

    <div class="col-md-3 <?= $filterType==='year'?'d-none':'' ?>">
        <label class="form-label">Month</label>
        <select name="month" class="form-select">
            <?php for ($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>>
                <?= date('F', mktime(0,0,0,$m,1)) ?>
            </option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Year</label>
        <input type="number" name="year" class="form-control"
               value="<?= $year ?>" min="2000" max="<?= date('Y') ?>">
    </div>

    <div class="col-md-3">
        <button class="btn btn-primary w-100">Generate</button>
    </div>
</form>

<!-- TABLE -->
<table class="table table-bordered table-striped text-center">
    <thead>
        <tr>
            <th>Department</th>
            <th>Item Name</th>
            <th>Description</th>
            <th>Requested Qty</th>
            <th>Approved Qty</th>
            <th>Total Price</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($allItems as $i): ?>
        <tr>
            <td><?= htmlspecialchars($i['department']) ?></td>
            <td><?= htmlspecialchars($i['name']) ?></td>
            <td><?= htmlspecialchars($i['description']) ?></td>
            <td><?= $i['requested'] ?></td>
            <td><?= $i['approved'] ?></td>
            <td>₱<?= number_format($i['total'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="fw-bold bg-light">
            <td colspan="3">GRAND TOTAL</td>
            <td><?= $grandRequested ?></td>
            <td><?= $grandApproved ?></td>
            <td>₱<?= number_format($grandTotal,2) ?></td>
        </tr>
    </tbody>
</table>

<!-- EXPORT -->
<a href="export_approved_items_pdf.php?filter=<?= $filterType ?>&month=<?= $month ?>&year=<?= $year ?>"
   class="btn btn-success">
   📄 Export PDF
</a>

</div>
</div>

</body>
</html>
