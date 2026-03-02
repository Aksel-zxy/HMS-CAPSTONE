<?php 
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ── Simple, easy-to-understand Category → Item Type map ──
$category_type_map = [

    'Medicines' => [
        'Antibiotics',
        'Pain Relievers',
        'Vitamins & Supplements',
        'Heart & Blood Pressure Medicines',
        'Diabetes Medicines',
        'Asthma & Breathing Medicines',
        'Stomach & Antacid Medicines',
        'Mental Health Medicines',
        'Eye Medicines & Drops',
        'IV Fluids (Dextrose, Saline, etc.)',
        'Vaccines & Injections',
        'Cancer Medicines',
        'Other Prescription Medicines',
        'Over-the-Counter (OTC) Medicines',
    ],

    'Medical Equipment' => [
        'Blood Pressure Monitor',
        'Pulse Oximeter',
        'Thermometer',
        'Blood Sugar Monitor (Glucometer)',
        'ECG / Heart Monitor',
        'Weighing Scale',
        'Stethoscope',
        'Ultrasound Machine',
        'X-Ray / Imaging Machine',
        'Dialysis Machine',
        'Ventilator / Breathing Machine',
        'Anesthesia Machine',
        'Defibrillator / AED',
        'Infusion Pump',
        'Suction Machine',
        'Other Medical Equipment',
    ],

    'Medical Supplies & Consumables' => [
        'Gloves (Surgical / Examination)',
        'Face Masks & PPE',
        'Syringes & Needles',
        'IV Set & IV Cannula',
        'Bandages & Wound Dressings',
        'Catheter & Tubing',
        'Cotton, Gauze & Medical Tape',
        'Sutures & Surgical Blades',
        'Blood Collection Tubes & Lancets',
        'Specimen Containers & Swabs',
        'Bedpan, Urinal & Patient Care Items',
        'Hospital Linen & Patient Gowns',
        'Other Disposable Supplies',
    ],

    'Laboratory' => [
        'Rapid Test Kits (COVID, Dengue, etc.)',
        'Lab Reagents & Chemicals',
        'Culture Media & Agar',
        'Microscope Slides & Coverslips',
        'Lab Tubes, Pipettes & Containers',
        'Other Lab Supplies',
    ],

    'Hospital Furniture & Equipment' => [
        'Hospital Bed',
        'Stretcher / Gurney',
        'Wheelchair',
        'IV Stand / Pole',
        'Bedside Cabinet & Overbed Table',
        'Operating / Procedure Table',
        'Sterilizer / Autoclave',
        'Surgical Instruments (Forceps, Scissors, etc.)',
        'Rehabilitation Equipment',
        'Other Furniture & Fixtures',
    ],

    'Cleaning & Sanitation' => [
        'Disinfectants & Cleaning Solutions',
        'Mops, Brooms & Cleaning Tools',
        'Trash Bags & Waste Bins',
        'Hand Soap & Hand Sanitizer',
        'Laundry Supplies',
        'Pest Control Supplies',
        'Other Cleaning Supplies',
    ],

    'IT & Office Equipment' => [
        'Computer & Laptop',
        'Printer & Scanner',
        'Ink, Toner & Cartridges',
        'CCTV & Security Equipment',
        'Communication Equipment (Radio, Phone)',
        'Hospital Software / System License',
        'Other IT Equipment',
    ],

    'Office & Administrative Supplies' => [
        'Bond Paper & Stationery',
        'Pens, Markers & Correction Supplies',
        'Folders, Binders & Filing Supplies',
        'Printer Paper & Forms',
        'Other Office Supplies',
    ],

    'Kitchen & Dietary' => [
        'Food & Beverages',
        'Kitchen Utensils & Cookware',
        'Dietary Supplements & Feeding Supplies',
        'Other Kitchen Supplies',
    ],

    'Facility & Maintenance' => [
        'Electrical Supplies & Spare Parts',
        'Plumbing Supplies',
        'Fire Extinguisher & Safety Equipment',
        'Generator & Power Supplies',
        'Air Conditioning & Ventilation Parts',
        'Other Maintenance Supplies',
    ],
];

