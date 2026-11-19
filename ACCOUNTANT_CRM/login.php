<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once "includes/db.php";
require_once __DIR__ . '/includes/security_events.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validate_request();

    $username = trim((string)($_POST["username"] ?? ''));
    $password = (string)($_POST["password"] ?? '');

    $identityHash = crm_security_hash(mb_strtolower($username, 'UTF-8'));
    $source = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $sourceHash = crm_security_hash($source);
    $throttle = crm_login_begin($mysqli, $identityHash, $sourceHash);

    if ($throttle['limited']) {
        http_response_code(429);
        crm_record_security_event($mysqli, 'login_throttled', 'blocked', $identityHash, $sourceHash, ['scope' => $throttle['scope']]);
        $mysqli->commit();
        $mysqli->commit();
    $error = "Λάθος στοιχεία σύνδεσης!";
    } else {

    // Fetch user
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();

        // Validate password
        if (password_verify($password, $user["password"])) {

            crm_login_success($mysqli, $identityHash);
            crm_record_security_event($mysqli, 'login_success', 'success', $identityHash, $sourceHash);

            $mysqli->commit();
            session_regenerate_id(true);
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

            $_SESSION["user_id"]   = $user["id"];
            $_SESSION["username"]  = $user["username"];
            $_SESSION["fullname"]  = $user["fullname"];
            $_SESSION["role"]      = $user["role"];

            header("Location: index.php");
            exit;
        }
    }

    if (!$throttle['limited']) {
        $locked = crm_login_failure($mysqli, $identityHash, $sourceHash);
        crm_record_security_event($mysqli, 'login_failure', $locked ? 'throttled' : 'failure', $identityHash, $sourceHash);
    }
    $mysqli->commit();
    $error = "Λάθος στοιχεία σύνδεσης!";
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>ELMICO CRM — Σύνδεση</title>
<link rel="stylesheet" href="assets/style.css">

<style>
body {
    background:#f0f0f5;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    font-family:Arial;
}
.login-box {
    background:white;
    padding:30px;
    width:350px;
    border-radius:10px;
    box-shadow:0 3px 8px rgba(0,0,0,0.2);
}
input {
    width:100%;
    padding:10px;
    margin-top:10px;
    border-radius:5px;
    border:1px solid #ccc;
}
button {
    width:100%;
    padding:12px;
    background:#007bff;
    border:none;
    color:white;
    border-radius:5px;
    margin-top:15px;
    cursor:pointer;
}
button:hover {
    background:#006fe0;
}
.error {
    color:#d9534f;
    margin-bottom:10px;
    font-size:15px;
}
</style>
</head>

<body>

<div class="login-box">
    <h2>ELMICO CRM</h2>
    <p>Σύνδεση</p>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="text" name="username" placeholder="Όνομα χρήστη" required>
        <input type="password" name="password" placeholder="Κωδικός" required>
        <button type="submit">Είσοδος</button>
    </form>
</div>

</body>
</html>

