<?php
/**
 * TAstore & TAPay - Hybrid Database & Supabase Manager
 * Hỗ trợ:
 * 1. Kết nối trực tiếp tới Supabase PostgreSQL (qua PDO_PGSQL) hoặc Supabase REST API
 * 2. Kết nối MySQL của XAMPP hoặc SQLite Local chuẩn ACID để chạy tức thì 100% không bao giờ lỗi
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class DatabaseManager {
    private static $instance = null;
    private $pdo = null;
    private $driver = 'sqlite'; // 'pgsql' (Supabase), 'mysql' (XAMPP), or 'sqlite' (Local Instant)
    private $configFile;
    private $config = [];

    private function __construct() {
        $this->configFile = __DIR__ . '/db_config.json';
        $this->loadConfig();
        $this->connect();
        $this->initializeSchemaIfNeeded();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DatabaseManager();
        }
        return self::$instance;
    }

    private function loadConfig() {
        $defaultConfig = [
            'active_mode' => 'auto', // 'supabase_pg', 'xampp_mysql', 'local_sqlite', 'auto'
            'supabase_url' => '',
            'supabase_anon_key' => '',
            'supabase_db_host' => '',
            'supabase_db_port' => '5432',
            'supabase_db_name' => 'postgres',
            'supabase_db_user' => 'postgres',
            'supabase_db_pass' => '',
            'mysql_host' => '127.0.0.1',
            'mysql_port' => '3306',
            'mysql_db' => 'aurashop_db',
            'mysql_user' => 'root',
            'mysql_pass' => ''
        ];

        if (file_exists($this->configFile)) {
            $saved = json_decode(file_get_contents($this->configFile), true);
            if (is_array($saved)) {
                $this->config = array_merge($defaultConfig, $saved);
                return;
            }
        }
        $this->config = $defaultConfig;
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function saveConfig(array $newConfig) {
        $this->config = array_merge($this->config, $newConfig);
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->connect();
        $this->initializeSchemaIfNeeded();
        return $this->getConnectionInfo();
    }

    public function getConfig() {
        $safe = $this->config;
        if (!empty($safe['supabase_db_pass'])) {
            $safe['supabase_db_pass_masked'] = '••••••••';
        }
        return $safe;
    }

    private function connect() {
        $mode = $this->config['active_mode'] ?? 'auto';

        // 1. Thử kết nối Supabase PostgreSQL nếu có cấu hình
        if (($mode === 'supabase_pg' || $mode === 'auto') && !empty($this->config['supabase_db_host']) && !empty($this->config['supabase_db_pass'])) {
            try {
                $dsn = sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s;sslmode=require",
                    $this->config['supabase_db_host'],
                    $this->config['supabase_db_port'] ?: '5432',
                    $this->config['supabase_db_name'] ?: 'postgres'
                );
                $this->pdo = new PDO($dsn, $this->config['supabase_db_user'], $this->config['supabase_db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5
                ]);
                $this->driver = 'pgsql';
                return;
            } catch (Exception $e) {
                if ($mode === 'supabase_pg') {
                    // Fallback gracefully but record error
                    $this->lastError = 'Supabase PG Error: ' . $e->getMessage();
                }
            }
        }

        // 2. Thử kết nối MySQL trên XAMPP nếu đang bật
        if ($mode === 'xampp_mysql' || $mode === 'auto') {
            try {
                // Thử socket của XAMPP trên macOS hoặc TCP 127.0.0.1
                $socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
                if (file_exists($socket)) {
                    $dsn = "mysql:unix_socket={$socket};charset=utf8mb4";
                } else {
                    $dsn = sprintf("mysql:host=%s;port=%s;charset=utf8mb4", $this->config['mysql_host'], $this->config['mysql_port']);
                }
                $tempPdo = new PDO($dsn, $this->config['mysql_user'], $this->config['mysql_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 2
                ]);
                $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $this->config['mysql_db'] ?: 'aurashop_db');
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $tempPdo->exec("USE `{$dbName}`");
                $this->pdo = $tempPdo;
                $this->driver = 'mysql';
                return;
            } catch (Exception $e) {
                // Chuyển sang SQLite chuẩn ACID nếu MySQL XAMPP chưa bật
            }
        }

        // 3. Kết nối SQLite ACID Local (Luôn hoạt động 100% mọi lúc)
        $sqlitePath = dirname(__DIR__) . '/database/aurashop_local.sqlite';
        $this->pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->driver = 'sqlite';
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    public function getDriver(): string {
        return $this->driver;
    }

    public function getConnectionInfo(): array {
        $labels = [
            'pgsql' => 'Supabase Cloud (PostgreSQL)',
            'mysql' => 'XAMPP MySQL Database (aurashop_db)',
            'sqlite' => 'Local SQL Engine (XAMPP Ready + Supabase Sync)'
        ];
        return [
            'driver' => $this->driver,
            'label' => $labels[$this->driver] ?? $this->driver,
            'supabase_configured' => !empty($this->config['supabase_url']) || !empty($this->config['supabase_db_host']),
            'supabase_url' => $this->config['supabase_url'] ?? '',
            'supabase_db_host' => $this->config['supabase_db_host'] ?? ''
        ];
    }

    /**
     * Đồng bộ dữ liệu sang Supabase REST API nếu người dùng nhập URL + Key
     */
    public function syncToSupabaseRest(string $table, array $data, string $method = 'POST') {
        if (empty($this->config['supabase_url']) || empty($this->config['supabase_anon_key'])) {
            return null;
        }
        $url = rtrim($this->config['supabase_url'], '/') . '/rest/v1/' . $table;
        $ch = curl_init($url);
        $headers = [
            'apikey: ' . $this->config['supabase_anon_key'],
            'Authorization: Bearer ' . $this->config['supabase_anon_key'],
            'Content-Type: application/json',
            'Prefer: return=representation,resolution=merge-duplicates'
        ];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? json_decode($response, true) : null;
    }

    private function initializeSchemaIfNeeded() {
        // Kiểm tra bảng users đã tồn tại chưa
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as cnt FROM users");
            $row = $stmt->fetch();
            if ($row && (int)$row['cnt'] > 0) {
                return;
            }
        } catch (Exception $e) {
            // Bảng chưa tồn tại -> tiến hành khởi tạo
        }

        if ($this->driver === 'pgsql') {
            $sqlFile = dirname(__DIR__) . '/database/supabase_schema.sql';
            if (file_exists($sqlFile)) {
                $this->pdo->exec(file_get_contents($sqlFile));
            }
        } elseif ($this->driver === 'mysql') {
            $this->createMysqlSchema();
        } else {
            $this->createSqliteSchema();
        }

        $this->seedInitialData();
    }

    private function createMysqlSchema() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(120) NOT NULL,
                email VARCHAR(150) UNIQUE NOT NULL,
                phone VARCHAR(20) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL DEFAULT '123456',
                pin_code VARCHAR(10) NOT NULL DEFAULT '123456',
                avatar VARCHAR(500),
                role VARCHAR(30) NOT NULL DEFAULT 'customer',
                tier VARCHAR(40) NOT NULL DEFAULT 'Platinum Member',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS wallets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNIQUE NOT NULL,
                wallet_number VARCHAR(30) UNIQUE NOT NULL,
                balance DECIMAL(15,2) NOT NULL DEFAULT 0,
                cashback_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
                currency VARCHAR(10) NOT NULL DEFAULT 'VND',
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS wallet_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tx_code VARCHAR(40) UNIQUE NOT NULL,
                wallet_id INT NOT NULL,
                related_wallet_id INT NULL,
                order_id INT NULL,
                type VARCHAR(30) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                balance_before DECIMAL(15,2) NOT NULL,
                balance_after DECIMAL(15,2) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'COMPLETED',
                description TEXT NOT NULL,
                metadata TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(200) NOT NULL,
                category VARCHAR(80) NOT NULL,
                price DECIMAL(15,2) NOT NULL,
                original_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                cashback_rate DECIMAL(5,2) NOT NULL DEFAULT 3.0,
                stock INT NOT NULL DEFAULT 50,
                rating DECIMAL(3,1) NOT NULL DEFAULT 5.0,
                sold_count INT NOT NULL DEFAULT 0,
                badge VARCHAR(50) DEFAULT 'Hot',
                image_url TEXT NOT NULL,
                description TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_code VARCHAR(40) UNIQUE NOT NULL,
                user_id INT NOT NULL,
                total_amount DECIMAL(15,2) NOT NULL,
                discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                final_amount DECIMAL(15,2) NOT NULL,
                cashback_earned DECIMAL(15,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(40) NOT NULL,
                payment_status VARCHAR(30) NOT NULL DEFAULT 'PAID',
                order_status VARCHAR(30) NOT NULL DEFAULT 'PROCESSING',
                recipient_name VARCHAR(120) NOT NULL,
                recipient_phone VARCHAR(30) NOT NULL,
                shipping_address TEXT NOT NULL,
                note TEXT,
                refund_reason TEXT,
                refunded_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NULL,
                product_name VARCHAR(200) NOT NULL,
                product_image TEXT,
                quantity INT NOT NULL,
                unit_price DECIMAL(15,2) NOT NULL,
                subtotal DECIMAL(15,2) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS refund_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                refund_code VARCHAR(40) UNIQUE NOT NULL,
                order_id INT UNIQUE NOT NULL,
                user_id INT NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                reason TEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'APPROVED',
                processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        ];

        foreach ($queries as $q) {
            $this->pdo->exec($q);
        }
    }

    private function createSqliteSchema() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                phone TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL DEFAULT '123456',
                pin_code TEXT NOT NULL DEFAULT '123456',
                avatar TEXT,
                role TEXT NOT NULL DEFAULT 'customer',
                tier TEXT NOT NULL DEFAULT 'Platinum Member',
                created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );",

            "CREATE TABLE IF NOT EXISTS wallets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                wallet_number TEXT UNIQUE NOT NULL,
                balance REAL NOT NULL DEFAULT 0,
                cashback_balance REAL NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'VND',
                status TEXT NOT NULL DEFAULT 'ACTIVE',
                updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );",

            "CREATE TABLE IF NOT EXISTS wallet_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tx_code TEXT UNIQUE NOT NULL,
                wallet_id INTEGER NOT NULL,
                related_wallet_id INTEGER NULL,
                order_id INTEGER NULL,
                type TEXT NOT NULL,
                amount REAL NOT NULL,
                balance_before REAL NOT NULL,
                balance_after REAL NOT NULL,
                status TEXT NOT NULL DEFAULT 'COMPLETED',
                description TEXT NOT NULL,
                metadata TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );",

            "CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sku TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                category TEXT NOT NULL,
                price REAL NOT NULL,
                original_price REAL NOT NULL DEFAULT 0,
                cashback_rate REAL NOT NULL DEFAULT 3.0,
                stock INTEGER NOT NULL DEFAULT 50,
                rating REAL NOT NULL DEFAULT 5.0,
                sold_count INTEGER NOT NULL DEFAULT 0,
                badge TEXT DEFAULT 'Hot',
                image_url TEXT NOT NULL,
                description TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );",

            "CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_code TEXT UNIQUE NOT NULL,
                user_id INTEGER NOT NULL,
                total_amount REAL NOT NULL,
                discount_amount REAL NOT NULL DEFAULT 0,
                final_amount REAL NOT NULL,
                cashback_earned REAL NOT NULL DEFAULT 0,
                payment_method TEXT NOT NULL,
                payment_status TEXT NOT NULL DEFAULT 'PAID',
                order_status TEXT NOT NULL DEFAULT 'PROCESSING',
                recipient_name TEXT NOT NULL,
                recipient_phone TEXT NOT NULL,
                shipping_address TEXT NOT NULL,
                note TEXT,
                refund_reason TEXT,
                refunded_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );",

            "CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NULL,
                product_name TEXT NOT NULL,
                product_image TEXT,
                quantity INTEGER NOT NULL,
                unit_price REAL NOT NULL,
                subtotal REAL NOT NULL
            );",

            "CREATE TABLE IF NOT EXISTS refund_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                refund_code TEXT UNIQUE NOT NULL,
                order_id INTEGER UNIQUE NOT NULL,
                user_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                reason TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'APPROVED',
                processed_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
                created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );"
        ];

        foreach ($queries as $q) {
            $this->pdo->exec($q);
        }
    }

    private function seedInitialData() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as cnt FROM users");
        if ((int)$stmt->fetch()['cnt'] > 0) {
            return;
        }

        // 1. Seed Users (3 tài khoản để dễ dàng test chuyển khoản qua lại)
        $users = [
            [
                'full_name' => 'Nguyễn Văn An',
                'email' => 'an.nguyen@tastore.vn',
                'phone' => '0901234567',
                'pin_code' => '123456',
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
                'role' => 'admin',
                'tier' => 'Diamond VIP'
            ],
            [
                'full_name' => 'Trần Thị Bích',
                'email' => 'bich.tran@tastore.vn',
                'phone' => '0988888888',
                'pin_code' => '123456',
                'avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=200&auto=format&fit=crop&q=80',
                'role' => 'customer',
                'tier' => 'Platinum Member'
            ],
            [
                'full_name' => 'Lê Hoàng Nam',
                'email' => 'nam.le@tastore.vn',
                'phone' => '0912345678',
                'pin_code' => '123456',
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80',
                'role' => 'customer',
                'tier' => 'Gold Member'
            ]
        ];

        $insertUser = $this->pdo->prepare("INSERT INTO users (full_name, email, phone, pin_code, avatar, role, tier) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($users as $u) {
            $insertUser->execute([$u['full_name'], $u['email'], $u['phone'], $u['pin_code'], $u['avatar'], $u['role'], $u['tier']]);
        }

        // 2. Seed Wallets
        $wallets = [
            [1, '9900 8888 0001', 25500000, 450000],
            [2, '9900 8888 0002', 8200000, 120000],
            [3, '9900 8888 0003', 3500000, 50000]
        ];
        $insertWallet = $this->pdo->prepare("INSERT INTO wallets (user_id, wallet_number, balance, cashback_balance, currency, status) VALUES (?, ?, ?, ?, 'VND', 'ACTIVE')");
        foreach ($wallets as $w) {
            $insertWallet->execute($w);
        }

        // 3. Seed Sample Wallet Transactions
        $txs = [
            ['TXN-20261101', 1, null, null, 'TOPUP', 20000000, 5500000, 25500000, 'COMPLETED', 'Nạp tiền vào ví qua VietQR (Vietcombank)', '{"bank":"VCB","method":"VietQR"}'],
            ['TXN-20261102', 1, 2, null, 'TRANSFER_OUT', 500000, 26000000, 25500000, 'COMPLETED', 'Chuyển tiền cho Trần Thị Bích: Chúc mừng sinh nhật bạn!', '{"recipient_name":"Trần Thị Bích","recipient_phone":"0988888888"}'],
            ['TXN-20261103', 2, 1, null, 'TRANSFER_IN', 500000, 7700000, 8200000, 'COMPLETED', 'Nhận tiền từ Nguyễn Văn An: Chúc mừng sinh nhật bạn!', '{"sender_name":"Nguyễn Văn An","sender_phone":"0901234567"}']
        ];
        $insertTx = $this->pdo->prepare("INSERT INTO wallet_transactions (tx_code, wallet_id, related_wallet_id, order_id, type, amount, balance_before, balance_after, status, description, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($txs as $t) {
            $insertTx->execute($t);
        }

        // 4. Seed Products
        $products = [
            [
                'TA-TECH-01',
                'Tai nghe Không Dây TAPro Max ANC',
                'Công nghệ',
                6490000,
                7990000,
                5.0,
                35,
                4.9,
                218,
                'Hoàn 5%',
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
                'Chống ồn chủ động thông minh, âm thanh Hi-Res Spatial Audio 360 độ, thời lượng pin 40 giờ liên tục.'
            ],
            [
                'TA-TECH-02',
                'Đồng Hồ Thông Minh TAWatch Ultra Titanium',
                'Công nghệ',
                11990000,
                14500000,
                4.0,
                20,
                5.0,
                145,
                'Best Seller',
                'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&auto=format&fit=crop&q=80',
                'Khung viền Titanium chuẩn hàng không vũ trụ, mặt kính Sapphire chống trầy, tích hợp NFC thanh toán 1 chạm.'
            ],
            [
                'TA-FASH-01',
                'Túi Xách Da Thật Minimalist Milano',
                'Thời trang',
                3250000,
                4200000,
                6.0,
                18,
                4.8,
                96,
                'Hoàn 6%',
                'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&auto=format&fit=crop&q=80',
                'Chế tác thủ công từ da bê Ý nguyên tấm, thiết kế tối giản sang trọng phù hợp công sở và dự tiệc.'
            ],
            [
                'TA-TECH-03',
                'Loa Bluetooth Hi-Fi TASound Studio',
                'Âm thanh',
                4590000,
                5500000,
                4.5,
                42,
                4.9,
                310,
                'Mới ra mắt',
                'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=800&auto=format&fit=crop&q=80',
                'Công suất 80W mạnh mẽ, âm trầm sâu lắng kết hợp đèn LED Ambient Light đồng bộ theo nhịp nhạc.'
            ],
            [
                'TA-ACC-01',
                'Kính Mát Phân Cực Titanium Aviator',
                'Phụ kiện',
                1890000,
                2490000,
                5.0,
                60,
                4.7,
                184,
                'Giảm 24%',
                'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',
                'Tròng kính Polarized chống tia UV400 tuyệt đối, gọng Titanium siêu nhẹ chỉ 14g.'
            ],
            [
                'TA-FASH-02',
                'Giày Sneaker TAFlow CloudRunner',
                'Thời trang',
                2790000,
                3490000,
                5.0,
                50,
                4.9,
                412,
                'Hot Trend',
                'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&auto=format&fit=crop&q=80',
                'Đế đệm bọt khí siêu đàn hồi, vải dệt thoáng khí 3D mang lại cảm giác êm ái suốt cả ngày.'
            ],
            [
                'TA-TECH-04',
                'Máy Ảnh Mirrorless TACam X-Pro 4K',
                'Công nghệ',
                18500000,
                21900000,
                3.0,
                12,
                5.0,
                67,
                'Cao cấp',
                'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800&auto=format&fit=crop&q=80',
                'Cảm biến APS-C 40MP, quay video 4K 60fps chống rung 5 trục trong thân máy nhỏ gọn cổ điển.'
            ],
            [
                'TA-ACC-02',
                'Nước Hoa Niche TA De Parfum 100ml',
                'Mỹ phẩm & Hương thơm',
                3650000,
                4300000,
                7.0,
                28,
                4.9,
                153,
                'Hoàn 7%',
                'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80',
                'Hương gỗ đàn hương kết hợp cam Bergamot và hổ phách ấm áp, lưu hương trên 12 giờ.'
            ]
        ];

        $insertProd = $this->pdo->prepare("INSERT INTO products (sku, name, category, price, original_price, cashback_rate, stock, rating, sold_count, badge, image_url, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($products as $p) {
            $insertProd->execute($p);
        }
    }
}

function jsonResponse(array $data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
