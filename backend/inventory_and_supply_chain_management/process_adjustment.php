<?php
session_start();
include '../../SQL/config.php';

/* =====================================================
   SAFETY CHECK
=====================================================*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: inventory_management.php");
    exit;
}

/* =====================================================
   GET & VALIDATE INPUTS
=====================================================*/
$inventory_id = isset($_POST['inventory_id']) ? (int)$_POST['inventory_id'] : 0;
$old_quantity = isset($_POST['old_quantity']) ? (int)$_POST['old_quantity'] : 0;
$new_quantity = isset($_POST['new_quantity']) ? (int)$_POST['new_quantity'] : -1;
$reason       = trim($_POST['reason'] ?? '');

if ($inventory_id <= 0 || $new_quantity < 0 || empty($reason)) {
    header("Location: inventory_management.php?error=invalid_input");
    exit;
}

try {

    /* =====================================================
       START TRANSACTION
    =====================================================*/
    $pdo->beginTransaction();

    /* =====================================================
       VERIFY INVENTORY EXISTS
    =====================================================*/
    $stmt = $pdo->prepare("
        SELECT id, item_name, total_qty
        FROM inventory
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$inventory_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $pdo->rollBack();
        header("Location: inventory_management.php?error=not_found");
        exit;
    }

    $currentQty = (int)$item['total_qty'];

    /* =====================================================
       PREVENT FAKE OLD QUANTITY (SECURITY CHECK)
    =====================================================*/
    if ($currentQty !== $old_quantity) {
        $old_quantity = $currentQty; // override with actual DB value
    }

    /* =====================================================
       UPDATE INVENTORY QUANTITY
    =====================================================*/
    $updateStmt = $pdo->prepare("
        UPDATE inventory
        SET total_qty = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$new_quantity, $inventory_id]);

    /* =====================================================
       INSERT ADJUSTMENT RECORD
    =====================================================*/
    $adjStmt = $pdo->prepare("
        INSERT INTO stock_adjustments
        (inventory_id, old_quantity, new_quantity, reason, adjusted_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $adjStmt->execute([
        $inventory_id,
        $old_quantity,
        $new_quantity,
        $reason
    ]);

    /* =====================================================
       COMMIT
    =====================================================*/
    $pdo->commit();

    header("Location: inventory_management.php?success=adjusted");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: inventory_management.php?error=server_error");
    exit;
}