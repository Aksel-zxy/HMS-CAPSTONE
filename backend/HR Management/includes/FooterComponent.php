<?php
class FooterComponent {
  public static function render() {
    ?>
    <style>
      .footer {
        background-color: #343a40;
        color: #ffffff;
        text-align: center;
        padding: 20px 0;
        width: 100%;
        font-size: 14px;
      }

      .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
      }

      .footer-links {
        margin-top: 8px;
      }

      .footer-links a {
        color: #ccc;
        text-decoration: none;
        margin: 0 5px;
      }

      .footer-links a:hover {
        color: #ffffff;
        text-decoration: underline;
      }

      /* Modal Styling */
      .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.5);
      }

      .modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 0;
        width: 90%;
        max-width: 600px;
        border-radius: 10px;
        border: 1px solid #dee2e6;
      }

      .modal-header {
        background-color: black;
        color: #fff;
        padding: 10px 20px;
        border-bottom: 1px solid #dee2e6;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .modal-title {
        font-weight: bold;
        margin: 0;
      }

      .btn-close {
        background-color: red;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        font-weight: bold;
        cursor: pointer;
      }

      .btn-close:hover {
        background-color: dark-red;
      }

      .modal-body {
        padding: 20px;
      }

      .modal-footer {
        padding: 10px 20px;
        border-top: 1px solid #dee2e6;
        text-align: right;
      }

      .modal-footer .btn {
        min-width: 100px;
      }
    </style>

    <footer class="footer">
      <div class="footer-container">
        <p>&copy; <?php echo date("Y"); ?> Dr. Eduardo V. Roquero Memorial Hospital. All rights reserved.</p>
        <div class="footer-links">
          <a href="#" id="openPrivacy">Privacy Policy</a> |
          <a href="#" id="openTerms">Terms of Use</a>
        </div>
      </div>
    </footer>

    <!-- Privacy Modal -->
    <div id="privacyModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Privacy Policy</h5>
          <button class="btn-close" id="closePrivacy">&times;</button>
        </div>
        <div class="modal-body">
          <p>
            Dr. Eduardo V. Roquero Memorial Hospital is committed to protecting your privacy in accordance with the Data Privacy Act of 2012.
            We ensure that all personal and medical data collected from patients, staff, and applicants are securely stored and used solely for official hospital purposes.
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" id="closePrivacyBtn">Close</button>
        </div>
      </div>
    </div>

    <!-- Terms Modal -->
    <div id="termsModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Terms of Use</h5>
          <button class="btn-close" id="closeTerms">&times;</button>
        </div>
        <div class="modal-body">
          <p>
            By accessing this system, you agree to abide by all hospital policies and data governance protocols.
            Unauthorized access or misuse of hospital systems may result in disciplinary action or legal consequences.
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" id="closeTermsBtn">Close</button>
        </div>
      </div>
    </div>

    <script>
      const privacyModal = document.getElementById("privacyModal");
      const termsModal = document.getElementById("termsModal");

      // Open modals
      document.getElementById("openPrivacy").onclick = () => privacyModal.style.display = "block";
      document.getElementById("openTerms").onclick = () => termsModal.style.display = "block";

      // Close modals
      document.getElementById("closePrivacy").onclick = () => privacyModal.style.display = "none";
      document.getElementById("closePrivacyBtn").onclick = () => privacyModal.style.display = "none";

      document.getElementById("closeTerms").onclick = () => termsModal.style.display = "none";
      document.getElementById("closeTermsBtn").onclick = () => termsModal.style.display = "none";

      // Click outside modal
      window.onclick = function(event) {
        if (event.target == privacyModal) privacyModal.style.display = "none";
        if (event.target == termsModal) termsModal.style.display = "none";
      };
    </script>
    <?php
  }
}
?>
