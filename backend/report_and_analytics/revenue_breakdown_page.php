<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7f9;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        h1 {
            color: #2c3e50;
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background-color: #f2f6fc;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }

        .description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .positive {
            color: #2e7d32;
        }

        .negative {
            color: #c62828;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination button {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 8px 16px;
            margin: 0 4px;
            cursor: pointer;
            border-radius: 4px;
        }

        .pagination button.active {
            background-color: #4c6ef5;
            color: white;
            border-color: #4c6ef5;
        }

        .search-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-container input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 250px;
        }

        .entries {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Financial Reports</h1>
        <table>
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Year</th>
                    <th>Month</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>

    <script>
        // Simple search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>

</html>