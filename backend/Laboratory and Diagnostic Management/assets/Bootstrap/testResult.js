
    // Tab functionality
    document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', function () {
        const tabId = this.getAttribute('data-tab');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    });
});

    // Show verification field only when status is 'verified'
    document.addEventListener('DOMContentLoaded', function () {
        const statusSelect = document.getElementById('status');
        const verifiedByGroup = document.querySelector('select[name="verified_by"]')?.closest('.form-group');

    if (verifiedByGroup && statusSelect) {
        verifiedByGroup.style.display = statusSelect.value === 'verified' ? 'block' : 'none';
        statusSelect.addEventListener('change', function () {
            verifiedByGroup.style.display = this.value === 'verified' ? 'block' : 'none';
        });
    }
});

    // Edit and view functionality
    const resultForm = document.getElementById('testresultForm');
    const createBtn = document.getElementById('createBtn');
    const updateBtn = document.getElementById('updateBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const modal = document.getElementById('resultModal');
    const closeModal = document.querySelector('.close');
    const tabButtons = document.querySelectorAll('.tab-btn');

    function fetchResultDetails(resultId, action) {
    const rows = document.querySelectorAll('#resultTable tbody tr'); 

    let resultData = null;

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
                if (row.getAttribute('data-result-id') === resultId) {
            resultData = {
                result_id: row.getAttribute('data-result-id'),
                appointment_id: row.getAttribute('data-appointment-id'),
                performed_by: row.getAttribute('data-performed-by-id'), 
                verified_by: row.getAttribute('data-verified-by-id'), 
                result_date: row.getAttribute('data-result-date'),
                verification_date: row.getAttribute('data-verification-date'),
                status: row.getAttribute('data-status'),
                conclusion: row.getAttribute('data-conclusion'),
                patient_name: row.getAttribute('data-patient-name'),
                performer_name: row.getAttribute('data-performer-name'),
               
            };

        }
    });

    if (resultData) {
        if (action === 'edit') {
            // Set values for the edit form
            document.getElementById('result_id').value = resultData.result_id;
            document.getElementById('appointment_id').value = resultData.appointment_id; // Set appointment ID
            document.getElementById('conclusion').value = resultData.conclusion;
            document.getElementById('status').value = resultData.status;

            // Set performed_by dropdown
            const performedBySelect = document.getElementById('performed_by');
            if (performedBySelect) {
                performedBySelect.value = resultData.performed_by; // Set by ID
            }

            // Set verified_by dropdown
            const verifiedBySelect = document.getElementById('verified_by');
            if (verifiedBySelect) {
                verifiedBySelect.value = resultData.verified_by; // Set by ID
            }

            // Show/hide buttons
            createBtn.style.display = 'none';
            updateBtn.style.display = 'inline-block';
            cancelBtn.style.display = 'inline-block';

            // Switch to 'new' tab
            document.querySelectorAll('.tab-btn').forEach(btn => {
                if (btn.getAttribute('data-tab') === 'new') {
                    btn.click();
                }
            });

            } else if (action === 'view') {
            const detailsHTML = `
                <div class="detail-row"><strong>Result ID:</strong> ${resultData.result_id}</div>
                <div class="detail-row"><strong>Appointment ID:</strong> ${resultData.appointment_id}</div>
                <div class="detail-row"><strong>Patient:</strong> ${resultData.patient_name || 'N/A'}</div>
                <div class="detail-row"><strong>Performed by:</strong> ${resultData.performer_name || 'N/A'}</div>
                <div class="detail-row"><strong>Result Date:</strong> ${new Date(resultData.result_date).toLocaleString()}</div>
                <div class="detail-row"><strong>Status:</strong> ${resultData.status || 'N/A'}</div>
                <div class="detail-row"><strong>Conclusion:</strong> ${resultData.conclusion || 'No conclusion'}</div>
            `;
            document.getElementById('resultDetails').innerHTML = detailsHTML;
            modal.style.display = 'block';
        }
    } else {
        console.warn("Result data not found for ID:", resultId);
    }
}

    // Function to reset form to create mode
    function resetForm() {
        resultForm.reset();
        document.getElementById('result_id').value = '';
        createBtn.style.display = 'inline-block';
        updateBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
}

    // Add event listeners to edit and view buttons
    function attachButtonListeners() {
        const editButtons = document.querySelectorAll('.btn-edit');
        const viewButtons = document.querySelectorAll('.btn-view');

    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const resultId = this.getAttribute('data-id');
            fetchResultDetails(resultId, 'edit');

            // Switch to 'new' tab if available
            tabButtons.forEach(btn => {
                if (btn.getAttribute('data-tab') === 'new') {
                    btn.click();
                }
            });
        });
    });

    viewButtons.forEach(button => {
        button.addEventListener('click', function () {
            const resultId = this.getAttribute('data-id');
            fetchResultDetails(resultId, 'view');
        });
    });
}

    // Initial attachment of listeners
    attachButtonListeners();

    // Cancel button
    if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
        resetForm();
    });
}

    // Modal close (X)
    if (closeModal) {
    closeModal.addEventListener('click', function () {
        modal.style.display = 'none';
    });
}

    // Close modal when clicking outside
    window.addEventListener('click', function (event) {
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

