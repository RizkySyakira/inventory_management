<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"];
$role = $_SESSION["role"];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Inventory Management</title>
</head>

<body>

    <h2>Inventory Management</h2>

    <p>
        Selamat datang, <strong><?= htmlspecialchars($username) ?></strong>!
    </p>

    <p>
        Role: <strong><?= htmlspecialchars($role) ?></strong>
    </p>

    <hr>

    <h3>Menu</h3>

    <ul>
        <li>Dashboard</li>
        <li>Produk</li>
        <li>Kategori</li>
        <li>Supplier</li>
        <li>Transaksi Stok</li>
    </ul>

    <?php if ($role === "admin"): ?>

        <h3>Menu Admin</h3>

        <ul>
            <li>Manajemen User</li>
        </ul>

    <?php endif; ?>

    <br>

    <a href="logout.php">Logout</a>

</body>
</html>