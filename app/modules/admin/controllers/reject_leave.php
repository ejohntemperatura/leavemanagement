<?php
session_start();
require_once '../../../../config/database.php';

// Ensure user is HR/Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

$request_id = $_GET['id'] ?? '';
$reason = $_POST['reason'] ?? 'No reason provided';

if (empty($request_id)) {
    $_SESSION['error'] = 'Invalid request ID';
    header('Location: ../views/leave_management.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // Fetch leave request
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception('Leave request not found');
    }

    // Verify sequence: Department Head must approve first
    if (($request['dept_head_approval'] ?? 'pending') !== 'approved') {
        throw new Exception('Department Head must approve first before HR can review.');
    }

    // Update HR/Admin approval to rejected and final status rejected
    $stmt = $pdo->prepare("UPDATE leave_requests SET admin_approval = 'rejected', admin_approved_by = ?, admin_approved_at = NOW(), admin_approval_notes = ?, status = 'rejected', rejected_by = ?, rejected_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $reason, $_SESSION['user_id'], $request_id]);

    $pdo->commit();
    $_SESSION['success'] = 'Leave request rejected by HR.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error processing HR rejection: ' . $e->getMessage();
}

header('Location: ../views/leave_management.php');
exit();
