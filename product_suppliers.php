
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$error = "";

// Proses tambah, edit, dan hapus hubungan produk-supplier
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // ==========================================
    // TAMBAH HUBUNGAN PRODUK DENGAN SUPPLIER
    // ==========================================
    if ($action === "add") {

        $productId = (int) ($_POST["product_id"] ?? 0);
        $supplierId = (int) ($_POST["supplier_id"] ?? 0);
        $priceInput = trim($_POST["supplier_price"] ?? "");

        if (
            $productId <= 0 ||
            $supplierId <= 0 ||
            $priceInput === "" ||
            !is_numeric($priceInput) ||
            !is_finite((float) $priceInput) ||
            (float) $priceInput < 0
        ) {
            $error = "Produk, supplier, dan harga beli harus valid.";

        } else {

            $price = (float) $priceInput;

            $stmt = $conn->prepare(
                "INSERT INTO product_suppliers
                 (product_id, supplier_id, supplier_price)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "iid",
                $productId,
                $supplierId,
                $price
            );

            try {

                if ($stmt->execute()) {
                    $stmt->close();

                    header(
                        "Location: product_suppliers.php?status=added"
                    );
                    exit;
                }

            } catch (mysqli_sql_exception $e) {

                if ((int) $e->getCode() === 1062) {
                    $error = "Produk dan supplier ini sudah terhubung!";
                } else {
                    $error = "Data hubungan produk dan supplier gagal disimpan.";
                }
            }

            $stmt->close();
        }
    }

    // ==========================================
    // EDIT HARGA BELI SUPPLIER
    // ==========================================
    elseif ($action === "edit") {

        $productId = (int) ($_POST["product_id"] ?? 0);
        $supplierId = (int) ($_POST["supplier_id"] ?? 0);
        $priceInput = trim($_POST["supplier_price"] ?? "");

        if (
            $productId <= 0 ||
            $supplierId <= 0 ||
            $priceInput === "" ||
            !is_numeric($priceInput) ||
            !is_finite((float) $priceInput) ||
            (float) $priceInput < 0
        ) {
            $error = "Data dan harga beli harus valid.";

        } else {

            $price = (float) $priceInput;

            $stmt = $conn->prepare(
                "UPDATE product_suppliers
                 SET supplier_price = ?
                 WHERE product_id = ? AND supplier_id = ?"
            );

            $stmt->bind_param(
                "dii",
                $price,
                $productId,
                $supplierId
            );

            try {

                if ($stmt->execute()) {

                    $stmt->close();

                    header(
                        "Location: product_suppliers.php?status=updated"
                    );
                    exit;
                }

            } catch (mysqli_sql_exception $e) {
                $error = "Harga beli gagal diperbarui.";
            }

            $stmt->close();
        }
    }

    // ==========================================
    // HAPUS HUBUNGAN PRODUK DENGAN SUPPLIER
    // ==========================================
    elseif ($action === "delete") {

        $productId = (int) ($_POST["product_id"] ?? 0);
        $supplierId = (int) ($_POST["supplier_id"] ?? 0);

        if ($productId <= 0 || $supplierId <= 0) {

            $error = "Data hubungan tidak valid.";

        } else {

            $stmt = $conn->prepare(
                "DELETE FROM product_suppliers
                 WHERE product_id = ? AND supplier_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $productId,
                $supplierId
            );

            try {

                if ($stmt->execute()) {

                    if ($stmt->affected_rows > 0) {
                        $stmt->close();

                        header(
                            "Location: product_suppliers.php?status=deleted"
                        );
                        exit;
                    }

                    $error =
                        "Hubungan produk dan supplier tidak ditemukan.";
                }

            } catch (mysqli_sql_exception $e) {
                $error = "Hubungan produk dan supplier gagal dihapus.";
            }

            $stmt->close();
        }
    }
}

// ==========================================
// AMBIL DATA HUBUNGAN UNTUK DIEDIT
// ==========================================
$editData = null;

