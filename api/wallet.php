<?php
/**
 * TAPay E-Wallet API Controller
 * Xử lý:
 * - Xem số dư, thông tin thẻ ví, thống kê dòng tiền, lịch sử giao dịch
 * - Nạp tiền (Top-up) qua VietQR / Thẻ ngân hàng
 * - Tra cứu người nhận chuyển khoản (qua SĐT, Email hoặc Số thẻ ví)
 * - Chuyển tiền P2P nguyên tử (Atomic Transfer) có kiểm tra mã PIN
 * - Chuyển đổi Cashback về số dư ví chính
 */

require_once __DIR__ . '/../config/database.php';

$db = DatabaseManager::getInstance();
$pdo = $db->getPdo();
$action = $_GET['action'] ?? '';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = (int)($_GET['user_id'] ?? $input['user_id'] ?? 1);

try {
    switch ($action) {
        case 'overview': {
            // Lấy danh sách tất cả users để hỗ trợ Quick Account Switcher & gợi ý chuyển khoản
            $allUsersStmt = $pdo->query("
                SELECT u.id, u.full_name, u.email, u.phone, u.avatar, u.role, u.tier,
                       w.wallet_number, w.balance, w.cashback_balance
                FROM users u
                JOIN wallets w ON w.user_id = u.id
                ORDER BY u.id ASC
            ");
            $allUsers = $allUsersStmt->fetchAll();

            // Lấy thông tin user + ví hiện tại
            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.email, u.phone, u.avatar, u.role, u.tier, u.pin_code,
                       w.id as wallet_id, w.wallet_number, w.balance, w.cashback_balance, w.currency, w.status
                FROM users u
                JOIN wallets w ON w.user_id = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();

            if (!$wallet) {
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy ví người dùng'], 404);
            }

            // Lấy lịch sử giao dịch của ví này
            $txStmt = $pdo->prepare("
                SELECT * FROM wallet_transactions
                WHERE wallet_id = ?
                ORDER BY id DESC
                LIMIT 50
            ");
            $txStmt->execute([$wallet['wallet_id']]);
            $transactions = $txStmt->fetchAll();

            // Tính toán thống kê thu/chi
            $stats = [
                'total_in' => 0,
                'total_out' => 0,
                'total_refund' => 0,
                'total_cashback' => 0
            ];
            foreach ($transactions as &$tx) {
                $amt = (float)$tx['amount'];
                if (in_array($tx['type'], ['TOPUP', 'TRANSFER_IN', 'REFUND', 'CASHBACK'])) {
                    $stats['total_in'] += $amt;
                } else {
                    $stats['total_out'] += $amt;
                }
                if ($tx['type'] === 'REFUND') {
                    $stats['total_refund'] += $amt;
                }
                if ($tx['type'] === 'CASHBACK') {
                    $stats['total_cashback'] += $amt;
                }
                $tx['metadata_parsed'] = !empty($tx['metadata']) ? json_decode($tx['metadata'], true) : [];
            }

            unset($wallet['pin_code']); // Không trả PIN thô ra frontend

            jsonResponse([
                'success' => true,
                'wallet' => $wallet,
                'stats' => $stats,
                'transactions' => $transactions,
                'all_users' => $allUsers,
                'db_info' => $db->getConnectionInfo()
            ]);
            break;
        }

        case 'lookup_recipient': {
            $query = trim($_GET['q'] ?? '');
            if ($query === '') {
                jsonResponse(['success' => false, 'message' => 'Vui lòng nhập SĐT, Email hoặc Số ví']);
            }
            $normalizedWallet = str_replace(' ', '', $query);
            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.phone, u.email, u.avatar, w.id as wallet_id, w.wallet_number
                FROM users u
                JOIN wallets w ON w.user_id = u.id
                WHERE (u.phone = ? OR LOWER(u.email) = LOWER(?) OR REPLACE(w.wallet_number, ' ', '') = ?)
                  AND u.id != ?
                LIMIT 1
            ");
            $stmt->execute([$query, $query, $normalizedWallet, $userId]);
            $recipient = $stmt->fetch();

            if (!$recipient) {
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy người nhận phù hợp']);
            }

            jsonResponse([
                'success' => true,
                'recipient' => $recipient
            ]);
            break;
        }

        case 'topup': {
            $amount = (float)($input['amount'] ?? 0);
            $source = trim($input['source'] ?? 'VietQR - Ngân hàng Vietcombank');
            $refNote = trim($input['note'] ?? 'Nạp tiền vào ví TAPay');

            if ($amount < 10000) {
                jsonResponse(['success' => false, 'message' => 'Số tiền nạp tối thiểu là 10.000đ'], 400);
            }
            if ($amount > 500000000) {
                jsonResponse(['success' => false, 'message' => 'Số tiền nạp tối đa mỗi giao dịch là 500.000.000đ'], 400);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id, balance, wallet_number FROM wallets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();

            if (!$wallet) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy ví'], 404);
            }

            $balanceBefore = (float)$wallet['balance'];
            $balanceAfter = $balanceBefore + $amount;

            $upd = $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
            $upd->execute([$balanceAfter, $wallet['id']]);

            $txCode = 'TOP-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $desc = "Nạp tiền vào ví từ {$source}" . ($refNote ? " ({$refNote})" : "");
            $meta = json_encode([
                'source' => $source,
                'note' => $refNote,
                'wallet_number' => $wallet['wallet_number']
            ], JSON_UNESCAPED_UNICODE);

            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions (tx_code, wallet_id, type, amount, balance_before, balance_after, status, description, metadata)
                VALUES (?, ?, 'TOPUP', ?, ?, ?, 'COMPLETED', ?, ?)
            ");
            $insTx->execute([$txCode, $wallet['id'], $amount, $balanceBefore, $balanceAfter, $desc, $meta]);

            $pdo->commit();

            // Đồng bộ Supabase REST nếu có cấu hình
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

            jsonResponse([
                'success' => true,
                'message' => 'Nạp tiền vào ví thành công!',
                'receipt' => [
                    'tx_code' => $txCode,
                    'type' => 'TOPUP',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'source' => $source,
                    'description' => $desc,
                    'created_at' => date('d/m/Y H:i:s')
                ]
            ]);
            break;
        }

        case 'transfer': {
            $recipientQuery = trim($input['recipient'] ?? '');
            $amount = (float)($input['amount'] ?? 0);
            $message = trim($input['message'] ?? 'Chuyển tiền qua ví TAPay');
            $pin = trim($input['pin'] ?? '');

            if ($amount < 1000) {
                jsonResponse(['success' => false, 'message' => 'Số tiền chuyển tối thiểu là 1.000đ'], 400);
            }

            $pdo->beginTransaction();

            // 1. Kiểm tra người gửi và mã PIN
            $senderStmt = $pdo->prepare("
                SELECT u.id as user_id, u.full_name, u.phone, u.pin_code,
                       w.id as wallet_id, w.wallet_number, w.balance
                FROM users u
                JOIN wallets w ON w.user_id = u.id
                WHERE u.id = ?
            ");
            $senderStmt->execute([$userId]);
            $sender = $senderStmt->fetch();

            if (!$sender) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Tài khoản gửi không hợp lệ'], 404);
            }

            if ($pin !== $sender['pin_code']) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Mã PIN bảo mật không chính xác (Mặc định: 123456)'], 403);
            }

            if ((float)$sender['balance'] < $amount) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Số dư ví không đủ để thực hiện giao dịch này'], 400);
            }

            // 2. Tìm người nhận
            $normalizedWallet = str_replace(' ', '', $recipientQuery);
            $recStmt = $pdo->prepare("
                SELECT u.id as user_id, u.full_name, u.phone, u.email,
                       w.id as wallet_id, w.wallet_number, w.balance
                FROM users u
                JOIN wallets w ON w.user_id = u.id
                WHERE (u.phone = ? OR LOWER(u.email) = LOWER(?) OR REPLACE(w.wallet_number, ' ', '') = ?)
                LIMIT 1
            ");
            $recStmt->execute([$recipientQuery, $recipientQuery, $normalizedWallet]);
            $recipient = $recStmt->fetch();

            if (!$recipient) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy người nhận với SĐT/Email/Số ví này'], 404);
            }

            if ((int)$recipient['user_id'] === (int)$sender['user_id']) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Không thể tự chuyển tiền cho chính mình'], 400);
            }

            // 3. Trừ tiền người gửi & Cộng tiền người nhận
            $senderBefore = (float)$sender['balance'];
            $senderAfter = $senderBefore - $amount;

            $recBefore = (float)$recipient['balance'];
            $recAfter = $recBefore + $amount;

            $updWallet = $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
            $updWallet->execute([$senderAfter, $sender['wallet_id']]);
            $updWallet->execute([$recAfter, $recipient['wallet_id']]);

            // 4. Lưu 2 bản ghi lịch sử giao dịch
            $baseCode = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $txOutCode = 'TRF-' . $baseCode . '-OUT';
            $txInCode  = 'TRF-' . $baseCode . '-IN';

            $cleanMsg = $message !== '' ? $message : "Chuyển tiền từ {$sender['full_name']}";

            $metaOut = json_encode([
                'recipient_name' => $recipient['full_name'],
                'recipient_phone' => $recipient['phone'],
                'recipient_wallet' => $recipient['wallet_number'],
                'message' => $cleanMsg
            ], JSON_UNESCAPED_UNICODE);

            $metaIn = json_encode([
                'sender_name' => $sender['full_name'],
                'sender_phone' => $sender['phone'],
                'sender_wallet' => $sender['wallet_number'],
                'message' => $cleanMsg
            ], JSON_UNESCAPED_UNICODE);

            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions
                (tx_code, wallet_id, related_wallet_id, type, amount, balance_before, balance_after, status, description, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?, ?)
            ");

            $insTx->execute([
                $txOutCode,
                $sender['wallet_id'],
                $recipient['wallet_id'],
                'TRANSFER_OUT',
                $amount,
                $senderBefore,
                $senderAfter,
                "Chuyển tiền đến {$recipient['full_name']} ({$recipient['phone']}): {$cleanMsg}",
                $metaOut
            ]);

            $insTx->execute([
                $txInCode,
                $recipient['wallet_id'],
                $sender['wallet_id'],
                'TRANSFER_IN',
                $amount,
                $recBefore,
                $recAfter,
                "Nhận tiền từ {$sender['full_name']} ({$sender['phone']}): {$cleanMsg}",
                $metaIn
            ]);

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => "Đã chuyển thành công " . number_format($amount, 0, ',', '.') . "đ cho {$recipient['full_name']}!",
                'receipt' => [
                    'tx_code' => $txOutCode,
                    'type' => 'TRANSFER_OUT',
                    'amount' => $amount,
                    'sender_name' => $sender['full_name'],
                    'sender_wallet' => $sender['wallet_number'],
                    'recipient_name' => $recipient['full_name'],
                    'recipient_phone' => $recipient['phone'],
                    'recipient_wallet' => $recipient['wallet_number'],
                    'message' => $cleanMsg,
                    'balance_after' => $senderAfter,
                    'created_at' => date('d/m/Y H:i:s')
                ]
            ]);
            break;
        }

        case 'bank_transfer': {
            $bankCode = trim($input['bank_code'] ?? 'TCB');
            $bankName = trim($input['bank_name'] ?? 'Techcombank');
            $accountNumber = preg_replace('/\s+/', '', trim($input['account_number'] ?? ''));
            $accountName = strtoupper(trim($input['account_name'] ?? ''));
            $amount = (float)($input['amount'] ?? 0);
            $message = trim($input['message'] ?? 'Chuyển khoản Napas 24/7 từ Ví TAPay');
            $pin = trim($input['pin'] ?? '');

            if (empty($accountNumber) || strlen($accountNumber) < 6) {
                jsonResponse(['success' => false, 'message' => 'Vui lòng nhập số tài khoản ngân hàng hợp lệ'], 400);
            }
            if (empty($accountName)) {
                jsonResponse(['success' => false, 'message' => 'Vui lòng nhập tên chủ tài khoản nhận'], 400);
            }
            if ($amount < 10000) {
                jsonResponse(['success' => false, 'message' => 'Số tiền chuyển khoản tối thiểu là 10.000đ'], 400);
            }

            $pdo->beginTransaction();

            $senderStmt = $pdo->prepare("
                SELECT u.id as user_id, u.full_name, u.phone, u.pin_code,
                       w.id as wallet_id, w.wallet_number, w.balance
                FROM users u
                JOIN wallets w ON w.user_id = u.id
                WHERE u.id = ?
            ");
            $senderStmt->execute([$userId]);
            $sender = $senderStmt->fetch();

            if (!$sender) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Tài khoản gửi không hợp lệ'], 404);
            }

            if ($pin !== $sender['pin_code']) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Mã PIN bảo mật không chính xác (Mặc định: 123456)'], 403);
            }

            if ((float)$sender['balance'] < $amount) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Số dư ví không đủ để thực hiện chuyển khoản'], 400);
            }

            $balanceBefore = (float)$sender['balance'];
            $balanceAfter = $balanceBefore - $amount;

            $upd = $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
            $upd->execute([$balanceAfter, $sender['wallet_id']]);

            $txCode = 'BNK-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $cleanMsg = $message ?: 'Chuyển khoản Napas 24/7';
            $desc = "Chuyển khoản {$bankName} ({$accountNumber} - {$accountName}): {$cleanMsg}";
            $meta = json_encode([
                'transfer_type' => 'BANK_247',
                'bank_code' => $bankCode,
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'message' => $cleanMsg
            ], JSON_UNESCAPED_UNICODE);

            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions (tx_code, wallet_id, type, amount, balance_before, balance_after, status, description, metadata)
                VALUES (?, ?, 'TRANSFER_OUT', ?, ?, ?, 'COMPLETED', ?, ?)
            ");
            $insTx->execute([$txCode, $sender['wallet_id'], $amount, $balanceBefore, $balanceAfter, $desc, $meta]);

            $pdo->commit();

            $db->syncToSupabaseRest('wallet_transactions', [
                'tx_code' => $txCode,
                'wallet_id' => $sender['wallet_id'],
                'type' => 'TRANSFER_OUT',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'COMPLETED',
                'description' => $desc
            ]);

            jsonResponse([
                'success' => true,
                'message' => "Đã chuyển " . number_format($amount, 0, ',', '.') . "đ tới {$accountName} ({$bankName}) thành công!",
                'receipt' => [
                    'tx_code' => $txCode,
                    'amount' => $amount,
                    'sender_name' => $sender['full_name'],
                    'sender_wallet' => $sender['wallet_number'],
                    'bank_code' => $bankCode,
                    'bank_name' => $bankName,
                    'account_number' => $accountNumber,
                    'account_name' => $accountName,
                    'message' => $cleanMsg,
                    'balance_after' => $balanceAfter,
                    'created_at' => date('d/m/Y H:i:s')
                ]
            ]);
            break;
        }

        case 'claim_cashback': {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, balance, cashback_balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();

            if (!$wallet || (float)$wallet['cashback_balance'] <= 0) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Bạn chưa có số dư tiền thưởng Cashback để rút về ví chính'], 400);
            }

            $cbAmount = (float)$wallet['cashback_balance'];
            $before = (float)$wallet['balance'];
            $after = $before + $cbAmount;

            $upd = $pdo->prepare("UPDATE wallets SET balance = ?, cashback_balance = 0 WHERE id = ?");
            $upd->execute([$after, $wallet['id']]);

            $txCode = 'CBK-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions (tx_code, wallet_id, type, amount, balance_before, balance_after, status, description, metadata)
                VALUES (?, ?, 'CASHBACK', ?, ?, ?, 'COMPLETED', 'Rút tiền thưởng tích lũy Cashback về số dư Ví chính', '{}')
            ");
            $insTx->execute([$txCode, $wallet['id'], $cbAmount, $before, $after]);

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => 'Đã cộng ' . number_format($cbAmount, 0, ',', '.') . 'đ tiền thưởng Cashback vào số dư ví chính!',
                'balance_after' => $after
            ]);
            break;
        }

        case 'db_config': {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $info = $db->saveConfig($input);
                jsonResponse([
                    'success' => true,
                    'message' => 'Đã lưu cấu hình Cơ sở dữ liệu (' . $info['label'] . ')',
                    'db_info' => $info,
                    'config' => $db->getConfig()
                ]);
            } else {
                jsonResponse([
                    'success' => true,
                    'db_info' => $db->getConnectionInfo(),
                    'config' => $db->getConfig()
                ]);
            }
            break;
        }

        default:
            jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
    }
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
}
