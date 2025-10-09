<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include '../../SQL/config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_POST['action']) && $_POST['action'] === 'submitVendor') {
    try {
        $pdo->beginTransaction();

        // Generate vendor registration number
        $registrationNumber = 'VEND-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        // Hash password
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insert vendor
        $stmt = $pdo->prepare("
            INSERT INTO vendors 
            (registration_number, company_name, company_address, contact_name, contact_title, phone, email, tin_vat, primary_product_categories, country, website, status, username, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
        ");
        $stmt->execute([
            $registrationNumber,
            $_POST['company_name'],
            $_POST['company_address'],
            $_POST['contact_name'],
            $_POST['contact_title'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['tin_vat'],
            $_POST['primary_product_categories'],
            $_POST['country'],
            $_POST['website'],
            $_POST['username'],
            $hashedPassword
        ]);

        $vendorId = $pdo->lastInsertId();

        // Accept all checkbox (1 if checked, else 0)
        $acceptAll = isset($_POST['accept_all']) ? 1 : 0;

        // Insert into acknowledgments
        $ackStmt = $pdo->prepare("
            INSERT INTO vendor_acknowledgments 
            (vendor_id, accept_use_policy, comply_procurement_policy, due_diligence_consent, data_processing_consent, contract_terms_accepted, warranty_terms_accepted, disposal_policy_accepted, info_certified, authorized_name, authorized_title, signed_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ackStmt->execute([
            $vendorId,
            $acceptAll, // accept_use_policy
            $acceptAll, // comply_procurement_policy
            $acceptAll, // due_diligence_consent
            $acceptAll, // data_processing_consent
            $acceptAll, // contract_terms_accepted
            $acceptAll, // warranty_terms_accepted
            $acceptAll, // disposal_policy_accepted
            $acceptAll, // info_certified
            $_POST['authorized_name'],
            $_POST['authorized_title'],
            $_POST['signed_date']
        ]);

        // Handle file uploads
        if (!empty($_FILES['documents']['name'][0])) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $docStmt = $pdo->prepare("INSERT INTO vendor_documents (vendor_id, doc_type, file_path) VALUES (?, ?, ?)");
            foreach ($_FILES['documents']['tmp_name'] as $key => $tmpName) {
                $filename = uniqid() . "_" . basename($_FILES['documents']['name'][$key]);
                $filePath = $uploadDir . $filename;
                if (move_uploaded_file($tmpName, $filePath)) {
                    $docStmt->execute([$vendorId, 'Compliance Document', 'uploads/' . $filename]);
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'vendor_id' => $vendorId,
            'registration_number' => $registrationNumber
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendor Registration</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="vendor.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center mb-4">Vendor Registration</h2>
    <div class="progress mb-4">
        <div id="progressBar" class="progress-bar" role="progressbar" style="width: 25%;">Step 1 of 5</div>
    </div>
    <form id="vendorForm" enctype="multipart/form-data">
        <!-- Step 1 -->
        <div class="step" id="step1">
            <h4>Vendor Profile</h4>
            <div class="mb-3"><label>Company Name:</label><input type="text" name="company_name" class="form-control" required></div>
            <div class="mb-3"><label>Company Address:</label><textarea name="company_address" class="form-control" required></textarea></div>
            <div class="mb-3"><label>Contact Name:</label><input type="text" name="contact_name" class="form-control" required></div>
            <div class="mb-3"><label>Contact Title:</label><input type="text" name="contact_title" class="form-control"></div>
            <div class="mb-3"><label>Phone:</label><input type="tel" name="phone" class="form-control" required></div>
            <div class="mb-3"><label>Email:</label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-3"><label>TIN/VAT:</label><input type="text" name="tin_vat" class="form-control" required></div>
            <div class="mb-3"><label>Primary Product Categories:</label><input type="text" name="primary_product_categories" class="form-control" required></div>
            <div class="mb-3"><label>Country:</label><input type="text" name="country" class="form-control" required></div>
            <div class="mb-3"><label>Website:</label><input type="url" name="website" class="form-control"></div>
            <button type="button" class="btn btn-primary nextBtn">Next</button>
        </div>
        <!-- Step 2 -->
<div class="step d-none" id="step2">
    <h4>Documents</h4>
    <div class="mb-3">
        <label>Upload Documents*</label>
        <input type="file" name="documents[]" multiple class="form-control" accept="application/pdf" required>
        <small class="text-muted">Only PDF files are allowed.</small>
    </div>
    <button type="button" class="btn btn-secondary prevBtn">Back</button>
    <button type="button" class="btn btn-primary nextBtn">Next</button>
</div>

       <!-- Step 3 -->
<div class="step d-none" id="step3">
    <h4 class="mb-4 text-center">Vendor Agreement & Acknowledgement</h4>

    <!-- Scrollable Agreement -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body" style="max-height: 350px; overflow-y: auto; padding: 20px; background-color: #fdfdfd; border: 1px solid #ddd; border-radius: 8px;">
            <p class="mb-4">
                This Vendor Agreement (“Agreement”) outlines the obligations, rights, and responsibilities between the registering vendor (“Vendor”) and the Company. By proceeding, you confirm your acceptance of these terms and conditions, which will become legally binding upon approval of your registration.
            </p>
            <ol style="line-height: 1.6;">
                <li><strong>Acceptable Use Policy</strong>Vendor shall comply with all platform usage guidelines, refraining from fraudulent activities, misrepresentation, or any violation of applicable laws.</li>
                <li><strong>Procurement Policy Compliance</strong>Vendor agrees to transparent pricing, ethical sourcing, timely deliveries, and adherence to company procurement standards.</li>
                <li><strong>Due Diligence Consent</strong>Vendor authorizes the Company to conduct background checks, credit assessments, and compliance audits.</li>
                <li><strong>Data Processing Consent</strong>Vendor consents to the lawful collection, processing, and storage of relevant personal and business information for vendor management purposes.</li>
                <li><strong>Contract Terms & Conditions</strong>Vendor agrees to abide by contractual obligations including payment terms, delivery schedules, liability clauses, and dispute resolution mechanisms.</li>
                <li><strong>Warranty & Return Policy</strong> Vendor guarantees the quality of goods and services supplied and accepts return or replacement of defective products.</li>
                <li><strong>Environmental & Disposal Policy</strong> Vendor commits to responsible disposal and recycling of waste materials in compliance with environmental regulations.</li>
                <li><strong>Accuracy of Information</strong> Vendor certifies that all submitted information is truthful and complete; false statements may result in immediate termination.</li>
            </ol>
        </div>
    </div>

 
    <div class="form-check mb-4">
        <input type="checkbox" name="accept_all" id="accept_all" class="form-check-input" required>
        <label class="form-check-label" for="accept_all">
            I have read, understood, and agree to all the terms and conditions outlined above.
        </label>
    </div>

    <!-- Signature Section -->
    <div class="card border-0 shadow-sm p-3 mb-4">
        <h5 class="mb-3">Authorized Signatory</h5>
        <div class="mb-3">
            <label class="form-label">Authorized Name*</label>
            <input type="text" name="authorized_name" class="form-control" placeholder="Full Name" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Authorized Title*</label>
            <input type="text" name="authorized_title" class="form-control" placeholder="Position / Title" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Date Signed*</label>
            <input type="date" name="signed_date" id="signed_date" class="form-control" readonly>
        </div>
    </div>

   
    <button type="button" class="btn btn-secondary prevBtn">Back</button>
    <button type="button" class="btn btn-primary nextBtn">Next</button>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
     
        let storedSignupDate = sessionStorage.getItem("registrationDate");

        if (!storedSignupDate) {
           
            let today = new Date().toISOString().split('T')[0];
            sessionStorage.setItem("registrationDate", today);
            storedSignupDate = today;
        }

        
        document.getElementById('signed_date').value = storedSignupDate;
    });
</script>


        <!-- Step 4 -->
      <div class="step d-none" id="step4">
    <h4>Account Creation</h4>

    <div class="mb-3">
        <label>Username*</label>
        <input type="text" name="username" id="username" class="form-control" required>
        <small id="username-status" class="form-text"></small>
    </div>

    <div class="mb-3">
        <label>Password*</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Confirm Password*</label>
        <input type="password" name="confirm_password" class="form-control" required>
    </div>

    <button type="button" class="btn btn-secondary prevBtn">Back</button>
    <button type="submit" id="submitBtn" class="btn btn-success" disabled>Submit</button>
</div>

<script>
document.getElementById("username").addEventListener("keyup", function() {
    let username = this.value.trim(),
        statusText = document.getElementById("username-status"),
        submitBtn = document.getElementById("submitBtn");

    if (username.length < 3) {
        statusText.textContent = "Username must be at least 3 characters.";
        statusText.style.color = "red";
        submitBtn.disabled = true;
        return;
    }

    fetch("check_username.php?username=" + encodeURIComponent(username))
    .then(r => r.json())
    .then(data => {
        if (data.status === "available" || data.status === "rejected") {
            statusText.textContent = " Username is available.";
            statusText.style.color = "green";
            submitBtn.disabled = false;
        } 
        else if (data.status === "pending" || data.status === "approved") {
            statusText.textContent = " Username is already taken.";
            statusText.style.color = "red";
            submitBtn.disabled = true;
        }
    });
});
</script>



        <!-- Step 5 -->
        <div class="step d-none" id="step5">
            <h4>Registration Submitted</h4>
            <div class="alert alert-success">
                <strong>Thank you!</strong> Your vendor application has been submitted successfully.<br>
                Your registration number is: <span id="regNumber" class="fw-bold"></span>.<br>
                Please wait 3–7 business days for approval. You will be notified by email once your registration is reviewed.
            </div>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentStep = 1;
function updateProgress(){ $("#progressBar").css("width",(currentStep/5*100)+"%").text("Step "+currentStep+" of 5"); }
$(".nextBtn").click(function(){
    if ($("#step"+currentStep+" :input[required]").filter(function(){return !this.value;}).length===0) {
        $("#step"+currentStep).addClass("d-none");
        currentStep++; $("#step"+currentStep).removeClass("d-none"); updateProgress();
    } else { alert("Please fill in all required fields."); }
});
$(".prevBtn").click(function(){ $("#step"+currentStep).addClass("d-none"); currentStep--; $("#step"+currentStep).removeClass("d-none"); updateProgress(); });
$("#vendorForm").submit(function(e){
    e.preventDefault();
    if ($("input[name=password]").val() !== $("input[name=confirm_password]").val()) {
        alert("Passwords do not match"); return;
    }
    let formData = new FormData(this);
    formData.append("action", "submitVendor");
    $.ajax({
        url: "vendor_registration.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            try {
                let result = JSON.parse(res);
                if(result.status === "success") {
                    $("#step"+currentStep).addClass("d-none");
                    currentStep = 5;
                    $("#regNumber").text(result.registration_number);
                    $("#step5").removeClass("d-none");
                    updateProgress();
                } else {
                    alert("Error: " + result.message);
                }
            } catch (err) {
                alert("Unexpected server response: " + res);
            }
        }
    });
});


</script>
</body>
</html>
