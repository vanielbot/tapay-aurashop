-- ============================================================================
-- TASTORE & TAPAY E-WALLET - SUPABASE POSTGRESQL SCHEMA & ATOMIC RPC
-- ============================================================================
-- Hướng dẫn: Copy toàn bộ nội dung file này chạy trong mục SQL Editor của Supabase
-- hoặc nhấn nút "Đồng bộ lên Supabase" ngay trên giao diện Quản trị DB của TAstore.

-- 1. Bảng Người Dùng (users)
CREATE TABLE IF NOT EXISTS public.users (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL DEFAULT '123456',
    pin_code VARCHAR(10) NOT NULL DEFAULT '123456',
    avatar VARCHAR(500),
    role VARCHAR(30) NOT NULL DEFAULT 'customer',
    tier VARCHAR(40) NOT NULL DEFAULT 'Platinum Member',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. Bảng Ví Điện Tử (wallets)
CREATE TABLE IF NOT EXISTS public.wallets (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT UNIQUE NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    wallet_number VARCHAR(30) UNIQUE NOT NULL,
    balance NUMERIC(15, 2) NOT NULL DEFAULT 0 CHECK (balance >= 0),
    cashback_balance NUMERIC(15, 2) NOT NULL DEFAULT 0 CHECK (cashback_balance >= 0),
    currency VARCHAR(10) NOT NULL DEFAULT 'VND',
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 3. Bảng Lịch Sử Giao Dịch Ví (wallet_transactions)
CREATE TABLE IF NOT EXISTS public.wallet_transactions (
    id BIGSERIAL PRIMARY KEY,
    tx_code VARCHAR(40) UNIQUE NOT NULL,
    wallet_id BIGINT NOT NULL REFERENCES public.wallets(id) ON DELETE CASCADE,
    related_wallet_id BIGINT REFERENCES public.wallets(id) ON DELETE SET NULL,
    order_id BIGINT,
    type VARCHAR(30) NOT NULL, -- TOPUP, TRANSFER_OUT, TRANSFER_IN, PAYMENT, REFUND, CASHBACK
    amount NUMERIC(15, 2) NOT NULL,
    balance_before NUMERIC(15, 2) NOT NULL,
    balance_after NUMERIC(15, 2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'COMPLETED',
    description TEXT NOT NULL,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 4. Bảng Sản Phẩm (products)
CREATE TABLE IF NOT EXISTS public.products (
    id BIGSERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(80) NOT NULL,
    price NUMERIC(15, 2) NOT NULL CHECK (price >= 0),
    original_price NUMERIC(15, 2) NOT NULL DEFAULT 0,
    cashback_rate NUMERIC(5, 2) NOT NULL DEFAULT 3.0, -- % hoàn tiền vào ví
    stock INT NOT NULL DEFAULT 50,
    rating NUMERIC(3, 1) NOT NULL DEFAULT 5.0,
    sold_count INT NOT NULL DEFAULT 0,
    badge VARCHAR(50) DEFAULT 'Hot',
    image_url TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 5. Bảng Đơn Hàng (orders)
CREATE TABLE IF NOT EXISTS public.orders (
    id BIGSERIAL PRIMARY KEY,
    order_code VARCHAR(40) UNIQUE NOT NULL,
    user_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    total_amount NUMERIC(15, 2) NOT NULL,
    discount_amount NUMERIC(15, 2) NOT NULL DEFAULT 0,
    final_amount NUMERIC(15, 2) NOT NULL,
    cashback_earned NUMERIC(15, 2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(40) NOT NULL, -- WALLET, COD, BANK_TRANSFER, PAY_LATER
    payment_status VARCHAR(30) NOT NULL DEFAULT 'PAID', -- PAID, PENDING, REFUNDED
    order_status VARCHAR(30) NOT NULL DEFAULT 'PROCESSING', -- PROCESSING, DELIVERED, CANCELLED, REFUNDED
    recipient_name VARCHAR(120) NOT NULL,
    recipient_phone VARCHAR(30) NOT NULL,
    shipping_address TEXT NOT NULL,
    note TEXT,
    refund_reason TEXT,
    refunded_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 6. Bảng Chi Tiết Đơn Hàng (order_items)
CREATE TABLE IF NOT EXISTS public.order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES public.products(id) ON DELETE SET NULL,
    product_name VARCHAR(200) NOT NULL,
    product_image TEXT,
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price NUMERIC(15, 2) NOT NULL,
    subtotal NUMERIC(15, 2) NOT NULL
);

-- 7. Bảng Yêu Cầu Hoàn Tiền (refund_requests)
CREATE TABLE IF NOT EXISTS public.refund_requests (
    id BIGSERIAL PRIMARY KEY,
    refund_code VARCHAR(40) UNIQUE NOT NULL,
    order_id BIGINT UNIQUE NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    amount NUMERIC(15, 2) NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'APPROVED',
    processed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Mở quyền truy cập API cho Supabase Anon/Service Role (Demo/Local Development)
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.wallets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.wallet_transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.products ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.order_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.refund_requests ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Allow all operations on users') THEN
        CREATE POLICY "Allow all operations on users" ON public.users FOR ALL USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Allow all operations on wallets') THEN
        CREATE POLICY "Allow all operations on wallets" ON public.wallets FOR ALL USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Allow all operations on wallet_transactions') THEN
        CREATE POLICY "Allow all operations on wallet_transactions" ON public.wallet_transactions FOR ALL USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Allow all operations on products') THEN
        CREATE POLICY "Allow all operations on products" ON public.products FOR ALL USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Allow all operations on orders') THEN
        CREATE POLICY "Allow all operations on orders" ON public.orders FOR ALL USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Allow all operations on order_items') THEN
        CREATE POLICY "Allow all operations on order_items" ON public.order_items FOR ALL USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Allow all operations on refund_requests') THEN
        CREATE POLICY "Allow all operations on refund_requests" ON public.refund_requests FOR ALL USING (true) WITH CHECK (true);
    END IF;
END $$;

-- Dữ liệu mẫu ban đầu (Seed Data)
INSERT INTO public.users (id, full_name, email, phone, pin_code, avatar, role, tier) VALUES
(1, 'Nguyễn Văn An', 'an.nguyen@tastore.vn', '0901234567', '123456', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80', 'admin', 'Diamond VIP'),
(2, 'Trần Thị Bích', 'bich.tran@tastore.vn', '0988888888', '123456', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=200&auto=format&fit=crop&q=80', 'customer', 'Platinum Member'),
(3, 'Lê Hoàng Nam', 'nam.le@tastore.vn', '0912345678', '123456', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80', 'customer', 'Gold Member')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.wallets (id, user_id, wallet_number, balance, cashback_balance, currency, status) VALUES
(1, 1, '9900 8888 0001', 25500000, 450000, 'VND', 'ACTIVE'),
(2, 2, '9900 8888 0002', 8200000, 120000, 'VND', 'ACTIVE'),
(3, 3, '9900 8888 0003', 3500000, 50000, 'VND', 'ACTIVE')
ON CONFLICT (id) DO NOTHING;
