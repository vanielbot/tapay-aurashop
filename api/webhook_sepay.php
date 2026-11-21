<?php
/**
 * TAstore Mall & Ví Điện Tử TAPay - SePay Webhook Receiver
 * Nhận biến động số dư từ SePay (https://my.sepay.vn/webhooks) và tự động cộng tiền vào Ví TAPay.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'service' => 'TAPay SePay Webhook Endpoint',
        'status' => 'READY',
        'message' => 'Endpoint đang hoạt động. Hãy cấu hình SePay gửi POST JSON về URL này.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

// Kiểm tra API Key nếu có cấu hình trong config/sepay_config.json
$configFile = __DIR__ . '/../config/sepay_config.json';
if (file_exists($configFile)) {
    $cfg = json_decode(file_get_contents($configFile), true) ?: [];
    $expectedKey = trim($cfg['api_key'] ?? '');
    if ($expectedKey !== '') {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $providedKey = '';
        if (stripos($authHeader, 'Apikey ') === 0) {
            $providedKey = trim(substr($authHeader, 7));
        } elseif (stripos($authHeader, 'Bearer ') === 0) {
            $providedKey = trim(substr($authHeader, 7));
        }
        if ($providedKey !== $expectedKey) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized API Key']);
            exit;
        }
    }
}

$transferType = strtolower(trim($payload['transferType'] ?? ''));
if ($transferType !== 'in') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored non-incoming transaction']);
    exit;
}

$sepayId = (string)($payload['id'] ?? '');
$amount = (float)($payload['transferAmount'] ?? 0);
$gateway = trim($payload['gateway'] ?? 'Ngân hàng');
$accountNumber = trim($payload['accountNumber'] ?? '');
$content = trim($payload['content'] ?? '');
$code = trim($payload['code'] ?? '');
$referenceCode = trim($payload['referenceCode'] ?? '');
$txDate = trim($payload['transactionDate'] ?? date('Y-m-d H:i:s'));

if ($amount <= 0) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored zero-amount transaction']);
    exit;
}

$txCode = $sepayId !== '' ? ('SEPAY-' . $sepayId) : ('SEPAY-' . strtoupper(substr(md5($rawBody . microtime(true)), 0, 8)));

try {
    $db = DatabaseManager::getInstance();
    $pdo = $db->getPdo();

    // 1. Chống cộng trùng giao dịch
    $dupStmt = $pdo->prepare("SELECT id FROM wallet_transactions WHERE tx_code = ? LIMIT 1");
    $dupStmt->execute([$txCode]);
    if ($dupStmt->fetch()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Duplicate transaction ignored', 'tx_code' => $txCode]);
        exit;
    }

    // 2. Tìm số ví từ nội dung chuyển khoản (VD: "NAP TAPAY 990088880001" hoặc "990088880001")
    $searchText = $code . ' ' . $content;
    $matchedWalletNum = null;
    if (preg_match('/NAP\s*TAPAY\s*(\d{8,16})/i', $searchText, $m)) {
        $matchedWalletNum = $m[1];
    } elseif (preg_match('/(9900\d{8})/', $searchText, $m)) {
        $matchedWalletNum = $m[1];
    }

    $pdo->beginTransaction();

    $wallet = null;
    if ($matchedWalletNum !== null) {
        $wStmt = $pdo->prepare("SELECT id, user_id, wallet_number, balance FROM wallets WHERE REPLACE(wallet_number, ' ', '') = ? LIMIT 1");
        $wStmt->execute([$matchedWalletNum]);
        $wallet = $wStmt->fetch();
    }

    // Fallback về ví chính (user_id = 1) nếu chuyển khoản test không ghi mã ví
    $matchedBy = $wallet ? 'WALLET_CODE' : 'DEFAULT_PRIMARY';
    if (!$wallet) {
        $wStmt = $pdo->prepare("SELECT id, user_id, wallet_number, balance FROM wallets ORDER BY id ASC LIMIT 1");
        $wStmt->execute();
        $wallet = $wStmt->fetch();
    }

    if (!$wallet) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No target wallet found']);
        exit;
    }

    $balanceBefore = (float)$wallet['balance'];
    $balanceAfter = $balanceBefore + $amount;

    $upd = $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $upd->execute([$balanceAfter, $wallet['id']]);

    $desc = "Tự động nạp tiền qua SePay ({$gateway}" . ($accountNumber ? " - {$accountNumber}" : '') . "): " . ($content ?: 'Chuyển khoản VietQR');
    $meta = json_encode([
        'source' => "SePay Webhook ({$gateway})",
        'sepay_id' => $sepayId,
        'gateway' => $gateway,
        'account_number' => $accountNumber,
        'reference_code' => $referenceCode,
        'transaction_date' => $txDate,
        'content' => $content,
        'matched_by' => $matchedBy
    ], JSON_UNESCAPED_UNICODE);

    $insTx = $pdo->prepare("
        INSERT INTO wallet_transactions (tx_code, wallet_id, type, amount, balance_before, balance_after, status, description, metadata)
        VALUES (?, ?, 'TOPUP', ?, ?, ?, 'COMPLETED', ?, ?)
    ");
    $insTx->execute([$txCode, $wallet['id'], $amount, $balanceBefore, $balanceAfter, $desc, $meta]);

    $pdo->commit();

    $db->syncToSupabaseRest('wallet_transactions', [
        'tx_code' => $txCode,
        'wallet_id' => $wallet['id'],
        'type' => 'TOPUP',
        'amount' => $amount,
        'balance_before' => $balanceBefore,
        'balance_after' => $balanceAfter,
        'status' => 'COMPLETED',
        'description' => $desc
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Credited wallet automatically via SePay Webhook',
        'tx_code' => $txCode,
        'wallet_number' => $wallet['wallet_number'],
        'amount' => $amount,
        'balance_after' => $balanceAfter
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Webhook processing error: ' . $e->getMessage()]);
}
