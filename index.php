<?php
/**
 * TAstore Mall & Ví Điện Tử TAPay
 * Giao diện chuẩn Website Bán Hàng Thương Mại Điện Tử (Shopee Mall / Tiki / Lazada Style)
 * Tích hợp đầy đủ:
 * - Cửa hàng TMĐT: Banner, Danh mục ngành hàng, Flash Sale đếm ngược, Bộ lọc & Sắp xếp giá, Đánh giá sao, Đã bán
 * - Tạo sản phẩm mới nhanh chóng ngay trên giao diện (+ Tạo Sản Phẩm)
 * - 4 Phương thức thanh toán: Ví điện tử TAPay (+Hoàn tiền Cashback), COD, Chuyển khoản VietQR, Mua trước trả sau (Chưa cập nhật)
 * - Ví điện tử TAPay: Nạp tiền VietQR, Chuyển khoản P2P qua SĐT/Số ví, Lịch sử giao dịch, Hoàn tiền đơn hàng 100% về Ví
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAstore Mall — Mua Sắm Chính Hãng & Ví Điện Tử Hoàn Tiền TAPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'tapay-blue': '#0068ff',
                        'tastore-orange': '#ee4d2d',
                    },
                    fontFamily: {
                        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
                    }
                }
            }
        };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen flex flex-col bg-[#f5f5fa] text-slate-800 pb-20 md:pb-0">

    <!-- ==================================================================
         1. TOP UTILITY BAR (THANH TIỆN ÍCH TRÊN CÙNG GIỐNG SHOPEE / TIKI)
         ================================================================== -->
    <div class="bg-[#d73211] text-white/95 text-xs py-1.5 px-4">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
            <!-- Left Links -->
            <div class="flex items-center gap-3 flex-wrap">
                <button onclick="openCreateProductModal()" class="font-semibold hover:text-yellow-200 flex items-center gap-1 transition">
                    <i data-lucide="store" class="w-3.5 h-3.5"></i>
                    Kênh Người Bán (Đăng Sản Phẩm Mới)
                </button>
                <span class="text-white/40">|</span>
                <button onclick="switchTab('wallet')" class="hover:text-yellow-200 flex items-center gap-1 transition">
                    <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
                    Tải ứng dụng Ví TAPay
                </button>
                <span class="text-white/40 hidden md:inline">|</span>
                <button onclick="openModal('dbConfigModal')" class="hidden md:inline-flex items-center gap-1 px-2 py-0.5 rounded bg-black/20 hover:bg-black/30 text-[11px] font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span id="dbStatusBadge">CSDL: Đang kết nối...</span>
                </button>
            </div>

            <!-- Right Account Switcher (For Easy P2P Transfer Testing) -->
            <div class="flex items-center gap-2">
                <span class="text-white/85 hidden sm:inline">Đổi tài khoản nhanh:</span>
                <div id="accountSwitcherList" class="flex items-center gap-1">
                    <!-- Rendered by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- ==================================================================
         2. MAIN E-COMMERCE HEADER (LOGO + THANH TÌM KIẾM LỚN + VÍ + GIỎ HÀNG)
         ================================================================== -->
    <header class="sticky top-0 z-30 ecom-header text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 pt-3 pb-3.5">
            <div class="flex flex-wrap md:flex-nowrap items-center justify-between gap-2.5 sm:gap-4 lg:gap-8">
                <!-- Brand Logo -->
                <div onclick="switchTab('shop')" class="cursor-pointer flex items-center gap-2 sm:gap-2.5 shrink-0 order-1">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-2xl bg-white text-[#ee4d2d] flex items-center justify-center shadow-md font-black text-xl sm:text-2xl">
                        <i data-lucide="shopping-bag" class="w-5 h-5 sm:w-6 sm:h-6 stroke-[2.5]"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-lg sm:text-2xl font-black tracking-tight text-white">TAstore</span>
                            <span class="px-1.5 py-0.5 text-[10px] font-extrabold bg-yellow-400 text-red-900 rounded uppercase">Mall</span>
                        </div>
                        <div class="text-[11px] text-white/85 hidden sm:block">Chính hãng 100% • Hoàn tiền Ví TAPay</div>
                    </div>
                </div>

                <!-- Center Big Search Bar (Chuẩn Shopee / Tiki / Lazada) -->
                <div class="order-3 md:order-2 w-full md:w-auto md:flex-1 max-w-2xl">
                    <div class="bg-white rounded-lg p-1 flex items-center shadow-inner">
                        <input id="productSearchInput" oninput="debounceSearchProducts()" type="text" placeholder="Tìm sản phẩm, thương hiệu... (Ví TAPay hoàn tiền tới 7%)" class="w-full min-w-0 px-3 py-1.5 sm:py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none">
                        <button onclick="switchTab('shop'); debounceSearchProducts();" aria-label="Tìm kiếm" class="bg-[#ee4d2d] hover:bg-[#d73211] text-white px-3.5 sm:px-5 py-1.5 sm:py-2 rounded-md font-semibold text-sm transition flex items-center gap-1.5 shrink-0">
                            <i data-lucide="search" class="w-4 h-4"></i>
                            <span class="hidden sm:inline">Tìm Kiếm</span>
                        </button>
                    </div>
                    <!-- Hot Keywords Under Search Bar -->
                    <div class="hidden md:flex items-center gap-3 text-[11px] text-white/90 mt-1.5 px-1">
                        <a href="javascript:void(0)" onclick="quickFilterKeyword('Tai nghe')" class="hover:underline">Tai nghe chống ồn</a>
                        <a href="javascript:void(0)" onclick="quickFilterKeyword('Đồng hồ')" class="hover:underline">Đồng hồ thông minh</a>
                        <a href="javascript:void(0)" onclick="quickFilterKeyword('Túi xách')" class="hover:underline">Túi xách da thật</a>
                        <a href="javascript:void(0)" onclick="quickFilterKeyword('Giày')" class="hover:underline">Giày Sneaker</a>
                        <a href="javascript:void(0)" onclick="quickFilterKeyword('Máy ảnh')" class="hover:underline">Máy ảnh 4K</a>
                        <a href="javascript:void(0)" onclick="quickFilterKeyword('Nước hoa')" class="hover:underline">Nước hoa Niche</a>
                    </div>
                </div>

                <!-- Right Actions: Ví Điện Tử TAPay + Đơn Hàng + Giỏ Hàng -->
                <div class="flex items-center gap-2 sm:gap-3 shrink-0 order-2 md:order-3">
                    <!-- Ví TAPay Box -->
                    <div onclick="switchTab('wallet')" class="cursor-pointer bg-white/15 hover:bg-white/25 border border-white/25 rounded-xl px-3 py-1.5 transition flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-white text-[#0068ff] flex items-center justify-center font-bold shadow-sm">
                            <i data-lucide="wallet" class="w-4 h-4"></i>
                        </div>
                        <div class="hidden sm:block text-left">
                            <div class="text-[10px] text-white/90 flex items-center gap-1">
                                <span>Ví TAPay</span>
                                <span id="headerCashbackMini" class="px-1 rounded bg-yellow-300 text-red-900 font-bold text-[9px]">+0đ</span>
                            </div>
                            <div id="headerWalletBalance" class="text-xs sm:text-sm font-extrabold text-white font-number">0 ₫</div>
                        </div>
                        <button onclick="event.stopPropagation(); openTopupModal();" title="Nạp tiền vào ví" class="w-6 h-6 rounded-md bg-yellow-400 hover:bg-yellow-300 text-red-950 flex items-center justify-center font-bold shadow">
                            <i data-lucide="plus" class="w-3.5 h-3.5 stroke-[3]"></i>
                        </button>
                    </div>

                    <!-- Cart Button -->
                    <button onclick="toggleCartDrawer(true)" class="relative p-2.5 sm:px-3.5 sm:py-2 rounded-xl bg-white text-[#ee4d2d] hover:bg-orange-50 font-bold text-sm shadow transition flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                        <span class="hidden lg:inline">Giỏ Hàng</span>
                        <span id="cartCountBadge" class="px-1.5 py-0.5 min-w-[20px] rounded-full bg-[#ee4d2d] text-white text-xs font-extrabold text-center">0</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Category & Main Menu Bar (Thanh Điều Hướng Danh Mục) -->
        <div class="bg-white text-slate-700 border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 flex items-center justify-between overflow-x-auto">
                <div class="flex items-center gap-1 py-1.5">
                    <button id="tabBtn-shop" onclick="switchTab('shop')" class="nav-tab-btn px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1.5 bg-[#fff5f1] text-[#ee4d2d]">
                        <i data-lucide="home" class="w-4 h-4"></i>
                        Trang Chủ Mall
                    </button>
                    <button id="tabBtn-wallet" onclick="switchTab('wallet')" class="nav-tab-btn px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition flex items-center gap-1.5 text-slate-700 hover:bg-slate-100">
                        <i data-lucide="wallet-cards" class="w-4 h-4 text-[#0068ff]"></i>
                        Ví Điện Tử TAPay (Nạp / Chuyển / Rút)
                    </button>
                    <button id="tabBtn-orders" onclick="switchTab('orders')" class="nav-tab-btn px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition flex items-center gap-1.5 text-slate-700 hover:bg-slate-100">
                        <i data-lucide="package-check" class="w-4 h-4 text-emerald-600"></i>
                        Đơn Mua & Hoàn Tiền
                        <span id="ordersCountBadge" class="px-1.5 py-0.2 text-[11px] rounded-full bg-[#ee4d2d] text-white font-bold">0</span>
                    </button>
                </div>

                <div class="flex items-center gap-2 py-1.5 shrink-0">
                    <button onclick="openCreateProductModal()" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        + Đăng Bán Sản Phẩm Mới
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- ==================================================================
         MAIN CONTENT AREA
         ================================================================== -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">

        <!-- ==============================================================
             TAB 1: GIAO DIỆN WEB BÁN HÀNG TMĐT (STOREFRONT)
             ============================================================== -->
        <section id="tabSection-shop" class="space-y-6">

            <!-- 1.1 TOP BANNER + VÍ ĐIỆN TỬ SUMMARY WIDGET -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- Left Main E-Commerce Promo Banner (8 cols) -->
                <div class="lg:col-span-8 rounded-2xl overflow-hidden relative bg-gradient-to-r from-[#d0011b] via-[#ee4d2d] to-[#ff7337] text-white p-6 sm:p-8 flex flex-col justify-between min-h-[250px] shadow-sm">
                    <div class="max-w-lg space-y-3 relative z-10">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-yellow-300 text-red-950 text-xs font-extrabold uppercase">
                            <i data-lucide="flame" class="w-3.5 h-3.5 fill-current"></i>
                            Siêu Hội Mua Sắm Chính Hãng
                        </div>
                        <h1 class="text-2xl sm:text-4xl font-black leading-tight">
                            GIẢM ĐẾN 50% + HOÀN TIỀN VÍ TAPAY 7%
                        </h1>
                        <p class="text-white/95 text-xs sm:text-sm leading-relaxed">
                            Miễn phí vận chuyển toàn quốc • Thanh toán linh hoạt qua <strong>Ví TAPay, COD, Chuyển khoản VietQR</strong> • Cam kết <strong>Đổi trả & Hoàn tiền 100% về Ví</strong> trong 3 giây!
                        </p>
                        <div class="pt-2 flex flex-wrap gap-2.5">
                            <a href="#productCatalogSection" class="px-5 py-2.5 rounded-lg bg-white text-[#ee4d2d] font-extrabold text-xs sm:text-sm shadow hover:bg-orange-50 transition">
                                Mua Sắm Ngay
                            </a>
                            <button onclick="openTopupModal()" class="px-4 py-2.5 rounded-lg bg-black/25 hover:bg-black/35 text-white font-bold text-xs sm:text-sm border border-white/30 transition flex items-center gap-1.5">
                                <i data-lucide="qr-code" class="w-4 h-4"></i>
                                Nạp Ví VietQR
                            </button>
                            <button onclick="openVirtualMoneyModal()" class="px-4 py-2.5 rounded-lg bg-violet-600/90 hover:bg-violet-600 text-white font-bold text-xs sm:text-sm border border-violet-300/40 transition flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="coins" class="w-4 h-4"></i>
                                + Tiền Ảo Mua Đồ
                            </button>
                            <button onclick="openTransferModal()" class="px-4 py-2.5 rounded-lg bg-black/25 hover:bg-black/35 text-white font-bold text-xs sm:text-sm border border-white/30 transition flex items-center gap-1.5">
                                <i data-lucide="send" class="w-4 h-4"></i>
                                Chuyển Tiền P2P
                            </button>
                        </div>
                    </div>
                    <!-- Decorative Shopping Illustration Badge -->
                    <div class="hidden sm:flex absolute right-6 bottom-6 w-36 h-36 rounded-full bg-white/15 border border-white/25 flex-col items-center justify-center text-center p-3 backdrop-blur-sm">
                        <span class="text-[11px] uppercase font-bold text-yellow-200">Mã Voucher</span>
                        <span class="text-xl font-black text-white tracking-wider">VIP500</span>
                        <span class="text-[11px] bg-yellow-300 text-red-950 font-extrabold px-2 py-0.5 rounded mt-1">Giảm 500K</span>
                    </div>
                </div>

                <!-- Right TAPay Quick Wallet Card (4 cols - Giống ShopeePay / Ví MoMo trên trang chủ) -->
                <div class="lg:col-span-4 bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-[#0068ff] text-white flex items-center justify-center">
                                    <i data-lucide="wallet" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-extrabold text-slate-800">Ví Điện Tử TAPay</h3>
                                    <p id="heroWalletHolder" class="text-[11px] text-slate-500 font-semibold">NGUYỄN VĂN AN</p>
                                </div>
                            </div>
                            <button onclick="toggleBalanceVisibility()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500" title="Ẩn/Hiện số dư">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>

                        <div class="py-3.5 flex items-baseline justify-between">
                            <div>
                                <div class="text-xs text-slate-500">Số dư Ví khả dụng</div>
                                <div id="heroWalletBalance" class="text-2xl font-black text-[#0068ff] font-number mt-0.5">0 ₫</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] text-slate-500">Xu / Hoàn tiền</div>
                                <div id="heroCashbackBalance" class="text-sm font-extrabold text-amber-600 font-number">0 ₫</div>
                            </div>
                        </div>
                        <div class="text-[11px] text-slate-400 font-number mb-3">
                            Số thẻ ví: <strong id="heroWalletNumber" class="text-slate-600">9900 8888 0001</strong>
                        </div>
                    </div>

                    <!-- 4 Quick Wallet Icons (Nạp tiền, Chuyển tiền, Hoàn tiền, Lịch sử) -->
                    <div class="grid grid-cols-4 gap-2 pt-3 border-t border-slate-100 text-center">
                        <button onclick="openTopupModal()" class="p-2 rounded-xl hover:bg-blue-50 transition group flex flex-col items-center gap-1">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 group-hover:bg-[#0068ff] text-[#0068ff] group-hover:text-white flex items-center justify-center transition">
                                <i data-lucide="qr-code" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[11px] font-bold text-slate-700">Nạp Tiền</span>
                        </button>
                        <button onclick="openTransferModal()" class="p-2 rounded-xl hover:bg-pink-50 transition group flex flex-col items-center gap-1">
                            <div class="w-9 h-9 rounded-xl bg-pink-50 group-hover:bg-pink-600 text-pink-600 group-hover:text-white flex items-center justify-center transition">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[11px] font-bold text-slate-700">Chuyển Tiền</span>
                        </button>
                        <button onclick="switchTab('orders')" class="p-2 rounded-xl hover:bg-emerald-50 transition group flex flex-col items-center gap-1">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 group-hover:bg-emerald-600 text-emerald-600 group-hover:text-white flex items-center justify-center transition">
                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[11px] font-bold text-slate-700">Hoàn Tiền</span>
                        </button>
                        <button onclick="switchTab('wallet')" class="p-2 rounded-xl hover:bg-amber-50 transition group flex flex-col items-center gap-1">
                            <div class="w-9 h-9 rounded-xl bg-amber-50 group-hover:bg-amber-500 text-amber-600 group-hover:text-white flex items-center justify-center transition">
                                <i data-lucide="history" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[11px] font-bold text-slate-700">Lịch Sử GD</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 1.2 E-COMMERCE SERVICE GUARANTEE STRIP (CAM KẾT CHÍNH HÃNG SHOPEE MALL) -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-50 text-[#ee4d2d] flex items-center justify-center shrink-0">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm font-bold text-slate-800">Trả Hàng & Hoàn Tiền 100%</div>
                        <div class="text-[11px] text-slate-500">Hoàn tiền trực tiếp về Ví TAPay</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-[#0068ff] flex items-center justify-center shrink-0">
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm font-bold text-slate-800">Thanh Toán Ví Hoàn Tới 7%</div>
                        <div class="text-[11px] text-slate-500">Cộng tiền thưởng Cashback mỗi đơn</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i data-lucide="truck" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm font-bold text-slate-800">Hỗ Trợ COD & VietQR 24/7</div>
                        <div class="text-[11px] text-slate-500">Nhận hàng kiểm tra rồi thanh toán</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm font-bold text-slate-800 flex items-center gap-1.5">
                            Mua Trước Trả Sau
                            <span class="px-1.5 py-0.2 text-[9px] rounded bg-amber-100 text-amber-800 font-bold">Chưa cập nhật</span>
                        </div>
                        <div class="text-[11px] text-slate-500">Trả góp 0% đang được nâng cấp</div>
                    </div>
                </div>
            </div>

            <!-- 1.3 DANH MỤC NGÀNH HÀNG (VISUAL CATEGORY ICONS GRID) -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700">Danh Mục Ngành Hàng</h2>
                    <button onclick="openCreateProductModal()" class="text-xs font-bold text-[#ee4d2d] hover:underline flex items-center gap-1">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        Thêm sản phẩm vào danh mục
                    </button>
                </div>
                <div id="categoryFilterBar" class="flex items-center gap-2.5 overflow-x-auto pb-1">
                    <!-- Rendered dynamically by JS -->
                </div>
            </div>

            <!-- 1.4 FLASH SALE ĐẾM NGƯỢC (CHUẨN SHOPEE / LAZADA / TIKI) -->
            <div class="bg-white rounded-2xl p-5 border border-orange-200 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5 text-[#ee4d2d] font-black text-lg sm:text-xl italic tracking-tight">
                            <i data-lucide="zap" class="w-6 h-6 fill-current"></i>
                            <span>FLASH SALE HÔM NAY</span>
                        </div>
                        <!-- Countdown Timer -->
                        <div class="flex items-center gap-1 text-xs font-extrabold">
                            <span id="fsHour" class="bg-slate-900 text-white px-2 py-1 rounded font-number">02</span>
                            <span class="text-slate-900 font-black">:</span>
                            <span id="fsMin" class="bg-slate-900 text-white px-2 py-1 rounded font-number">45</span>
                            <span class="text-slate-900 font-black">:</span>
                            <span id="fsSec" class="bg-[#ee4d2d] text-white px-2 py-1 rounded font-number">19</span>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                        Thanh toán bằng Ví TAPay được cộng thêm Hoàn tiền Cashback!
                    </span>
                </div>

                <!-- Top 4 Flash Sale Products -->
                <div id="flashSaleGrid" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Rendered by JS -->
                </div>
            </div>

            <!-- 1.5 KHU VỰC DANH SÁCH SẢN PHẨM CHÍNH + THANH SẮP XẾP (SORT BAR) -->
            <div id="productCatalogSection" class="space-y-4">
                <!-- Shopee-style Sort & Filter Bar -->
                <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-slate-500 mr-1">Sắp xếp theo:</span>
                        <button onclick="setSortOrder('popular')" id="sortBtn-popular" class="sort-btn px-3.5 py-1.5 rounded-lg text-xs font-bold bg-[#ee4d2d] text-white">
                            Phổ Biến
                        </button>
                        <button onclick="setSortOrder('newest')" id="sortBtn-newest" class="sort-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">
                            Mới Nhất
                        </button>
                        <button onclick="setSortOrder('bestseller')" id="sortBtn-bestseller" class="sort-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">
                            Bán Chạy
                        </button>
                        <button onclick="setSortOrder('price_asc')" id="sortBtn-price_asc" class="sort-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">
                            Giá: Thấp đến Cao
                        </button>
                        <button onclick="setSortOrder('price_desc')" id="sortBtn-price_desc" class="sort-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">
                            Giá: Cao đến Thấp
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <span id="totalProductsCountText" class="text-xs text-slate-500 font-medium">Hiển thị sản phẩm</span>
                        <button onclick="openCreateProductModal()" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            Thêm Sản Phẩm
                        </button>
                    </div>
                </div>

                <!-- Standard E-Commerce Product Grid -->
                <div id="productsGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <!-- Rendered dynamically by JS -->
                </div>
            </div>
        </section>

        <!-- ==============================================================
             TAB 2: TRUNG TÂM VÍ ĐIỆN TỬ TA-PAY (SHOPEEPAY / MOMO STYLE)
             ============================================================== -->
        <section id="tabSection-wallet" class="hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-50 text-[#0068ff] text-xs font-bold mb-1">
                        <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                        Bảo mật chuẩn Ngân hàng ACID • Napas 24/7
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-800">Ví Điện Tử TAPay</h2>
                    <p class="text-slate-500 text-xs sm:text-sm">Nạp tiền VietQR tức thì, Chuyển khoản Ngân hàng 24/7, Chuyển tiền P2P miễn phí & Nhận tiền hoàn</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <button onclick="openTopupModal()" class="px-4 py-2.5 rounded-xl bg-[#0068ff] hover:bg-blue-700 text-white font-bold text-xs sm:text-sm transition flex items-center gap-2 shadow-sm">
                        <i data-lucide="qr-code" class="w-4 h-4"></i>
                        Nạp Tiền VietQR
                    </button>
                    <button onclick="openVirtualMoneyModal()" class="px-4 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-bold text-xs sm:text-sm transition flex items-center gap-2 shadow-sm">
                        <i data-lucide="coins" class="w-4 h-4"></i>
                        + Tiền Ảo
                    </button>
                    <button onclick="openBankTransferModal()" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm transition flex items-center gap-2 shadow-sm">
                        <i data-lucide="landmark" class="w-4 h-4"></i>
                        Chuyển Khoản Ngân Hàng
                    </button>
                    <button onclick="openTransferModal()" class="px-4 py-2.5 rounded-xl bg-[#ee4d2d] hover:bg-[#d73211] text-white font-bold text-xs sm:text-sm transition flex items-center gap-2 shadow-sm">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        Chuyển Ví P2P
                    </button>
                </div>
            </div>

            <!-- Quick Action Strip (Nạp tiền / Chuyển khoản / P2P / VietQR) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
                <button onclick="openTopupModal()" class="quick-action-tile bg-white hover:border-[#0068ff] border border-slate-200 rounded-2xl p-4 text-left shadow-sm group">
                    <div class="flex items-center justify-between mb-2.5">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0068ff] group-hover:bg-[#0068ff] group-hover:text-white flex items-center justify-center transition">
                            <i data-lucide="wallet" class="w-5 h-5"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold">VietQR</span>
                    </div>
                    <div class="text-sm font-extrabold text-slate-800">Nạp Tiền Vào Ví</div>
                    <div class="text-xs text-slate-500 mt-0.5">MB Bank &amp; Techcombank 24/7</div>
                </button>

                <button onclick="openBankTransferModal()" class="quick-action-tile bg-white hover:border-emerald-600 border border-slate-200 rounded-2xl p-4 text-left shadow-sm group">
                    <div class="flex items-center justify-between mb-2.5">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition">
                            <i data-lucide="landmark" class="w-5 h-5"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-blue-50 text-[#0068ff] text-[10px] font-bold">Napas 247</span>
                    </div>
                    <div class="text-sm font-extrabold text-slate-800">Chuyển Khoản NH</div>
                    <div class="text-xs text-slate-500 mt-0.5">Liên ngân hàng miễn phí</div>
                </button>

                <button onclick="openTransferModal()" class="quick-action-tile bg-white hover:border-[#ee4d2d] border border-slate-200 rounded-2xl p-4 text-left shadow-sm group">
                    <div class="flex items-center justify-between mb-2.5">
                        <div class="w-10 h-10 rounded-xl bg-orange-50 text-[#ee4d2d] group-hover:bg-[#ee4d2d] group-hover:text-white flex items-center justify-center transition">
                            <i data-lucide="send" class="w-5 h-5"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-orange-50 text-[#ee4d2d] text-[10px] font-bold">Tức thì</span>
                    </div>
                    <div class="text-sm font-extrabold text-slate-800">Chuyển Tiền P2P</div>
                    <div class="text-xs text-slate-500 mt-0.5">Qua SĐT hoặc Số thẻ ví</div>
                </button>

                <button onclick="openTopupModal()" class="quick-action-tile bg-gradient-to-br from-blue-50 via-white to-indigo-50 hover:border-blue-400 border border-blue-200 rounded-2xl p-4 text-left shadow-sm group">
                    <div class="flex items-center justify-between mb-2.5">
                        <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-sm">
                            <i data-lucide="qr-code" class="w-5 h-5"></i>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-extrabold">MB Bank</span>
                    </div>
                    <div class="text-sm font-extrabold text-slate-800">VietQR Tự Động</div>
                    <div class="text-xs text-slate-600 font-number mt-0.5">0382548328 • SePay</div>
                </button>
            </div>

            <!-- Wallet Card & Stats Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left Banking Wallet Card (5 cols) -->
                <div class="lg:col-span-5 aurapay-card rounded-2xl p-6 text-white flex flex-col justify-between min-h-[265px]">
                    <div class="flex items-start justify-between relative z-10">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-7 rounded-md bg-gradient-to-br from-amber-200 to-yellow-500 border border-yellow-300/60 shadow-inner flex items-center justify-center">
                                <div class="w-5 h-4 border border-amber-700/40 rounded-sm"></div>
                            </div>
                            <div>
                                <div class="text-[11px] uppercase tracking-wider text-blue-100 font-semibold">TAPay Smart Wallet</div>
                                <div id="walletTabTier" class="text-sm font-extrabold text-yellow-300">Diamond VIP</div>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-emerald-400/20 border border-emerald-300/30 text-emerald-200 text-xs font-bold flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Đang hoạt động
                        </span>
                    </div>

                    <div class="my-5 relative z-10">
                        <div class="flex items-center gap-2 text-xs text-blue-100">
                            <span>Số dư khả dụng</span>
                            <button type="button" onclick="toggleWalletBalanceVisibility()" class="p-1 rounded hover:bg-white/15 transition" title="Ẩn/Hiện số dư">
                                <i id="walletEyeIcon" data-lucide="eye" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <div id="walletTabBalance" class="text-3xl sm:text-4xl font-black font-number tracking-tight mt-1">0 ₫</div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <div class="px-3 py-1.5 rounded-lg bg-black/25 backdrop-blur-sm border border-white/10 text-xs">
                                Cashback: <strong id="walletTabCashback" class="text-yellow-300 font-number">0 ₫</strong>
                            </div>
                            <button onclick="claimCashbackToMainWallet()" class="px-3 py-1.5 rounded-lg bg-yellow-400 hover:bg-yellow-300 text-slate-950 font-extrabold text-xs transition shadow-sm">
                                Rút về Ví chính
                            </button>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <div class="px-3 py-1.5 rounded-lg bg-violet-500/30 backdrop-blur-sm border border-violet-300/30 text-xs flex items-center gap-1.5">
                                <i data-lucide="coins" class="w-3.5 h-3.5 text-violet-200"></i>
                                Tiền Ảo Mua Đồ: <strong id="walletTabVirtualBalance" class="text-violet-100 font-number">0 ₫</strong>
                            </div>
                            <button type="button" onclick="openVirtualMoneyModal()" class="px-3 py-1.5 rounded-lg bg-violet-500 hover:bg-violet-400 text-white font-extrabold text-xs transition shadow-sm flex items-center gap-1">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                Cộng Tiền Ảo
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-white/20 text-xs relative z-10">
                        <div>
                            <div class="text-[10px] uppercase text-blue-100">Số tài khoản Ví</div>
                            <div class="flex items-center gap-1.5">
                                <div id="walletTabNumber" class="font-bold font-number text-sm">9900 8888 0001</div>
                                <button type="button" onclick="copyTextToClipboard((state.wallet?.wallet_number || '990088880001').replace(/\s+/g,''), 'Số ví TAPay')" class="p-1 rounded hover:bg-white/15 transition" title="Copy số ví">
                                    <i data-lucide="copy" class="w-3 h-3"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase text-blue-100">Chủ tài khoản</div>
                            <div id="walletTabHolder" class="font-bold uppercase text-sm">NGUYỄN VĂN AN</div>
                        </div>
                    </div>
                </div>

                <!-- Right Cashflow Summary + Quick Contacts (7 cols) -->
                <div class="lg:col-span-7 flex flex-col justify-between gap-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-slate-500">Tổng Tiền Vào</div>
                                <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-500"></i>
                            </div>
                            <div id="statTotalIn" class="text-base sm:text-lg font-extrabold text-emerald-600 font-number mt-1">0 ₫</div>
                        </div>
                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-slate-500">Tổng Chi Tiêu</div>
                                <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-500"></i>
                            </div>
                            <div id="statTotalOut" class="text-base sm:text-lg font-extrabold text-rose-600 font-number mt-1">0 ₫</div>
                        </div>
                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-slate-500">Đã Hoàn Tiền</div>
                                <i data-lucide="rotate-ccw" class="w-4 h-4 text-[#0068ff]"></i>
                            </div>
                            <div id="statTotalRefund" class="text-base sm:text-lg font-extrabold text-[#0068ff] font-number mt-1">0 ₫</div>
                        </div>
                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-slate-500">Mã PIN Mặc Định</div>
                                <i data-lucide="lock" class="w-4 h-4 text-amber-500"></i>
                            </div>
                            <div class="text-base sm:text-lg font-extrabold text-amber-600 font-number mt-1">123456</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                                <i data-lucide="users" class="w-4 h-4 text-[#0068ff]"></i>
                                Danh Bạ Chuyển Tiền Nhanh (Bấm chọn để chuyển tiền ngay)
                            </h4>
                            <span class="text-xs text-emerald-600 font-semibold">Miễn phí 100%</span>
                        </div>
                        <div id="quickContactsList" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Rendered by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- SePay Webhook & Bank Integration Hub -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-blue-950 rounded-2xl p-6 text-white shadow-lg border border-slate-700">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 border-b border-white/10">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-bold mb-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            SePay Auto-Topup &amp; Napas 24/7
                        </div>
                        <h3 class="text-lg font-extrabold">Cổng Nhận Tiền Tự Động &amp; Tài Khoản Thụ Hưởng</h3>
                        <p class="text-xs text-slate-300 mt-0.5">Tự động ghi nhận biến động số dư từ SePay Webhook và cộng thẳng vào Ví TAPay</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" onclick="simulateSepayWebhookTest()" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs transition flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="zap" class="w-4 h-4"></i>
                            Test SePay Webhook (+10.000đ)
                        </button>
                        <button type="button" onclick="openTopupModal()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-xs transition flex items-center gap-1.5">
                            <i data-lucide="qr-code" class="w-4 h-4"></i>
                            Mở Mã QR Nạp Tiền
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-5">
                    <!-- MB Bank Card -->
                    <div class="rounded-xl bg-white/10 backdrop-blur-sm border border-white/15 p-4 flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-blue-500 text-white text-[11px] font-extrabold">MB BANK</span>
                            <span class="text-[11px] text-emerald-300 font-bold">Đã kết nối SePay</span>
                        </div>
                        <div>
                            <div class="text-xs text-slate-300">Chủ TK: <strong class="text-white">NGUYEN TIEN DAT</strong></div>
                            <div class="text-base font-extrabold font-number text-white mt-0.5">0382548328</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="copyTextToClipboard('0382548328', 'STK MB Bank')" class="flex-1 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-xs font-bold transition">Copy STK</button>
                            <button type="button" onclick="openTopupWithBank('VietQR - MB Bank (SePay)')" class="flex-1 py-1.5 rounded-lg bg-blue-500 hover:bg-blue-400 text-xs font-bold transition">Quét QR</button>
                        </div>
                    </div>

                    <!-- Techcombank Card -->
                    <div class="rounded-xl bg-white/10 backdrop-blur-sm border border-white/15 p-4 flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 rounded bg-rose-600 text-white text-[11px] font-extrabold">TECHCOMBANK</span>
                            <span class="text-[11px] text-amber-300 font-bold">VietQR / Napas 247</span>
                        </div>
                        <div>
                            <div class="text-xs text-slate-300">Chủ TK: <strong class="text-white">PHAM TUAN ANH</strong></div>
                            <div class="text-base font-extrabold font-number text-white mt-0.5">1903 9937 1730 10</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="copyTextToClipboard('19039937173010', 'STK Techcombank')" class="flex-1 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-xs font-bold transition">Copy STK</button>
                            <button type="button" onclick="openTopupWithBank('VietQR - Techcombank (TCB)')" class="flex-1 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-xs font-bold transition">Quét QR</button>
                        </div>
                    </div>

                    <!-- Webhook Endpoint Card -->
                    <div class="rounded-xl bg-black/30 border border-white/15 p-4 flex flex-col justify-between gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-sky-300">Webhook URL (SePay)</span>
                            <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 text-[10px] font-bold">READY</span>
                        </div>
                        <div class="text-[11px] font-mono text-slate-200 break-all bg-black/30 p-2 rounded-lg border border-white/10" id="sepayWebhookUrlText">
                            https://professionals-banana-cellular-changelog.trycloudflare.com/api/webhook_sepay.php
                        </div>
                        <button type="button" onclick="copySepayWebhookUrl()" class="w-full py-1.5 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-950 text-xs font-extrabold transition flex items-center justify-center gap-1.5">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            Copy URL Dán Vào SePay
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transaction History Table -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">Lịch Sử Giao Dịch & Biến Động Số Dư</h3>
                        <p class="text-xs text-slate-500">Theo dõi minh bạch mọi giao dịch Nạp tiền, Chuyển khoản, Thanh toán và Hoàn tiền</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button onclick="filterTransactions('ALL')" class="tx-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-[#0068ff] text-white">Tất cả</button>
                        <button onclick="filterTransactions('TOPUP')" class="tx-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">Nạp tiền</button>
                        <button onclick="filterTransactions('TRANSFER')" class="tx-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">Chuyển khoản</button>
                        <button onclick="filterTransactions('PAYMENT')" class="tx-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">Thanh toán</button>
                        <button onclick="filterTransactions('REFUND')" class="tx-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">Hoàn tiền</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase text-slate-500 bg-slate-50">
                                <th class="py-3 px-4">Mã GD</th>
                                <th class="py-3 px-4">Loại GD</th>
                                <th class="py-3 px-4">Nội Dung Chi Tiết</th>
                                <th class="py-3 px-4 text-right">Số Tiền</th>
                                <th class="py-3 px-4 text-right">Số Dư Sau GD</th>
                                <th class="py-3 px-4 text-right">Thời Gian</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody" class="divide-y divide-slate-100 text-sm">
                            <!-- Rendered by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ==============================================================
             TAB 3: QUẢN LÝ ĐƠN MUA & YÊU CẦU HOÀN TIỀN VỀ VÍ
             ============================================================== -->
        <section id="tabSection-orders" class="hidden space-y-5">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                        <i data-lucide="package-check" class="w-6 h-6 text-[#ee4d2d]"></i>
                        Đơn Mua & Hoàn Tiền Tự Động Về Ví TAPay
                    </h2>
                    <p class="text-slate-500 text-xs sm:text-sm mt-1">
                        Hỗ trợ trả hàng & hoàn tiền 100% giá trị đơn hàng về lại số dư Ví điện tử TAPay ngay lập tức
                    </p>
                </div>
                <button onclick="switchTab('shop')" class="px-4 py-2.5 rounded-xl bg-[#ee4d2d] hover:bg-[#d73211] text-white font-bold text-xs sm:text-sm transition flex items-center gap-2 self-start">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                    Tiếp Tục Mua Sắm
                </button>
            </div>

            <div id="ordersListContainer" class="space-y-4">
                <!-- Rendered by JS -->
            </div>
        </section>
    </main>

    <!-- ==================================================================
         SLIDE-OVER GIỎ HÀNG & THANH TOÁN ĐA PHƯƠNG THỨC (CHECKOUT DRAWER)
         ================================================================== -->
    <div id="cartDrawerBackdrop" onclick="toggleCartDrawer(false)" class="fixed inset-0 bg-black/50 backdrop-blur-[2px] z-40 hidden"></div>
    <aside id="cartDrawer" class="fixed top-0 right-0 h-full w-full max-w-lg bg-white border-l border-slate-200 z-50 transform translate-x-full transition-transform duration-300 flex flex-col shadow-2xl">
        <!-- Drawer Header -->
        <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-[#fff5f1]">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-[#ee4d2d] text-white flex items-center justify-center">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-800">Giỏ Hàng & Thanh Toán</h3>
                    <p class="text-xs text-slate-500">Hỗ trợ Ví TAPay, COD, Chuyển khoản & Trả sau</p>
                </div>
            </div>
            <button onclick="toggleCartDrawer(false)" class="p-2 rounded-lg hover:bg-slate-200 text-slate-500">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Scrollable Body -->
        <div class="flex-1 overflow-y-auto p-5 space-y-5">
            <!-- Cart Items -->
            <div id="cartItemsContainer" class="space-y-3">
                <!-- Rendered by JS -->
            </div>

            <!-- Checkout Form Section -->
            <div id="checkoutFormSection" class="space-y-5 pt-4 border-t border-slate-200">
                <!-- Shop Voucher -->
                <div class="bg-orange-50/70 p-3.5 rounded-xl border border-orange-200/80">
                    <label class="block text-xs font-extrabold uppercase text-[#ee4d2d] mb-2 flex items-center gap-1.5">
                        <i data-lucide="ticket" class="w-4 h-4"></i>
                        TAstore Voucher Giảm Giá
                    </label>
                    <div class="flex gap-2">
                        <input id="checkoutVoucherInput" type="text" placeholder="Nhập mã giảm giá..." class="flex-1 px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800 uppercase">
                        <button onclick="applyVoucherCode()" class="px-4 py-2 rounded-lg bg-[#ee4d2d] hover:bg-[#d73211] text-xs font-bold text-white">Áp dụng</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <button onclick="setVoucher('TA50')" class="text-[11px] px-2.5 py-1 rounded bg-white text-[#ee4d2d] border border-orange-300 font-semibold hover:bg-orange-50">TA50 (-50K)</button>
                        <button onclick="setVoucher('TA200')" class="text-[11px] px-2.5 py-1 rounded bg-white text-[#ee4d2d] border border-orange-300 font-semibold hover:bg-orange-50">TA200 (-200K đơn từ 2Tr)</button>
                        <button onclick="setVoucher('VIP500')" class="text-[11px] px-2.5 py-1 rounded bg-white text-[#ee4d2d] border border-orange-300 font-semibold hover:bg-orange-50">VIP500 (-500K đơn từ 5Tr)</button>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="space-y-2.5">
                    <label class="block text-xs font-extrabold uppercase text-slate-600 flex items-center gap-1.5">
                        <i data-lucide="map-pin" class="w-4 h-4 text-[#ee4d2d]"></i>
                        Địa Chỉ Nhận Hàng
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <input id="shipName" type="text" placeholder="Họ tên người nhận" class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                        <input id="shipPhone" type="text" placeholder="Số điện thoại" class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                    </div>
                    <input id="shipAddress" type="text" value="Tầng 18, Tòa nhà Landmark 81, Quận Bình Thạnh, TP.HCM" placeholder="Địa chỉ nhận hàng chi tiết" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                </div>

                <!-- 4 Payment Methods -->
                <div class="space-y-2.5">
                    <label class="block text-xs font-extrabold uppercase text-slate-600">Phương Thức Thanh Toán</label>

                    <!-- 1. Ví Điện Tử TAPay -->
                    <div id="payCard-WALLET" onclick="selectPaymentMethod('WALLET')" class="payment-option-card active rounded-xl p-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0068ff] flex items-center justify-center shrink-0">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                    Ví Điện Tử TAPay
                                    <span class="px-1.5 py-0.5 text-[10px] rounded bg-emerald-100 text-emerald-700 font-bold">+Hoàn tiền</span>
                                </div>
                                <div class="text-xs text-slate-500">Số dư: <strong id="checkoutWalletBal" class="text-[#0068ff] font-number">0 ₫</strong></div>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 border-[#ee4d2d] flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-[#ee4d2d] pay-radio-dot"></div>
                        </div>
                    </div>

                    <!-- 1.5. Ví Tiền Ảo (Mua Sắm Demo) -->
                    <div id="payCard-VIRTUAL_COIN" onclick="selectPaymentMethod('VIRTUAL_COIN')" class="payment-option-card rounded-xl p-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                                <i data-lucide="coins" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800 flex items-center gap-1.5 flex-wrap">
                                    Ví Tiền Ảo (Mua Sắm Demo)
                                    <span class="px-1.5 py-0.5 text-[10px] rounded bg-violet-100 text-violet-700 font-bold">Không cần PIN</span>
                                </div>
                                <div class="text-xs text-slate-500 flex items-center gap-2 flex-wrap mt-0.5">
                                    <span>Số dư tiền ảo: <strong id="checkoutVirtualBal" class="text-violet-600 font-number">0 ₫</strong></span>
                                    <button type="button" onclick="event.stopPropagation(); quickAddVirtualMoney(5000000)" class="px-2 py-0.5 rounded bg-violet-600 hover:bg-violet-700 text-white text-[10px] font-extrabold transition">+5Tr Tiền Ảo</button>
                                </div>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center shrink-0">
                            <div class="w-2.5 h-2.5 rounded-full bg-violet-600 hidden pay-radio-dot"></div>
                        </div>
                    </div>

                    <!-- 2. Thanh toán khi nhận hàng (COD) -->
                    <div id="payCard-COD" onclick="selectPaymentMethod('COD')" class="payment-option-card rounded-xl p-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                <i data-lucide="truck" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800">Thanh Toán Khi Nhận Hàng (COD)</div>
                                <div class="text-xs text-slate-500">Kiểm tra hàng trước khi thanh toán tiền mặt</div>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-[#ee4d2d] hidden pay-radio-dot"></div>
                        </div>
                    </div>

                    <!-- 3. Chuyển khoản Ngân hàng (VietQR) -->
                    <div id="payCard-BANK_TRANSFER" onclick="selectPaymentMethod('BANK_TRANSFER')" class="payment-option-card rounded-xl p-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                                <i data-lucide="qr-code" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800">Chuyển Khoản Ngân Hàng (VietQR)</div>
                                <div class="text-xs text-slate-500">Quét mã QR ngân hàng tự động điền số tiền</div>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-[#ee4d2d] hidden pay-radio-dot"></div>
                        </div>
                    </div>

                    <!-- 4. Mua trước trả sau (Pay Later - Chưa cập nhật) -->
                    <div id="payCard-PAY_LATER" onclick="selectPaymentMethod('PAY_LATER')" class="payment-option-card pay-later-card rounded-xl p-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                    Mua Trước Trả Sau (Pay Later)
                                    <span class="px-1.5 py-0.5 text-[10px] rounded bg-amber-100 text-amber-800 font-bold">Chưa cập nhật</span>
                                </div>
                                <div class="text-xs text-slate-500">Tính năng trả góp 0% đang được cập nhật</div>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-amber-500 hidden pay-radio-dot"></div>
                        </div>
                    </div>
                </div>

                <!-- Wallet PIN Box -->
                <div id="checkoutWalletPinBox" class="p-3.5 rounded-xl bg-blue-50 border border-blue-200 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-[#0068ff] flex items-center gap-1">
                            <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                            Mã PIN Ví TAPay xác nhận thanh toán
                        </span>
                        <span class="text-slate-500">Mặc định: <strong class="text-slate-800">123456</strong></span>
                    </div>
                    <input id="checkoutPinInput" type="password" maxlength="6" value="123456" class="w-full px-3 py-2 rounded-lg bg-white border border-blue-300 text-center tracking-[0.5em] font-extrabold text-slate-800">
                </div>

                <!-- Bank Transfer VietQR Box -->
                <div id="checkoutBankQrBox" class="hidden p-4 rounded-xl bg-sky-50 border border-sky-200 text-center space-y-2">
                    <div class="text-xs font-bold text-sky-800">Quét mã VietQR để chuyển khoản thanh toán đơn hàng</div>
                    <img id="checkoutVietQrImg" src="" alt="VietQR Checkout" class="w-40 h-40 mx-auto rounded-lg bg-white p-2 border border-slate-200 shadow-sm">
                    <div class="text-[11px] text-slate-600">
                        Vietcombank • STK: <strong>990088880001</strong> • <strong>TASTORE VIET NAM</strong>
                    </div>
                </div>

                <!-- Pay Later Not Updated Warning -->
                <div id="checkoutPayLaterNotice" class="hidden p-3.5 rounded-xl bg-amber-50 border border-amber-300 flex items-start gap-2.5">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                    <div class="text-xs text-amber-900 leading-relaxed">
                        <strong>Thông báo:</strong> Phương thức <strong>Mua trước trả sau (Pay Later)</strong> hiện chưa cập nhật. Quý khách vui lòng chọn <strong>Ví điện tử TAPay</strong>, <strong>COD</strong> hoặc <strong>Chuyển khoản ngân hàng</strong>!
                    </div>
                </div>
            </div>
        </div>

        <!-- Drawer Footer Totals -->
        <div class="p-4 border-t border-slate-200 bg-slate-50 space-y-3">
            <div class="space-y-1 text-xs sm:text-sm">
                <div class="flex justify-between text-slate-500">
                    <span>Tổng tiền hàng:</span>
                    <span id="cartSubtotalText" class="font-number font-semibold text-slate-800">0 ₫</span>
                </div>
                <div class="flex justify-between text-slate-500">
                    <span>Giảm giá Voucher:</span>
                    <span id="cartDiscountText" class="font-number font-semibold text-emerald-600">-0 ₫</span>
                </div>
                <div class="flex justify-between text-xs text-amber-700 font-medium">
                    <span>Hoàn tiền vào Ví TAPay:</span>
                    <span id="cartCashbackText" class="font-number font-bold">+0 ₫</span>
                </div>
                <div class="flex justify-between items-baseline pt-2 border-t border-slate-200">
                    <span class="font-extrabold text-slate-800">Tổng thanh toán:</span>
                    <span id="cartFinalTotalText" class="font-number text-xl font-black text-[#ee4d2d]">0 ₫</span>
                </div>
            </div>

            <button id="submitCheckoutBtn" onclick="submitCheckoutOrder()" class="w-full py-3.5 rounded-xl bg-[#ee4d2d] hover:bg-[#d73211] text-white font-extrabold text-sm shadow-lg shadow-orange-500/25 transition flex items-center justify-center gap-2">
                <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                <span id="submitCheckoutBtnText">Đặt Hàng & Thanh Toán Ngay</span>
            </button>
        </div>
    </aside>

    <!-- ==================================================================
         MODAL CHI TIẾT SẢN PHẨM (PRODUCT QUICK VIEW / DETAIL MODAL)
         ================================================================== -->
    <div id="productDetailModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-3xl rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="mall-badge">Mall Chính Hãng</span>
                    <span id="detailSkuText" class="text-xs text-slate-400 font-number">SKU: TA-01</span>
                </div>
                <button onclick="closeModal('productDetailModal')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="productDetailBody" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 max-h-[82vh] overflow-y-auto">
                <!-- Rendered by JS -->
            </div>
        </div>
    </div>

    <!-- ==================================================================
         MODAL 1: TẠO SẢN PHẨM MỚI (+ ĐĂNG BÁN SẢN PHẨM)
         ================================================================== -->
    <div id="createProductModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-xl rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center">
                        <i data-lucide="package-plus" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">Đăng Bán Sản Phẩm Mới</h3>
                        <p class="text-xs text-slate-500">Thêm sản phẩm mới vào cửa hàng cực nhanh chỉ trong vài giây</p>
                    </div>
                </div>
                <button onclick="closeModal('createProductModal')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form onsubmit="handleCreateProduct(event)" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Điền nhanh sản phẩm mẫu (1-click):</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" onclick="setSampleProductPreset('Bàn Phím Cơ Không Dây TAKey Pro RGB', 'Công nghệ', 2490000, 2990000, 5, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&auto=format&fit=crop&q=80')" class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-orange-50 hover:text-[#ee4d2d] text-slate-700 border border-slate-200 font-medium">Mẫu: Bàn phím cơ</button>
                        <button type="button" onclick="setSampleProductPreset('Laptop Siêu Mỏng TABook Air M3', 'Công nghệ', 26900000, 29900000, 4, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&auto=format&fit=crop&q=80')" class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-orange-50 hover:text-[#ee4d2d] text-slate-700 border border-slate-200 font-medium">Mẫu: Laptop</button>
                        <button type="button" onclick="setSampleProductPreset('Áo Khoác Bomber Da Lộn TAStreet', 'Thời trang', 1590000, 1990000, 6, 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=800&auto=format&fit=crop&q=80')" class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-orange-50 hover:text-[#ee4d2d] text-slate-700 border border-slate-200 font-medium">Mẫu: Áo khoác</button>
                        <button type="button" onclick="setSampleProductPreset('Máy Pha Cà Phê Espresso TABrew Barista', 'Đồ gia dụng thông minh', 5490000, 6800000, 5, 'https://images.unsplash.com/photo-1517668808822-9ebb02f2a0e6?w=800&auto=format&fit=crop&q=80')" class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-orange-50 hover:text-[#ee4d2d] text-slate-700 border border-slate-200 font-medium">Mẫu: Máy pha Cafe</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tên sản phẩm *</label>
                    <input id="newProdName" type="text" required placeholder="VD: Bàn Phím Cơ Không Dây TAKey Pro RGB" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800 focus:border-[#ee4d2d] focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Danh mục *</label>
                        <select id="newProdCategory" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                            <option value="Công nghệ">Công nghệ</option>
                            <option value="Thời trang">Thời trang</option>
                            <option value="Âm thanh">Âm thanh</option>
                            <option value="Phụ kiện">Phụ kiện</option>
                            <option value="Mỹ phẩm & Hương thơm">Mỹ phẩm & Hương thơm</option>
                            <option value="Đồ gia dụng thông minh">Đồ gia dụng thông minh</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Giá bán (VNĐ) *</label>
                        <input id="newProdPrice" type="number" required min="1000" step="1000" value="2490000" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm font-bold text-[#ee4d2d] font-number">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Giá gốc (VNĐ)</label>
                        <input id="newProdOriginalPrice" type="number" min="0" step="1000" value="2990000" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-600 font-number">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">% Hoàn tiền Ví TAPay</label>
                        <input id="newProdCashback" type="number" min="0" max="50" step="0.5" value="5" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-emerald-600 font-bold font-number">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Tồn kho</label>
                        <input id="newProdStock" type="number" min="1" value="50" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800 font-number">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nhãn khuyến mãi</label>
                        <input id="newProdBadge" type="text" placeholder="VD: Yêu thích / Hoàn 5%" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Link ảnh sản phẩm (URL)</label>
                    <input id="newProdImage" type="url" value="https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&auto=format&fit=crop&q=80" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-xs text-slate-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Mô tả chi tiết sản phẩm</label>
                    <textarea id="newProdDesc" rows="2" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-700">Sản phẩm chính hãng phân phối tại TAstore Mall, bảo hành 12 tháng, hỗ trợ đổi trả và hoàn tiền 100% về ví TAPay.</textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeModal('createProductModal')" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold">Hủy</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#ee4d2d] hover:bg-[#d73211] text-white font-extrabold text-sm transition flex items-center gap-2 shadow-md">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                        Đăng Bán Ngay
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================
         MODAL 2: NẠP TIỀN VÀO VÍ ĐIỆN TỬ (TOP-UP VIETQR TECHCOMBANK)
         ================================================================== -->
    <div id="topupModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-2xl rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-gradient-to-r from-blue-50 via-white to-rose-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#0068ff] text-white flex items-center justify-center shadow-sm">
                        <i data-lucide="qr-code" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">Nạp Tiền Vào Ví Điện Tử TAPay</h3>
                        <p class="text-xs text-slate-500">Quét mã VietQR Techcombank / Napas 24/7 hoặc xác nhận nạp tức thì</p>
                    </div>
                </div>
                <button onclick="closeModal('topupModal')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form onsubmit="handleTopupSubmit(event)" class="p-6 max-h-[84vh] overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
                    <!-- Left Column: Amount, Source & Note -->
                    <div class="md:col-span-7 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Chọn nhanh mệnh giá nạp</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" onclick="setTopupAmount(200000)" class="topup-preset-btn py-2 rounded-lg bg-slate-100 hover:bg-blue-50 border border-slate-200 text-xs font-bold text-slate-700 font-number transition">200.000đ</button>
                                <button type="button" onclick="setTopupAmount(500000)" class="topup-preset-btn py-2 rounded-lg bg-slate-100 hover:bg-blue-50 border border-slate-200 text-xs font-bold text-slate-700 font-number transition">500.000đ</button>
                                <button type="button" onclick="setTopupAmount(1000000)" class="topup-preset-btn py-2 rounded-lg bg-blue-50 border border-[#0068ff] text-xs font-bold text-[#0068ff] font-number transition">1.000.000đ</button>
                                <button type="button" onclick="setTopupAmount(2000000)" class="topup-preset-btn py-2 rounded-lg bg-slate-100 hover:bg-blue-50 border border-slate-200 text-xs font-bold text-slate-700 font-number transition">2.000.000đ</button>
                                <button type="button" onclick="setTopupAmount(5000000)" class="topup-preset-btn py-2 rounded-lg bg-slate-100 hover:bg-blue-50 border border-slate-200 text-xs font-bold text-slate-700 font-number transition">5.000.000đ</button>
                                <button type="button" onclick="setTopupAmount(10000000)" class="topup-preset-btn py-2 rounded-lg bg-slate-100 hover:bg-blue-50 border border-slate-200 text-xs font-bold text-slate-700 font-number transition">10.000.000đ</button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Số tiền nạp (VNĐ) *</label>
                            <input id="topupAmountInput" oninput="updateTopupVietQR()" type="number" min="1000" step="1000" value="1000000" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-lg font-extrabold text-[#0068ff] font-number focus:border-[#0068ff] focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Ngân hàng / Cổng VietQR nhận nạp</label>
                            <select id="topupSourceSelect" onchange="updateTopupVietQR()" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-sm font-semibold text-slate-800 focus:border-[#0068ff] focus:outline-none">
                                <option value="VietQR - MB Bank (SePay)">MB Bank • NGUYEN TIEN DAT (0382548328) — SePay Auto</option>
                                <option value="VietQR - Techcombank (TCB)">Techcombank • PHAM TUAN ANH (1903 9937 1730 10)</option>
                                <option value="VietQR - Vietcombank (VCB)">Vietcombank (VCB) • Ví TAPay</option>
                                <option value="Thẻ Quốc Tế Visa/Mastercard">Thẻ Visa / Mastercard</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nội dung chuyển khoản</label>
                            <input id="topupNoteInput" oninput="updateTopupVietQR()" type="text" placeholder="Để trống để dùng mã tự động..." class="w-full px-3.5 py-2 rounded-xl bg-white border border-slate-300 text-sm text-slate-800 focus:border-[#0068ff] focus:outline-none">
                        </div>

                        <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-200 text-xs text-slate-700 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Ngân hàng thụ hưởng:</span>
                                <strong id="topupBankBadge" class="text-rose-600 font-extrabold">TECHCOMBANK</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Chủ tài khoản:</span>
                                <strong id="topupAccountNameDisplay" class="text-slate-900">PHAM TUAN ANH</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Số tài khoản:</span>
                                <div class="flex items-center gap-1.5">
                                    <strong id="topupAccountNumberDisplay" class="text-slate-900 font-number">1903 9937 1730 10</strong>
                                    <button type="button" onclick="copyTopupAccount()" class="px-1.5 py-0.5 rounded bg-white border border-slate-300 hover:bg-slate-100 text-[10px] font-bold text-[#0068ff]">Copy</button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Nội dung CK:</span>
                                <div class="flex items-center gap-1.5">
                                    <strong id="topupTransferRef" class="text-[#0068ff] font-number">NAP TAPAY</strong>
                                    <button type="button" onclick="copyTopupRef()" class="px-1.5 py-0.5 rounded bg-white border border-slate-300 hover:bg-slate-100 text-[10px] font-bold text-[#0068ff]">Copy</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Live VietQR Card -->
                    <div class="md:col-span-5">
                        <div class="rounded-2xl bg-gradient-to-b from-rose-50/80 via-white to-rose-50/60 border border-rose-200 p-4 text-center shadow-sm">
                            <div class="text-[11px] font-semibold text-slate-500">Quét mã để chuyển tiền đến</div>
                            <div id="topupQrHolderTitle" class="text-sm font-extrabold text-slate-900 uppercase mt-0.5">PHAM TUAN ANH</div>
                            <div id="topupQrAccTitle" class="text-xs font-bold text-slate-700 font-number mb-3">1903 9937 1730 10</div>

                            <div class="bg-white rounded-2xl p-3 border border-slate-200 shadow-md inline-block">
                                <div id="topupQrBankHeader" class="flex items-center justify-center gap-1.5 text-xs font-black tracking-wide text-slate-900 mb-2">
                                    <span class="text-rose-600">TECHCOMBANK</span>
                                    <span class="w-2.5 h-2.5 rotate-45 bg-rose-600 inline-block"></span>
                                </div>
                                <img id="topupVietQrImg" src="" alt="VietQR Topup" class="w-44 h-44 mx-auto rounded-lg object-contain">
                                <div class="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-center gap-3 text-[10px] font-extrabold">
                                    <span class="text-rose-600">VIETQR</span>
                                    <span class="text-sky-600 italic">napas 247</span>
                                </div>
                            </div>

                            <div class="mt-3 text-xs text-slate-500">
                                Số tiền cần chuyển:
                                <div id="topupQrAmountBadge" class="text-base font-black text-[#0068ff] font-number mt-0.5">1.000.000 ₫</div>
                            </div>
                            <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-[11px] font-bold text-emerald-700">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Tự động kiểm tra tiền về mỗi 3 giây
                            </div>
                        </div>
                    </div>
                </div>

                <div id="topupVerifyAlert" class="hidden mt-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-start gap-2.5">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5"></i>
                    <span id="topupVerifyAlertText" class="font-semibold leading-relaxed"></span>
                </div>

                <div class="pt-5 mt-5 border-t border-slate-200 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeModal('topupModal')" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold">Đóng</button>
                    <button id="topupSubmitBtn" type="submit" class="px-6 py-2.5 rounded-xl bg-[#0068ff] hover:bg-blue-700 text-white font-extrabold text-sm transition flex items-center gap-2 shadow-md">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        Kiểm Tra &amp; Xác Nhận Đã Chuyển
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================
         MODAL 2B: CỘNG TIỀN ẢO DEMO MUA SẮM
         ================================================================== -->
    <div id="virtualMoneyModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-md rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-violet-50/70">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-violet-600 text-white flex items-center justify-center shadow-sm">
                        <i data-lucide="coins" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">Nạp Tiền Ảo Mua Sắm (Demo)</h3>
                        <p class="text-xs text-slate-500">Dùng để mua đồ thử nghiệm trên TAstore Mall miễn phí</p>
                    </div>
                </div>
                <button onclick="closeModal('virtualMoneyModal')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form onsubmit="handleAddVirtualMoneySubmit(event)" class="p-6 space-y-4">
                <div class="p-3.5 rounded-xl bg-violet-50 border border-violet-200 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600">Số dư Tiền Ảo hiện có:</span>
                    <strong id="virtualModalCurrentBalance" class="text-lg font-black text-violet-700 font-number">0 ₫</strong>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Chọn nhanh gói Tiền Ảo (+)</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="setVirtualAmount(1000000)" class="py-2.5 rounded-xl bg-slate-100 hover:bg-violet-50 border border-slate-200 hover:border-violet-400 text-xs font-extrabold text-slate-800 font-number transition">+ 1.000.000 ₫</button>
                        <button type="button" onclick="setVirtualAmount(5000000)" class="py-2.5 rounded-xl bg-violet-50 border border-violet-500 text-xs font-extrabold text-violet-700 font-number transition">+ 5.000.000 ₫</button>
                        <button type="button" onclick="setVirtualAmount(10000000)" class="py-2.5 rounded-xl bg-slate-100 hover:bg-violet-50 border border-slate-200 hover:border-violet-400 text-xs font-extrabold text-slate-800 font-number transition">+ 10.000.000 ₫</button>
                        <button type="button" onclick="setVirtualAmount(50000000)" class="py-2.5 rounded-xl bg-slate-100 hover:bg-violet-50 border border-slate-200 hover:border-violet-400 text-xs font-extrabold text-slate-800 font-number transition">+ 50.000.000 ₫</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Hoặc nhập số Tiền Ảo muốn cộng *</label>
                    <input id="virtualAmountInput" type="number" min="10000" max="100000000" step="10000" value="5000000" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-300 text-lg font-extrabold text-violet-700 font-number focus:border-violet-600 focus:outline-none">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeModal('virtualMoneyModal')" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold">Đóng</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-extrabold text-sm transition flex items-center gap-2 shadow-md">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        Cộng Tiền Ảo Ngay
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================
         MODAL 3: CHUYỂN TIỀN P2P & CHUYỂN KHOẢN NGÂN HÀNG 24/7
         ================================================================== -->
    <div id="transferModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-xl rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-orange-50/70">
                <div class="flex items-center gap-2.5">
                    <div id="transferModalIcon" class="w-9 h-9 rounded-xl bg-[#ee4d2d] text-white flex items-center justify-center">
                        <i data-lucide="send" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="transferModalTitle" class="text-base font-extrabold text-slate-800">Chuyển Tiền & Chuyển Khoản 24/7</h3>
                        <p id="transferModalSubtitle" class="text-xs text-slate-500">Chuyển ví nội bộ P2P hoặc chuyển khoản liên ngân hàng Napas 24/7</p>
                    </div>
                </div>
                <button onclick="closeModal('transferModal')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Mode Switcher Tabs -->
            <div class="px-6 pt-4">
                <div class="grid grid-cols-2 gap-2 p-1 rounded-xl bg-slate-100 border border-slate-200">
                    <button type="button" id="transferTabBtn-p2p" onclick="switchTransferMode('p2p')" class="py-2 px-3 rounded-lg text-xs font-extrabold transition flex items-center justify-center gap-1.5 bg-white text-[#ee4d2d] shadow-sm">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        Chuyển Ví TAPay (P2P)
                    </button>
                    <button type="button" id="transferTabBtn-bank" onclick="switchTransferMode('bank')" class="py-2 px-3 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900">
                        <i data-lucide="landmark" class="w-3.5 h-3.5"></i>
                        Chuyển Khoản Ngân Hàng 24/7
                    </button>
                </div>
            </div>

            <!-- Form 1: P2P Internal Wallet Transfer -->
            <form id="transferForm-p2p" onsubmit="handleTransferSubmit(event)" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">Chọn nhanh người nhận trong danh bạ:</label>
                    <div id="transferQuickRecipients" class="flex flex-wrap gap-2">
                        <!-- Rendered by JS -->
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">SĐT / Email / Số Ví người nhận *</label>
                    <div class="flex gap-2">
                        <input id="transferRecipientInput" onblur="lookupRecipient()" type="text" required placeholder="VD: 0988888888 hoặc 9900 8888 0002" class="flex-1 px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                        <button type="button" onclick="lookupRecipient()" class="px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs font-bold text-[#0068ff] border border-slate-300">Kiểm tra</button>
                    </div>
                    <div id="recipientLookupResult" class="mt-2 hidden p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                        <span id="recipientLookupName">Người nhận hợp lệ</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Số tiền chuyển (VNĐ) *</label>
                        <input id="transferAmountInput" type="number" min="1000" step="1000" value="500000" required class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-base font-extrabold text-[#ee4d2d] font-number">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Mã PIN Ví (Mặc định: 123456) *</label>
                        <input id="transferPinInput" type="password" maxlength="6" value="123456" required class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-center tracking-[0.4em] font-bold text-slate-800">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Lời nhắn chuyển tiền</label>
                    <input id="transferMessageInput" type="text" value="Chuyển tiền mua sắm qua TAPay" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeModal('transferModal')" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold">Hủy</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#ee4d2d] hover:bg-[#d73211] text-white font-extrabold text-sm transition flex items-center gap-2 shadow-md">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        Xác Nhận Chuyển Ví P2P
                    </button>
                </div>
            </form>

            <!-- Form 2: External Bank Transfer (Napas 24/7) -->
            <form id="transferForm-bank" onsubmit="handleBankTransferSubmit(event)" class="hidden p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">Tài khoản thụ hưởng đã lưu:</label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="selectQuickBankRecipient('TCB', '19039937173010', 'PHAM TUAN ANH')" class="px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 border border-rose-200 text-xs font-bold text-rose-700 flex items-center gap-1.5 transition">
                            <span class="px-1.5 py-0.5 rounded bg-rose-600 text-white text-[10px]">TCB</span>
                            PHAM TUAN ANH • 1903 9937 1730 10
                        </button>
                        <button type="button" onclick="selectQuickBankRecipient('VCB', '0011001932418', 'NGUYEN VAN MINH')" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 border border-slate-200 text-xs font-semibold text-slate-700 flex items-center gap-1.5 transition">
                            <span class="px-1.5 py-0.5 rounded bg-emerald-600 text-white text-[10px]">VCB</span>
                            NGUYEN VAN MINH • 0011001932418
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Ngân hàng nhận *</label>
                    <select id="bankTransferBankSelect" class="w-full px-3.5 py-2.5 rounded-lg bg-white border border-slate-300 text-sm font-semibold text-slate-800">
                        <option value="TCB|Techcombank">Techcombank (TCB) - Ngân hàng TMCP Kỹ Thương</option>
                        <option value="VCB|Vietcombank">Vietcombank (VCB) - Ngân hàng Ngoại Thương</option>
                        <option value="MB|MB Bank">MB Bank (MB) - Ngân hàng Quân Đội</option>
                        <option value="BIDV|BIDV">BIDV - Đầu tư và Phát triển Việt Nam</option>
                        <option value="ICB|VietinBank">VietinBank (CTG) - Công Thương Việt Nam</option>
                        <option value="ACB|ACB">ACB - Ngân hàng Á Châu</option>
                        <option value="VPB|VPBank">VPBank - Việt Nam Thịnh Vượng</option>
                        <option value="TPB|TPBank">TPBank - Ngân hàng Tiên Phong</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Số tài khoản nhận *</label>
                        <input id="bankTransferAccountInput" type="text" required value="19039937173010" placeholder="VD: 19039937173010" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm font-bold font-number text-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Tên chủ tài khoản *</label>
                        <input id="bankTransferNameInput" type="text" required value="PHAM TUAN ANH" placeholder="VD: PHAM TUAN ANH" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm font-bold uppercase text-slate-800">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Chọn nhanh số tiền</label>
                    <div class="grid grid-cols-4 gap-2 mb-2">
                        <button type="button" onclick="setBankTransferAmount(200000)" class="py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 border border-slate-200 text-xs font-bold text-slate-700 font-number">200.000đ</button>
                        <button type="button" onclick="setBankTransferAmount(500000)" class="py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 border border-slate-200 text-xs font-bold text-slate-700 font-number">500.000đ</button>
                        <button type="button" onclick="setBankTransferAmount(1000000)" class="py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 border border-slate-200 text-xs font-bold text-slate-700 font-number">1.000.000đ</button>
                        <button type="button" onclick="setBankTransferAmount(5000000)" class="py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 border border-slate-200 text-xs font-bold text-slate-700 font-number">5.000.000đ</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Số tiền chuyển (VNĐ) *</label>
                            <input id="bankTransferAmountInput" type="number" min="10000" step="10000" value="500000" required class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-base font-extrabold text-emerald-600 font-number">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Mã PIN Ví (Mặc định: 123456) *</label>
                            <input id="bankTransferPinInput" type="password" maxlength="6" value="123456" required class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-center tracking-[0.4em] font-bold text-slate-800">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nội dung chuyển khoản</label>
                    <input id="bankTransferMessageInput" type="text" value="Chuyen khoan Napas 24/7 tu Vi TAPay" class="w-full px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeModal('transferModal')" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold">Hủy</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm transition flex items-center gap-2 shadow-md">
                        <i data-lucide="landmark" class="w-4 h-4"></i>
                        Xác Nhận Chuyển Khoản 24/7
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================
         MODAL 4: YÊU CẦU TRẢ HÀNG & HOÀN TIỀN VỀ VÍ (REFUND MODAL)
         ================================================================== -->
    <div id="refundModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-md rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#0068ff] text-white flex items-center justify-center">
                        <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">Yêu Cầu Trả Hàng / Hoàn Tiền</h3>
                        <p class="text-xs text-slate-500">Hoàn lại 100% tiền đơn hàng vào Ví TAPay tức thì</p>
                    </div>
                </div>
                <button onclick="closeModal('refundModal')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form onsubmit="handleRefundSubmit(event)" class="p-6 space-y-4">
                <input id="refundOrderIdInput" type="hidden">
                <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-xs space-y-1">
                    <div class="text-slate-600">Đơn hàng: <strong id="refundOrderCodeText" class="text-slate-900">#ORD-XXXX</strong></div>
                    <div class="text-slate-600">Số tiền hoàn lại vào Ví: <strong id="refundAmountText" class="text-base text-[#ee4d2d] font-extrabold font-number">0 ₫</strong></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Lý do trả hàng / hoàn tiền *</label>
                    <select id="refundReasonSelect" class="w-full px-3 py-2.5 rounded-lg bg-white border border-slate-300 text-sm text-slate-800">
                        <option value="Đổi ý, muốn hoàn tiền về Ví TAPay để mua sản phẩm khác">Đổi ý, muốn hoàn tiền về Ví để mua sản phẩm khác</option>
                        <option value="Đặt nhầm mẫu mã / số lượng sản phẩm">Đặt nhầm mẫu mã / số lượng sản phẩm</option>
                        <option value="Muốn đổi sang áp dụng mã giảm giá mới">Muốn đổi sang áp dụng mã giảm giá mới</option>
                        <option value="Sản phẩm không đúng như mô tả">Sản phẩm không đúng như mô tả</option>
                    </select>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeModal('refundModal')" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold">Đóng</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#0068ff] hover:bg-blue-700 text-white font-extrabold text-sm transition flex items-center gap-2 shadow-md">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Hoàn Tiền Về Ví Ngay
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================
         MODAL 5: BIÊN LAI GIAO DỊCH THÀNH CÔNG (RECEIPT MODAL)
         ================================================================== -->
    <div id="receiptModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-md rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-6 text-center bg-gradient-to-b from-emerald-50 to-white">
                <div class="w-14 h-14 rounded-full bg-emerald-500 text-white flex items-center justify-center mx-auto mb-3 shadow-md">
                    <i data-lucide="check" class="w-8 h-8 stroke-[3]"></i>
                </div>
                <h3 id="receiptTitle" class="text-lg font-extrabold text-slate-800">Giao Dịch Thành Công!</h3>
                <div id="receiptAmount" class="text-3xl font-black text-emerald-600 font-number mt-1">+1.000.000 ₫</div>
                <p id="receiptSubtitle" class="text-xs text-slate-500 mt-1">Đã lưu vào cơ sở dữ liệu</p>
            </div>

            <div id="receiptDetailsList" class="px-6 py-4 space-y-2 text-xs border-t border-slate-100 bg-slate-50/60">
                <!-- Rendered by JS -->
            </div>

            <div class="p-4 border-t border-slate-200 flex justify-center">
                <button onclick="closeModal('receiptModal')" class="w-full py-2.5 rounded-xl bg-[#ee4d2d] hover:bg-[#d73211] text-white font-bold text-sm transition">
                    Hoàn Tất
                </button>
            </div>
        </div>
    </div>

    <!-- ==================================================================
         MODAL 6: CẤU HÌNH CSDL SUPABASE & XAMPP
         ================================================================== -->
    <div id="dbConfigModal" class="fixed inset-0 bg-black/60 backdrop-blur-[2px] z-50 hidden items-center justify-center p-4">
        <div class="bg-white modal-animate w-full max-w-xl rounded-2xl border border-slate-200 overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center">
                        <i data-lucide="database" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">Cấu Hình Cơ Sở Dữ Liệu Supabase & XAMPP</h3>
                        <p class="text-xs text-slate-500">Hỗ trợ Supabase Cloud (PostgreSQL / REST API) và XAMPP MySQL</p>
                    </div>
                </div>
                <button onclick="closeModal('dbConfigModal')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form onsubmit="handleSaveDbConfig(event)" class="p-6 space-y-4 text-xs">
                <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200 text-slate-700 leading-relaxed">
                    File SQL chuẩn cho Supabase đã được tạo sẵn tại: <code class="text-[#0068ff] font-bold">database/supabase_schema.sql</code>. Bạn có thể dán thông tin Project Supabase dưới đây để đồng bộ trực tiếp lên Cloud.
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Supabase Project URL</label>
                        <input id="cfgSupabaseUrl" type="url" placeholder="https://xyzcompany.supabase.co" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-800">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Supabase Anon / Service Key</label>
                        <input id="cfgSupabaseKey" type="text" placeholder="eyJhbGciOiJIUzI1NiIsInR5..." class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-800">
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-3">
                    <div class="font-bold text-slate-700 mb-2">Hoặc Kết nối trực tiếp PostgreSQL của Supabase:</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-slate-500 mb-1">Supabase DB Host</label>
                            <input id="cfgPgHost" type="text" placeholder="db.xyzcompany.supabase.co" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-800">
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1">Database Password</label>
                            <input id="cfgPgPass" type="password" placeholder="••••••••" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-800">
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeModal('dbConfigModal')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-semibold">Đóng</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">Lưu Cấu Hình</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 space-y-2 max-w-sm w-full pointer-events-none"></div>

    <!-- ==================================================================
         STANDARD E-COMMERCE FOOTER (CHÂN TRANG CHUẨN TMĐT)
         ================================================================== -->
    <footer class="bg-white border-t-4 border-[#ee4d2d] mt-12 text-slate-600 text-xs">
        <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="space-y-2.5">
                <h4 class="font-extrabold text-slate-800 uppercase">Chăm Sóc Khách Hàng</h4>
                <ul class="space-y-1.5">
                    <li><a href="javascript:void(0)" onclick="switchTab('orders')" class="hover:text-[#ee4d2d]">Hướng Dẫn Trả Hàng & Hoàn Tiền Ví</a></li>
                    <li><a href="javascript:void(0)" onclick="openTopupModal()" class="hover:text-[#ee4d2d]">Hướng Dẫn Nạp Tiền Ví TAPay</a></li>
                    <li><a href="javascript:void(0)" onclick="openTransferModal()" class="hover:text-[#ee4d2d]">Chuyển Khoản Nội Bộ P2P Miễn Phí</a></li>
                    <li><a href="javascript:void(0)" class="hover:text-[#ee4d2d]">Chính Sách Bảo Hành Chính Hãng</a></li>
                </ul>
            </div>
            <div class="space-y-2.5">
                <h4 class="font-extrabold text-slate-800 uppercase">Về TAstore Mall</h4>
                <ul class="space-y-1.5">
                    <li><a href="javascript:void(0)" onclick="openCreateProductModal()" class="hover:text-[#ee4d2d]">Kênh Người Bán (Tạo Sản Phẩm)</a></li>
                    <li><a href="javascript:void(0)" class="hover:text-[#ee4d2d]">Chương Trình Hoàn Tiền Cashback 7%</a></li>
                    <li><a href="javascript:void(0)" class="hover:text-[#ee4d2d]">Điều Khoản Sử Dụng Ví Điện Tử</a></li>
                </ul>
            </div>
            <div class="space-y-2.5">
                <h4 class="font-extrabold text-slate-800 uppercase">Phương Thức Thanh Toán</h4>
                <div class="flex flex-wrap gap-2">
                    <span class="px-2.5 py-1 rounded border border-slate-200 bg-slate-50 font-bold text-[#0068ff]">Ví TAPay</span>
                    <span class="px-2.5 py-1 rounded border border-slate-200 bg-slate-50 font-bold text-emerald-700">COD</span>
                    <span class="px-2.5 py-1 rounded border border-slate-200 bg-slate-50 font-bold text-sky-700">VietQR 24/7</span>
                    <span class="px-2.5 py-1 rounded border border-slate-200 bg-slate-50 font-bold text-amber-700">Pay Later (Sắp mở)</span>
                </div>
            </div>
            <div class="space-y-2.5">
                <h4 class="font-extrabold text-slate-800 uppercase">Hệ Thống Cơ Sở Dữ Liệu</h4>
                <p class="leading-relaxed">Vận hành trên XAMPP & Supabase PostgreSQL, đảm bảo an toàn tuyệt đối cho mọi giao dịch Ví điện tử và đơn hàng.</p>
            </div>
        </div>
        <div class="bg-slate-100 py-4 text-center text-slate-500 border-t border-slate-200">
            © 2026 TAstore Mall & Ví Điện Tử TAPay. Đã đăng ký bản quyền.
        </div>
    </footer>

    <!-- Mobile Bottom Navigation (Fintech Super-App Style) -->
    <nav aria-label="Điều hướng di động" class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 px-2 py-1.5 shadow-[0_-4px_20px_rgba(15,23,42,0.06)]">
        <div class="grid grid-cols-4 gap-1">
            <button onclick="switchTab('shop')" class="flex flex-col items-center justify-center py-1 rounded-xl text-slate-600 hover:text-[#ee4d2d] transition">
                <i data-lucide="home" class="w-5 h-5"></i>
                <span class="text-[10px] font-bold mt-0.5">Mall</span>
            </button>
            <button onclick="switchTab('wallet')" class="flex flex-col items-center justify-center py-1 rounded-xl text-[#0068ff] bg-blue-50/70 transition">
                <i data-lucide="wallet" class="w-5 h-5"></i>
                <span class="text-[10px] font-extrabold mt-0.5">Ví TAPay</span>
            </button>
            <button onclick="openBankTransferModal()" class="flex flex-col items-center justify-center py-1 rounded-xl text-slate-600 hover:text-emerald-600 transition">
                <i data-lucide="landmark" class="w-5 h-5"></i>
                <span class="text-[10px] font-bold mt-0.5">Chuyển NH</span>
            </button>
            <button onclick="switchTab('orders')" class="flex flex-col items-center justify-center py-1 rounded-xl text-slate-600 hover:text-emerald-600 transition">
                <i data-lucide="package-check" class="w-5 h-5"></i>
                <span class="text-[10px] font-bold mt-0.5">Đơn Mua</span>
            </button>
        </div>
    </nav>

    <script src="assets/js/app.js"></script>
</body>
</html>
