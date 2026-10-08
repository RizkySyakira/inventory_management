<?php

session_start();

require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "SELECT id, username, password_hash, role
         FROM users
         WHERE username = ?"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password_hash"])) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            header("Location: index.php");
            exit;
        }
    }

    $error = "Username atau password salah.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Inventory Management</title>
</head>

<body>

    <h2>Login Inventory Management</h2>

    <?php if ($error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Username</label><br>
        <input type="text" name="username" required>

        <br><br>

        <label>Password</label><br>
        <input type="password" name="password" required>

        <br><br>

        <button type="submit">Login</button>

    </form>

</body>
</html>