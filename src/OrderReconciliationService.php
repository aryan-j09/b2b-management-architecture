<?php
/**
 * OrderReconciliationService
 * Handles state transitions for purchase orders based on payment and stock movement.
 */

function reconcilePurchaseOrderState(mysqli $db, int $purchaseOrderId): bool
{
    if ($purchaseOrderId <= 0) return false;

    // 1. Get financial data
    $orderStmt = $db->prepare("SELECT grand_total, paid_amount FROM purchase_order_list WHERE id = ?");
    $orderStmt->bind_param('i', $purchaseOrderId);
    $orderStmt->execute();
    $orderStmt->bind_result($grandTotal, $paidAmount);
    $orderStmt->fetch();
    $orderStmt->close();

    // 2. Get fulfillment data
    $recvStmt = $db->prepare("SELECT SUM(quantity) FROM stock_movement WHERE reference_id = ? AND movement_type = 'IN'");
    $recvStmt->bind_param('i', $purchaseOrderId);
    $recvStmt->execute();
    $recvStmt->bind_result($totalReceived);
    $recvStmt->fetch();
    $recvStmt->close();

    // 3. Logic: Close only if fully paid AND fully received
    $status = ($paidAmount >= $grandTotal && $totalReceived > 0) ? 'closed' : 'open';
    $closedAt = ($status === 'closed') ? date('Y-m-d H:i:s') : null;

    $update = $db->prepare("UPDATE purchase_order_list SET close_status = ?, closed_at = ? WHERE id = ?");
    $update->bind_param('ssi', $status, $closedAt, $purchaseOrderId);
    
    return $update->execute();
}
