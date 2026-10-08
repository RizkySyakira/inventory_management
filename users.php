<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    echo "Akses ditolak. Halaman ini hanya untuk Admin.";
    exit;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen User</title>
</head>

<body>

    <h2>Manajemen User</h2>

    <p>Halaman ini hanya dapat diakses oleh Admin.</p>

    <p>
        Login sebagai:
        <strong><?= htmlspecialchars($_SESSION["username"]) ?></strong>
    </p>

    <a href="index.php">Kembali ke Dashboard</a> |
    <a href="logout.php">Logout</a>

</body>
</html>