// Handle setting expiry for new delivered items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_expiry_submit'])) {
    $request_item_id = intval($_POST['request_item_id']);
    $has_expiry      = isset($_POST['has_expiry']) ? 1 : 0;
    $expiry_date     = ($has_expiry && !empty($_POST['expiry_date'])) ? $_POST['expiry_date'] : null;
    $sel_category    = trim($_POST['sel_category']  ?? '');
    $sel_item_type   = trim($_POST['sel_item_type'] ?? '');

    $stmt = $pdo->prepare("
        SELECT di.*, dr.id AS req_id
        FROM department_request_items di
        JOIN department_request dr ON di.request_id = dr.id
        WHERE di.id = ? AND dr.status = 'Completed'
    ");
    $stmt->execute([$request_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        if ($sel_category) {
            $pdo->prepare("UPDATE inventory SET category = ?, item_type = ? WHERE item_name = ?")
                ->execute([$sel_category, $sel_item_type, $item['item_name']]);
        }

        $stmt2 = $pdo->prepare("SELECT id FROM vendor_products WHERE item_name LIKE ? LIMIT 1");
        $stmt2->execute(['%' . $item['item_name'] . '%']);
        $vp      = $stmt2->fetch(PDO::FETCH_ASSOC);
        $item_id = $vp ? $vp['id'] : 0;

        $batch_no  = 'DRI-' . $item['id'];
        $total_pcs = intval($item['received_quantity']) * intval($item['pcs_per_box']);
        if ($total_pcs <= 0) $total_pcs = intval($item['received_quantity']);

        $pdo->prepare("INSERT INTO medicine_batches (item_id, batch_no, quantity, expiration_date, has_expiry) VALUES (?, ?, ?, ?, ?)")
            ->execute([$item_id, $batch_no, $total_pcs, $expiry_date, $has_expiry]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle expiry update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'], $_POST['expiry_date']) && !isset($_POST['set_expiry_submit'])) {
    $batch_id    = intval($_POST['batch_id']);
    $expiry_date = $_POST['expiry_date'];
    $pdo->prepare("UPDATE medicine_batches SET expiration_date = ? WHERE id = ?")->execute([$expiry_date, $batch_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle disposal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_id'], $_POST['dispose_qty'])) {
    $dispose_id  = intval($_POST['dispose_id']);
    $dispose_qty = intval($_POST['dispose_qty']);

    $stmt = $pdo->prepare("
        SELECT mb.*, vp.item_name, vp.price
        FROM medicine_batches mb
        LEFT JOIN vendor_products vp ON mb.item_id = vp.id
        WHERE mb.id = ?
    ");
    $stmt->execute([$dispose_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch && $dispose_qty > 0 && $dispose_qty <= $batch['quantity']) {
        $price     = $batch['price']     ?? 0;
        $item_name = $batch['item_name'] ?? 'Unknown';

        $pdo->prepare("INSERT INTO disposed_medicines (batch_id, batch_no, item_id, item_name, quantity, price, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$batch['id'], $batch['batch_no'], $batch['item_id'], $item_name, $dispose_qty, $price, $batch['expiration_date']]);

        $new_qty = $batch['quantity'] - $dispose_qty;
        if ($new_qty > 0) {
            $pdo->prepare("UPDATE medicine_batches SET quantity = ? WHERE id = ?")->execute([$new_qty, $batch['id']]);
        } else {
            $pdo->prepare("DELETE FROM medicine_batches WHERE id = ?")->execute([$batch['id']]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch new delivered items
$stmt = $pdo->prepare("
    SELECT di.*, dr.delivered_at, dr.id AS request_id,
           COALESCE(inv.item_type, '') AS item_type,
           COALESCE(inv.category,  '') AS category
    FROM department_request_items di
    JOIN department_request dr ON di.request_id = dr.id
    LEFT JOIN inventory inv ON inv.item_name = di.item_name
    WHERE dr.status = 'Completed'
      AND NOT EXISTS (
          SELECT 1 FROM medicine_batches mb WHERE mb.batch_no = CONCAT('DRI-', di.id)
      )
    GROUP BY di.id
    ORDER BY di.id ASC
");
$stmt->execute();
$new_delivered = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── UPDATED: Fetch inventory / batches — only items WITH expiry dates ──
// Also uses subquery to resolve real item names for DRI-xx batches
$stmt = $pdo->prepare("
    SELECT 
        mb.id AS batch_id, mb.item_id,
        COALESCE(
            vp.item_name,
            (SELECT di.item_name FROM department_request_items di
             WHERE CONCAT('DRI-', di.id) = mb.batch_no LIMIT 1),
            mb.batch_no
        ) AS item_name,
        vp.price, vp.unit_type, vp.pcs_per_box,
        mb.quantity, mb.batch_no, mb.expiration_date, mb.has_expiry,
        COALESCE(inv.item_type, '')  AS item_type,
        COALESCE(inv.category,  '')  AS category
    FROM medicine_batches mb
    LEFT JOIN vendor_products vp ON mb.item_id = vp.id
    LEFT JOIN inventory inv ON inv.item_name = COALESCE(
        vp.item_name,
        (SELECT di2.item_name FROM department_request_items di2
         WHERE CONCAT('DRI-', di2.id) = mb.batch_no LIMIT 1)
    )
    WHERE mb.has_expiry = 1 AND mb.expiration_date IS NOT NULL
    GROUP BY mb.id
    ORDER BY COALESCE(
        vp.item_name,
        (SELECT di3.item_name FROM department_request_items di3
         WHERE CONCAT('DRI-', di3.id) = mb.batch_no LIMIT 1),
        mb.batch_no
    ), mb.expiration_date
");
$stmt->execute();
$inventory_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$expired = $near_expiry = $safe = $seven_days_alert = [];

foreach ($inventory_rows as $row) {
    $today    = new DateTime();
    $expiry   = new DateTime($row['expiration_date']);
    $diffDays = (int)$today->diff($expiry)->format("%r%a");

    if ($expiry < $today) {
        $row['status'] = 'Expired';
        $expired[]     = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 7) {
        $row['status']    = 'Near Expiry';
        $row['days_left'] = $diffDays;
        $near_expiry[]    = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 30) {
        $row['status']    = 'Near Expiry';
        $row['days_left'] = $diffDays;
        $near_expiry[]    = $row;
    } else {
        $row['status'] = 'Safe';
        $safe[]        = $row;
    }
}

// ── No expiry items removed from all_stocks ──
$all_stocks = array_merge($expired, $near_expiry, $safe);

$stmt = $pdo->prepare("SELECT * FROM disposed_medicines ORDER BY disposed_at DESC");
$stmt->execute();
$disposed = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_disposed_value = array_sum(array_map(fn($d) => $d['quantity'] * $d['price'], $disposed));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch & Expiry Tracking</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:       #00acc1;
            --primary-light: rgba(0,172,193,.1);
            --danger:        #e05555;
            --danger-light:  rgba(224,85,85,.09);
            --success:       #27ae60;
            --success-light: rgba(39,174,96,.1);
            --warning:       #f39c12;
            --warning-light: rgba(243,156,18,.12);
            --info:          #2980b9;
            --info-light:    rgba(41,128,185,.1);
            --purple:        #7c4dff;
            --purple-light:  rgba(124,77,255,.1);
            --sidebar-w:     250px;
            --text:          #6e768e;
            --text-dark:     #3a4060;
            --bg:            #F5F6F7;
            --card:          #ffffff;
            --border:        #e8eaed;
            --radius:        12px;
            --shadow:        0 2px 12px rgba(0,0,0,.07);
            --shadow-lg:     0 8px 32px rgba(0,0,0,.11);
        }

        body { font-family:"Nunito","Segoe UI",Arial,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
        .sidebar-area { position:fixed; left:0; top:0; width:var(--sidebar-w); height:100vh; z-index:100; }
        .main-content { margin-left:var(--sidebar-w); min-height:100vh; padding:32px 32px 56px; }

        .page-header { margin-bottom:28px; }
        .breadcrumb-row { display:flex; align-items:center; gap:6px; font-size:.8rem; margin-bottom:6px; }
        .breadcrumb-row span { color:var(--primary); font-weight:700; }
        .page-header h1 { font-size:1.65rem; font-weight:800; color:var(--text-dark); }
        .page-header p  { font-size:.9rem; color:var(--text); margin-top:4px; }

        .expiry-alert-banner { background:linear-gradient(135deg,#fff8e6,#fff3cd); border:1.5px solid #f39c12; border-left:5px solid #f39c12; border-radius:var(--radius); padding:16px 20px; margin-bottom:24px; animation:slideIn .3s ease; }
        .expiry-alert-banner .alert-title { font-size:.92rem; font-weight:800; color:#8a6300; margin-bottom:10px; display:flex; align-items:center; gap:8px; }
        .expiry-alert-banner ul { margin:0; padding-left:20px; }
        .expiry-alert-banner li { font-size:.87rem; color:#7a5a00; margin-bottom:4px; }
        .alert-close { float:right; background:none; border:none; font-size:1.1rem; cursor:pointer; color:#8a6300; }
        @keyframes slideIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:none} }

        .stats-row { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:28px; }
        .stat-card { background:var(--card); border-radius:var(--radius); padding:16px 18px; border:1px solid var(--border); box-shadow:var(--shadow); display:flex; align-items:center; gap:12px; transition:transform .2s,box-shadow .2s; }
        .stat-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
        .stat-icon { width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0; }
        .stat-icon.teal{background:var(--primary-light)} .stat-icon.red{background:var(--danger-light)} .stat-icon.amber{background:var(--warning-light)} .stat-icon.green{background:var(--success-light)} .stat-icon.blue{background:var(--info-light)}
        .stat-info .value { font-size:1.35rem;font-weight:800;color:var(--text-dark);line-height:1; }
        .stat-info .label { font-size:.75rem;color:var(--text);margin-top:3px; }
        .stat-info .value.red{color:var(--danger)} .stat-info .value.amber{color:var(--warning)} .stat-info .value.green{color:var(--success)}

        .tab-bar { display:flex;gap:4px;background:var(--card);border:1px solid var(--border);border-radius:12px 12px 0 0;padding:8px 8px 0;box-shadow:var(--shadow);overflow-x:auto; }
        .tab-btn { display:flex;align-items:center;gap:7px;padding:10px 20px;border:none;background:none;font-family:"Nunito",sans-serif;font-size:.88rem;font-weight:700;color:var(--text);cursor:pointer;border-radius:8px 8px 0 0;border-bottom:3px solid transparent;transition:color .2s,background .2s,border-color .2s;white-space:nowrap; }
        .tab-btn:hover{background:var(--primary-light);color:var(--primary)} .tab-btn.active{color:var(--primary);border-bottom-color:var(--primary);background:var(--primary-light)}
        .tab-badge{color:#fff;font-size:.7rem;font-weight:700;padding:2px 7px;border-radius:20px}
        .tab-badge.red{background:var(--danger)} .tab-badge.teal{background:var(--primary)} .tab-badge.muted{background:var(--text)}

        .tab-panel{display:none;background:var(--card);border:1px solid var(--border);border-top:none;border-radius:0 0 12px 12px;padding:28px;box-shadow:var(--shadow);animation:fadeIn .25s ease}
        .tab-panel.active{display:block}
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}

        .section-header{display:flex;align-items:center;gap:12px;margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid var(--border)}
        .icon-wrap{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
        .icon-wrap.teal{background:var(--primary-light)} .icon-wrap.amber{background:var(--warning-light)} .icon-wrap.red{background:var(--danger-light)}
        .section-header h3{font-size:1.05rem;font-weight:800;color:var(--text-dark)} .section-header p{font-size:.82rem;color:var(--text);margin-top:2px}

        .filter-bar{display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap}
        .filter-bar input{flex:1;min-width:160px;padding:9px 14px 9px 36px;border:1.5px solid var(--border);border-radius:8px;font-family:"Nunito",sans-serif;font-size:.88rem;color:var(--text-dark);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236e768e' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 12px center;outline:none;transition:border-color .2s;appearance:none}
        .filter-bar input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,172,193,.1)}

        .status-pills{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
        .status-pill{display:flex;align-items:center;gap:7px;padding:8px 16px;border-radius:24px;font-size:.82rem;font-weight:700;cursor:pointer;transition:transform .15s,box-shadow .15s;border:2px solid transparent;user-select:none}
        .status-pill:hover{transform:translateY(-1px);box-shadow:var(--shadow)} .status-pill.active{border-color:currentColor}
        .status-pill.all{background:#f0f0f0;color:var(--text-dark)} .status-pill.exp{background:var(--danger-light);color:var(--danger)} .status-pill.near{background:var(--warning-light);color:#8a5a00} .status-pill.safe{background:var(--success-light);color:var(--success)}
        .status-pill .pill-count{font-size:.95rem}

        .table-wrapper{overflow-x:auto;border-radius:10px;border:1px solid var(--border)}
        .data-table{width:100%;border-collapse:collapse;font-size:.87rem;min-width:600px}
        .data-table thead th{background:var(--bg);color:var(--text);font-size:.71rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:11px 14px;border-bottom:2px solid var(--border);white-space:nowrap}
        .data-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s} .data-table tbody tr:last-child{border-bottom:none}
        .data-table td{padding:11px 14px;color:var(--text-dark);vertical-align:middle}
        .row-expired{background:rgba(224,85,85,.05)!important} .row-near{background:rgba(243,156,18,.05)!important} .row-safe{background:rgba(39,174,96,.03)!important}
        .data-table tbody tr.row-expired:hover{background:rgba(224,85,85,.1)!important} .data-table tbody tr.row-near:hover{background:rgba(243,156,18,.1)!important} .data-table tbody tr.row-safe:hover{background:rgba(39,174,96,.07)!important}

        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700}
        .badge-expired{background:var(--danger-light);color:var(--danger);border:1px solid rgba(224,85,85,.2)} .badge-near{background:var(--warning-light);color:#8a5a00;border:1px solid rgba(243,156,18,.25)} .badge-safe{background:var(--success-light);color:var(--success);border:1px solid rgba(39,174,96,.2)} .badge-noexp{background:var(--info-light);color:var(--info);border:1px solid rgba(41,128,185,.2)} .badge-type{background:var(--primary-light);color:var(--primary)} .badge-muted{background:#eee;color:var(--text)} .badge-purple{background:var(--purple-light);color:var(--purple)}
        .days-left{font-size:.75rem;color:var(--text);margin-top:3px} .days-left.urgent{color:var(--danger);font-weight:700}

        .select-styled {
            width:100%; padding:7px 10px; border:1.5px solid var(--border); border-radius:7px;
            font-family:"Nunito",sans-serif; font-size:.82rem; color:var(--text-dark);
            background-color:#fff; outline:none; cursor:pointer; transition:border-color .2s;
            appearance:none; -webkit-appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236e768e' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 10px center; padding-right:28px;
        }
        .select-styled:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(0,172,193,.1); }
        .select-styled:disabled { background-color:#f8f8f8; color:#bbb; cursor:not-allowed; }
        .dropdown-label { font-size:.68rem; font-weight:800; color:var(--text); text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:3px; }

        .toggle-switch{position:relative;display:inline-block;width:44px;height:24px} .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:24px;cursor:pointer;transition:background .2s}
        .toggle-slider::before{content:"";position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
        .toggle-switch input:checked+.toggle-slider{background:var(--primary)} .toggle-switch input:checked+.toggle-slider::before{transform:translateX(20px)}
        .toggle-label{font-size:.8rem;font-weight:700;color:var(--text-dark);margin-left:6px;vertical-align:middle}

        .td-input{width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:7px;font-family:"Nunito",sans-serif;font-size:.85rem;color:var(--text-dark);background:#fff;outline:none;transition:border-color .2s}
        .td-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,172,193,.1)}

        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border:none;border-radius:8px;font-family:"Nunito",sans-serif;font-size:.87rem;font-weight:700;cursor:pointer;white-space:nowrap;transition:background .2s,transform .15s;text-decoration:none}
        .btn:hover{transform:translateY(-1px)} .btn:active{transform:translateY(0)}
        .btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(0,172,193,.3)} .btn-primary:hover{background:#009ab0}
        .btn-danger{background:var(--danger);color:#fff;box-shadow:0 4px 12px rgba(224,85,85,.3)} .btn-danger:hover{background:#c0392b}
        .btn-secondary{background:#e8eaed;color:var(--text-dark)} .btn-secondary:hover{background:#d8dade}
        .btn-sm{padding:5px 12px;font-size:.8rem}

        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(30,40,60,.45);z-index:500;align-items:center;justify-content:center} .modal-overlay.open{display:flex}
        .modal-box{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow-lg);width:100%;max-width:520px;padding:0;position:relative;margin:16px;overflow:hidden;animation:slideUp .25s ease}
        @keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
        .modal-head{padding:20px 24px;color:#fff} .modal-head h4{font-size:1rem;font-weight:800} .modal-head p{font-size:.82rem;opacity:.85;margin-top:3px}
        .modal-body{padding:24px} .modal-foot{display:flex;gap:10px;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--border)}
        .modal-close-btn{position:absolute;top:14px;right:16px;background:rgba(255,255,255,.2);border:none;color:#fff;font-size:1rem;cursor:pointer;padding:4px 8px;border-radius:6px} .modal-close-btn:hover{background:rgba(255,255,255,.35)}
        .form-group{margin-bottom:16px}
        .form-group label{display:flex;align-items:center;gap:6px;font-size:.77rem;font-weight:800;color:var(--text-dark);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
        .form-control{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:"Nunito",sans-serif;font-size:.9rem;color:var(--text-dark);background:#fff;outline:none;transition:border-color .2s,box-shadow .2s} .form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,172,193,.12)}

        .disposed-summary{display:flex;gap:20px;flex-wrap:wrap;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:14px 18px;margin-bottom:18px}
        .ds-item .ds-label{font-size:.75rem;font-weight:800;color:var(--text);text-transform:uppercase;letter-spacing:.04em} .ds-item .ds-value{font-size:1.1rem;font-weight:800;color:var(--text-dark);margin-top:2px}
        .empty-state{text-align:center;padding:48px 20px;color:var(--text)} .empty-state .empty-icon{font-size:2.5rem;margin-bottom:12px}
        .no-results{text-align:center;padding:28px;color:var(--text);font-style:italic;display:none}

        @media(max-width:1200px){.stats-row{grid-template-columns:repeat(3,1fr)}}
        @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:768px){.main-content{margin-left:0;padding:20px 16px 48px}.stats-row{grid-template-columns:1fr 1fr}.filter-bar{flex-direction:column}}
        @media(max-width:480px){.stats-row{grid-template-columns:1fr}}
    </style>
</head>
<body>

<div class="sidebar-area">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">

    <div class="page-header">
        <div class="breadcrumb-row">Equipment & Medicine Stock &rsaquo; <span>Batch & Expiry Tracking</span></div>
        <h1>Batch & Expiry Tracking</h1>
        <p>Monitor medicine batches, set expiration dates, and manage expired stock disposal</p>
    </div>

    <?php if (!empty($seven_days_alert)): ?>
    <div class="expiry-alert-banner" id="expiryAlertBanner">
        <button class="alert-close" onclick="document.getElementById('expiryAlertBanner').style.display='none'">✕</button>
        <div class="alert-title">⚠️ Urgent Expiry Alerts (<?= count($seven_days_alert) ?> item<?= count($seven_days_alert) > 1 ? 's' : '' ?>)</div>
        <ul>
            <?php foreach ($seven_days_alert as $alertItem):
                $today  = new DateTime();
                $expiry = new DateTime($alertItem['expiration_date']);
                $diff   = (int)$today->diff($expiry)->format("%r%a");
                $msg = $expiry < $today
                    ? htmlspecialchars($alertItem['item_name']) . " is <strong>Expired</strong>"
                    : htmlspecialchars($alertItem['item_name']) . " expires in <strong>{$diff} day(s)</strong>";
            ?>
                <li><?= $msg ?> — Batch: <?= htmlspecialchars($alertItem['batch_no']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon teal">💊</div>
            <div class="stat-info"><div class="value"><?= count($all_stocks) ?></div><div class="label">Total Batches</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">🔴</div>
            <div class="stat-info"><div class="value red"><?= count($expired) ?></div><div class="label">Expired Batches</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">🟡</div>
            <div class="stat-info"><div class="value amber"><?= count($near_expiry) ?></div><div class="label">Near Expiry (≤30d)</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🟢</div>
            <div class="stat-info"><div class="value green"><?= count($safe) ?></div><div class="label">Safe Batches</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🗑️</div>
            <div class="stat-info"><div class="value"><?= count($disposed) ?></div><div class="label">Total Disposed</div></div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('delivered', this)">
            📦 New Delivered
            <?php if (!empty($new_delivered)): ?><span class="tab-badge red"><?= count($new_delivered) ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('stocks', this)">
            💊 Stocks <span class="tab-badge teal"><?= count($all_stocks) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('disposed', this)">
            🗑️ Disposed <span class="tab-badge muted"><?= count($disposed) ?></span>
        </button>
    </div>

    <!-- ── NEW DELIVERED TAB ── -->
    <div class="tab-panel active" id="tab-delivered">
        <div class="section-header">
            <div class="icon-wrap teal">📦</div>
            <div>
                <h3>New Delivered Items — Set Expiration</h3>
                <p>Pick a <strong>Category</strong> then an <strong>Item Type</strong>, set the expiry date, and click <strong>✔ Save</strong>.</p>
            </div>
        </div>

        <?php if (empty($new_delivered)): ?>
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <p>All delivered items have been processed. No pending expiry setups.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Item Type</th>
                            <th>Description</th>
                            <th>Rcvd Qty</th>
                            <th>Pcs/Box</th>
                            <th>Total Pcs</th>
                            <th>Has Expiry?</th>
                            <th>Expiration Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $ndNum = 1; foreach ($new_delivered as $nd):
                        $total_pcs = intval($nd['received_quantity']) * intval($nd['pcs_per_box']);
                        if ($total_pcs <= 0) $total_pcs = intval($nd['received_quantity']);
                        $cur_cat  = $nd['category']  ?? '';
                        $cur_type = $nd['item_type'] ?? '';
                    ?>
                    <tr>
                        <form method="post">
                            <input type="hidden" name="set_expiry_submit" value="1">
                            <input type="hidden" name="request_item_id" value="<?= $nd['id'] ?>">

                            <td style="color:var(--text);font-size:.8rem;"><?= $ndNum++ ?></td>
                            <td><strong><?= htmlspecialchars($nd['item_name']) ?></strong></td>

                            <!-- CATEGORY DROPDOWN -->
                            <td style="min-width:195px;">
                                <span class="dropdown-label">Category</span>
                                <select name="sel_category"
                                        id="catSel_<?= $nd['id'] ?>"
                                        class="select-styled"
                                        onchange="updateTypeDropdown(<?= $nd['id'] ?>, this.value)">
                                    <option value="">— Choose —</option>
                                    <?php foreach ($category_type_map as $cat => $types): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"
                                            <?= ($cur_cat === $cat) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <!-- ITEM TYPE DROPDOWN (filled by JS when category chosen) -->
                            <td style="min-width:220px;">
                                <span class="dropdown-label">Item Type</span>
                                <select name="sel_item_type"
                                        id="typeSel_<?= $nd['id'] ?>"
                                        class="select-styled"
                                        <?= empty($cur_cat) ? 'disabled' : '' ?>>
                                    <option value="">— Pick Category First —</option>
                                    <?php
                                    if (!empty($cur_cat) && isset($category_type_map[$cur_cat])) {
                                        foreach ($category_type_map[$cur_cat] as $t) {
                                            $sel = ($cur_type === $t) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($t) . '" ' . $sel . '>'
                                               . htmlspecialchars($t) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </td>

                            <td style="font-size:.83rem;color:var(--text);"><?= htmlspecialchars($nd['description'] ?? '—') ?></td>
                            <td><span class="badge badge-type"><?= intval($nd['received_quantity']) ?></span></td>
                            <td><?= intval($nd['pcs_per_box']) ?></td>
                            <td><span class="badge badge-type"><?= $total_pcs ?></span></td>

                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="has_expiry" id="hasExpiry_<?= $nd['id'] ?>" checked
                                           onchange="toggleExpiryField(<?= $nd['id'] ?>, this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label" id="expiryLabel_<?= $nd['id'] ?>">Yes</span>
                            </td>

                            <td style="min-width:160px;">
                                <div id="expiryField_<?= $nd['id'] ?>">
                                    <input type="date" name="expiry_date" class="td-input" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div id="noExpiryMsg_<?= $nd['id'] ?>" style="display:none;font-size:.8rem;color:var(--text);font-style:italic;padding:8px 0;">
                                    No expiration date
                                </div>
                            </td>

                            <td>
                                <button type="submit" class="btn btn-primary btn-sm">✔ Save</button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── STOCKS TAB ── -->
    <div class="tab-panel" id="tab-stocks">
        <div class="section-header">
            <div class="icon-wrap amber">💊</div>
            <div><h3>Medicine & Supply Stock Overview</h3><p>All batches with expiry dates — use the filters to find items needing action</p></div>
        </div>

        <!-- ── Status filter pills (No Expiry removed) ── -->
        <div class="status-pills">
            <div class="status-pill all active" onclick="filterStocks('all', this)">
                <span>📋 All</span><span class="pill-count"><?= count($all_stocks) ?></span>
            </div>
            <div class="status-pill exp" onclick="filterStocks('expired', this)">
                <span>🔴 Expired</span><span class="pill-count"><?= count($expired) ?></span>
            </div>
            <div class="status-pill near" onclick="filterStocks('near_expiry', this)">
                <span>🟡 Near Expiry</span><span class="pill-count"><?= count($near_expiry) ?></span>
            </div>
            <div class="status-pill safe" onclick="filterStocks('safe', this)">
                <span>🟢 Safe</span><span class="pill-count"><?= count($safe) ?></span>
            </div>
        </div>

        <div class="filter-bar">
            <input type="text" id="stockSearch" placeholder="Search by item name or batch no…" oninput="liveSearchStocks()">
        </div>

        <div class="table-wrapper">
            <?php if (empty($all_stocks)): ?>
                <div class="empty-state"><div class="empty-icon">📭</div><p>No stock records found.</p></div>
            <?php else: ?>
                <table class="data-table" id="stocksTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Qty (pcs)</th>
                            <th>Batch No</th>
                            <th>Expiration Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; foreach ($all_stocks as $row):
                            switch ($row['status']) {
                                case 'Expired':     $rc = 'row-expired'; $bc = 'badge-expired'; $bl = '🔴 Expired';     $fa = 'expired';     break;
                                case 'Near Expiry': $rc = 'row-near';    $bc = 'badge-near';    $bl = '🟡 Near Expiry'; $fa = 'near_expiry'; break;
                                default:            $rc = 'row-safe';    $bc = 'badge-safe';    $bl = '🟢 Safe';        $fa = 'safe';
                            }
                        ?>
                        <tr class="stock-row <?= $rc ?>"
                            data-status="<?= $fa ?>"
                            data-name="<?= strtolower(htmlspecialchars($row['item_name'])) ?>"
                            data-batch="<?= strtolower(htmlspecialchars($row['batch_no'])) ?>">
                            <td style="color:var(--text);font-size:.8rem;"><?= $rowNum++ ?></td>
                            <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                            <td><span class="badge badge-type"><?= number_format($row['quantity']) ?></span></td>
                            <td><span class="badge badge-muted" style="font-family:monospace;"><?= htmlspecialchars($row['batch_no']) ?></span></td>
                            <td>
                                <?= date('M d, Y', strtotime($row['expiration_date'])) ?>
                                <?php if (!empty($row['days_left'])): ?>
                                    <div class="days-left <?= $row['days_left'] <= 7 ? 'urgent' : '' ?>">
                                        <?= $row['days_left'] ?> day(s) remaining
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $bc ?>"><?= $bl ?></span></td>
                            <td>
                                <?php if ($row['status'] === 'Expired'): ?>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="openDisposeModal(<?= $row['batch_id'] ?>, <?= htmlspecialchars(json_encode($row['item_name'])) ?>, <?= $row['quantity'] ?>)">
                                        🗑️ Dispose
                                    </button>
                                <?php else: ?>
                                    <span style="color:var(--text);font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="no-results" id="noStockResults">No items match your search.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── DISPOSED TAB ── -->
    <div class="tab-panel" id="tab-disposed">
        <div class="section-header">
            <div class="icon-wrap red">🗑️</div>
            <div><h3>Disposed Items</h3><p>Full audit log of all disposed batches</p></div>
        </div>
        <?php if (!empty($disposed)): ?>
        <div class="disposed-summary">
            <div class="ds-item"><div class="ds-label">Total Records</div><div class="ds-value"><?= count($disposed) ?></div></div>
            <div class="ds-item"><div class="ds-label">Total Units Disposed</div><div class="ds-value"><?= number_format(array_sum(array_column($disposed,'quantity'))) ?></div></div>
            <div class="ds-item"><div class="ds-label">Total Value Lost</div><div class="ds-value" style="color:var(--danger);">₱<?= number_format($total_disposed_value,2) ?></div></div>
        </div>
        <?php endif; ?>
        <?php if (empty($disposed)): ?>
            <div class="empty-state"><div class="empty-icon">🗂️</div><p>No disposed items on record.</p></div>
        <?php else: ?>
            <div class="filter-bar">
                <input type="text" id="disposedSearch" placeholder="Search disposed items…" oninput="filterDisposed()">
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="disposedTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Batch ID</th>
                            <th>Item Name</th>
                            <th>Qty Disposed</th>
                            <th>Price</th>
                            <th>Total Loss</th>
                            <th>Expiration Date</th>
                            <th>Disposed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($disposed as $row): ?>
                        <tr>
                            <td style="color:var(--text);font-size:.8rem;"><?= $i++ ?></td>
                            <td><span class="badge badge-muted" style="font-family:monospace;"><?= $row['batch_id'] ?></span></td>
                            <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                            <td><span class="badge badge-expired"><?= number_format($row['quantity']) ?></span></td>
                            <td>₱<?= number_format($row['price'],2) ?></td>
                            <td style="color:var(--danger);font-weight:700;">₱<?= number_format($row['quantity']*$row['price'],2) ?></td>
                            <td><?= $row['expiration_date'] ? date('M d, Y',strtotime($row['expiration_date'])) : '—' ?></td>
                            <td style="font-size:.82rem;color:var(--text);white-space:nowrap;"><?= date('M d, Y g:i A',strtotime($row['disposed_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->

<!-- ── DISPOSE MODAL ── -->
<div class="modal-overlay" id="disposeModal">
    <div class="modal-box">
        <div class="modal-head" style="background:var(--danger);">
            <button class="modal-close-btn" onclick="closeDisposeModal()">✕</button>
            <h4>🗑️ Confirm Disposal</h4>
            <p id="modalSubtitle">Permanently remove expired stock</p>
        </div>
        <form method="post">
            <input type="hidden" name="dispose_id" id="disposeId">
            <div class="modal-body">
                <p style="font-size:.9rem;color:var(--text-dark);margin-bottom:16px;">
                    You are about to dispose: <strong id="medicineName" style="color:var(--danger);"></strong>
                </p>
                <div class="form-group">
                    <label>Quantity to Dispose</label>
                    <input type="number" name="dispose_qty" id="disposeQty" class="form-control" min="1" required placeholder="Enter quantity">
                </div>
                <div style="background:var(--danger-light);border:1px solid rgba(224,85,85,.2);border-radius:8px;padding:10px 14px;font-size:.83rem;color:var(--danger);">
                    ⚠️ This action is permanent and cannot be undone.
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeDisposeModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">🗑️ Confirm Dispose</button>
            </div>
        </form>
    </div>
</div>

<script>
// Category → Types map (passed from PHP)
const categoryTypeMap = <?= json_encode($category_type_map, JSON_HEX_TAG) ?>;

// When the category dropdown changes, fill the Item Type dropdown
function updateTypeDropdown(rowId, selectedCat) {
    const typeSel = document.getElementById('typeSel_' + rowId);
    typeSel.innerHTML = '';

    if (!selectedCat || !categoryTypeMap[selectedCat]) {
        typeSel.innerHTML = '<option value="">— Pick Category First —</option>';
        typeSel.disabled  = true;
        return;
    }

    const ph = document.createElement('option');
    ph.value = ''; ph.textContent = '— Select Item Type —';
    typeSel.appendChild(ph);

    categoryTypeMap[selectedCat].forEach(function(type) {
        const opt = document.createElement('option');
        opt.value = type; opt.textContent = type;
        typeSel.appendChild(opt);
    });

    typeSel.disabled = false;
}

// Toggle expiry date field
function toggleExpiryField(id, hasExpiry) {
    const fd = document.getElementById('expiryField_' + id);
    const md = document.getElementById('noExpiryMsg_' + id);
    const lb = document.getElementById('expiryLabel_' + id);
    const di = fd.querySelector('input[type="date"]');
    if (hasExpiry) {
        fd.style.display = ''; md.style.display = 'none';
        lb.textContent   = 'Yes'; di.required = true;
    } else {
        fd.style.display = 'none'; md.style.display = '';
        lb.textContent   = 'No';  di.required = false; di.value = '';
    }
}

// Tab switching
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// Stock filter
let currentStatusFilter = 'all';
function filterStocks(value, pill) {
    currentStatusFilter = value;
    document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
    if (pill) pill.classList.add('active');
    applyStockFilters();
}
function liveSearchStocks() { applyStockFilters(); }
function applyStockFilters() {
    const q    = (document.getElementById('stockSearch')?.value || '').toLowerCase();
    const rows = document.querySelectorAll('#stocksTable .stock-row');
    let visible = 0;
    rows.forEach(row => {
        const sm = currentStatusFilter === 'all' || row.dataset.status === currentStatusFilter;
        const tm = !q || row.dataset.name.includes(q) || row.dataset.batch.includes(q);
        row.style.display = (sm && tm) ? '' : 'none';
        if (sm && tm) visible++;
    });
    const nr = document.getElementById('noStockResults');
    if (nr) nr.style.display = visible === 0 ? 'block' : 'none';
}

// Disposed search
function filterDisposed() {
    const q = document.getElementById('disposedSearch').value.toLowerCase();
    document.querySelectorAll('#disposedTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// Dispose modal
function openDisposeModal(id, name, qty) {
    document.getElementById('disposeId').value           = id;
    document.getElementById('medicineName').textContent  = name;
    document.getElementById('disposeQty').value          = qty;
    document.getElementById('disposeQty').max            = qty;
    document.getElementById('modalSubtitle').textContent = 'Batch has ' + qty + ' unit(s) available';
    document.getElementById('disposeModal').classList.add('open');
    setTimeout(() => document.getElementById('disposeQty').focus(), 100);
}
function closeDisposeModal() { document.getElementById('disposeModal').classList.remove('open'); }
document.getElementById('disposeModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeDisposeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDisposeModal(); });

if (window.location.search.includes('tab=delivered')) {
    const delBtn = document.querySelector('.tab-btn');
    if (delBtn) switchTab('delivered', delBtn);
}
</script>
</body>
</html>