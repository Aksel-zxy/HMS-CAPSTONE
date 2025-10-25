<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Portal</title>
    <link rel="stylesheet" type="text/css" href="assets/css/vendorsidebar.css">
    <!-- Include only one stylesheet link -->
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Nunito", "Segoe UI", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F5F6F7;
            color: #6e768e;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #fff;
            color: #6e768e;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            border-right: 1px solid #e0e0e0;
            transition: all 0.3s ease-in-out;
        }

        /* Logo */
        .sidebar .logo-container {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .sidebar .logo-container img {
            max-width: 100px;
            height: auto;
        }

        /* Section Titles */
        .menu .title {
            font-size: .6875rem;
            font-weight: 600;
            padding: 10px 20px;
            text-transform: uppercase;
            color: #6e768e;
            letter-spacing: .05em;
        }

        /* Menu items */
        .menu ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .menu ul li a,
        .dropdown-btn {
            display: block;
            width: 100%;
            padding: .625rem 1.625rem;
            font-size: .95rem;
            text-decoration: none;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-family: "Nunito", sans-serif;
            color: #6e768e;
            transition: color 0.3s, background 0.3s;
        }

        /* Hover + active state */
        .menu ul li a:hover,
        .dropdown-btn:hover,
        .dropdown-btn.active {
            color: #00acc1;
            background: #f0f0f0;
        }

        /* Dropdown caret */
        .dropdown-btn {
            position: relative;
            padding-right: 30px;
        }

        .dropdown-btn::after {
            content: "";
            border: solid;
            border-width: 0 .075rem .075rem 0;
            display: inline-block;
            padding: 2px;
            position: absolute;
            right: 1.5rem;
            top: 1.2rem;
            transform: rotate(45deg);
            transition: transform 0.2s ease-out, color 0.2s;
            color: #6e768e;
        }

        .dropdown-btn[aria-expanded="true"]::after {
            transform: rotate(-135deg);
            color: #00acc1;
        }

        /* Dropdown container */
        .dropdown-container {
            display: none;
            flex-direction: column;
            margin-left: .5rem;
        }

        .dropdown-container a {
            padding: .5rem 2rem;
            font-size: .9rem;
            color: #6e768e;
            text-decoration: none;
            transition: color 0.3s;
        }

        .dropdown-container a:hover {
            color: #00acc1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-container">
            <img src="assets/image/logo-dark.png" alt="Logo"> 
            <h2>Supplier Portal</h2>
        </div>

        <nav class="nav">
            <div class="menu">
                <p class="title">Main Menu</p>
                <ul>
                    <li><a href="vendor_orders.php">Orders</a></li>
                    <li><a href="vendor_products.php">Product List</a></li>
                    <li><a href="vendor_return_request.php">Return Request</a></li>
                    <li><a href="vendor_documents.php">View Compliance Document</a></li>
                    <li><a href="vendorcontract.php">Contract & Agreement</a></li>
                    <li><a href="v_rating.php">Rating</a></li>
                </ul>
            </div>

            <div class="menu">
                <p class="title">Account</p>
                <ul>
                    <li><a href="vendorprofile.php">View Profile</a></li>
                    <li>
                        <a href="vlogout.php" onclick="return confirm('Are you sure you want to log out?');" aria-label="Logout">
                            <i class="icon ph-bold ph-sign-out"></i> Log Out
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</body>
</html>
