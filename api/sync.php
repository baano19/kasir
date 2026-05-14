<?php
header('Content-Type: application/json');
include "../includes/db.php";

$type = $_GET['type'] ?? '';

if ($type === 'pull') {
    // Pull master data & existing transactions/expenses
    $branches = $db->query("SELECT id, name, meal_allowance, address FROM branches")->fetchAll(PDO::FETCH_ASSOC);
    $services = $db->query("SELECT id, name, price, branch_id FROM services")->fetchAll(PDO::FETCH_ASSOC);
    $users = $db->query("SELECT id, username, name, role, branch_id, meal_allowance FROM users")->fetchAll(PDO::FETCH_ASSOC);

    // History limit: 30 days
    $transactions = $db->query("SELECT id, user_id, service_name, amount, notes, created_at, branch_id FROM transactions WHERE created_at >= date('now', '-30 days')")->fetchAll(PDO::FETCH_ASSOC);
    $expenses = $db->query("SELECT id, user_id, branch_id, category, amount, notes, created_at FROM expenses WHERE created_at >= date('now', '-30 days')")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'branches' => $branches,
            'services' => $services,
            'users' => $users,
            'transactions' => $transactions,
            'expenses' => $expenses
        ]
    ]);
} elseif ($type === 'push' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $txs = $data['transactions'] ?? [];
    $exps = $data['expenses'] ?? [];

    $db->beginTransaction();
    try {
        $txResults = [];
        $expResults = [];

        foreach ($txs as $tx) {
            $stmt = $db->prepare("INSERT INTO transactions (user_id, service_name, amount, notes, created_at, branch_id) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$tx['user_id'], $tx['service_name'], $tx['amount'], $tx['notes'], $tx['created_at'], $tx['branch_id']]);
            $txResults[] = ['localId' => $tx['localId'], 'remoteId' => (int)$db->lastInsertId()];
        }

        foreach ($exps as $exp) {
            $stmt = $db->prepare("INSERT INTO expenses (user_id, branch_id, category, amount, notes, created_at) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$exp['user_id'], $exp['branch_id'], $exp['category'], $exp['amount'], $exp['notes'], $exp['created_at']]);
            $expResults[] = ['localId' => $exp['localId'], 'remoteId' => (int)$db->lastInsertId()];
        }

        $db->commit();
        echo json_encode([
            'success' => true,
            'results' => [
                'transactions' => $txResults,
                'expenses' => $expResults
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
