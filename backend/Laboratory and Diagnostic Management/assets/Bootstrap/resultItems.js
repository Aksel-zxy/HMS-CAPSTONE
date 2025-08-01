   
 
        // Modal functions
        function openModal() {
            document.getElementById('modalTitle').textContent = 'Add Result Item';
            document.getElementById('item_id').value = '';
            document.getElementById('parameter').value = '';
            document.getElementById('result_value').value = '';
            document.getElementById('normal_range').value = '';
            document.getElementById('units').value = '';
            document.getElementById('flag').value = '';
            document.getElementById('createBtn').style.display = 'inline-block';
            document.getElementById('updateBtn').style.display = 'none';
            document.getElementById('modal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        function editItem(data) {
            document.getElementById('modalTitle').textContent = 'Edit Result Item';
            document.getElementById('item_id').value = data.item_id;
            document.getElementById('parameter').value = data.parameter;
            document.getElementById('result_value').value = data.result_value;
            document.getElementById('normal_range').value = data.normal_range;
            document.getElementById('units').value = data.units;
            document.getElementById('flag').value = data.flag || '';
            document.getElementById('createBtn').style.display = 'none';
            document.getElementById('updateBtn').style.display = 'inline-block';
            document.getElementById('modal').style.display = 'block';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('modal')) {
                closeModal();
            }
        }
    