
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$error = "";

// Proses tambah supplier
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "add" || $action === "edit") {

        $name = trim($_POST["name"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $address = trim($_POST["address"] ?? "");

        if ($name === "") {
            $error = "Nama supplier tidak boleh kosong.";
        } else {

            if ($action === "add") {

                $stmt = $conn->prepare(
                    "INSERT INTO suppliers (name, phone, address)
                     VALUES (?, ?, ?)"
                );

                $stmt->bind_param("sss", $name, $phone, $address);
                $successStatus = "added";

            } else {

                $id = (int) ($_POST["id"] ?? 0);

                if ($id <= 0) {
                    $error = "ID supplier tidak valid.";
                } else {

                    $stmt = $conn->prepare(
                        "UPDATE suppliers
                         SET name = ?, phone = ?, address = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "sssi",
                        $name,
                        $phone,
                        $address,
                        $id
                    );

                    $successStatus = "updated";
                }
            }

            if (isset($stmt)) {

                if ($stmt->execute()) {
                    $stmt->close();

                    header(
                        "Location: suppliers.php?status=" .
                        $successStatus
                    );
                    exit;
                }

                $error = "Data supplier gagal disimpan.";
                $stmt->close();
            }
        }
    }

    // Hapus supplier
    elseif ($action === "delete") {

        $id = (int) ($_POST["id"] ?? 0);

        if ($id <= 0) {
            $error = "ID supplier tidak valid.";
        } else {

            // Periksa apakah supplier masih terhubung ke produk
            $check = $conn->prepare(
                "SELECT supplier_id FROM product_suppliers
                 WHERE supplier_id = ?
                 LIMIT 1"
            );

            $check->bind_param("i", $id);
            $check->execute();

            $hasProducts =
                $check->get_result()->num_rows > 0;

            $check->close();

            if ($hasProducts) {

                $error =
                    "Supplier masih terhubung dengan produk. Hapus hubungan produk terlebih dahulu.";

            } else {

                $stmt = $conn->prepare(
                    "DELETE FROM suppliers WHERE id = ?"
                );

                $stmt->bind_param("i", $id);

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $stmt->close();

                    header("Location: suppliers.php?status=deleted");
                    exit;
                }

                $error = "Supplier gagal dihapus atau tidak ditemukan.";
                $stmt->close();
            }
        }
    }
}

// Ambil supplier untuk diedit
$editSupplier = null;

if (isset($_GET["edit"])) {

    $editId = (int) $_GET["edit"];

    if ($editId > 0) {

        $stmt = $conn->prepare(
            "SELECT id, name, phone, address
             FROM suppliers WHERE id = ?"
        );

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $editSupplier =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

// Ambil daftar supplier
$result = $conn->query(
    "SELECT id, name, phone, address
     FROM suppliers
     ORDER BY id ASC"
);

// Pesan berhasil
$statusMessages = [
    "added" => "Supplier berhasil ditambahkan!",
    "updated" => "Supplier berhasil diperbarui!",
    "deleted" => "Supplier berhasil dihapus!"
];

$success = $statusMessages[$_GET["status"] ?? ""] ?? "";

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Data Supplier - Inventory Management</title>
</head>

<body>

    <h2>Data Supplier</h2>

    <a href="index.php">Kembali ke Dashboard</a>

    <hr>

    <?php if ($error !== ""): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <h3>
        <?= $editSupplier ? "Edit Supplier" : "Tambah Supplier" ?>
    </h3>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="<?= $editSupplier ? "edit" : "add" ?>"
        >

        <?php if ($editSupplier): ?>
            <input
                type="hidden"
                name="id"
                value="<?= (int) $editSupplier["id"] ?>"
            >
        <?php endif; ?>

        <label for="name">Nama Supplier</label><br>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= htmlspecialchars($editSupplier["name"] ?? "") ?>"
            required
        >

        <br><br>

        <label for="phone">Nomor Telepon</label><br>
        <input
            type="text"
            id="phone"
            name="phone"
            value="<?= htmlspecialchars($editSupplier["phone"] ?? "") ?>"
        >

        <br><br>

        <label for="address">Alamat</label><br>
        <textarea
            id="address"
            name="address"
            rows="4"
            cols="40"
        ><?= htmlspecialchars($editSupplier["address"] ?? "") ?></textarea>

        <br><br>

        <button type="submit">
            <?= $editSupplier ? "Simpan Perubahan" : "Tambah Supplier" ?>
        </button>

        <?php if ($editSupplier): ?>
            <a href="suppliers.php">Batal</a>
        <?php endif; ?>

    </form>

    <hr>

    <h3>Daftar Supplier</h3>

    <table border="1" cellpadding="8">

        <tr>
            <th>ID</th>
            <th>Nama Supplier</th>
            <th>Nomor Telepon</th>
            <th>Alamat</th>
            <th>Aksi</th>
        </tr>

        <?php while ($supplier = $result->fetch_assoc()): ?>

            <tr>

                <td><?= (int) $supplier["id"] ?></td>

                <td>
                    <?= htmlspecialchars($supplier["name"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($supplier["phone"] ?? "") ?>
                </td>

                <td>
                    <?= htmlspecialchars($supplier["address"] ?? "") ?>
                </td>

                <td>

                    <a href="suppliers.php?edit=<?= (int) $supplier["id"] ?>">
                        Edit
                    </a>

                    <form
                        method="POST"
                        style="display:inline;"
                        onsubmit="return confirm('Yakin ingin menghapus supplier ini?');"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="delete"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $supplier["id"] ?>"
                        >

                        <button type="submit">Hapus</button>

                    </form>

                </td>

            </tr>

        <?php endwhile; ?>

    </table>

</body>
</html>