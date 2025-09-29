<?php
require '../../../SQL/config.php';
include '../includes/FooterComponent.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once 'classes/ApplicantManager.php';

Auth::checkHR();

$conn = $conn;

$userId = Auth::getUserId();
if (!$userId) {
    die("User ID not set.");
}

$userModel = new User($conn);
$applicantManager = new ApplicantManager($conn);

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) {
    die("User not found.");
}

$rejectedApplicants = $applicantManager->getRejectedApplicants();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | HR Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="css/applicant_history.css">
</head>
<body>

    <!----- Full-page Loader ----->
    <div id="loading-screen">
        <div class="loader">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
    
    <div class="main">
        <div class="mamamo">
            <a href="applicant_management.php" style="color:black;">
                <svg xmlns="http://www.w3.org/2000/svg" width="35px" height="35px" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
                </svg>
            </a>

            <div class="history">
                <p style="text-align: center; font-size: 35px; font-weight: bold; padding-bottom: 20px; color: black;">Rejected Applicant History</p>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Position Applied</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Documents</th>
                            <th>Date Rejected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rejectedApplicants)): ?>
                            <?php foreach ($rejectedApplicants as $applicant): ?>
                                <tr>
                                    <td><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['middle_name'] . ' ' . $applicant['last_name'] . ' ' . $applicant['suffix_name']); ?></td>
                                    <td><?= htmlspecialchars($applicant['role']) ?></td>
                                    <td><?= htmlspecialchars($applicant['email']) ?></td>
                                    <td><?= htmlspecialchars($applicant['phone']) ?></td>
                                    <td>
                                        <?php 
                                            $docs = $applicantManager->getApplicantDocuments($applicant['applicant_id']);
                                            if (!empty($docs)) {
                                                // Group documents by type
                                                $groupedDocs = [];
                                                foreach ($docs as $doc) {
                                                    $type = !empty($doc['document_type']) ? $doc['document_type'] : 'Others';

                                                    // check kung anong column name ang meron
                                                    $filePath = $doc['file_name'] ?? ($doc['document_path'] ?? ($doc['file_path'] ?? ''));
                                                    
                                                    if (!empty($filePath)) {
                                                        $groupedDocs[$type][] = [
                                                            'path' => htmlspecialchars($filePath),
                                                            'name' => htmlspecialchars(basename($filePath))
                                                        ];
                                                    }
                                                }
                                                ?>

                                                <?php foreach ($groupedDocs as $docType => $files): ?>
                                                    <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $docType))); ?>:</strong><br>
                                                    <?php foreach ($files as $file): ?>
                                                        <a href="<?= $file['path']; ?>" target="_blank"><?= $file['name']; ?></a><br>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>

                                            <?php 
                                            } else {
                                                echo "No documents uploaded.";
                                            }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($applicant['update_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No rejected applicants found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!----- Footer Content ----->
    <?php FooterComponent::render();?>

    <!----- End of Footer Content ----->

    <script>

        window.addEventListener("load", function(){
            setTimeout(function(){
                document.getElementById("loading-screen").style.display = "none";
            }, 2000);
        });

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
