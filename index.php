
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$username = $_SESSION["username"];
$role = $_SESSION["role"];

// Ringkasan dashboard
$total_products = 0;
$total_stock = 0;
$low_stock = 0;
$total_categories = 0;
$total_suppliers = 0;
$total_stock_in = 0;
$total_stock_out = 0;

// Total produk dan seluruh stok
$result = $conn->query(
    "SELECT COUNT(*) AS total_products,
            COALESCE(SUM(stock), 0) AS total_stock
     FROM products"
);

if ($result) {
    $data = $result->fetch_assoc();
    $total_products = (int) $data["total_products"];
    $total_stock = (int) $data["total_stock"];
}

// Produk dengan stok menipis
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE stock <= minimum_stock"
);

if ($result) {
    $low_stock = (int) $result->fetch_assoc()["total"];
}

// Total kategori
$result = $conn->query("SELECT COUNT(*) AS total FROM categories");

if ($result) {
    $total_categories = (int) $result->fetch_assoc()["total"];
}

// Total supplier
$result = $conn->query("SELECT COUNT(*) AS total FROM suppliers");

if ($result) {
    $total_suppliers = (int) $result->fetch_assoc()["total"];
}

// Total transaksi Stock IN
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM stock_movements
     WHERE type = 'IN'"
);

if ($result) {
    $total_stock_in = (int) $result->fetch_assoc()["total"];
}

// Total transaksi Stock OUT
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM stock_movements
     WHERE type = 'OUT'"
);

if ($result) {
    $total_stock_out = (int) $result->fetch_assoc()["total"];
}

// Riwayat transaksi terbaru
$recent_transactions = $conn->query(
    "SELECT sm.type, sm.quantity, sm.created_at,
            p.name AS product_name, p.sku,
            u.username
     FROM stock_movements sm
     JOIN products p ON sm.product_id = p.id
     JOIN users u ON sm.user_id = u.id
     ORDER BY sm.created_at DESC, sm.id DESC
     LIMIT 5"
);

