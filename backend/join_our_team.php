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
                    <?php foreach ($posts as $row): ?>
                        <div class="job_post-item">
                            <?php if (!empty($row['image'])): ?>
                                <img src="hr_management/uploads/job_pics/<?= htmlspecialchars(basename($row['image'])) ?>" alt="Job Post Image" style="max-width: 100%; height: auto; margin-bottom: 10px;">
                                <p style="font-size: 13px; margin-top: 5px; text-align: justify;">
                                    <span style="font-weight: bold; color: black;">Profession: </span><?= htmlspecialchars($row['profession']) ?><br>
                                    <span style="font-weight: bold; color: black;">Title: </span><?= htmlspecialchars($row['title']) ?><br>
                                    <span style="font-weight: bold; color: black;">Job Position: </span><?= htmlspecialchars($row['job_position']) ?><br>
                                    <span style="font-weight: bold; color: black;">Specialization: </span><?= htmlspecialchars($row['specialization']) ?><br>
                                    <span style="font-weight: bold; color: black;">Job Description: </span><?= htmlspecialchars($row['job_description']) ?><br>
                                </p>
                                <center>
                                    <button class="apply" onclick="openForm('<?= htmlspecialchars($row['profession']) ?>','<?= htmlspecialchars($row['job_position']) ?>', '<?= htmlspecialchars($row['specialization']) ?>', '<?= htmlspecialchars($row['title']) ?>')">APPLY NOW!!!!</button>
                                </center>
                            <?php endif; ?>
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
                        <input type="file" id="resume" name="resume" required>

                        <label for="application_letter">Upload Application Letter</label>
                        <input type="file" id="application_letter" name="application_letter" required>

                        <label for="government_id">Upload Goverment ID</label>
                        <input type="file" id="government_id" name="government_id" required>

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