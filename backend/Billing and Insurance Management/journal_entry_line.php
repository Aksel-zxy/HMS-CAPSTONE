<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entry Lines</title>
    <link rel="stylesheet" href="../assets/CSS/journalentryline.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Journal Entry Lines - Entry #001</h1>
            <div class="entry-info">
                <div class="info-item">
                    <span class="label">Date</span>
                    <span class="value">2023-10-15</span>
                </div>
                <div class="info-item">
                    <span class="label">Status:</span>
                    <span class="badge posted">Posted</span>
                </div>
            </div>
        </header>

        <div class="table-container">
            <table id="entry-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="amount-col">Debit</th>
                        <th class="amount-col">Credit</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Accounts Receivable</td>
                        <td class="amount debit">5,000.00</td>
                        <td class="amount"></td>
                        <td>Invoice #1234 for consulting services</td>
                    </tr>
                    <tr>
                        <td>Service Revenue</td>
                        <td class="amount"></td>
                        <td class="amount credit">5,000.00</td>
                        <td>Revenue from consulting services</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th>TOTAL</th>
                        <th class="amount total">5,000.00</th>
                        <th class="amount total">5,000.00</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="actions">
            <button id="add-line" class="btn-secondary">+ Add Line</button>
            <button id="edit-entry" class="btn-primary">Edit Entry</button>
            <button id="post-entry" class="btn-success">Post Entry</button>
            <button id="print-entry" class="btn-secondary">Print</button>
        </div>

        <div class="entry-details">
            <h2>Entry Details</h2>
            <div class="details-grid">
                <div class="detail-item">
                    <span class="label">Created By:</span>
                    <span class="value">John Smith</span>
                </div>
                <div class="detail-item">
                    <span class="label">Created Date:</span>
                    <span class="value">2023-10-15 09:30:45</span>
                </div>
                <div class="detail-item">
                    <span class="label">Last Modified:</span>
                    <span class="value">2023-10-15 09:30:45</span>
                </div>
                <div class="detail-item">
                    <span class="label">Reference:</span>
                    <span class="value">INV-1234</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Basic functionality for the UI
        document.getElementById('add-line').addEventListener('click', function() {
            alert('Add Line functionality would open a form here.');
        });

        document.getElementById('edit-entry').addEventListener('click', function() {
            alert('Edit Entry functionality would open here.');
        });

        document.getElementById('post-entry').addEventListener('click', function() {
            if(confirm('Are you sure you want to post this entry? This action cannot be undone.')) {
                alert('Entry posted successfully.');
            }
        });

        document.getElementById('print-entry').addEventListener('click', function() {
            window.print();
        });
    </script>
</body>
</html>