// Daftar produk dengan stok menipis
$low_stock_products = $conn->query(
    "SELECT name, sku, stock, minimum_stock
     FROM products
     WHERE stock <= minimum_stock
     ORDER BY stock ASC, name ASC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Inventory Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #263238;
        }

        header {
            background: #243447;
            color: white;
            padding: 22px 30px;
        }

        header h1 {
            margin: 0 0 8px;
            font-size: 25px;
        }

        header p {
            margin: 0;
            color: #e0e7ef;
        }

        main {
            padding: 25px 30px;
            max-width: 1400px;
            margin: auto;
        }

        .section-title {
            margin: 25px 0 15px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0000000d;
        }

        .card h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: #65727e;
        }

        .number {
            font-size: 30px;
            font-weight: bold;
            color: #243447;
        }

        .warning-number {
            color: #d97706;
        }

        .green-number {
            color: #16804a;
        }

        .purple-number {
            color: #7544b8;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .menu a {
            display: block;
            padding: 16px;
            background: white;
            border-radius: 8px;
            color: #243447;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 2px 8px #0000000d;
        }

        .menu a:hover {
            background: #e8eef5;
        }

        .tables {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
            align-items: start;
        }

        .table-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 2px 8px #0000000d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 11px 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f1f4f8;
            white-space: nowrap;
        }

        .stock-in {
            color: #16804a;
            font-weight: bold;
        }

        .stock-out {
            color: #c62828;
            font-weight: bold;
        }

        .warning-text {
            color: #c62828;
            font-weight: bold;
        }

        .empty {
            color: #777;
            text-align: center;
        }

        .logout {
            display: inline-block;
            margin-top: 25px;
            background: #c62828;
            color: white;
            padding: 10px 17px;
            text-decoration: none;
            border-radius: 6px;
        }

        .logout:hover {
            background: #a51f1f;
        }

        footer {
            color: #6b7280;
            padding: 10px 30px 25px;
            text-align: center;
        }

        @media (max-width: 900px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tables {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            main {
                padding: 18px;
            }

            header {
                padding: 20px 18px;
            }

            .cards,
            .menu {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Inventory Management System</h1>

    <p>
        Selamat datang,
        <strong><?= htmlspecialchars($username, ENT_QUOTES, "UTF-8") ?></strong>!
        Kamu login sebagai
        <strong><?= htmlspecialchars($role, ENT_QUOTES, "UTF-8") ?></strong>.
    </p>
</header>

<main>

    <h2 class="section-title">Dashboard Overview</h2>

    <div class="cards">
        <div class="card">
            <h3>Total Produk</h3>
            <div class="number"><?= $total_products ?></div>
        </div>

        <div class="card">
            <h3>Total Unit Stok</h3>
            <div class="number green-number"><?= $total_stock ?></div>
        </div>

        <div class="card">
            <h3>Produk Stok Menipis</h3>
            <div class="number warning-number"><?= $low_stock ?></div>
        </div>

        <div class="card">
            <h3>Total Kategori</h3>
            <div class="number purple-number"><?= $total_categories ?></div>
        </div>

        <div class="card">
            <h3>Total Supplier</h3>
            <div class="number"><?= $total_suppliers ?></div>
        </div>

        <div class="card">
            <h3>Transaksi Stock IN</h3>
            <div class="number green-number"><?= $total_stock_in ?></div>
        </div>

        <div class="card">
            <h3>Transaksi Stock OUT</h3>
            <div class="number warning-number"><?= $total_stock_out ?></div>
        </div>
    </div>

    <h2 class="section-title">Menu Utama</h2>

    
<div class="menu">

    <!-- Semua role boleh mengakses dashboard -->
    <a href="index.php">🏠 Dashboard</a>

    <!-- Admin bisa mengelola data master -->
    <?php if ($role === "admin"): ?>

        <a href="products.php">📦 Kelola Produk</a>
        <a href="categories.php">📁 Kelola Kategori</a>
        <a href="suppliers.php">🏢 Kelola Supplier</a>
        <a href="product_suppliers.php">🔗 Produk & Supplier</a>
        <a href="users.php">👥 Manajemen User</a>

    <?php else: ?>

        <!-- Staff hanya melihat produk -->
        <a href="products.php">📦 Lihat Produk</a>

    <?php endif; ?>

    <!-- Admin dan Staff boleh melakukan transaksi stok -->
    <a href="stock.php">🔄 Transaksi Stok IN/OUT</a>

</div>


    <h2 class="section-title">Laporan Stok Menipis dan Riwayat Transaksi</h2>

    <div class="tables">

        <div class="table-card">
            <h3>5 Transaksi Terbaru</h3>

            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Produk</th>
                        <th>Jenis</th>
                        <th>Jumlah</th>
                        <th>Pengguna</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($recent_transactions && $recent_transactions->num_rows > 0): ?>
                        <?php while ($row = $recent_transactions->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($row["created_at"], ENT_QUOTES, "UTF-8") ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row["product_name"], ENT_QUOTES, "UTF-8") ?>
                                    <br>
                                    <small><?= htmlspecialchars($row["sku"], ENT_QUOTES, "UTF-8") ?></small>
                                </td>

                                <td class="<?= $row["type"] === "IN" ? "stock-in" : "stock-out" ?>">
                                    <?= $row["type"] === "IN" ? "Stock IN" : "Stock OUT" ?>
                                </td>

                                <td><?= (int) $row["quantity"] ?></td>

                                <td>
                                    <?= htmlspecialchars($row["username"], ENT_QUOTES, "UTF-8") ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty">
                                Belum ada transaksi stok.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p><a href="stock.php">Lihat semua transaksi →</a></p>
        </div>

        <div class="table-card">
            <h3>Produk Stok Menipis</h3>

            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Stok</th>
                        <th>Minimum</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($low_stock_products && $low_stock_products->num_rows > 0): ?>
                        <?php while ($row = $low_stock_products->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($row["name"], ENT_QUOTES, "UTF-8") ?>
                                    <br>
                                    <small><?= htmlspecialchars($row["sku"], ENT_QUOTES, "UTF-8") ?></small>
                                </td>

                                <td class="warning-text">
                                    <?= (int) $row["stock"] ?>
                                </td>

                                <td><?= (int) $row["minimum_stock"] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty">
                                Tidak ada produk dengan stok menipis.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p><a href="products.php">Lihat semua produk →</a></p>
        </div>

    </div>

    <?php if ($role === "admin"): ?>
        <h2 class="section-title">Menu Admin</h2>
        <p>
            Kamu memiliki akses ke fitur manajemen pengguna.
        </p>
    <?php endif; ?>

    <a class="logout" href="logout.php">Logout</a>

</main>

<footer>
    Inventory Management System
</footer>

</body>
</html>