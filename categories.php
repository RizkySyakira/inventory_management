<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$error = "";

// Proses tambah kategori
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // Tambah kategori
    if ($action === "add") {

        $name = trim($_POST["name"] ?? "");

        if ($name === "") {
            $error = "Nama kategori tidak boleh kosong.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO categories (name) VALUES (?)"
            );

            $stmt->bind_param("s", $name);

            if ($stmt->execute()) {
                header("Location: categories.php?status=added");
                exit;
            }

            $error = "Kategori gagal ditambahkan.";
            $stmt->close();
        }
    }

    // Edit kategori
    if ($action === "edit") {

        $id = (int) ($_POST["id"] ?? 0);
        $name = trim($_POST["name"] ?? "");

        if ($id <= 0 || $name === "") {
            $error = "Data kategori tidak valid.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE categories SET name = ? WHERE id = ?"
            );

            $stmt->bind_param("si", $name, $id);

            if ($stmt->execute()) {
                header("Location: categories.php?status=updated");
                exit;
            }

            $error = "Kategori gagal diperbarui.";
            $stmt->close();
        }
    }

    // Hapus kategori
    if ($action === "delete") {

        $id = (int) ($_POST["id"] ?? 0);

        if ($id <= 0) {
            $error = "ID kategori tidak valid.";
        } else {
            $stmt = $conn->prepare(
                "DELETE FROM categories WHERE id = ?"
            );

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                header("Location: categories.php?status=deleted");
                exit;
            }

            $error = "Kategori tidak bisa dihapus. Pastikan kategori tidak sedang digunakan oleh produk.";
            $stmt->close();
        }
    }
}

// Ambil kategori yang ingin diedit
$editCategory = null;

if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT id, name FROM categories WHERE id = ?"
    );

    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $editCategory = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Ambil daftar kategori
$result = $conn->query(
    "SELECT id, name FROM categories ORDER BY id ASC"
);

$statusMessages = [
    "added" => "Kategori berhasil ditambahkan!",
    "updated" => "Kategori berhasil diperbarui!",
    "deleted" => "Kategori berhasil dihapus!"
];

$status = $_GET["status"] ?? "";
$success = $statusMessages[$status] ?? "";

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kategori - Inventory Management</title>
</head>

<body>

    <h2>Data Kategori</h2>

    <a href="index.php">Kembali ke Dashboard</a>

    <hr>

    <?php if ($error !== ""): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <h3>
        <?= $editCategory ? "Edit Kategori" : "Tambah Kategori" ?>
    </h3>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="<?= $editCategory ? "edit" : "add" ?>"
        >

        <?php if ($editCategory): ?>
            <input
                type="hidden"
                name="id"
                value="<?= (int) $editCategory["id"] ?>"
            >
        <?php endif; ?>

        <label for="name">Nama Kategori</label><br>

        <input
            type="text"
            id="name"
            name="name"
            value="<?= htmlspecialchars($editCategory["name"] ?? "") ?>"
            required
        >

        <button type="submit">
            <?= $editCategory ? "Simpan Perubahan" : "Tambah" ?>
        </button>

        <?php if ($editCategory): ?>
            <a href="categories.php">Batal</a>
        <?php endif; ?>

    </form>

    <hr>

    <h3>Daftar Kategori</h3>

    <table border="1" cellpadding="8">

        <tr>
            <th>ID</th>
            <th>Nama Kategori</th>
            <th>Aksi</th>
        </tr>

        <?php while ($category = $result->fetch_assoc()): ?>

            <tr>
                <td>
                    <?= (int) $category["id"] ?>
                </td>

                <td>
                    <?= htmlspecialchars($category["name"]) ?>
                </td>

                <td>
                    <a href="categories.php?edit=<?= (int) $category["id"] ?>">
                        Edit
                    </a>

                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">

                        <input type="hidden" name="action" value="delete">

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $category["id"] ?>"
                        >

                        <button type="submit">Hapus</button>

                    </form>
                </td>
            </tr>

        <?php endwhile; ?>

    </table>

</body>
</html>