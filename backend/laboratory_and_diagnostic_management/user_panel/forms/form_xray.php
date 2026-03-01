<?php
if (!isset($conn)) {
    include __DIR__ . "/../../../../SQL/config.php";
}

$scheduleID = $_GET['scheduleID'] ?? null;

if (!$scheduleID) {
    echo "Invalid request.";
    exit();
}

$query = "SELECT s.scheduleID, s.patientID, s.serviceName, s.scheduleDate, s.scheduleTime,
                 p.fname, p.lname
          FROM dl_schedule s
          JOIN patientinfo p ON s.patientID = p.patient_id
          WHERE s.scheduleID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $scheduleID);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    echo "Schedule not found.";
    exit();
}

$machineQuery = $conn->query("SELECT machine_id, machine_name, machine_type FROM machine_equipments WHERE status = 'Available' AND machine_name LIKE '%X-ray%' ORDER BY machine_name ASC");
$machineItems = [];
if ($machineQuery) {
    while ($row = $machineQuery->fetch_assoc()) {
        $machineItems[] = $row;
    }
}

$inventoryQuery = $conn->query("SELECT item_id, item_name, quantity, unit_type, price FROM inventory WHERE quantity > 0 ORDER BY item_name ASC");
$inventoryItems = [];
if ($inventoryQuery) {
    while ($row = $inventoryQuery->fetch_assoc()) {
        $inventoryItems[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>X-ray Test Form</title>
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .btn-save {
            background-color: #007bff;
            color: white;
        }
        .btn-save:hover {
            background-color: #0056b3;
        }
        textarea.form-control {
            resize: vertical;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header">
                X-ray Test
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <strong>Schedule ID:</strong> <?= htmlspecialchars($patient['scheduleID']) ?><br>
                    <strong>Patient:</strong> <?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?><br>
                    <strong>Test:</strong> <?= htmlspecialchars($patient['serviceName']) ?><br>
                    <strong>Schedule:</strong> <?= htmlspecialchars($patient['scheduleDate']) ?> at <?= htmlspecialchars($patient['scheduleTime']) ?>
                </div>

                <form method="POST" action="forms/results.php" enctype="multipart/form-data">
                    <input type="hidden" name="testType" value="X-ray">
                    <input type="hidden" name="scheduleID" value="<?= $scheduleID ?>">
                    <input type="hidden" name="patientID" value="<?= $patient['patientID'] ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Findings</label>
                            <textarea class="form-control" name="findings" rows="5" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Impression</label>
                            <textarea class="form-control" name="impression" rows="5" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="5"></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload X-ray Image</label>
                        <input class="form-control" type="file" name="xray_image" accept="image/*" required>
                    </div>

                    <hr>
                    <h5>Tools & Equipment Used (For Billing/Record)</h5>
                    <div id="tools-container">
                        <!-- Tools will be appended here -->
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addToolRow()">+ Add Tool/Equipment</button>
                    <br>

                    <button type="submit" class="btn btn-save">Save Result</button>
                    <a href="sample_processing.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        const machineItems = <?= json_encode($machineItems) ?>;
        const inventoryItems = <?= json_encode($inventoryItems) ?>;
        function addToolRow() {
            const container = document.getElementById('tools-container');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 align-items-center tool-row';
            
            let options = '<option value="">Select Tool / Equipment...</option>';
            options += '<optgroup label="Laboratory Equipment">';
            machineItems.forEach(item => {
                options += `<option value="mac_${item.machine_id}">
                    [Equipment] ${item.machine_name} (${item.machine_type})
                </option>`;
            });
            options += '</optgroup>';

            options += '<optgroup label="Inventory & Consumables">';
            inventoryItems.forEach(item => {
                options += `<option value="inv_${item.item_id}" data-price="${item.price}" data-max="${item.quantity}">
                    [Supply] ${item.item_name} (Stock: ${item.quantity} ${item.unit_type}) - ₱${parseFloat(item.price).toFixed(2)}
                </option>`;
            });
            options += '</optgroup>';

            row.innerHTML = `
                <div class="col-md-5">
                    <select name="tool_id[]" class="form-select tool-select" required onchange="updateToolName(this)">
                        ${options}
                    </select>
                    <input type="hidden" name="tool_name[]" class="tool-name">
                    <input type="hidden" name="tool_price[]" class="tool-price">
                </div>
                <div class="col-md-4">
                    <input type="number" name="tool_qty[]" class="form-control tool-qty" placeholder="Qty" min="1" required>
                    <small class="text-muted tool-max-text"></small>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.tool-row').remove()"><i class="fas fa-trash"></i> Remove</button>
                </div>
            `;
            container.appendChild(row);
        }

        function updateToolName(select) {
            const selectedOption = select.options[select.selectedIndex];
            const row = select.closest('.tool-row');
            const nameInput = row.querySelector('.tool-name');
            const qtyInput = row.querySelector('.tool-qty');
            const priceInput = row.querySelector('.tool-price');
            const maxText = row.querySelector('.tool-max-text');
            
            if (selectedOption.value.startsWith('inv_')) {
                nameInput.value = selectedOption.text.split(' (Stock')[0].replace('[Supply] ', '').trim();
                priceInput.value = selectedOption.getAttribute('data-price');
                qtyInput.max = selectedOption.getAttribute('data-max');
                qtyInput.readOnly = false;
                maxText.innerText = 'Max: ' + qtyInput.max;
            } else if (selectedOption.value.startsWith('mac_')) {
                nameInput.value = selectedOption.text.split(' (')[0].replace('[Equipment] ', '').trim();
                priceInput.value = '0';
                qtyInput.value = '1';
                qtyInput.readOnly = true;
                qtyInput.removeAttribute('max');
                maxText.innerText = 'N/A';
            } else {
                nameInput.value = '';
                priceInput.value = '';
                qtyInput.value = '';
                qtyInput.readOnly = false;
                qtyInput.removeAttribute('max');
                maxText.innerText = '';
            }
        }
    </script>
</body>

</html>
