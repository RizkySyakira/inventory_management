
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$error = "";

// ==========================================
// PROSES TAMBAH, EDIT, DAN HAPUS PRODUK
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add" || $action === "edit") {
        $id = (int) ($_POST["id"] ?? 0);
        $sku = trim($_POST["sku"] ?? "");
        $name = trim($_POST["name"] ?? "");
        $categoryId = (int) ($_POST["category_id"] ?? 0);

        $price = filter_var(
            $_POST["price"] ?? "",
            FILTER_VALIDATE_FLOAT
        );

        $stock = filter_var(
            $_POST["stock"] ?? "",
            FILTER_VALIDATE_INT
        );

        $minimumStock = filter_var(
            $_POST["minimum_stock"] ?? "",
            FILTER_VALIDATE_INT
        );

        if (
            $sku === "" ||
            $name === "" ||
            $categoryId <= 0 ||
            $price === false ||
            $stock === false ||
            $minimumStock === false ||
            $price < 0 ||
            $stock < 0 ||
            $minimumStock < 0
        ) {
            $error = "Data tidak valid. Periksa kembali semua input.";
        } else {
            $categoryCheck = $conn->prepare(
                "SELECT id FROM categories WHERE id = ?"
            );
            $categoryCheck->bind_param("i", $categoryId);
            $categoryCheck->execute();

            if ($categoryCheck->get_result()->num_rows === 0) {
                $error = "Kategori tidak ditemukan.";
            } else {
                try {
                    if ($action === "add") {
                        $stmt = $conn->prepare(
                            "INSERT INTO products
                            (category_id, sku, name, price, stock, minimum_stock)
                            VALUES (?, ?, ?, ?, ?, ?)"
                        );

                        $stmt->bind_param(
                            "issdii",
                            $categoryId,
                            $sku,
                            $name,
                            $price,
                            $stock,
                            $minimumStock
                        );
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE products
                             SET category_id = ?, sku = ?, name = ?,
                                 price = ?, stock = ?, minimum_stock = ?
                             WHERE id = ?"
                        );

                        $stmt->bind_param(
                            "issdiii",
                            $categoryId,
                            $sku,
                            $name,
                            $price,
                            $stock,
                            $minimumStock,
                            $id
                        );
                    }

                    $stmt->execute();

                    $status = $action === "add" ? "added" : "updated";

                    header("Location: products.php?status=" . $status);
                    exit;
                } catch (mysqli_sql_exception $e) {
                    if ((int) $e->getCode() === 1062) {
                        $error = "SKU sudah digunakan. Gunakan SKU lain.";
                    } else {
                        $error = "Produk gagal disimpan. Periksa kembali datanya.";
                    }
                }
            }

            $categoryCheck->close();
        }
    }

    if ($action === "delete") {
        $id = (int) ($_POST["id"] ?? 0);

        $movementCheck = $conn->prepare(
            "SELECT id FROM stock_movements
             WHERE product_id = ? LIMIT 1"
        );
        $movementCheck->bind_param("i", $id);
        $movementCheck->execute();

        if ($movementCheck->get_result()->num_rows > 0) {
            $error = "Produk tidak dapat dihapus karena memiliki riwayat stok.";
        } else {
            $supplierCheck = $conn->prepare(
                "SELECT product_id FROM product_suppliers
                 WHERE product_id = ? LIMIT 1"
            );
            $supplierCheck->bind_param("i", $id);
            $supplierCheck->execute();

            if ($supplierCheck->get_result()->num_rows > 0) {
                $error = "Hubungan supplier harus dihapus terlebih dahulu.";
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM products WHERE id = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();

                header("Location: products.php?status=deleted");
                exit;
            }

            $supplierCheck->close();
        }

        $movementCheck->close();
    }
}

// ==========================================
// DATA KATEGORI
// ==========================================

$categoryResult = $conn->query(
    "SELECT id, name FROM categories ORDER BY id ASC"
);

$categoryList = $categoryResult->fetch_all(MYSQLI_ASSOC);

// ==========================================
// PENCARIAN DAN FILTER KATEGORI
// ==========================================

$search = trim($_GET["q"] ?? "");
$categoryFilter = (int) ($_GET["category_filter"] ?? 0);

$like = "%" . $search . "%";

// ==========================================
// SORTING
// ==========================================

$allowedSorts = [
    "name" => "products.name",
    "sku" => "products.sku",
    "price" => "products.price",
    "stock" => "products.stock"
];

$sort = $_GET["sort"] ?? "name";

if (!isset($allowedSorts[$sort])) {
    $sort = "name";
}

$order = strtoupper($_GET["order"] ?? "ASC");

if (!in_array($order, ["ASC", "DESC"], true)) {
    $order = "ASC";
}

