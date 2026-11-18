<?php 
if (!isset($pageTitle)) $pageTitle = "Accountant CRM"; 
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars((string)$pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>

    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Global CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/assets/style.css">

    <style>
        body {
            margin: 0;
            background: #f5f6fa;
            font-family: 'Inter', sans-serif;
        }

        header {
            position: relative;
        }

        /* =========================================
           DESKTOP TOP MENU
        ========================================== */
        .top-menu {
            width: 100%;
            box-sizing: border-box;
            background: #1f1f1f;
            padding: 14px 20px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.25);
        }

        .menu-logo {
            font-size: 20px;
            font-weight: 600;
            margin-right: 0;
            white-space: nowrap;
        }

        .menu-links {
            display: flex;
            flex: 1 1 auto;
            gap: 12px;
            min-width: 0;
        }

        .menu-links a {
            text-decoration: none;
            color: #d4d4d4;
            padding-bottom: 2px;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
            font-size: 15px;
            font-weight: 400;
        }

        .menu-links a:hover,
        .menu-links a.active {
            color: white;
            border-bottom: 2px solid #3fa9f5;
        }

        .menu-user {
            flex: 0 1 auto;
            margin-left: 0;
            max-width: 32%;
            color: #d4d4d4;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: normal;
        }

        .menu-user b {
            overflow-wrap: anywhere;
        }

        .menu-user a {
            color: #3fa9f5;
            text-decoration: none;
        }
        .menu-user a:hover {
            text-decoration: underline;
        }


        /* =========================================
           MOBILE MENU
        ========================================== */

        /* Hide desktop menu on small screens */
        @media (max-width: 1400px) {
            .menu-links,
            .menu-user {
                display: none;
            }
        }

        /* Hamburger */
        .hamburger {
            display: none;
            cursor: pointer;
            font-size: 26px;
            margin-left: auto;
        }

        @media (max-width: 1400px) {
            .hamburger {
                display: block;
            }
        }

        /* Mobile drawer */
        .mobile-menu {
            display: none;
            flex-direction: column;
            background: #1f1f1f;
            padding: 15px 20px;
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            width: auto;
            box-sizing: border-box;
            z-index: 999;
        }

        .mobile-menu a {
            padding: 10px 0;
            text-decoration: none;
            color: #e1e1e1;
            font-size: 17px;
            border-bottom: 1px solid #333;
        }

        .mobile-menu a:last-child {
            border: none;
        }

        .mobile-user-box {
            padding: 12px 0;
            margin-top: 10px;
            border-top: 1px solid #444;
            color: #ccc;
            font-size: 15px;
        }

        .mobile-user-box a {
            color: #3fa9f5;
        }

    </style>

    <script>
        function toggleMobileMenu() {
            let m = document.getElementById("mobileMenu");
            m.style.display = (m.style.display === "flex") ? "none" : "flex";
        }
    </script>

</head>
<body>

<header>
    <div class="top-menu">

        <div class="menu-logo">Accountant CRM</div>

        <!-- DESKTOP MENU -->
        <nav class="menu-links">
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/index.php" class="<?= $pageTitle=='Αρχική' ? 'active':'' ?>">Αρχική</a>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/tasks.php" class="<?= $pageTitle=='Εργασίες' ? 'active':'' ?>">Εργασίες</a>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/recurring_tasks.php" class="<?= $pageTitle=='Επαναλαμβανόμενες εργασίες' ? 'active':'' ?>">Επαναλήψεις</a>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/clients.php" class="<?= $pageTitle=='Πελάτες' ? 'active':'' ?>">Πελάτες</a>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/partners.php" class="<?= $pageTitle=='Συνεργάτες' ? 'active':'' ?>">Συνεργάτες</a>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/calendar.php" class="<?= $pageTitle=='Ημερολόγιο Εργασιών' ? 'active':'' ?>">Ημερολόγιο</a>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/active_professionals.php" class="<?= $pageTitle=='Ενεργοί Επαγγελματίες' ? 'active':'' ?>">Ενεργοί Επαγγελματίες</a>
                <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/mydiary.php" class="<?= $pageTitle=='MyDiary' ? 'active':'' ?>">MyDiary</a>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/payment_methods.php" class="<?= $pageTitle=='Μέσα είσπραξης' ? 'active':'' ?>">Μέσα είσπραξης</a>
                <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/task_titles.php" class="<?= $pageTitle=='Τίτλοι εργασιών' ? 'active':'' ?>">Τίτλοι εργασιών</a>
            <?php endif; ?>

        </nav>

        <!-- USER (desktop) -->
        <div class="menu-user">
            Συνδεδεμένος ως: <b><?= htmlspecialchars((string)$_SESSION['fullname'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></b> |
            <form action="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/logout.php" method="post" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" style="padding:0;border:0;background:none;color:inherit;text-decoration:underline;cursor:pointer;">Αποσύνδεση</button>
            </form>
        </div>

        <!-- HAMBURGER (mobile) -->
        <div class="hamburger" onclick="toggleMobileMenu()">☰</div>

    </div>

    <!-- MOBILE MENU (drawer) -->
    <div class="mobile-menu" id="mobileMenu">

        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/index.php">Αρχική</a>
        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/tasks.php">Εργασίες</a>
        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/recurring_tasks.php">Επαναλήψεις</a>
        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/clients.php">Πελάτες</a>
        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/partners.php">Συνεργάτες</a>
        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/calendar.php">Ημερολόγιο</a>
        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/active_professionals.php">Ενεργοί Επαγγελματίες</a>
        <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/mydiary.php">MyDiary</a>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/payment_methods.php">Μέσα είσπραξης</a>
            <a href="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/task_titles.php">Τίτλοι εργασιών</a>
        <?php endif; ?>
        <div class="mobile-user-box">
            Συνδεδεμένος ως:<br>
            <b><?= htmlspecialchars((string)$_SESSION['fullname'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></b><br>
            <form action="<?= htmlspecialchars(crm_base_path(), ENT_QUOTES, 'UTF-8') ?>/logout.php" method="post">
                <?= csrf_field() ?>
                <button type="submit" style="padding:0;border:0;background:none;color:inherit;text-decoration:underline;cursor:pointer;">Αποσύνδεση</button>
            </form>
        </div>

    </div>
</header>

<main>
<div class="page-container">


