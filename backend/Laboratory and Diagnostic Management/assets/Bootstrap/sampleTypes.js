document.addEventListener('DOMContentLoaded', function() {
    // View button click handler
    document.querySelectorAll('.btn-view').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            
            const sampleTypeData = {
                id: row.querySelector('td:nth-child(1)').textContent,
                name: row.querySelector('td:nth-child(2)').textContent,
                code: row.querySelector('td:nth-child(3)').textContent,
                storage: row.querySelector('td:nth-child(4)').textContent,
                duration: row.querySelector('td:nth-child(5)').textContent
            };

            const detailsHTML = `
                <div class="detail-row"><strong>ID:</strong> ${sampleTypeData.id}</div>
                <div class="detail-row"><strong>Name:</strong> ${sampleTypeData.name}</div>
                <div class="detail-row"><strong>Code:</strong> ${sampleTypeData.code}</div>
                <div class="detail-row"><strong>Storage Requirements:</strong> ${sampleTypeData.storage}</div>
                <div class="detail-row"><strong>Stability Duration:</strong> ${sampleTypeData.duration}</div>
            `;
            
            document.getElementById('sampleTypesDetails').innerHTML = detailsHTML;
            document.getElementById('sampleTypesModal').style.display = 'block';
        });
    });

    // Edit button click handler
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function() {
            const sampleTypeId = this.getAttribute('data-id');
            window.location.href = `sampleTypes.php?edit=${sampleTypeId}`;
        });
    });

    // Delete button confirmation
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this sample type?')) {
                e.preventDefault();
            }
        });
    });

    // Close modal
    document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('sampleTypesModal').style.display = 'none';
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('sampleTypesModal')) {
            document.getElementById('sampleTypesModal').style.display = 'none';
        }
    });
});