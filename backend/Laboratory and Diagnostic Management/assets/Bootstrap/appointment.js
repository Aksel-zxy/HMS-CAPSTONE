   document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Add active class to current button and pane
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            // If calendar tab, initialize calendar
            if (tabId === 'calendar') {
                generateCalendar();
            }
        });
    });
    
    // Edit and view functionality
    const appointmentForm = document.getElementById('appointmentForm');
    const createBtn = document.getElementById('createBtn');
    const updateBtn = document.getElementById('updateBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const modal = document.getElementById('appointmentModal');
    const closeModal = document.querySelector('.close');
    
    //Function to fetch appointment details
    function formatDateTimeForInput(dateString) {
    const date = new Date(dateString);
    return date.toISOString().slice(0, 16);  //YYYY-MM-DDTHH:MM
}

    function fetchAppointmentDetails(appointmentId, action) {
        // Find the appointment in the table
        const rows = document.querySelectorAll('#appointmentsTable tbody tr');
        let appointmentData = null;
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells[0].textContent === appointmentId) {
                appointmentData = {
                    appointment_id: cells[0].textContent,
                    test_name: cells[1].textContent,
                    patient_name: row.getAttribute('data-patient-name'),
                    doctor_name: row.getAttribute('data-doctor-name'),
                    scheduled_datetime: cells[2].textContent,
                    end_datetime: cells[3].textContent,
                    status: cells[4].querySelector('.status-badge').textContent.trim().toLowerCase(),
                    notes:row.dataset.notes 
                };
            }
        });
        
        if (appointmentData) {
            if (action === 'edit') {
                // Populate form for editing
                document.getElementById('appointment_id').value = appointmentId;
                
                // Populate patient dropdown
                const patientSelect = document.getElementById('patient_id');
                for (let i = 0; i < patientSelect.options.length; i++) {
                    if (patientSelect.options[i].text === appointmentData.patient_name) {
                        patientSelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Populate doctor dropdown
                const doctorSelect = document.getElementById('doctor_id');
                for (let i = 0; i < doctorSelect.options.length; i++) {
                    if (doctorSelect.options[i].text === appointmentData.doctor_name) {
                        doctorSelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Populate test dropdown
                const testSelect = document.getElementById('test_id');
                for (let i = 0; i < testSelect.options.length; i++) {
                    if (testSelect.options[i].text === appointmentData.test_name) {
                        testSelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Set status dropdown
                const statusSelect = document.getElementById('status');
                statusSelect.value = appointmentData.status;
                
                // Set notes/date and time
                document.getElementById('scheduled_datetime').value = formatDateTimeForInput(appointmentData.scheduled_datetime);
                document.getElementById('end_datetime').value =  formatDateTimeForInput(appointmentData.end_datetime);        
                document.getElementById('notes').value = appointmentData.notes || '';
                
                // Show update and cancel buttons, hide create button
                createBtn.style.display = 'none';
                updateBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
                
            } 

            else if (action === 'view') {
                // Show modal with appointment details
                const detailsHTML = ` 
                    <div class="detail-row">
                        <strong>Test:</strong> ${appointmentData.test_name}
                    </div>
                    <div class="detail-row">
                        <strong>Patient:</strong> ${appointmentData.patient_name || 'N/A'}
                    </div>
                    <div class="detail-row">
                        <strong>Doctor:</strong> ${appointmentData.doctor_name || 'N/A'}
                    </div>
                    <div class="detail-row">
                        <strong>Scheduled Date:</strong> ${appointmentData.scheduled_datetime}
                    </div>
                    <div class="detail-row">
                        <strong>End Date:</strong> ${appointmentData.end_datetime}
                    </div>
                    <div class="detail-row">
                        <strong>Status:</strong> <span class="status-badge status-${appointmentData.status}">${appointmentData.status.charAt(0).toUpperCase() + appointmentData.status.slice(1)}</span>
                    </div>
                    <div class="detail-row">
                        <strong>Notes:</strong>
                        <p>${appointmentData.notes || 'No notes'}</p>
                    </div>
                `;
                
                document.getElementById('appointmentDetails').innerHTML = detailsHTML;
                modal.style.display = 'block';
            }
        }
    }
    
    // Function to reset form to create mode
    function resetForm() {
        appointmentForm.reset();
        document.getElementById('appointment_id').value = '';
        createBtn.style.display = 'inline-block';
        updateBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
    }
    
    // Add event listeners to edit and view buttons
    function attachButtonListeners() {
        
        const editButtons = document.querySelectorAll('.btn-edit');
        const viewButtons = document.querySelectorAll('.btn-view');
        
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-id');
                fetchAppointmentDetails(appointmentId, 'edit');
                
                // Switch to new appointment tab
                tabButtons.forEach(btn => {
                    if (btn.getAttribute('data-tab') === 'new') {
                        btn.click();
                    }
                });
            });
        });
        
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-id');
                fetchAppointmentDetails(appointmentId, 'view');
            });
        });
    }
    
    // Initial attachment of button listeners
    attachButtonListeners();
    
    // Cancel button functionality
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            resetForm();
        });
    }
    
    // Close modal when clicking the X
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Filter functionality
    const statusFilter = document.getElementById('status-filter');
    const dateFilter = document.getElementById('date-filter');
    const resetFiltersBtn = document.getElementById('reset-filters');
    
    function applyFilters() {
        const statusValue = statusFilter ? statusFilter.value : '';
        const dateValue = dateFilter ? dateFilter.value : '';
        
        const rows = document.querySelectorAll('#appointmentsTable tbody tr');
        
        rows.forEach(row => {
            let showRow = true;
            
            if (statusValue && row.getAttribute('data-status') !== statusValue) {
                showRow = false;
            }
            
            if (dateValue && row.getAttribute('data-date') !== dateValue) {
                showRow = false;
            }
            
            row.style.display = showRow ? '' : 'none';
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }
    
    if (dateFilter) {
        dateFilter.addEventListener('change', applyFilters);
    }
    
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', function() {
            if (statusFilter) statusFilter.value = '';
            if (dateFilter) dateFilter.value = '';
            applyFilters();
        });
    }
    
    // Calendar functionality
    let currentDate = new Date();
    
    function generateCalendar() {
        const calendarDays = document.getElementById('calendar-days');
        const currentMonthElement = document.getElementById('current-month');
        
        if (!calendarDays || !currentMonthElement) return;
        
        
        // Clear previous calendar
        calendarDays.innerHTML = '';
        
        // Set current month display
        currentMonthElement.textContent = currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        // Get first day of month and number of days
        const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
        
        // Calculate first day of week (0 = Sunday)
        let firstDayIndex = firstDay.getDay();
        
        // Add empty cells for days before first day of month
        for (let i = 0; i < firstDayIndex; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.classList.add('day', 'empty');
            calendarDays.appendChild(emptyDay);
        }
        
        // Add days of month
        for (let i = 1; i <= lastDay.getDate(); i++) {
            const dayElement = document.createElement('div');
            dayElement.classList.add('day');
            
            // Check if this is today
            const dayDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), i);
            if (dayDate.toDateString() === new Date().toDateString()) {
                dayElement.classList.add('today');
            }
            
            // Add date number
            dayElement.textContent = i;
            
            // Add click event to show appointments for this day
            dayElement.addEventListener('click', function() {
                const selectedDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), i);
                showAppointmentsForDay(selectedDate);
                
                // Remove selected class from all days
                document.querySelectorAll('.day').forEach(day => {
                    day.classList.remove('selected');
                });
                
                // Add selected class to clicked day
                this.classList.add('selected');
            });
            
            calendarDays.appendChild(dayElement);
        }
    }
    
    function showAppointmentsForDay(date) {
        const selectedDateElement = document.getElementById('select-date');
        const appointmentsList = document.getElementById('day-appointment-list');
        
        if (!selectedDateElement || !appointmentsList) return;
        
        // Format date for display
        selectedDateElement.textContent = date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Format date for comparison
        const dateString = date.toISOString().split('T')[0];
        
        // Find appointments for this day
        const rows = document.querySelectorAll('#appointmentsTable tbody tr');
        let appointmentsHTML = '';
        let hasAppointments = false;
        
        rows.forEach(row => {
            if (row.getAttribute('data-date') === dateString) {
                hasAppointments = true;
                const cells = row.querySelectorAll('td');
                appointmentsHTML += `
                    <div class="day-appointment">
                        <div class="appointment-time">${cells[2].textContent}</div>
                        <div class="appointment-details">
                            <div class="appointment-test">${cells[1].textContent}</div>
                            <div class="appointment-status">${cells[4].innerHTML}</div>
                        </div>
                        <div class="appointment-actions">
                            <button class="btn-view" data-id="${cells[0].textContent}">View</button>
                        </div>
                    </div>
                `;
            }
        });
        
        if (hasAppointments) {
            appointmentsList.innerHTML = appointmentsHTML;
            
            // Add event listeners to new view buttons
            appointmentsList.querySelectorAll('.btn-view').forEach(button => {
                button.addEventListener('click', function() {
                    const appointmentId = this.getAttribute('data-id');
                    fetchAppointmentDetails(appointmentId, 'view');
                });
            });
        } else {
            appointmentsList.innerHTML = '<p class="no-appointments">No appointments for this date.</p>';
        }
    }
    
    // Month navigation
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar();
        });
    }
    
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar();

            
        });
    }
});

