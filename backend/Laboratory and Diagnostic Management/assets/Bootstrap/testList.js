   // JavaScript for handling edit functionality
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.btn-edit');
        const viewButtons = document.querySelectorAll('.btn-view');
        const testForm = document.getElementById('testForm');
        const createBtn = document.getElementById('createBtn');
        const updateBtn = document.getElementById('updateBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const modal = document.getElementById('testModal');
        const closeBtn = document.querySelector('.close');
        
        // Add event listeners to all edit buttons
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const testId = this.getAttribute('data-id');
                fetchTestDetails(testId, 'edit');
            });
        });

        // Add event listeners to all view buttons
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const testId = this.getAttribute('data-id');
                fetchTestDetails(testId, 'view');
            });
        });
        
        // Close modal when clicking X
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
        
        // Cancel button functionality
        cancelBtn.addEventListener('click', function() {
            resetForm();
        });
        
        // Function to fetch test details for editing or viewing
        function fetchTestDetails(testId, mode) {
            // Using to get test details
            fetch(`get_test.php?id=${testId}`)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        if (mode === 'edit') {
                            // Populate form with test details for editing
                            document.getElementById('test_id').value = data.test_id;
                            document.getElementById('test_code').value = data.test_code;
                            document.getElementById('test_name').value = data.test_name;
                            document.getElementById('description').value = data.description;
                            document.getElementById('category').value = data.category;
                            document.getElementById('preparation_instructions').value = data.preparation_instructions;
                            document.getElementById('estimated_duration').value = data.estimated_duration;
                            document.getElementById('is_active').checked = data.is_active == 1;
                            
                            // Show update and cancel buttons, hide create button
                            createBtn.style.display = 'none';
                            updateBtn.style.display = 'inline-block';
                            cancelBtn.style.display = 'inline-block';
                            
                            // Scroll to form
                            testForm.scrollIntoView({ behavior: 'smooth' });
                        } else if (mode === 'view') {
                            // Display details in modal for viewing
                            const detailsDiv = document.getElementById('testDetails');
                            detailsDiv.innerHTML = `
                                <div class="detail-row">
                                    <span class="detail-label">Test ID:</span>
                                    <span>${data.test_id}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Test Code:</span>
                                    <span>${data.test_code}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Test Name:</span>
                                    <span>${data.test_name}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Description:</span>
                                    <span>${data.description || 'N/A'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Category:</span>
                                    <span>${data.category}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Preparation Instructions:</span>
                                    <span>${data.preparation_instructions || 'N/A'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Estimated Duration:</span>
                                    <span>${data.estimated_duration} minutes</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status:</span>
                                    <span>${data.is_active ? 'Active' : 'Inactive'}</span>
                                </div>
                            `;
                            modal.style.display = 'block';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching test details:', error);
                    alert('Failed to load test details. Please try again.');
                });
        }
        
        // Function to reset form to create mode
        function resetForm() {
            testForm.reset();
            document.getElementById('test_id').value = '';
            createBtn.style.display = 'inline-block';
            updateBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
        }
    });
