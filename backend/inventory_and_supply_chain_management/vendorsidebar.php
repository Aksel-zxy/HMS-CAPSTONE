<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Portal</title>
    <link rel="stylesheet" type="text/css" href="assets/css/vendorsidebar.css">
    <link rel="stylesheet" type="text/css" href="/HMS-CAPSTONE/backend/inventory_and_supply_chain_management/assets/CSS/vendorsidebar.css"> 
</head>
<style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f4f4;
}

.sidebar {
    width: 250px;
    height: 100vh;
    background: whitesmoke;
    color: black;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto; 
    transition: all 0.3s ease-in-out;
}

.sidebar .logo-container {
    text-align: center;
    padding: 20px;
}

.sidebar .logo-container img {
    width: 80px;
    height: auto;
    border-radius: 50%;
}

.sidebar .logo-container h2 {
    margin-top: 10px;
    font-size: 18px;
    font-weight: bold;
}

.sidebar .nav {
    padding: 10px;
}

.menu {
    margin-bottom: 20px;
}

.menu .title {
    font-size: 16px;
    font-weight: bold;
    padding: 10px;
    background: #34495e;
    border-radius: 5px;
}

.menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.menu p {
   color: white;
    font-size: 14px;
    padding: 10px;
}

.menu ul li {
    padding: 10px;
    border-bottom: 1px solid rgba(187, 159, 159, 0.1);
}

.menu ul li a {
    text-decoration: none;
    color: black;
    display: block;
    transition: 0.3s;
}

.menu ul li a:hover {
    background: #0084ff;
    border-radius: 5px;
}

.sidebar.active {
    width: 60px;
}

.sidebar.active .menu .title, 
.sidebar.active .menu ul li a .text {
    display: none;
}

.sidebar.active .menu ul li a {
    text-align: center;
    padding: 10px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 200px;
    }
    .sidebar.active {
        width: 50px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    .sidebar .menu-btn {
        text-align: left;
        padding: 15px;
    }
    .sidebar .nav {
        display: none;
    }
    .sidebar.active .nav {
        display: block;
    }
}


</style>
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
                
                <li><a href="vendor_orders.php"><span class="text">Orders</span></a></li>
                <li><a href="vendor_products.php"><span class="text">Product List</span></a></li>
                <li><a href="vendor_return_request.php"><span class="text">Return Request</span></a></li>
                <li><a href="vendor_documents.php"><span class="text">View Compliance Document</span></a></li>
                <li><a href="vendorcontract.php"><span class="text">Contract & Agreement</span></a></li>

                <li><a href="v_rating.php"><span class="text">Rating</span></a></li>
            </ul>
        </div>

        <div class="menu">
            <p class="title">Account</p>
            <ul>
                <li><a href="vendorprofile.php"><span class="text">View Profile</span></a></li>
                <li>
                    <a href="vlogout.php" onclick="return confirm('Are you sure you want to log out?');" aria-label="Logout">
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