// ==========================================
// PAGINATION
// ==========================================

$perPageOptions = [5, 10, 20];

$perPage = (int) ($_GET["per_page"] ?? 5);

if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 5;
}

$page = max(1, (int) ($_GET["page"] ?? 1));

$whereSql = "
    WHERE (products.name LIKE ? OR products.sku LIKE ?)
      AND (? = 0 OR products.category_id = ?)
";

// Hitung produk yang cocok dengan pencarian
$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM products
     JOIN categories ON products.category_id = categories.id
     $whereSql"
);

$countStmt->bind_param(
    "ssii",
    $like,
    $like,
    $categoryFilter,
    $categoryFilter
);

$countStmt->execute();

$totalProducts = (int) $countStmt
    ->get_result()
    ->fetch_assoc()["total"];

$countStmt->close();

$totalPages = max(
    1,
    (int) ceil($totalProducts / $perPage)
);

$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Ambil produk sesuai sorting dan halaman
$sortColumn = $allowedSorts[$sort];

$sql = "
    SELECT
        products.id,
        products.sku,
        products.name,
        products.price,
        products.stock,
        products.minimum_stock,
        products.category_id,
        categories.name AS category_name
    FROM products
    JOIN categories ON products.category_id = categories.id
    $whereSql
    ORDER BY $sortColumn $order, products.id ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssiiii",
    $like,
    $like,
    $categoryFilter,
    $categoryFilter,
    $perPage,
    $offset
);

$stmt->execute();

$result = $stmt->get_result();

// ==========================================
// MODE EDIT
// ==========================================

$editProduct = null;

if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];

    $editStmt = $conn->prepare(
        "SELECT * FROM products WHERE id = ?"
    );
    $editStmt->bind_param("i", $editId);
    $editStmt->execute();

    $editProduct = $editStmt->get_result()->fetch_assoc();
}

// ==========================================
// PESAN STATUS
// ==========================================

$statusMessages = [
    "added" => "Produk berhasil ditambahkan!",
    "updated" => "Produk berhasil diperbarui!",
    "deleted" => "Produk berhasil dihapus!"
];

$status = $_GET["status"] ?? "";

