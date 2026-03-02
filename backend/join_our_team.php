<?php
require '../SQL/config.php';
require 'assets/classes/JobPost.php';

$jobPost = new JobPost($conn);
$posts = $jobPost->getAllPosts();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System | Login Page</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/join_our_team.css">
</head>

<body>
    <div class="job-container">
        <div class="job-box">
            <a href="../index.php" class="logo">
                <center>
                    <img src="assets/image/logo-dark.png" alt="HMS Logo" style="height: 30px;">
                </center>
            </a>
            <div class="subtext">
                <h1>We're Looking for Passionate People Like You</h1>
                <p>at Dr. Eduardo V. Roquero Memorial Hospital</p>
            </div>

        <div class="job_post-container">
            <?php if (!empty($posts)): ?>

                <?php
                // Function to format text to bullet list, preserve existing bullets
                function formatToListPreserve($text) {
                    $lines = explode("\n", $text); // split by line break
                    $output = "<ul>";
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line != '') {
                            // Remove leading bullet character if exists
                            $line = preg_replace('/^•\s*/', '', $line);
                            $output .= "<li>" . htmlspecialchars($line) . "</li>";
                        }
                    }
                    $output .= "</ul>";
                    return $output;
                }
                ?>

                <?php foreach ($posts as $row): ?>
                    <div class="job_post-item">

                        <!-- Job Image -->
                        <?php if (!empty($row['image'])): ?>
                            <img src="data:image/jpeg;base64,<?= htmlspecialchars($row['image']) ?>" 
                                alt="Job Post Image">
                        <?php endif; ?>

                        <!-- Job Info -->
                        <div class="job_post-info" style="font-size: 14px; line-height: 1.5; text-align: justify; color: black; margin-top: 10px;">
                            <p><strong>Profession:</strong> <?= htmlspecialchars($row['profession']) ?></p>
                            <p><strong>Title:</strong> <?= htmlspecialchars($row['title']) ?></p>
                            <p><strong>Job Position:</strong> <?= htmlspecialchars($row['job_position']) ?></p>
                            <p><strong>Specialization:</strong> <?= htmlspecialchars($row['specialization']) ?></p>

                            <!-- Job Description -->
                            <p><strong>Job Description:</strong></p>
                            <?= formatToListPreserve($row['job_description']) ?>

                            <!-- Job Qualification -->
                            <p><strong>Job Qualification:</strong></p>
                            <?= formatToListPreserve($row['job_qualification']) ?>
                        </div>

                        <!-- Apply Button -->
                        <div style="text-align: center; margin-top: 10px;">
                            <button class="apply" onclick="openForm(
                                '<?= htmlspecialchars($row['profession']) ?>',
                                '<?= htmlspecialchars($row['job_position']) ?>',
                                '<?= htmlspecialchars($row['specialization']) ?>',
                                '<?= htmlspecialchars($row['title']) ?>'
                            )">APPLY NOW</button>
                        </div>

                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <p style="color:white; text-align:center;">No job post available at the moment.</p>
            <?php endif; ?>
        </div>

            <div id="jobApplicationForm" class="popup-form">
                <div class="form-container">
                    <bttn class="close-btn" onclick="closeForm()">X</bttn>
                    <center>
                        <h3 style="font-weight: bold;">Job Application Form</h3>
                    </center>
                    <form action="hr_management/recruitment_onboarding_module/submit_application.php" method="post" enctype="multipart/form-data">

                        <br />
                        <br />

                        <label for="profession">Profession:</label>
                        <input type="text" id="profession" name="profession" readonly>

                        <label for="job_title">Job Title:</label>
                        <input type="text" id="job_title" name="job_title" readonly>

                        <label for="job_position">Job Position:</label>
                        <input type="text" id="job_position" name="job_position" readonly>

                        <label for="specialization">Specialization:</label>
                        <input type="text" id="specialization" name="specialization" readonly>
                        <br />
                        <br />

                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" required>

                        <label for="middle_name">Middle Name:</label>
                        <input type="text" id="middle_name" name="middle_name">

                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" required>

                        <label for="suffix_name">Suffix Name:</label>
                        <input type="text" id="suffix_name" name="suffix_name">

                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>

                        <label for="phone">Phone Number:</label>
                        <input type="text" id="phone" name="phone" required>

                        <label for="address">Address:</label>
                        <input type="text" id="address" name="address" required>

                        <center>
                            <h4 style="font-weight: bold;">Upload Documents</h4>
                            <span style="color: red;font-weight: bold;font-size: 15px;">*Maximum file size: 5MB per document. Only PDF, JPG, JPEG, or PNG formats are accepted.*</span>
                        </center>
                        <br />

                        <label for="resume">Upload Resume</label>
                        <input type="file" id="resume" name="resume" required accept=".pdf">

                        <label for="application_letter">Upload Application Letter</label>
                        <input type="file" id="application_letter" name="application_letter" required>

                        <label for="license_id">Upload License ID</label>
                        <input type="file" id="license_id" name="license_id" required>

                        <label for="nbi_clearance">Upload NBI Clearance</label>
                        <input type="file" id="nbi_clearance" name="nbi_clearance" required>

                        <label for="id_picture">Upload 2x2 Picture</label>
                        <input type="file" id="id_picture" name="id_picture" required>

                        <label for="other_documents">Upload Other Supporting Documents</label>
                        <input type="file" id="other_documents" name="other_documents[]" multiple>

                        <button type="submit">Submit Application</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
    <script>
        function openForm(profession, position, specialization, title) {
            document.getElementById("jobApplicationForm").style.display = "flex";
            document.getElementById("profession").value = profession;
            document.getElementById("job_position").value = position;
            document.getElementById("specialization").value = specialization;
            document.getElementById("job_title").value = title;
        }

        function closeForm() {
            document.getElementById("jobApplicationForm").style.display = "none";
        }
    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>