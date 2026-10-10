
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$message = "";
$error = "";

// Proses transaksi stok
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $product_id = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
    $type = $_POST["type"] ?? "";
    $quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

    if (
        !$product_id ||
        !in_array($type, ["IN", "OUT"], true) ||
        $quantity === false ||
        $quantity === null ||
        $quantity <= 0
    ) {
        $error = "Data transaksi tidak valid. Periksa produk dan jumlahnya.";
    } else {
        try {
            // Mulai transaksi database
            $conn->begin_transaction();

            // Ambil dan kunci stok produk
            $stmt = $conn->prepare(
                "SELECT stock FROM products WHERE id = ? FOR UPDATE"
            );
            $stmt->bind_param("i", $product_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();

            if (!$product) {
                throw new Exception("Produk tidak ditemukan.");
            }

            $current_stock = (int) $product["stock"];

            // Pastikan stok mencukupi untuk barang keluar
            if ($type === "OUT" && $quantity > $current_stock) {
                throw new Exception(
                    "Stok tidak mencukupi. Stok tersedia: " . $current_stock
                );
            }

            // Perbarui stok produk
            if ($type === "IN") {
                $stmt = $conn->prepare(
                    "UPDATE products SET stock = stock + ? WHERE id = ?"
                );
            } else {
                $stmt = $conn->prepare(
                    "UPDATE products SET stock = stock - ? WHERE id = ?"
                );
            }

            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();
            $stmt->close();

            // Simpan riwayat transaksi
            $user_id = (int) $_SESSION["user_id"];

            $stmt = $conn->prepare(
                "INSERT INTO stock_movements
                (product_id, user_id, type, quantity)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iisi",
                $product_id,
                $user_id,
                $type,
                $quantity
            );

            $stmt->execute();
            $stmt->close();

            // Simpan semua perubahan sekaligus
            $conn->commit();

            header("Location: stock.php?success=1");
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            if ($e instanceof mysqli_sql_exception) {
                $error = "Transaksi gagal disimpan. Periksa koneksi database.";
            } else {
                $error = $e->getMessage();
            }
        }
    }
}

// Pesan setelah transaksi berhasil
if (isset($_GET["success"])) {
    $message = "Transaksi stok berhasil disimpan!";
}

// Ambil daftar produk
$products = $conn->query(
    "SELECT id, sku, name, stock
     FROM products
     ORDER BY name ASC"
);

// Ambil riwayat transaksi: data lama di atas, data baru di bawah
$history = $conn->query(
    "SELECT sm.id, p.name AS product_name, p.sku,
            u.username, sm.type, sm.quantity, sm.created_at
     FROM stock_movements sm
     JOIN products p ON sm.product_id = p.id
     JOIN users u ON sm.user_id = u.id
     ORDER BY sm.created_at ASC, sm.id ASC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Stock IN/OUT Transactions</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f5f6fa;
            color: #222;
        }

        h1, h2 {
            color: #243447;
        }

        .card {
            background: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px #00000010;
        }

        label {
            display: block;
            margin-top: 12px;
        }

        input, select, button {
            padding: 9px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        input, select {
            width: 100%;
            max-width: 450px;
        }

        button {
            background: #167d5a;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 16px;
        }

        button:hover {
            background: #116747;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #243447;
            color: white;
        }

        .success {
            color: #167d3f;
            margin-bottom: 15px;
        }

        .error {
            color: #c62828;
            margin-bottom: 15px;
        }

        .in {
            color: #167d3f;
            font-weight: bold;
        }

        .out {
            color: #c62828;
            font-weight: bold;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <h1>Stock IN/OUT Transactions</h1>

    <p>
        Login sebagai:
        <strong>
            <?= htmlspecialchars(
                $_SESSION["username"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </strong>

        (<?= htmlspecialchars(
            $_SESSION["role"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>)
    </p>

    <a class="back-link" href="index.php">
        ← Kembali ke Dashboard
    </a>

    <div class="card">
        <h2>Tambah Transaksi Stok</h2>

        <?php if ($message): ?>
            <p class="success">
                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label for="product_id">Pilih Produk</label>

            <select name="product_id" id="product_id" required>
                <option value="">-- Pilih Produk --</option>

                <?php while ($p = $products->fetch_assoc()): ?>
                    <option value="<?= (int) $p["id"] ?>">
                        <?= htmlspecialchars(
                            $p["sku"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                        -
                        <?= htmlspecialchars(
                            $p["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                        (Stok: <?= (int) $p["stock"] ?>)
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="type">Jenis Transaksi</label>

            <select name="type" id="type" required>
                <option value="">-- Pilih Jenis --</option>
                <option value="IN">Stock IN (Barang Masuk)</option>
                <option value="OUT">Stock OUT (Barang Keluar)</option>
            </select>

            <label for="quantity">Jumlah</label>

            <input
                type="number"
                name="quantity"
                id="quantity"
                min="1"
                step="1"
                required
            >

            <button type="submit">Simpan Transaksi</button>
        </form>
    </div>

    <div class="card">
        <h2>Riwayat Transaksi Stok</h2>

        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Waktu</th>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Jenis</th>
                    <th>Jumlah</th>
                    <th>Pengguna</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($history->num_rows > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = $history->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["sku"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["product_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td class="<?= $row["type"] === "IN" ? "in" : "out" ?>">
                                <?= $row["type"] === "IN"
                                    ? "Stock IN"
                                    : "Stock OUT" ?>
                            </td>

                            <td><?= (int) $row["quantity"] ?></td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["username"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            Belum ada riwayat transaksi.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>