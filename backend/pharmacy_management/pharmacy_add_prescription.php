<?php
include '../../SQL/config.php';
require 'classes/prescription.php';
require 'classes/medicine.php';

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}


$medicineObj = new Medicine($conn);
$medicines = $medicineObj->getAllMedicines();

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

$prescription = new Prescription($conn);
$doctors = $prescription->getDoctors();
$patients = $prescription->getPatients();
// $medicines = $prescription->getMedicines();

$doctors = [];
$result_doc = $prescription->getDoctors();
if ($result_doc && $result_doc->num_rows > 0) {
    while ($row = $result_doc->fetch_assoc()) {
        $doctors[] = $row;
    }
}


$patients = [];
$result_pat = $prescription->getPatients();
if ($result_pat && $result_pat->num_rows > 0) {
    while ($row = $result_pat->fetch_assoc()) {
        $patients[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Pharmacy Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | Dashboard</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="pharmacy_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-cast" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_med_inventory.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-solid fa-capsules" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Medicine Inventory</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-link" data-bs-toggle="collapse" href="#prescriptionMenu" role="button"
                    aria-expanded="false" aria-controls="prescriptionMenu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-solid fa-file-prescription" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Prescription</span>
                </a>
                <ul class="collapse list-unstyled ms-3" id="prescriptionMenu">
                    <li>
                        <a href="pharmacy_prescription.php" class="sidebar-link">View Prescriptions</a>
                    </li>
                    <li>
                        <a href="pharmacy_add_prescription.php" class="sidebar-link">Add Prescription</a>
                    </li>
                </ul>
            </li>


            <li class="sidebar-item">
                <a href="pharmacy_sales.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-solid fa-chart-line" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Sales</span>
                </a>
            </li>

        </aside>
        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor"
                            class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?>
                            <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton"
                            style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong
                                        style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php"
                                    style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->

            <div class="content">
                <div class="title-container">
                    <i class="fa-solid fa-capsules"></i>
                    <h1 class="page-title">Add Prescription</h1>
                </div>

                <div class="content mt-4">
                    <!-- Header Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2></h2>
                        <div>
                            <!-- Button trigger modal -->
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#prescriptionModal">
                                Add Prescription
                            </button>
                        </div>
                    </div>



                    <!-- Modal -->
                    <div class="modal fade" id="prescriptionModal" tabindex="-1"
                        aria-labelledby="prescriptionModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title" id="prescriptionModalLabel">Add Prescription</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <form action="process_prescription.php" method="POST">
                                    <div class="modal-body">

                                        <!-- Doctor -->
                                        <div class="mb-3">
                                            <label for="doctor_id" class="form-label">Doctor</label>
                                            <select class="form-select" id="doctor_id" name="doctor_id" required>
                                                <option value="">-- Select Doctor --</option>
                                                <?php foreach ($doctors as $doc): ?>
                                                <option value="<?= $doc['employee_id'] ?>">
                                                    <?= $doc['first_name'] . ' ' . $doc['last_name'] ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Patient -->
                                        <div class="mb-3">
                                            <label for="patient_id" class="form-label">Patient</label>
                                            <select class="form-select" id="patient_id" name="patient_id" required>
                                                <option value="">-- Select Patient --</option>
                                                <?php foreach ($patients as $pat): ?>
                                                <option value="<?= $pat['patient_id'] ?>">
                                                    <?= htmlspecialchars($pat['fname'] . ' ' . $pat['lname']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Medicine Rows -->
                                        <div id="medicineRows">
                                            <div class="medicine-row row mb-2">
                                                <div class="col-md-4">
                                                    <label class="form-label">Medicine</label>
                                                    <select class="form-select medicine-select" name="med_id[]"
                                                        required>
                                                        <option value="">-- Select Medicine --</option>
                                                        <?php foreach ($medicines as $med): ?>
                                                        <option value="<?= htmlspecialchars($med['med_id']) ?>"
                                                            data-dosage="<?= htmlspecialchars($med['dosage']) ?>"
                                                            data-stock="<?= htmlspecialchars($med['stock_quantity'] ?? 0) ?>">
                                                            <?= htmlspecialchars($med['med_name'] . ' (' . $med['dosage'] . ')') ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" class="dosage-input" name="dosage[]">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Stock</label>
                                                    <input type="text" class="form-control stock-display" value=""
                                                        readonly>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Quantity</label>
                                                    <input type="number" class="form-control" name="quantity[]"
                                                        required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Note</label>
                                                    <input type="text" class="form-control" name="note[]"
                                                        placeholder="e.g. 3x a day">
                                                </div>
                                                <div class="col-md-1 d-flex align-items-end">
                                                    <button type="button"
                                                        class="btn btn-danger remove-medicine">X</button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add Medicine Button -->
                                        <button type="button" id="addMedicine" class="btn btn-success mb-3">+ Add
                                            Medicine</button>

                                        <!-- Status (Auto Pending, hidden from doctor) -->
                                        <input type="hidden" name="status" value="Pending">

                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary">Save Prescription</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>


                    <script>
                    // Update medicine info and validate quantity
                    function updateMedicineInfo(selectElem) {
                        let selected = selectElem.options[selectElem.selectedIndex];
                        let dosage = selected.getAttribute('data-dosage') || '';
                        let stock = selected.getAttribute('data-stock') || '0';

                        let row = selectElem.closest('.medicine-row');
                        row.querySelector('.dosage-input').value = dosage;
                        row.querySelector('.stock-display').value = stock;

                        // Remove old input listener to prevent stacking
                        let qtyInput = row.querySelector('input[name="quantity[]"]');
                        let newQtyInput = qtyInput.cloneNode(true);
                        qtyInput.parentNode.replaceChild(newQtyInput, qtyInput);

                        newQtyInput.addEventListener('input', function() {
                            let currentStock = parseInt(row.querySelector('.stock-display').value) || 0;
                            let enteredQty = parseInt(this.value);

                            if (!isNaN(enteredQty) && enteredQty > currentStock) {
                                alert("Entered quantity exceeds available stock (" + currentStock + ")");
                                this.value = currentStock;
                            }
                        });

                        updateMedicineOptions(); // refresh options to disable already selected medicines
                    }

                    // Disable already selected medicines in all rows
                    function updateMedicineOptions() {
                        let selectedValues = Array.from(document.querySelectorAll('.medicine-select'))
                            .map(sel => sel.value)
                            .filter(val => val !== '');

                        document.querySelectorAll('.medicine-select').forEach(sel => {
                            Array.from(sel.options).forEach(option => {
                                if (option.value !== '' && option.value !== sel.value) {
                                    option.disabled = selectedValues.includes(option.value);
                                }
                            });
                        });
                    }

                    // Bind existing medicine selects
                    document.querySelectorAll('.medicine-select').forEach(sel => {
                        sel.addEventListener('change', function() {
                            updateMedicineInfo(this);
                        });
                    });

                    // Add new medicine row
                    document.getElementById('addMedicine').addEventListener('click', function() {
                        let newRow = document.querySelector('.medicine-row').cloneNode(true);

                        // Clear inputs and selects
                        newRow.querySelectorAll('input').forEach(input => input.value = '');
                        newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);

                        document.getElementById('medicineRows').appendChild(newRow);

                        // Re-bind events for the new row
                        let newSelect = newRow.querySelector('.medicine-select');
                        newSelect.addEventListener('change', function() {
                            updateMedicineInfo(this);
                        });

                        newRow.querySelector('.remove-medicine').addEventListener('click', function() {
                            newRow.remove();
                            updateMedicineOptions(); // re-enable removed medicine
                        });

                        updateMedicineOptions(); // refresh options for all rows
                    });

                    // Remove medicine row buttons
                    document.querySelectorAll('.remove-medicine').forEach(btn => {
                        btn.addEventListener('click', function() {
                            this.closest('.medicine-row').remove();
                            updateMedicineOptions();
                        });
                    });

                    // Prevent form submission if any quantity is 0 or empty
                    document.querySelector('form').addEventListener('submit', function(e) {
                        let invalid = false;
                        document.querySelectorAll('input[name="quantity[]"]').forEach(qtyInput => {
                            let val = parseInt(qtyInput.value);
                            if (isNaN(val) || val <= 0) {
                                invalid = true;
                                qtyInput.classList.add(
                                'is-invalid'); // optional: highlight invalid fields
                            } else {
                                qtyInput.classList.remove('is-invalid');
                            }
                        });

                        if (invalid) {
                            e.preventDefault();
                            alert("Please enter a valid quantity greater than 0 for all medicines.");
                        }
                    });
                    </script>








                    <!-- Medicine Inventory Table -->
                    <table class="table">
                        <thead class="table">
                            <tr>
                                <th>Prescription ID</th>
                                <th>Doctor</th>
                                <th>Patient</th>
                                <th>Medicines</th>
                                <th>Total Quantity</th>
                                <th>Note</th>
                                <th>Date Prescribed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT 
            p.prescription_id,
            CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
            CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
            GROUP_CONCAT(
                CONCAT(m.med_name, ' (', i.dosage, ') - Qty: ', i.quantity_prescribed)
                SEPARATOR '<br>'
            ) AS medicines_list,
            SUM(i.quantity_prescribed) AS total_quantity,
            p.note,
            DATE_FORMAT(p.prescription_date, '%b %e, %Y %l:%i%p') AS formatted_date,
            p.status
        FROM pharmacy_prescription p
        JOIN patientinfo pi 
            ON p.patient_id = pi.patient_id
        JOIN hr_employees e 
            ON p.doctor_id = e.employee_id 
            AND LOWER(e.profession) = 'doctor'
        JOIN pharmacy_prescription_items i 
            ON p.prescription_id = i.prescription_id
        JOIN pharmacy_inventory m 
            ON i.med_id = m.med_id
        GROUP BY p.prescription_id
        ORDER BY p.prescription_date DESC";

                            $result = $conn->query($sql);

                            if ($result === false) {
                                echo "<tr><td colspan='8' class='text-danger'>SQL Error: " . $conn->error . "</td></tr>";
                            } elseif ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $noteId = "noteModal" . $row['prescription_id'];

                                    // Determine badge class based on status
                                    $status = strtolower($row['status']);
                                    if ($status === 'dispensed') {
                                        $badgeClass = 'bg-success';
                                    } elseif ($status === 'pending') {
                                        $badgeClass = 'bg-secondary';
                                    } elseif ($status === 'cancelled' || $status === 'out of stock') {
                                        $badgeClass = 'bg-danger';
                                    } else {
                                        $badgeClass = 'bg-info';
                                    }

                                    echo "<tr>
                    <td>{$row['prescription_id']}</td>
                    <td>{$row['doctor_name']}</td>
                    <td>{$row['patient_name']}</td>
                    <td>{$row['medicines_list']}</td>
                    <td>{$row['total_quantity']}</td>
                    <td><button class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#{$noteId}'>View Note</button></td>
                    <td>{$row['formatted_date']}</td>
                    <td><span class='badge {$badgeClass} text-uppercase fw-bold'>" . htmlspecialchars($row['status']) . "</span></td>
                </tr>";

                                    // Modal for each note
                                    echo "
                <div class='modal fade' id='{$noteId}' tabindex='-1'>
                    <div class='modal-dialog'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title'>Prescription Note</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                            </div>
                            <div class='modal-body'>
                                <p>" . nl2br(htmlspecialchars($row['note'])) . "</p>
                            </div>
                            <div class='modal-footer'>
                                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                ";
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No prescriptions found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>







                </div>
            </div>





            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
    function addMedicine() {
        let medItem = document.querySelector('#med-items .row').cloneNode(true);
        document.getElementById('med-items').appendChild(medItem);
    }
    </script>
    <script>
    const toggler = document.querySelector(".toggler-btn");
    toggler.addEventListener("click", function() {
        document.querySelector("#sidebar").classList.toggle("collapsed");
    });
    </script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/all.min.js"></script>

    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>