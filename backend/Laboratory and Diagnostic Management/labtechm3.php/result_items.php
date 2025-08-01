<?php
session_start();
include '../../../SQL/config.php';
require_once 'resultitems_class.php';
require_once 'testresult_class.php';


//log in 
if (!isset($_SESSION['labtech']) || $_SESSION['labtech'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

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

//main content
$labdiagnostic_resultitems = new labdiagnostic_resultitems($conn);

// Get result_id from URL if available
$result_id = isset($_GET['result_id']) ? $_GET['result_id'] : null;

// Handle selection of result_id from dropdown
if (isset($_POST['select_result']) && !empty($_POST['selected_result_id'])) {
    $result_id = $_POST['selected_result_id'];
    // Redirect to the same page with the selected result_id in the URL to keep it clean
    header('Location: result_items.php?result_id=' . $result_id);
    exit();
}

// Get all result items for the specific result if ID is provided
if ($result_id) {
    $resultItems = $labdiagnostic_resultitems->getByResultId($result_id)->fetch_all(MYSQLI_ASSOC);
    $header = $labdiagnostic_resultitems->getHeaderInfo($result_id);
} else {
    $resultItems = [];
    $header = null;
}
//Get  dropdown data
$testResults = $conn->query("SELECT  result_id FROM labdiagnostic_testresults
ORDER BY result_id DESC")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
$message = '';
$error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {


if (isset($_POST['create'])) {

    $labdiagnostic_resultitems->result_id = $_POST['result_id'];
    $labdiagnostic_resultitems->parameter = $_POST['parameter'];
    $labdiagnostic_resultitems->result_value = $_POST['result_value'];
    $labdiagnostic_resultitems->normal_range = $_POST['normal_range'];
    $labdiagnostic_resultitems->units = $_POST['units'];
    $labdiagnostic_resultitems->flag = $_POST['flag'];

    if ($labdiagnostic_resultitems->create()) {
        $_SESSION['message'] = "Result item created successfully!";
        
        $resultItems = $labdiagnostic_resultitems->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
        $_SESSION['error'] =  "Unable to create result item.";
    }
    header('Location: result_items.php?item_id' . urlencode($item_id));
    exit();
}

    elseif (isset($_POST['update'])) {
    $labdiagnostic_resultitems->item_id = $_POST['item_id'];
    $labdiagnostic_resultitems->parameter = $_POST['parameter'];
    $labdiagnostic_resultitems->result_value = $_POST['result_value'];
    $labdiagnostic_resultitems->normal_range = $_POST['normal_range'];
    $labdiagnostic_resultitems->units = $_POST['units'];
    $labdiagnostic_resultitems->flag = $_POST['flag'];  
    
    if ($labdiagnostic_resultitems->update()) {
        $_SESSION['message'] = "Result item updated successfully!";
        
        $resultItems = $labdiagnostic_resultitems->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
        $_SESSION['error'] =  "Unable to update result item.";
    }
    header('Location: result_items.php?item_id' . urlencode($item_id));
    exit();
}
    elseif (isset($_POST['delete'])) {
    $labdiagnostic_resultitems->item_id = $_POST['item_id'];
    
    if ($labdiagnostic_resultitems->delete()) {
     $message = "Result item deleted successfully!";
        
        // After deleting, re-fetch items for the *current* result_id if one is selected
        if ($result_id) {
            $resultItems = $labdiagnostic_resultitems->getByResultId($result_id)->fetch_all(MYSQLI_ASSOC);
        } else {
            $resultItems = $labdiagnostic_resultitems->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        $error =  "Unable to delete result item.";    
    }
}
}
//get all result
if (!isset($resultItems) || empty($resultItems)) { // Changed condition to check if $resultItems is empty
   if (!$result_id) { // Only fetch all if no specific result_id is selected
       $resultItems = $labdiagnostic_resultitems->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
   }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Laboratory and Diagnostic Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="../assets/CSS/labtestform.css">
</head>
<style>

 .header-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .header-info p {
            margin-bottom: 5px;
        }
    </style>
<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
        
            <li class="sidebar-item">
                <a href="../labtech_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#labtech"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building"
                        viewBox="0 0 16 16" style="margin-bottom: 7px;">
                        <path
                            d="M4 2.5a.5.5 0
             0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z" />
                        <path
                            d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z" />
                    </svg>
                    <span style="font-size: 18px;">Test Booking and Scheduling</span>
                </a>

                <ul id="labtech" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../test.php" class="sidebar-link">Test Available</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../../Laboratory and Diagnostic Management/labtechm1.php/appointment.php" class="sidebar-link">Appointment</a>
                    </li>
                </ul>
            </li>

<!-- Module 2: Sample Collection & Tracking -->
<li class="sidebar-item">
    <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#sample"
        aria-expanded="true" aria-controls="auth">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-collection" viewBox="0 0 16 16">
            <path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-11zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7zM0 13a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 16 13V6a1.5 1.5 0 0 0-1.5-1.5h-13A1.5 1.5 0 0 0 0 6v7zm1.5.5A.5.5 0 0 1 1 13V6a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-13z"/>
        </svg>
        <span style="font-size: 18px;">Sample Collection & Tracking</span>
    </a>
    <ul id="sample" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
        <li class="sidebar-item">
            <a href="../Sample/collection_log.php" class="sidebar-link">Collection Log</a>
        </li>
        <li class="sidebar-item">
            <a href="../Sample/sample_tracking.php" class="sidebar-link">Sample Tracking</a>
        </li>
        <li class="sidebar-item">
            <a href="../Sample/collection_team.php" class="sidebar-link">Collection Team</a>
        </li>
    </ul>
</li>

<!-- Module 3: Report Generation & Delivery -->
<li class="sidebar-item">
    <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#report"
        aria-expanded="true" aria-controls="auth">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
            <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
            <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
        </svg>
        <span style="font-size: 18px;">Report Generation & Delivery</span>
    </a>
    <ul id="report" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
        <li class="sidebar-item">
            <a href="../../Laboratory and Diagnostic Management/labtechm3.php/test_results.php" class="sidebar-link">Test Results</a>
        </li>
        <li class="sidebar-item">
            <a href="../../Laboratory and Diagnostic Management/labtechm3.php/result_items.php" class="sidebar-link">Result items</a>
        </li>
        <li class="sidebar-item">
            <a href="../../Laboratory and Diagnostic Management/labtechm3.php/report_deliveries.php" class="sidebar-link">Delivery Methods</a>
        </li>
    </ul>
</li>

<!-- Module 4: Equipment Maintenance -->
<li class="sidebar-item">
    <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#equipment"
        aria-expanded="true" aria-controls="auth">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 16 16">
            <path d="M1 0 0 1l2.2 3.081a1 1 0 0 0 .815.419h.07a1 1 0 0 1 .708.293l2.675 2.675-2.617 2.654A3.003 3.003 0 0 0 0 13a3 3 0 1 0 5.878-.851l2.654-2.617.968.968-.305.914a1 1 0 0 0 .242 1.023l3.27 3.27a.997.997 0 0 0 1.414 0l1.586-1.586a.997.997 0 0 0 0-1.414l-3.27-3.27a1 1 0 0 0-1.023-.242L10.5 9.5l-.96-.96 2.68-2.643A3.005 3.005 0 0 0 16 3c0-.269-.035-.53-.102-.777l-2.14 2.141L12 4l-.364-1.757L13.777.102a3 3 0 0 0-3.675 3.68L7.462 6.46 4.793 3.793a1 1 0 0 1-.293-.707v-.071a1 1 0 0 0-.419-.814L1 0Zm9.646 10.646a.5.5 0 0 1 .708 0l2.914 2.915a.5.5 0 0 1-.707.707l-2.915-2.914a.5.5 0 0 1 0-.708ZM3 11l.471.242.529.026.287.445.445.287.026.529L5 13l-.242.471-.026.529-.445.287-.287.445-.529.026L3 15l-.471-.242L2 14.732l-.287-.445L1.268 14l-.026-.529L1 13l.242-.471.026-.529.445-.287.287-.445.529-.026L3 11Z"/>
        </svg>
        <span style="font-size: 18px;">Equipment Maintenance</span>
    </a>
    <ul id="equipment" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
        <li class="sidebar-item">
            <a href="../Equipment/equipment_list.php" class="sidebar-link">Equipment Inventory</a>
        </li>
        <li class="sidebar-item">
            <a href="../Equipment/maintenance_schedule.php" class="sidebar-link">Maintenance Schedule</a>
        </li>
        <li class="sidebar-item">
            <a href="../Equipment/service_log.php" class="sidebar-link">Service Log</a>
        </li>
    </ul>
</li>
</aside>
<!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul"
                            viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
           
            <div class="container">
                <h1>Test Result Details</h1>
    
        <?php if (!empty($message)): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>
                
        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

    <form method="POST" class="mb-3">
        <div class="form-group d-flex align-items-center">
            <label for="selected_result_id" class="me-2">Select Result ID:</label>
            <select name="selected_result_id" id="selected_result_id" class="form-select" style="width: auto;">
                <option value="">-- Select Result --</option>
                <?php foreach ($testResults as $testResult): ?>
                    <option value="<?= $testResult['result_id'] ?>" <?= ($result_id == $testResult['result_id']) ? 'selected' : '' ?>>
                        <?= $testResult['result_id'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="select_result" class="btn btn-info ms-2">View Details</button>
        </div>
    </form>
        
 <?php if ($header): ?>
                    <div class="header-info">
                        <p><strong>Patient:</strong> <?= $header['patient_name'] ?></p>
                        <p><strong>Test:</strong> <?= $header['test_name'] ?></p>
                        <p><strong>Doctor:</strong> <?= $header['doctor_name'] ?></p>
                        <p><strong>Date:</strong> <?= $header['result_date'] ?></p>
                    </div>
                <?php else: ?>
                    <div class="alert error">Please select a result ID to view details.</div>
                <?php endif; ?>
                
                <button class="btn btn-primary" onclick="openModal()">Add New Result Item</button>

  <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Result Value</th>
                                <th>Normal Range</th>
                                <th>Units</th>
                                <th>Flag</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultItems as $item): ?>
                                <tr data-item-id="<?= $item['item_id'] ?>">
                                    <td><?= $item['parameter'] ?></td>
                                    <td><?= $item['result_value'] ?></td>
                                    <td><?= $item['normal_range'] ?></td>
                                    <td><?= $item['units'] ?></td>
                                    <td><?= $item['flag'] ?? '' ?></td>
                                    <td>
                                        <button class="btn btn-warning" onclick='editItem(<?= json_encode($item) ?>)'>Edit</button>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Delete this item?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($resultItems)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No result items found for this result ID.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="modal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal()">&times;</span>
                        <h2 id="modalTitle">Add Result Item</h2>
                        <form method="POST">
                            <input type="hidden" name="item_id" id="item_id">
                            <input type="hidden" name="result_id" id="result_id" value="<?= $result_id ?>">
                            
                            <div class="form-group">
                                <label for="parameter">Parameter:</label>
                                <input type="text" name="parameter" id="parameter" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="result_value">Result Value:</label>
                                <input type="text" name="result_value" id="result_value" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="normal_range">Normal Range:</label>
                                <input type="text" name="normal_range" id="normal_range">
                            </div>
                            
                            <div class="form-group">
                                <label for="units">Units:</label>
                                <input type="text" name="units" id="units">
                            </div>
                            
                            <div class="form-group">
                                <label for="flag">Flag:</label>
                                <select name="flag" id="flag">
                                    <option value="">-- Select --</option>
                                    <option value="Normal">Normal</option>
                                    <option value="High">High</option>
                                    <option value="Low">Low</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="create" id="createBtn" class="btn btn-primary">Save</button>
                                <button type="submit" name="update" id="updateBtn" class="btn btn-primary" style="display:none;">Update</button>
                                <button type="button" onclick="closeModal()" class="btn btn-danger">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script src="../assets/Bootstrap/resultItems.js"></script>

        <!----- End of Main Content ----->
   
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>