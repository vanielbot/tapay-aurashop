<?php
/**
 * TAstore E-Commerce API Controller
 * Xử lý:
 * - Danh sách sản phẩm, lọc, tìm kiếm
 * - Tạo sản phẩm mới (Create Product) & Cập nhật/Xóa sản phẩm dễ dàng
 * - Đặt hàng & Thanh toán (Hỗ trợ: Ví điện tử TAPay, COD, Chuyển khoản ngân hàng VietQR, và Trả sau Pay Later báo chưa cập nhật)
 * - Quản lý đơn hàng & Hoàn tiền đơn hàng (Order Refund) 100% về Ví điện tử
 */

require_once __DIR__ . '/../config/database.php';

$db = DatabaseManager::getInstance();
$pdo = $db->getPdo();
$action = $_GET['action'] ?? '';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = (int)($_GET['user_id'] ?? $input['user_id'] ?? 1);

try {
    switch ($action) {
        case 'products': {
            $category = trim($_GET['category'] ?? '');
            $search = trim($_GET['q'] ?? '');

            $sql = "SELECT * FROM products WHERE 1=1";
            $params = [];

            if ($category !== '' && $category !== 'all') {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            if ($search !== '') {
                $sql .= " AND (LOWER(name) LIKE LOWER(?) OR LOWER(description) LIKE LOWER(?) OR LOWER(sku) LIKE LOWER(?))";
                $kw = "%{$search}%";
                $params[] = $kw;
                $params[] = $kw;
                $params[] = $kw;
            }
            $sql .= " ORDER BY id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();

            // Lấy danh sách các danh mục hiện có
            $catStmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category ASC");
            $categories = array_column($catStmt->fetchAll(), 'category');

            jsonResponse([
                'success' => true,
                'products' => $products,
                'categories' => $categories
            ]);
            break;
        }

        case 'create_product': {
            $name = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? 'Công nghệ');
            $price = (float)($input['price'] ?? 0);
            $originalPrice = (float)($input['original_price'] ?? 0);
            $cashbackRate = (float)($input['cashback_rate'] ?? 5.0);
            $stock = (int)($input['stock'] ?? 50);
            $badge = trim($input['badge'] ?? '');
            $imageUrl = trim($input['image_url'] ?? '');
            $description = trim($input['description'] ?? '');

            if ($name === '' || $price <= 0) {
                jsonResponse(['success' => false, 'message' => 'Vui lòng nhập tên sản phẩm và giá bán hợp lệ (> 0đ)'], 400);
            }

            if ($originalPrice <= 0 || $originalPrice < $price) {
                $originalPrice = round($price * 1.18, -3);
            }
            if ($badge === '') {
                $badge = $cashbackRate > 0 ? "Hoàn {$cashbackRate}%" : "Mới";
            }
            if ($imageUrl === '') {
                $imageUrl = 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800&auto=format&fit=crop&q=80';
            }
            if ($description === '') {
                $description = "Sản phẩm chính hãng {$name} thuộc danh mục {$category}, ưu đãi hoàn tiền {$cashbackRate}% khi thanh toán qua ví TAPay.";
            }

            $sku = trim($input['sku'] ?? '');
            if ($sku === '') {
                $sku = 'TA-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
            }

            $stmt = $pdo->prepare("
                INSERT INTO products (sku, name, category, price, original_price, cashback_rate, stock, rating, sold_count, badge, image_url, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, 5.0, 1, ?, ?, ?)
            ");
            $stmt->execute([
                $sku,
                $name,
                $category,
                $price,
                $originalPrice,
                $cashbackRate,
                $stock,
                $badge,
                $imageUrl,
                $description
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Đồng bộ lên Supabase nếu có cấu hình
            $db->syncToSupabaseRest('products', [
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'original_price' => $originalPrice,
                'cashback_rate' => $cashbackRate,
                'stock' => $stock,
                'badge' => $badge,
                'image_url' => $imageUrl,
                'description' => $description
            ]);

            $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $prodStmt->execute([$newId]);
            $product = $prodStmt->fetch();

            jsonResponse([
                'success' => true,
                'message' => "Đã tạo sản phẩm \"{$name}\" thành công!",
                'product' => $product
            ]);
            break;
        }

        case 'delete_product': {
            $prodId = (int)($input['product_id'] ?? 0);
            if ($prodId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Mã sản phẩm không hợp lệ'], 400);
            }
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$prodId]);
            jsonResponse([
                'success' => true,
                'message' => 'Đã xóa sản phẩm khỏi cửa hàng'
            ]);
            break;
        }

        case 'checkout': {
            $items = $input['items'] ?? [];
            $paymentMethod = strtoupper(trim($input['payment_method'] ?? 'WALLET'));
            $recipientName = trim($input['recipient_name'] ?? '');
            $recipientPhone = trim($input['recipient_phone'] ?? '');
            $shippingAddress = trim($input['shipping_address'] ?? '');
            $note = trim($input['note'] ?? '');
            $voucherCode = strtoupper(trim($input['voucher_code'] ?? ''));
            $pin = trim($input['pin'] ?? '');

            // Kiểm tra phương thức Trả sau (chưa cập nhật theo đúng yêu cầu)
            if ($paymentMethod === 'PAY_LATER') {
                jsonResponse([
                    'success' => false,
                    'message' => 'Phương thức "Mua trước trả sau (Pay Later)" hiện chưa cập nhật. Vui lòng chọn Ví điện tử TAPay, Chuyển khoản ngân hàng hoặc COD!'
                ], 400);
            }

            if (empty($items) || !is_array($items)) {
                jsonResponse(['success' => false, 'message' => 'Giỏ hàng của bạn đang trống'], 400);
            }

            if ($recipientName === '' || $recipientPhone === '' || $shippingAddress === '') {
                jsonResponse(['success' => false, 'message' => 'Vui lòng điền đầy đủ Họ tên, Số điện thoại và Địa chỉ nhận hàng'], 400);
            }

            $pdo->beginTransaction();

            // 1. Lấy thông tin người dùng & ví
            $userStmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.phone, u.pin_code,
                       w.id as wallet_id, w.wallet_number, w.balance, w.cashback_balance,
                       COALESCE(w.virtual_balance, 0) as virtual_balance
                FROM users u
                JOIN wallets w ON w.user_id = u.id
                WHERE u.id = ?
            ");
            $userStmt->execute([$userId]);
            $userWallet = $userStmt->fetch();

            if (!$userWallet) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy tài khoản người dùng'], 404);
            }

            // 2. Tính tổng tiền & Cashback từ CSDL thực tế
            $totalAmount = 0;
            $totalCashback = 0;
            $validatedItems = [];

            $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            foreach ($items as $item) {
                $pId = (int)($item['product_id'] ?? $item['id'] ?? 0);
                $qty = max(1, (int)($item['quantity'] ?? 1));

                $prodStmt->execute([$pId]);
                $prod = $prodStmt->fetch();
                if (!$prod) {
                    continue;
                }
                if ((int)$prod['stock'] < $qty) {
                    $pdo->rollBack();
                    jsonResponse(['success' => false, 'message' => "Sản phẩm \"{$prod['name']}\" không đủ tồn kho (Còn {$prod['stock']})"], 400);
                }

                $unitPrice = (float)$prod['price'];
                $subtotal = $unitPrice * $qty;
                $cbRate = (float)$prod['cashback_rate'];
                $itemCashback = round($subtotal * ($cbRate / 100));

                $totalAmount += $subtotal;
                $totalCashback += $itemCashback;

                $validatedItems[] = [
                    'product_id' => $prod['id'],
                    'product_name' => $prod['name'],
                    'product_image' => $prod['image_url'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ];
            }

            if (empty($validatedItems)) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Không có sản phẩm hợp lệ trong giỏ hàng'], 400);
            }

            // 3. Áp dụng Voucher giảm giá
            $discountAmount = 0;
            if ($voucherCode === 'TA50') {
                $discountAmount = min(50000, $totalAmount * 0.1);
            } elseif ($voucherCode === 'TA200') {
                $discountAmount = $totalAmount >= 2000000 ? 200000 : 50000;
            } elseif ($voucherCode === 'VIP500') {
                $discountAmount = $totalAmount >= 5000000 ? 500000 : 100000;
            }

            $finalAmount = max(0, $totalAmount - $discountAmount);
            // Chỉ tích lũy Cashback khi thanh toán bằng Ví điện tử TAPay
            $cashbackEarned = ($paymentMethod === 'WALLET') ? $totalCashback : 0;

            // 4. Xử lý theo từng phương thức thanh toán
            $paymentStatus = 'PAID';
            $orderStatus = 'PROCESSING';
            $balanceBefore = (float)$userWallet['balance'];
            $balanceAfter = $balanceBefore;

            if ($paymentMethod === 'WALLET') {
                if ($pin !== $userWallet['pin_code']) {
                    $pdo->rollBack();
                    jsonResponse(['success' => false, 'message' => 'Mã PIN Ví điện tử không đúng (Mặc định: 123456)'], 403);
                }
                if ($balanceBefore < $finalAmount) {
                    $pdo->rollBack();
                    jsonResponse([
                        'success' => false,
                        'message' => 'Số dư Ví TAPay không đủ (' . number_format($balanceBefore, 0, ',', '.') . 'đ). Vui lòng nạp thêm tiền vào ví hoặc chọn COD / Chuyển khoản!'
                    ], 400);
                }
                $balanceAfter = $balanceBefore - $finalAmount;
                $newCashbackBalance = (float)$userWallet['cashback_balance'] + $cashbackEarned;

                $updW = $pdo->prepare("UPDATE wallets SET balance = ?, cashback_balance = ? WHERE id = ?");
                $updW->execute([$balanceAfter, $newCashbackBalance, $userWallet['wallet_id']]);
                $paymentStatus = 'PAID';
            } elseif ($paymentMethod === 'VIRTUAL_COIN') {
                if ($balanceBefore < $finalAmount) {
                    $pdo->rollBack();
                    jsonResponse([
                        'success' => false,
                        'message' => 'Số dư Ví TAPay không đủ (' . number_format($balanceBefore, 0, ',', '.') . 'đ). Hãy bấm nút "+ Tiền" hoặc nạp QR Pay!'
                    ], 400);
                }
                $balanceAfter = $balanceBefore - $finalAmount;
                $updV = $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
                $updV->execute([$balanceAfter, $userWallet['wallet_id']]);
                $paymentStatus = 'PAID';
            } elseif ($paymentMethod === 'COD') {
                $paymentStatus = 'COD_PENDING';
                $orderStatus = 'PROCESSING';
            } elseif ($paymentMethod === 'BANK_TRANSFER') {
                $paymentStatus = 'PAID';
                $orderStatus = 'PROCESSING';
            } else {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Phương thức thanh toán không hợp lệ'], 400);
            }

            // 5. Tạo đơn hàng
            $orderCode = 'ORD-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $insOrder = $pdo->prepare("
                INSERT INTO orders
                (order_code, user_id, total_amount, discount_amount, final_amount, cashback_earned, payment_method, payment_status, order_status, recipient_name, recipient_phone, shipping_address, note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insOrder->execute([
                $orderCode,
                $userId,
                $totalAmount,
                $discountAmount,
                $finalAmount,
                $cashbackEarned,
                $paymentMethod,
                $paymentStatus,
                $orderStatus,
                $recipientName,
                $recipientPhone,
                $shippingAddress,
                $note
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // 6. Lưu chi tiết đơn hàng & cập nhật tồn kho
            $insItem = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $updStock = $pdo->prepare("UPDATE products SET stock = stock - ?, sold_count = sold_count + ? WHERE id = ?");

            foreach ($validatedItems as $vi) {
                $insItem->execute([
                    $orderId,
                    $vi['product_id'],
                    $vi['product_name'],
                    $vi['product_image'],
                    $vi['quantity'],
                    $vi['unit_price'],
                    $vi['subtotal']
                ]);
                $updStock->execute([$vi['quantity'], $vi['quantity'], $vi['product_id']]);
            }

            // 7. Nếu thanh toán bằng Ví điện tử hoặc Tiền Ảo, ghi lịch sử giao dịch
            $txCode = null;
            if ($paymentMethod === 'WALLET' || $paymentMethod === 'VIRTUAL_COIN') {
                $isVirtual = ($paymentMethod === 'VIRTUAL_COIN');
                $txCode = ($isVirtual ? 'VPAY-' : 'PAY-') . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
                $txType = $isVirtual ? 'VIRTUAL_PAYMENT' : 'PAYMENT';
                $txDesc = $isVirtual
                    ? "Mua sắm bằng Tiền Ảo (#{$orderCode})"
                    : "Thanh toán đơn hàng #{$orderCode}" . ($cashbackEarned > 0 ? " (+Hoàn tiền " . number_format($cashbackEarned, 0, ',', '.') . "đ)" : "");
                $txMeta = json_encode([
                    'order_code' => $orderCode,
                    'order_id' => $orderId,
                    'cashback_earned' => $cashbackEarned,
                    'items_count' => count($validatedItems),
                    'virtual' => $isVirtual
                ], JSON_UNESCAPED_UNICODE);

                $insTx = $pdo->prepare("
                    INSERT INTO wallet_transactions
                    (tx_code, wallet_id, order_id, type, amount, balance_before, balance_after, status, description, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?, ?)
                ");
                $insTx->execute([
                    $txCode,
                    $userWallet['wallet_id'],
                    $orderId,
                    $txType,
                    $finalAmount,
                    $balanceBefore,
                    $balanceAfter,
                    $txDesc,
                    $txMeta
                ]);
            }

            $pdo->commit();

            $methodLabels = [
                'WALLET' => 'Ví điện tử TAPay',
                'VIRTUAL_COIN' => 'Ví Tiền Ảo (Mua Sắm Demo)',
                'COD' => 'Thanh toán khi nhận hàng (COD)',
                'BANK_TRANSFER' => 'Chuyển khoản Ngân hàng (VietQR)'
            ];

            jsonResponse([
                'success' => true,
                'message' => "Đặt hàng thành công (#{$orderCode})!",
                'order' => [
                    'id' => $orderId,
                    'order_code' => $orderCode,
                    'tx_code' => $txCode,
                    'total_amount' => $totalAmount,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $finalAmount,
                    'cashback_earned' => $cashbackEarned,
                    'payment_method' => $paymentMethod,
                    'payment_method_label' => $methodLabels[$paymentMethod] ?? $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'balance_after' => $balanceAfter,
                    'recipient_name' => $recipientName,
                    'recipient_phone' => $recipientPhone,
                    'shipping_address' => $shippingAddress,
                    'created_at' => date('d/m/Y H:i:s'),
                    'items' => $validatedItems
                ]
            ]);
            break;
        }

        case 'orders': {
            $stmt = $pdo->prepare("
                SELECT * FROM orders
                WHERE user_id = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll();

            $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            foreach ($orders as &$ord) {
                $itemStmt->execute([$ord['id']]);
                $ord['items'] = $itemStmt->fetchAll();
            }

            jsonResponse([
                'success' => true,
                'orders' => $orders
            ]);
            break;
        }

        case 'refund': {
            $orderId = (int)($input['order_id'] ?? 0);
            $reason = trim($input['reason'] ?? 'Đổi ý / Muốn hoàn tiền về Ví điện tử TAPay');

            if ($orderId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Mã đơn hàng không hợp lệ'], 400);
            }

            $pdo->beginTransaction();

            // 1. Kiểm tra đơn hàng
            $ordStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
            $ordStmt->execute([$orderId, $userId]);
            $order = $ordStmt->fetch();

            if (!$order) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy đơn hàng này'], 404);
            }

            if ($order['order_status'] === 'REFUNDED' || $order['payment_status'] === 'REFUNDED') {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Đơn hàng này đã được hoàn tiền trước đó'], 400);
            }

            // 2. Lấy ví người dùng để cộng tiền hoàn
            $wStmt = $pdo->prepare("SELECT id, balance, cashback_balance, COALESCE(virtual_balance, 0) as virtual_balance, wallet_number FROM wallets WHERE user_id = ?");
            $wStmt->execute([$userId]);
            $wallet = $wStmt->fetch();

            if (!$wallet) {
                $pdo->rollBack();
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy ví để nhận tiền hoàn'], 404);
            }

            $refundAmount = (float)$order['final_amount'];
            $isVirtualOrder = ($order['payment_method'] === 'VIRTUAL_COIN');
            $balanceBefore = $isVirtualOrder ? (float)$wallet['virtual_balance'] : (float)$wallet['balance'];
            $balanceAfter = $balanceBefore + $refundAmount;

            // Khấu trừ lại cashback đã tặng của đơn này (nếu có)
            $cbEarned = (float)$order['cashback_earned'];
            $newCashback = max(0, (float)$wallet['cashback_balance'] - $cbEarned);

            // 3. Cộng tiền hoàn vào Ví TAPay chính ngay lập tức
            $balanceBefore = (float)$wallet['balance'];
            $balanceAfter = $balanceBefore + $refundAmount;
            $updWallet = $pdo->prepare("UPDATE wallets SET balance = ?, cashback_balance = ? WHERE id = ?");
            $updWallet->execute([$balanceAfter, $newCashback, $wallet['id']]);

            // 4. Cập nhật trạng thái đơn hàng -> REFUNDED
            $nowStr = date('Y-m-d H:i:s');
            $updOrd = $pdo->prepare("
                UPDATE orders
                SET payment_status = 'REFUNDED',
                    order_status = 'REFUNDED',
                    refund_reason = ?,
                    refunded_at = ?
                WHERE id = ?
            ");
            $updOrd->execute([$reason, $nowStr, $orderId]);

            // Hoàn lại tồn kho sản phẩm
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$orderId]);
            $orderItems = $itemsStmt->fetchAll();
            $restoreStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            foreach ($orderItems as $oi) {
                if (!empty($oi['product_id'])) {
                    $restoreStock->execute([$oi['quantity'], $oi['product_id']]);
                }
            }

            // 5. Lưu bản ghi refund_requests
            $refundCode = 'RFD-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $insRefund = $pdo->prepare("
                INSERT INTO refund_requests (refund_code, order_id, user_id, amount, reason, status)
                VALUES (?, ?, ?, ?, ?, 'APPROVED')
            ");
            $insRefund->execute([$refundCode, $orderId, $userId, $refundAmount, $reason]);

            // 6. Ghi lịch sử biến động số dư REFUND / VIRTUAL_REFUND
            $txCode = ($isVirtualOrder ? 'VREF-' : 'REF-') . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $txType = $isVirtualOrder ? 'VIRTUAL_REFUND' : 'REFUND';
            $txDesc = $isVirtualOrder
                ? "Hoàn tiền 100% đơn hàng #{$order['order_code']} về Ví Tiền Ảo (Lý do: {$reason})"
                : "Hoàn tiền 100% đơn hàng #{$order['order_code']} về Ví TAPay (Lý do: {$reason})";
            $txMeta = json_encode([
                'refund_code' => $refundCode,
                'order_code' => $order['order_code'],
                'order_id' => $orderId,
                'reason' => $reason,
                'virtual' => $isVirtualOrder
            ], JSON_UNESCAPED_UNICODE);

            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions
                (tx_code, wallet_id, order_id, type, amount, balance_before, balance_after, status, description, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?, ?)
            ");
            $insTx->execute([
                $txCode,
                $wallet['id'],
                $orderId,
                $txType,
                $refundAmount,
                $balanceBefore,
                $balanceAfter,
                $txDesc,
                $txMeta
            ]);

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => "Đã hoàn tiền " . number_format($refundAmount, 0, ',', '.') . "đ từ đơn #{$order['order_code']} về Ví điện tử thành công!",
                'receipt' => [
                    'tx_code' => $txCode,
                    'refund_code' => $refundCode,
                    'order_code' => $order['order_code'],
                    'type' => 'REFUND',
                    'amount' => $refundAmount,
                    'reason' => $reason,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'created_at' => date('d/m/Y H:i:s')
                ]
            ]);
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
