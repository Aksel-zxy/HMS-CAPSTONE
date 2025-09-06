<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Portal</title>
    <link rel="stylesheet" type="text/css" href="assets/css/vendorsidebar.css">
</head>
<body>

<div class="sidebar">
    <div class="logo-container">
        <img src="hospitallogo.jpg" alt="Logo"> 
        <h2>Supplier Portal</h2>
    </div>

    <nav class="nav">
        <div class="menu">
            <p class="title">Main Menu</p>
            <ul>
                
                <li><a href="vendor_orders.php"><span class="text">Orders</span></a></li>
                <li><a href="vendor_products.php"><span class="text">Product List</span></a></li>
                <li><a href="vendor_documents.php"><span class="text">View Compliance Document</span></a></li>
                <li><a href="vendorcontract.php"><span class="text">Contract & Agreement</span></a></li>
            </ul>
        </div>

        <div class="menu">
            <p class="title">Account</p>
            <ul>
                <li><a href="vendorprofile.php"><span class="text">View Profile</span></a></li>
                <li>
                    <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');" aria-label="Logout">
                        <i class="icon ph-bold ph-sign-out"></i>
                        <span class="text">Log Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</div>

</body>
</html>