// Parameter untuk mempertahankan filter
$currentParams = [
    "q" => $search,
    "category_filter" => $categoryFilter,
    "sort" => $sort,
    "order" => $order,
    "per_page" => $perPage
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Produk - Inventory Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f4f6f9;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1, h2 {
            color: #243447;
        }

        .card {
            background: white;
            padding: 22px;
            margin-bottom: 24px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin: 7px 0 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button, .button {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            text-align: center;
        }

        .primary {
            background: #2563eb;
            color: white;
        }

        .danger {
            background: #dc2626;
            color: white;
        }

        .secondary {
            background: #64748b;
            color: white;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .search-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 10px;
            align-items: center;
        }

        .search-grid input,
        .search-grid select {
            margin: 0;
        }

        .search-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #eaf0f7;
        }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .actions button {
            white-space: nowrap;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        @media (max-width: 900px) {
            .search-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            body {
                margin: 15px;
            }

            .search-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Manajemen Produk</h1>

    <p>
        Login sebagai:
        <strong>
            <?= htmlspecialchars(
                $_SESSION["username"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </strong>
        |
        <a href="index.php">Dashboard</a>
    </p>

    <?php if (isset($statusMessages[$status])): ?>
        <div class="success-message">
            <?= htmlspecialchars(
                $statusMessages[$status],
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error-message">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <!-- PENCARIAN, FILTER, SORTING, PAGINATION -->
    <div class="card">
        <h2>Cari dan Atur Produk</h2>

        <form method="GET" action="products.php">
            <div class="search-grid">

                <input
                    type="text"
                    name="q"
                    placeholder="Cari nama produk atau SKU..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <select name="category_filter">
                    <option value="0">Semua Kategori</option>

                    <?php foreach ($categoryList as $category): ?>
                        <option
                            value="<?= (int) $category["id"] ?>"
                            <?= $categoryFilter === (int) $category["id"]
                                ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars(
                                $category["name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="sort">
                    <option value="name"
                        <?= $sort === "name" ? "selected" : "" ?>>
                        Nama Produk
                    </option>

                    <option value="sku"
                        <?= $sort === "sku" ? "selected" : "" ?>>
                        SKU
                    </option>

                    <option value="price"
                        <?= $sort === "price" ? "selected" : "" ?>>
                        Harga
                    </option>

                    <option value="stock"
                        <?= $sort === "stock" ? "selected" : "" ?>>
                        Stok
                    </option>
                </select>

                <select name="order">
                    <option value="ASC"
                        <?= $order === "ASC" ? "selected" : "" ?>>
                        Naik (A-Z / terkecil)
                    </option>

                    <option value="DESC"
                        <?= $order === "DESC" ? "selected" : "" ?>>
                        Turun (Z-A / terbesar)
                    </option>
                </select>

                <select name="per_page">
                    <?php foreach ($perPageOptions as $option): ?>
                        <option
                            value="<?= $option ?>"
                            <?= $perPage === $option ? "selected" : "" ?>
                        >
                            <?= $option ?> per halaman
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>

            <div class="search-actions">
                <button type="submit" class="primary">
                    Terapkan
                </button>

                <a href="products.php" class="button secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- FORM TAMBAH DAN EDIT -->
    <div class="card">
        <h2>
            <?= $editProduct ? "Edit Produk" : "Tambah Produk" ?>
        </h2>

        <form method="POST" action="products.php">
            <input
                type="hidden"
                name="action"
                value="<?= $editProduct ? "edit" : "add" ?>"
            >

            <?php if ($editProduct): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $editProduct["id"] ?>"
                >
            <?php endif; ?>

            <label>SKU</label>
            <input
                type="text"
                name="sku"
                required
                value="<?= htmlspecialchars(
                    $editProduct["sku"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label>Nama Produk</label>
            <input
                type="text"
                name="name"
                required
                value="<?= htmlspecialchars(
                    $editProduct["name"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label>Kategori</label>
            <select name="category_id" required>
                <option value="">Pilih Kategori</option>

                <?php foreach ($categoryList as $category): ?>
                    <option
                        value="<?= (int) $category["id"] ?>"
                        <?= isset($editProduct["category_id"]) &&
                            (int) $editProduct["category_id"] ===
                            (int) $category["id"]
                            ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars(
                            $category["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Harga (Rp)</label>
            <input
                type="number"
                name="price"
                min="0"
                step="0.01"
                required
                value="<?= htmlspecialchars(
                    (string) ($editProduct["price"] ?? ""),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label>Stok</label>
            <input
                type="number"
                name="stock"
                min="0"
                step="1"
                required
                value="<?= htmlspecialchars(
                    (string) ($editProduct["stock"] ?? "0"),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label>Minimum Stok</label>
            <input
                type="number"
                name="minimum_stock"
                min="0"
                step="1"
                required
                value="<?= htmlspecialchars(
                    (string) ($editProduct["minimum_stock"] ?? "0"),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <button type="submit" class="primary">
                <?= $editProduct ? "Simpan Perubahan" : "Tambah Produk" ?>
            </button>

            <?php if ($editProduct): ?>
                <a
                    href="products.php?<?= htmlspecialchars(
                        http_build_query($currentParams),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    class="button secondary"
                >
                    Batal
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- DAFTAR PRODUK -->
    <div class="card">
        <h2>Daftar Produk</h2>

        <p>
            Total produk ditemukan:
            <strong><?= $totalProducts ?></strong>
        </p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Minimum Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result->num_rows > 0): ?>

                    <?php while ($product = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int) $product["id"] ?></td>

                            <td>
                                <?= htmlspecialchars(
                                    $product["sku"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $product["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $product["category_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                Rp <?= number_format(
                                    (float) $product["price"],
                                    0,
                                    ",",
                                    "."
                                ) ?>
                            </td>

                            <td><?= (int) $product["stock"] ?></td>

                            <td><?= (int) $product["minimum_stock"] ?></td>

                            <td>
                                <div class="actions">

                                    <a
                                        class="button primary"
                                        href="products.php?<?= htmlspecialchars(
                                            http_build_query(
                                                array_merge(
                                                    $currentParams,
                                                    [
                                                        "edit" => (int) $product["id"]
                                                    ]
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="products.php"
                                        onsubmit="return confirm('Yakin ingin menghapus produk ini?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $product["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="danger"
                                        >
                                            Hapus
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">
                            Produk tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- NAVIGASI HALAMAN -->
        <div class="pagination">

            <span>
                Halaman <?= $page ?> dari <?= $totalPages ?>
            </span>

            <?php if ($page > 1): ?>
                <?php
                $previousParams = array_merge(
                    $currentParams,
                    ["page" => $page - 1]
                );
                ?>

                <a
                    class="button primary"
                    href="products.php?<?= htmlspecialchars(
                        http_build_query($previousParams),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >
                    &laquo; Sebelumnya
                </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <?php
                $nextParams = array_merge(
                    $currentParams,
                    ["page" => $page + 1]
                );
                ?>

                <a
                    class="button primary"
                    href="products.php?<?= htmlspecialchars(
                        http_build_query($nextParams),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >
                    Berikutnya &raquo;
                </a>
            <?php endif; ?>

        </div>
    </div>

</div>
</body>
</html>