if (isset($_GET["edit"])) {

    $productId = (int) ($_GET["product_id"] ?? 0);
    $supplierId = (int) ($_GET["supplier_id"] ?? 0);

    if ($productId > 0 && $supplierId > 0) {

        $stmt = $conn->prepare(
            "SELECT
                ps.product_id,
                ps.supplier_id,
                ps.supplier_price,
                p.name AS product_name,
                s.name AS supplier_name
             FROM product_suppliers ps
             JOIN products p ON ps.product_id = p.id
             JOIN suppliers s ON ps.supplier_id = s.id
             WHERE ps.product_id = ?
               AND ps.supplier_id = ?"
        );

        $stmt->bind_param("ii", $productId, $supplierId);
        $stmt->execute();

        $editData = $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

// ==========================================
// AMBIL DAFTAR PRODUK UNTUK DROPDOWN
// ==========================================
$products = $conn->query(
    "SELECT id, name, sku
     FROM products
     ORDER BY name ASC"
);

// ==========================================
// AMBIL DAFTAR SUPPLIER UNTUK DROPDOWN
// ==========================================
$suppliers = $conn->query(
    "SELECT id, name
     FROM suppliers
     ORDER BY name ASC"
);

// ==========================================
// AMBIL DAFTAR HUBUNGAN PRODUK-SUPPLIER
// Data diurutkan berdasarkan ID secara menaik
// ==========================================
$result = $conn->query(
    "SELECT
        ps.product_id,
        ps.supplier_id,
        ps.supplier_price,
        p.name AS product_name,
        p.sku,
        s.name AS supplier_name
     FROM product_suppliers ps
     JOIN products p ON ps.product_id = p.id
     JOIN suppliers s ON ps.supplier_id = s.id
     ORDER BY ps.product_id ASC, ps.supplier_id ASC"
);

// ==========================================
// PESAN BERHASIL
// ==========================================
$statusMessages = [
    "added" => "Supplier berhasil dihubungkan dengan produk!",
    "updated" => "Harga beli berhasil diperbarui!",
    "deleted" => "Hubungan produk dan supplier berhasil dihapus!"
];

$success = $statusMessages[$_GET["status"] ?? ""] ?? "";

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Produk dan Supplier - Inventory Management</title>
</head>

<body>

    <h2>Hubungan Produk dan Supplier</h2>

    <a href="index.php">Kembali ke Dashboard</a>

    <hr>

    <?php if ($error !== ""): ?>
        <p style="color: red;">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p style="color: green;">
            <?= htmlspecialchars($success) ?>
        </p>
    <?php endif; ?>

    <h3>
        <?= $editData
            ? "Edit Harga Beli"
            : "Hubungkan Produk dengan Supplier" ?>
    </h3>

    <?php if (!$editData): ?>

        <form method="POST">

            <input type="hidden" name="action" value="add">

            <label for="product_id">Pilih Produk</label><br>

            <select id="product_id" name="product_id" required>
                <option value="">-- Pilih Produk --</option>

                <?php while ($product = $products->fetch_assoc()): ?>
                    <option value="<?= (int) $product["id"] ?>">
                        <?= htmlspecialchars($product["name"]) ?>
                        (<?= htmlspecialchars($product["sku"]) ?>)
                    </option>
                <?php endwhile; ?>

            </select>

            <br><br>

            <label for="supplier_id">Pilih Supplier</label><br>

            <select id="supplier_id" name="supplier_id" required>
                <option value="">-- Pilih Supplier --</option>

                <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                    <option value="<?= (int) $supplier["id"] ?>">
                        <?= htmlspecialchars($supplier["name"]) ?>
                    </option>
                <?php endwhile; ?>

            </select>

            <br><br>

            <label for="supplier_price">Harga Beli dari Supplier (Rp)</label><br>

            <input
                type="number"
                id="supplier_price"
                name="supplier_price"
                min="0"
                step="0.01"
                required
            >

            <br><br>

            <button type="submit">Hubungkan Produk</button>

        </form>

    <?php else: ?>

        <p>
            <strong>Produk:</strong>
            <?= htmlspecialchars($editData["product_name"]) ?>
        </p>

        <p>
            <strong>Supplier:</strong>
            <?= htmlspecialchars($editData["supplier_name"]) ?>
        </p>

        <form method="POST">

            <input type="hidden" name="action" value="edit">

            <input
                type="hidden"
                name="product_id"
                value="<?= (int) $editData["product_id"] ?>"
            >

            <input
                type="hidden"
                name="supplier_id"
                value="<?= (int) $editData["supplier_id"] ?>"
            >

            <label for="supplier_price">Harga Beli Baru (Rp)</label><br>

            <input
                type="number"
                id="supplier_price"
                name="supplier_price"
                min="0"
                step="0.01"
                value="<?= htmlspecialchars(
                    (string) $editData["supplier_price"]
                ) ?>"
                required
            >

            <br><br>

            <button type="submit">Simpan Perubahan</button>

            <a href="product_suppliers.php">Batal</a>

        </form>

    <?php endif; ?>

    <hr>

    <h3>Daftar Hubungan Produk dan Supplier</h3>

    <table border="1" cellpadding="8">

        <tr>
            <th>Produk</th>
            <th>SKU</th>
            <th>Supplier</th>
            <th>Harga Beli Supplier</th>
            <th>Aksi</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): ?>

            <tr>

                <td>
                    <?= htmlspecialchars($row["product_name"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row["sku"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row["supplier_name"]) ?>
                </td>

                <td>
                    Rp<?= number_format(
                        (float) $row["supplier_price"],
                        0,
                        ',',
                        '.'
                    ) ?>
                </td>

                <td>

                    <a
                        href="product_suppliers.php?edit=1&product_id=<?= (int) $row["product_id"] ?>&supplier_id=<?= (int) $row["supplier_id"] ?>"
                    >
                        Edit Harga
                    </a>

                    <form
                        method="POST"
                        style="display: inline;"
                        onsubmit="return confirm('Yakin ingin menghapus hubungan ini?');"
                    >

                        <input type="hidden" name="action" value="delete">

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?= (int) $row["product_id"] ?>"
                        >

                        <input
                            type="hidden"
                            name="supplier_id"
                            value="<?= (int) $row["supplier_id"] ?>"
                        >

                        <button type="submit">Hapus</button>

                    </form>

                </td>

            </tr>

        <?php endwhile; ?>

    </table>

</body>

</html>