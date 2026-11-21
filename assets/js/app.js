/**
 * TAstore Mall & Ví Điện Tử TAPay - E-Commerce Frontend Logic
 * Hỗ trợ:
 * - Giao diện chuẩn Website Bán Hàng TMĐT (Flash Sale đếm ngược, Sắp xếp giá/bán chạy, Đánh giá sao, Xem chi tiết SP)
 * - Tạo sản phẩm mới dễ dàng (+ Đăng Bán Sản Phẩm)
 * - 4 Phương thức thanh toán: Ví điện tử TAPay, COD, Chuyển khoản VietQR, Mua trước trả sau (Chưa cập nhật)
 * - Nghiệp vụ Ví điện tử: Nạp tiền VietQR, Chuyển khoản P2P, Rút Cashback, Hoàn tiền đơn hàng 100% về Ví
 */

const state = {
    currentUserId: 1,
    wallet: null,
    stats: { total_in: 0, total_out: 0, total_refund: 0, total_cashback: 0 },
    transactions: [],
    txFilter: 'ALL',
    allUsers: [],
    products: [],
    categories: [],
    activeCategory: 'all',
    sortBy: 'popular', // 'popular', 'newest', 'bestseller', 'price_asc', 'price_desc'
    searchQuery: '',
    cart: [],
    selectedPaymentMethod: 'WALLET', // 'WALLET', 'COD', 'BANK_TRANSFER', 'PAY_LATER'
    voucherCode: '',
    orders: [],
    balanceVisible: true
};

// Format VND Currency
function formatVND(amount) {
    const num = Number(amount || 0);
    return num.toLocaleString('vi-VN') + ' ₫';
}

// Calculate Discount Percentage
function calcDiscountPercent(price, originalPrice) {
    const p = Number(price || 0);
    const orig = Number(originalPrice || 0);
    if (orig <= p || orig <= 0) return 0;
    return Math.round(((orig - p) / orig) * 100);
}

// Show Toast Message
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const colors = {
        success: 'border-emerald-500 bg-white text-slate-800 shadow-xl',
        error: 'border-rose-500 bg-white text-slate-800 shadow-xl',
        warning: 'border-amber-500 bg-white text-slate-800 shadow-xl',
        info: 'border-[#0068ff] bg-white text-slate-800 shadow-xl'
    };

    const iconColors = {
        success: 'text-emerald-600',
        error: 'text-rose-600',
        warning: 'text-amber-600',
        info: 'text-[#0068ff]'
    };

    const icons = {
        success: 'check-circle-2',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto modal-animate flex items-start gap-3 p-4 rounded-xl border-l-4 border border-slate-200 ${colors[type] || colors.info}`;
    toast.innerHTML = `
        <i data-lucide="${icons[type] || 'info'}" class="w-5 h-5 shrink-0 mt-0.5 ${iconColors[type] || iconColors.info}"></i>
        <div class="text-xs font-semibold leading-relaxed flex-1 text-slate-800">${message}</div>
    `;
    container.appendChild(toast);
    if (window.lucide) lucide.createIcons();

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        toast.style.transition = 'all 0.25s ease';
        setTimeout(() => toast.remove(), 260);
    }, 4000);
}

// Initialize App & Countdown Timer
document.addEventListener('DOMContentLoaded', async () => {
    const savedCart = localStorage.getItem('aurashop_cart');
    if (savedCart) {
        try { state.cart = JSON.parse(savedCart); } catch (e) { state.cart = []; }
    }
    startFlashSaleCountdown();
    await Promise.all([
        fetchWalletOverview(),
        fetchProducts(),
        fetchOrders(),
        loadDbConfigInfo()
    ]);
    renderCart();
    updateTopupVietQR();
    const webhookUrlEl = document.getElementById('sepayWebhookUrlText');
    if (webhookUrlEl && !['127.0.0.1', 'localhost'].includes(window.location.hostname)) {
        webhookUrlEl.textContent = `${window.location.origin}/api/webhook_sepay.php`;
    }
    if (window.location.hash === '#wallet') {
        switchTab('wallet');
    } else if (window.location.hash === '#orders') {
        switchTab('orders');
    }
    window.addEventListener('hashchange', () => {
        if (window.location.hash === '#wallet') switchTab('wallet');
        else if (window.location.hash === '#orders') switchTab('orders');
        else if (window.location.hash === '#shop' || !window.location.hash) switchTab('shop');
    });
    if (window.lucide) lucide.createIcons();
});

// Flash Sale Countdown Timer
function startFlashSaleCountdown() {
    let totalSeconds = 2 * 3600 + 45 * 60 + 19;
    setInterval(() => {
        totalSeconds = totalSeconds > 0 ? totalSeconds - 1 : 9900;
        const h = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
        const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
        const s = String(totalSeconds % 60).padStart(2, '0');
        const elH = document.getElementById('fsHour');
        const elM = document.getElementById('fsMin');
        const elS = document.getElementById('fsSec');
        if (elH) elH.textContent = h;
        if (elM) elM.textContent = m;
        if (elS) elS.textContent = s;
    }, 1000);
}

// Switch Main Tabs
function switchTab(tabName) {
    if (window.location.hash !== `#${tabName}`) {
        history.replaceState(null, '', `#${tabName}`);
    }
    ['shop', 'wallet', 'orders'].forEach(t => {
        const sec = document.getElementById(`tabSection-${t}`);
        const btn = document.getElementById(`tabBtn-${t}`);
        if (sec) sec.classList.toggle('hidden', t !== tabName);
        if (btn) {
            if (t === tabName) {
                btn.className = 'nav-tab-btn px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1.5 bg-[#fff5f1] text-[#ee4d2d]';
            } else {
                btn.className = 'nav-tab-btn px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition flex items-center gap-1.5 text-slate-700 hover:bg-slate-100';
            }
        }
    });

    if (tabName === 'wallet') fetchWalletOverview();
    if (tabName === 'orders') fetchOrders();
    if (window.lucide) lucide.createIcons();
}

// Switch Active User Account (1-click test P2P Transfer)
async function switchUserAccount(userId) {
    state.currentUserId = Number(userId);
    await Promise.all([
        fetchWalletOverview(),
        fetchOrders()
    ]);
    const shipName = document.getElementById('shipName');
    const shipPhone = document.getElementById('shipPhone');
    if (shipName && state.wallet) shipName.value = state.wallet.full_name;
    if (shipPhone && state.wallet) shipPhone.value = state.wallet.phone;
    showToast(`Đã chuyển sang tài khoản: ${state.wallet?.full_name || ''}`, 'info');
}

// ============================================================================
// 1. WALLET API CALLS & UI RENDERING
// ============================================================================
async function fetchWalletOverview() {
    try {
        const res = await fetch(`api/wallet.php?action=overview&user_id=${state.currentUserId}`);
        const data = await res.json();
        if (!data.success) return;

        state.wallet = data.wallet;
        state.stats = data.stats;
        state.transactions = data.transactions || [];
        state.allUsers = data.allUsers || data.all_users || [];

        if (data.db_info) {
            const badge = document.getElementById('dbStatusBadge');
            if (badge) badge.textContent = data.db_info.label;
        }

        renderWalletUI();
        renderAccountSwitcher();
        renderQuickContacts();
        renderTransactionsTable();

        const shipName = document.getElementById('shipName');
        const shipPhone = document.getElementById('shipPhone');
        if (shipName && !shipName.value) shipName.value = state.wallet.full_name;
        if (shipPhone && !shipPhone.value) shipPhone.value = state.wallet.phone;
    } catch (e) {
        console.error('Error fetching wallet overview:', e);
    }
}

function toggleBalanceVisibility() {
    state.balanceVisible = !state.balanceVisible;
    renderWalletUI();
}

function renderWalletUI() {
    if (!state.wallet) return;
    const balText = state.balanceVisible ? formatVND(state.wallet.balance) : '•••••••• ₫';
    const cbText = state.balanceVisible ? formatVND(state.wallet.cashback_balance) : '•••••• ₫';

    const setEl = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };

    setEl('headerWalletBalance', balText);
    setEl('headerCashbackMini', `+${formatVND(state.wallet.cashback_balance)}`);
    setEl('heroWalletBalance', balText);
    setEl('heroCashbackBalance', cbText);
    setEl('heroWalletNumber', state.wallet.wallet_number);
    setEl('heroWalletHolder', state.wallet.full_name);

    setEl('walletTabBalance', state.hideWalletBalance ? '•••••••• ₫' : balText);
    setEl('walletTabCashback', cbText);
    const virtualBalText = formatVND(state.wallet.virtual_balance || 0);
    setEl('walletTabVirtualBalance', virtualBalText);
    setEl('virtualModalCurrentBalance', virtualBalText);
    setEl('walletTabNumber', state.wallet.wallet_number);
    setEl('walletTabHolder', state.wallet.full_name);
    setEl('walletTabTier', state.wallet.tier || 'Platinum Member');

    setEl('checkoutWalletBal', formatVND(state.wallet.balance));
    setEl('checkoutVirtualBal', virtualBalText);

    setEl('statTotalIn', formatVND(state.stats.total_in));
    setEl('statTotalOut', formatVND(state.stats.total_out));
    setEl('statTotalRefund', formatVND(state.stats.total_refund));
}

function renderAccountSwitcher() {
    const container = document.getElementById('accountSwitcherList');
    if (!container) return;

    container.innerHTML = state.allUsers.map(u => {
        const active = Number(u.id) === Number(state.currentUserId);
        return `
            <button onclick="switchUserAccount(${u.id})" class="px-2 py-0.5 rounded text-[11px] font-bold transition flex items-center gap-1 ${
                active
                ? 'bg-yellow-300 text-red-950 shadow-sm'
                : 'bg-black/20 text-white/90 hover:bg-black/30'
            }">
                <img src="${u.avatar}" class="w-3.5 h-3.5 rounded-full object-cover" alt="">
                <span>${u.full_name.split(' ').slice(-2).join(' ')}</span>
            </button>
        `;
    }).join('');
}

function renderQuickContacts() {
    const contactsContainer = document.getElementById('quickContactsList');
    const modalQuickContainer = document.getElementById('transferQuickRecipients');
    const others = state.allUsers.filter(u => Number(u.id) !== Number(state.currentUserId));

    if (contactsContainer) {
        contactsContainer.innerHTML = others.map(u => `
            <div onclick="openTransferToContact('${u.phone}', '${u.full_name}')" class="cursor-pointer p-3 rounded-xl bg-slate-50 hover:bg-blue-50/70 border border-slate-200 hover:border-[#0068ff] transition flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <img src="${u.avatar}" class="w-10 h-10 rounded-full object-cover border border-slate-300" alt="${u.full_name}">
                    <div>
                        <div class="text-xs font-extrabold text-slate-800">${u.full_name}</div>
                        <div class="text-[11px] text-slate-500 font-number">${u.phone} • Ví: ${u.wallet_number}</div>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-lg bg-[#0068ff] text-white text-xs font-bold">Chuyển ngay</span>
            </div>
        `).join('');
    }

    if (modalQuickContainer) {
        modalQuickContainer.innerHTML = others.map(u => `
            <button type="button" onclick="selectQuickRecipient('${u.phone}', '${u.full_name}', '${u.wallet_number}')" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-orange-50 border border-slate-200 hover:border-[#ee4d2d] text-xs text-slate-800 flex items-center gap-1.5 transition">
                <img src="${u.avatar}" class="w-4 h-4 rounded-full object-cover" alt="">
                <span class="font-bold">${u.full_name}</span>
                <span class="text-slate-500 font-number">(${u.phone})</span>
            </button>
        `).join('');
    }
}

function filterTransactions(type) {
    state.txFilter = type;
    document.querySelectorAll('.tx-filter-btn').forEach(btn => {
        btn.className = 'tx-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200';
    });
    if (event && event.target) {
        event.target.className = 'tx-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-[#0068ff] text-white';
    }
    renderTransactionsTable();
}

function renderTransactionsTable() {
    const tbody = document.getElementById('transactionsTableBody');
    if (!tbody) return;

    const filtered = state.transactions.filter(tx => {
        if (state.txFilter === 'ALL') return true;
        if (state.txFilter === 'TRANSFER') return tx.type === 'TRANSFER_IN' || tx.type === 'TRANSFER_OUT';
        return tx.type === state.txFilter;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-10 text-center text-slate-400 text-sm">Chưa có giao dịch nào trong danh mục này</td>
            </tr>
        `;
        return;
    }

    const typeBadges = {
        TOPUP: { label: 'Nạp tiền Ví', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', sign: '+', color: 'text-emerald-600' },
        TRANSFER_IN: { label: 'Nhận tiền P2P', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', sign: '+', color: 'text-emerald-600' },
        TRANSFER_OUT: { label: 'Chuyển tiền P2P', cls: 'bg-orange-50 text-[#ee4d2d] border-orange-200', sign: '-', color: 'text-rose-600' },
        PAYMENT: { label: 'Thanh toán đơn', cls: 'bg-blue-50 text-[#0068ff] border-blue-200', sign: '-', color: 'text-rose-600' },
        REFUND: { label: 'Hoàn tiền về Ví', cls: 'bg-sky-50 text-sky-700 border-sky-200', sign: '+', color: 'text-[#0068ff]' },
        CASHBACK: { label: 'Thưởng Cashback', cls: 'bg-amber-50 text-amber-800 border-amber-200', sign: '+', color: 'text-amber-600' },
        VIRTUAL_TOPUP: { label: '+ Tiền Ảo', cls: 'bg-violet-50 text-violet-700 border-violet-200', sign: '+', color: 'text-violet-600' },
        VIRTUAL_PAYMENT: { label: 'Mua bằng Tiền Ảo', cls: 'bg-purple-50 text-purple-700 border-purple-200', sign: '-', color: 'text-purple-600' },
        VIRTUAL_REFUND: { label: 'Hoàn Tiền Ảo', cls: 'bg-violet-50 text-violet-700 border-violet-200', sign: '+', color: 'text-violet-600' }
    };

    tbody.innerHTML = filtered.map(tx => {
        let cfg = typeBadges[tx.type] || { label: tx.type, cls: 'bg-slate-100 text-slate-700', sign: '', color: 'text-slate-800' };
        if (tx.type === 'TRANSFER_OUT' && tx.metadata_parsed?.transfer_type === 'BANK_247') {
            cfg = { label: 'Chuyển khoản NH', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', sign: '-', color: 'text-rose-600' };
        } else if (tx.type === 'TOPUP' && String(tx.tx_code || '').startsWith('SEPAY-')) {
            cfg = { label: '⚡ SePay Auto', cls: 'bg-blue-50 text-[#0068ff] border-blue-200', sign: '+', color: 'text-emerald-600' };
        }
        return `
            <tr class="hover:bg-slate-50 transition">
                <td class="py-3 px-4 font-number text-xs text-slate-600 font-bold">${tx.tx_code}</td>
                <td class="py-3 px-4">
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${cfg.cls}">${cfg.label}</span>
                </td>
                <td class="py-3 px-4 text-slate-700 max-w-md">${tx.description}</td>
                <td class="py-3 px-4 text-right font-number font-extrabold ${cfg.color}">${cfg.sign}${formatVND(tx.amount)}</td>
                <td class="py-3 px-4 text-right font-number font-semibold text-slate-700">${formatVND(tx.balance_after)}</td>
                <td class="py-3 px-4 text-right text-xs text-slate-400">${tx.created_at}</td>
            </tr>
        `;
    }).join('');
}

// ============================================================================
// 2. TOP-UP, P2P & BANK TRANSFER HANDLERS
// ============================================================================
const TOPUP_BANK_CONFIG = {
    'VietQR - MB Bank (SePay)': {
        bankId: 'MB',
        bankLabel: 'MB BANK',
        accountNumber: '0382548328',
        accountDisplay: '0382548328',
        accountName: 'NGUYEN TIEN DAT',
        accentClass: 'text-blue-600'
    },
    'VietQR - Techcombank (TCB)': {
        bankId: 'TCB',
        bankLabel: 'TECHCOMBANK',
        accountNumber: '19039937173010',
        accountDisplay: '1903 9937 1730 10',
        accountName: 'PHAM TUAN ANH',
        accentClass: 'text-rose-600'
    },
    'VietQR - Vietcombank (VCB)': {
        bankId: 'VCB',
        bankLabel: 'VIETCOMBANK',
        accountNumber: '990088880001',
        accountDisplay: '9900 8888 0001',
        accountName: 'VI DIEN TU TAPAY',
        accentClass: 'text-emerald-600'
    },
    'Thẻ Quốc Tế Visa/Mastercard': {
        bankId: 'MB',
        bankLabel: 'MB BANK',
        accountNumber: '0382548328',
        accountDisplay: '0382548328',
        accountName: 'NGUYEN TIEN DAT',
        accentClass: 'text-blue-600'
    }
};

let topupPollTimer = null;
let topupLastSeenTxId = 0;

function showTopupVerifyAlert(message) {
    const box = document.getElementById('topupVerifyAlert');
    const txt = document.getElementById('topupVerifyAlertText');
    if (box && txt) {
        txt.textContent = message;
        box.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }
}

function hideTopupVerifyAlert() {
    const box = document.getElementById('topupVerifyAlert');
    if (box) box.classList.add('hidden');
}

function openTopupModal() {
    hideTopupVerifyAlert();
    openModal('topupModal');
    updateTopupVietQR();
    startTopupPolling();
}

function startTopupPolling() {
    stopTopupPolling();
    const maxId = (state.transactions || []).reduce((max, t) => Math.max(max, Number(t.id || 0)), 0);
    topupLastSeenTxId = maxId;

    topupPollTimer = setInterval(async () => {
        const modalEl = document.getElementById('topupModal');
        if (!modalEl || modalEl.classList.contains('hidden')) {
            stopTopupPolling();
            return;
        }
        try {
            await fetchWalletOverview();
            const expectedAmount = Number(document.getElementById('topupAmountInput')?.value || 0);
            const newTopup = (state.transactions || []).find(
                t => Number(t.id || 0) > topupLastSeenTxId &&
                     t.type === 'TOPUP' &&
                     String(t.tx_code || '').startsWith('SEPAY-')
            );
            if (newTopup) {
                if (Number(newTopup.amount || 0) < expectedAmount) {
                    showTopupVerifyAlert(
                        `Đã nhận ${formatVND(newTopup.amount)} nhưng chưa đủ ${formatVND(expectedAmount)}. Vui lòng chuyển thêm phần còn thiếu.`
                    );
                    return;
                }
                hideTopupVerifyAlert();
                stopTopupPolling();
                closeModal('topupModal');
                showReceiptModal({
                    title: 'Nhận Tiền Tự Động Thành Công!',
                    amount: `+${formatVND(newTopup.amount)}`,
                    subtitle: 'Hệ thống đã tự động xác nhận & cộng tiền từ SePay Webhook',
                    rows: [
                        ['Mã giao dịch', newTopup.tx_code],
                        ['Nội dung', newTopup.description],
                        ['Số dư trước GD', formatVND(newTopup.balance_before)],
                        ['Số dư sau GD', formatVND(newTopup.balance_after)],
                        ['Thời gian', newTopup.created_at]
                    ]
                });
                showToast(`Đã tự động cộng +${formatVND(newTopup.amount)} vào Ví TAPay!`, 'success');
            }
        } catch (e) {
            // Bỏ qua lỗi mạng tạm thời khi polling
        }
    }, 3000);
}

function stopTopupPolling() {
    if (topupPollTimer) {
        clearInterval(topupPollTimer);
        topupPollTimer = null;
    }
}

function toggleWalletBalanceVisibility() {
    state.hideWalletBalance = !state.hideWalletBalance;
    const el = document.getElementById('walletTabBalance');
    const icon = document.getElementById('walletEyeIcon');
    if (el && state.wallet) {
        el.textContent = state.hideWalletBalance ? '•••••••• ₫' : formatVND(state.wallet.balance);
    }
    if (icon) {
        icon.setAttribute('data-lucide', state.hideWalletBalance ? 'eye-off' : 'eye');
        if (window.lucide) lucide.createIcons();
    }
}

function openTopupWithBank(sourceValue) {
    const sel = document.getElementById('topupSourceSelect');
    if (sel) sel.value = sourceValue;
    openTopupModal();
}

function copySepayWebhookUrl() {
    const url = document.getElementById('sepayWebhookUrlText')?.textContent.trim();
    if (url) copyTextToClipboard(url, 'URL Webhook SePay');
}

async function simulateSepayWebhookTest() {
    const walletNum = (state.wallet?.wallet_number || '990088880001').replace(/\s+/g, '');
    const randId = Math.floor(100000 + Math.random() * 900000);
    try {
        const res = await fetch('api/webhook_sepay.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: randId,
                gateway: 'MBBank',
                transactionDate: new Date().toISOString().slice(0, 19).replace('T', ' '),
                accountNumber: '0382548328',
                content: `NAP TAPAY ${walletNum}`,
                transferType: 'in',
                transferAmount: 10000,
                referenceCode: `FT${randId}`
            })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Test Webhook thất bại', 'error');
            return;
        }
        await fetchWalletOverview();
        showReceiptModal({
            title: 'Test SePay Webhook Thành Công!',
            amount: `+${formatVND(data.amount)}`,
            subtitle: 'Tiền đã được tự động cộng vào Ví TAPay qua Webhook',
            rows: [
                ['Mã giao dịch SePay', data.tx_code],
                ['Ví nhận', data.wallet_number],
                ['Số dư sau GD', formatVND(data.balance_after)],
                ['Nguồn', 'SePay Webhook (MBBank 0382548328)']
            ]
        });
        showToast('Đã tự động cộng +10.000đ qua SePay Webhook!', 'success');
    } catch (e) {
        showToast('Lỗi kết nối khi test SePay Webhook', 'error');
    }
}

function setTopupAmount(amt) {
    const inp = document.getElementById('topupAmountInput');
    if (inp) {
        inp.value = amt;
        updateTopupVietQR();
    }
}

function updateTopupVietQR() {
    const amt = Number(document.getElementById('topupAmountInput')?.value || 1000000);
    const source = document.getElementById('topupSourceSelect')?.value || 'VietQR - Techcombank (TCB)';
    const bankCfg = TOPUP_BANK_CONFIG[source] || TOPUP_BANK_CONFIG['VietQR - Techcombank (TCB)'];

    const walletNum = (state.wallet?.wallet_number || '990088880001').replace(/\s+/g, '');
    const customNote = document.getElementById('topupNoteInput')?.value.trim();
    const refText = customNote || `NAP TAPAY ${walletNum}`;

    const refEl = document.getElementById('topupTransferRef');
    if (refEl) refEl.textContent = refText;

    const bankBadge = document.getElementById('topupBankBadge');
    if (bankBadge) {
        bankBadge.textContent = bankCfg.bankLabel;
        bankBadge.className = `${bankCfg.accentClass} font-extrabold`;
    }
    const accNameEl = document.getElementById('topupAccountNameDisplay');
    if (accNameEl) accNameEl.textContent = bankCfg.accountName;

    const accNumEl = document.getElementById('topupAccountNumberDisplay');
    if (accNumEl) accNumEl.textContent = bankCfg.accountDisplay;

    const qrHolderEl = document.getElementById('topupQrHolderTitle');
    if (qrHolderEl) qrHolderEl.textContent = bankCfg.accountName;

    const qrAccEl = document.getElementById('topupQrAccTitle');
    if (qrAccEl) qrAccEl.textContent = bankCfg.accountDisplay;

    const qrBankHeader = document.getElementById('topupQrBankHeader');
    if (qrBankHeader) {
        qrBankHeader.innerHTML = `<span class="${bankCfg.accentClass}">${bankCfg.bankLabel}</span><span class="w-2.5 h-2.5 rotate-45 bg-rose-600 inline-block"></span>`;
    }

    const qrAmountBadge = document.getElementById('topupQrAmountBadge');
    if (qrAmountBadge) qrAmountBadge.textContent = formatVND(amt);

    const qrImg = document.getElementById('topupVietQrImg');
    if (qrImg) {
        qrImg.src = `https://img.vietqr.io/image/${bankCfg.bankId}-${bankCfg.accountNumber}-compact2.png?amount=${amt}&addInfo=${encodeURIComponent(refText)}&accountName=${encodeURIComponent(bankCfg.accountName)}`;
    }
}

async function copyTextToClipboard(text, label) {
    try {
        await navigator.clipboard.writeText(text);
        showToast(`Đã sao chép ${label}: ${text}`, 'success');
    } catch (e) {
        showToast(`Thông tin ${label}: ${text}`, 'info');
    }
}

function copyTopupAccount() {
    const source = document.getElementById('topupSourceSelect')?.value || 'VietQR - Techcombank (TCB)';
    const bankCfg = TOPUP_BANK_CONFIG[source] || TOPUP_BANK_CONFIG['VietQR - Techcombank (TCB)'];
    copyTextToClipboard(bankCfg.accountNumber, 'Số tài khoản');
}

function copyTopupRef() {
    const refText = document.getElementById('topupTransferRef')?.textContent || 'NAP TAPAY';
    copyTextToClipboard(refText, 'Nội dung CK');
}

async function handleTopupSubmit(e) {
    e.preventDefault();
    hideTopupVerifyAlert();

    const amount = Number(document.getElementById('topupAmountInput')?.value || 0);
    const source = document.getElementById('topupSourceSelect')?.value || 'VietQR - MB Bank (SePay)';
    const submitBtn = document.getElementById('topupSubmitBtn');
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Đang kiểm tra giao dịch...`;
        if (window.lucide) lucide.createIcons();
    }

    try {
        const res = await fetch('api/wallet.php?action=verify_topup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: state.currentUserId,
                amount,
                source,
                since_tx_id: topupLastSeenTxId
            })
        });
        const data = await res.json();
        if (!data.success) {
            const errMsg = data.message || 'Chưa nhận được giao dịch chuyển khoản!';
            showTopupVerifyAlert(errMsg);
            showToast(errMsg, 'error');
            return;
        }

        stopTopupPolling();
        closeModal('topupModal');
        await fetchWalletOverview();
        showReceiptModal({
            title: 'Xác Nhận Chuyển Khoản Thành Công!',
            amount: `+${formatVND(data.receipt.amount)}`,
            subtitle: 'Đã đối soát thành công giao dịch từ ngân hàng (SePay)',
            rows: [
                ['Mã giao dịch', data.receipt.tx_code],
                ['Nguồn tiền', data.receipt.source],
                ['Số dư trước GD', formatVND(data.receipt.balance_before)],
                ['Số dư sau GD', formatVND(data.receipt.balance_after)],
                ['Thời gian', data.receipt.created_at]
            ]
        });
        showToast(data.message, 'success');
    } catch (err) {
        const errMsg = 'Lỗi kết nối khi kiểm tra giao dịch ngân hàng';
        showTopupVerifyAlert(errMsg);
        showToast(errMsg, 'error');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
            if (window.lucide) lucide.createIcons();
        }
    }
}

function switchTransferMode(mode) {
    const p2pForm = document.getElementById('transferForm-p2p');
    const bankForm = document.getElementById('transferForm-bank');
    const p2pBtn = document.getElementById('transferTabBtn-p2p');
    const bankBtn = document.getElementById('transferTabBtn-bank');
    const iconBox = document.getElementById('transferModalIcon');

    if (mode === 'bank') {
        p2pForm?.classList.add('hidden');
        bankForm?.classList.remove('hidden');
        if (p2pBtn) p2pBtn.className = 'py-2 px-3 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900';
        if (bankBtn) bankBtn.className = 'py-2 px-3 rounded-lg text-xs font-extrabold transition flex items-center justify-center gap-1.5 bg-white text-emerald-600 shadow-sm';
        if (iconBox) iconBox.className = 'w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center';
    } else {
        bankForm?.classList.add('hidden');
        p2pForm?.classList.remove('hidden');
        if (p2pBtn) p2pBtn.className = 'py-2 px-3 rounded-lg text-xs font-extrabold transition flex items-center justify-center gap-1.5 bg-white text-[#ee4d2d] shadow-sm';
        if (bankBtn) bankBtn.className = 'py-2 px-3 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900';
        if (iconBox) iconBox.className = 'w-9 h-9 rounded-xl bg-[#ee4d2d] text-white flex items-center justify-center';
    }
    if (window.lucide) lucide.createIcons();
}

function openTransferModal() {
    openModal('transferModal');
    switchTransferMode('p2p');
}

function openBankTransferModal() {
    openModal('transferModal');
    switchTransferMode('bank');
}

function selectQuickBankRecipient(bankCode, accountNumber, accountName) {
    const bankSelect = document.getElementById('bankTransferBankSelect');
    if (bankSelect) {
        const option = Array.from(bankSelect.options).find(o => o.value.startsWith(`${bankCode}|`));
        if (option) bankSelect.value = option.value;
    }
    const accInput = document.getElementById('bankTransferAccountInput');
    const nameInput = document.getElementById('bankTransferNameInput');
    if (accInput) accInput.value = accountNumber;
    if (nameInput) nameInput.value = accountName;
}

function setBankTransferAmount(amt) {
    const inp = document.getElementById('bankTransferAmountInput');
    if (inp) inp.value = amt;
}

async function handleBankTransferSubmit(e) {
    e.preventDefault();
    const bankVal = document.getElementById('bankTransferBankSelect')?.value || 'TCB|Techcombank';
    const [bankCode, bankName] = bankVal.split('|');
    const accountNumber = document.getElementById('bankTransferAccountInput')?.value.trim();
    const accountName = document.getElementById('bankTransferNameInput')?.value.trim();
    const amount = Number(document.getElementById('bankTransferAmountInput')?.value || 0);
    const pin = document.getElementById('bankTransferPinInput')?.value.trim();
    const message = document.getElementById('bankTransferMessageInput')?.value.trim();

    try {
        const res = await fetch('api/wallet.php?action=bank_transfer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: state.currentUserId,
                bank_code: bankCode,
                bank_name: bankName,
                account_number: accountNumber,
                account_name: accountName,
                amount,
                pin,
                message
            })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Chuyển khoản ngân hàng thất bại', 'error');
            return;
        }

        closeModal('transferModal');
        await fetchWalletOverview();
        showReceiptModal({
            title: 'Chuyển Khoản Ngân Hàng 24/7 Thành Công!',
            amount: `-${formatVND(data.receipt.amount)}`,
            subtitle: `${data.receipt.account_name} • ${data.receipt.bank_name}`,
            rows: [
                ['Mã giao dịch', data.receipt.tx_code],
                ['Tài khoản nguồn', `${data.receipt.sender_name} (${data.receipt.sender_wallet})`],
                ['Ngân hàng nhận', `${data.receipt.bank_name} (${data.receipt.bank_code})`],
                ['Số tài khoản nhận', data.receipt.account_number],
                ['Chủ tài khoản', data.receipt.account_name],
                ['Nội dung', data.receipt.message],
                ['Số dư còn lại', formatVND(data.receipt.balance_after)],
                ['Thời gian', data.receipt.created_at]
            ]
        });
        showToast(data.message, 'success');
    } catch (err) {
        showToast('Lỗi kết nối khi chuyển khoản ngân hàng', 'error');
    }
}

function openTransferToContact(phone, name) {
    openModal('transferModal');
    selectQuickRecipient(phone, name, '');
}

function selectQuickRecipient(phone, name, walletNum) {
    const inp = document.getElementById('transferRecipientInput');
    if (inp) inp.value = phone;
    const box = document.getElementById('recipientLookupResult');
    const nameEl = document.getElementById('recipientLookupName');
    if (box && nameEl) {
        box.classList.remove('hidden');
        nameEl.innerHTML = `Người nhận: <strong>${name}</strong> ${walletNum ? `(${walletNum})` : ''}`;
    }
}

async function lookupRecipient() {
    const q = document.getElementById('transferRecipientInput')?.value.trim();
    const box = document.getElementById('recipientLookupResult');
    const nameEl = document.getElementById('recipientLookupName');
    if (!q || !box || !nameEl) return;

    try {
        const res = await fetch(`api/wallet.php?action=lookup_recipient&user_id=${state.currentUserId}&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        box.classList.remove('hidden');
        if (data.success && data.recipient) {
            box.className = 'mt-2 p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2';
            nameEl.innerHTML = `Chủ ví: <strong>${data.recipient.full_name}</strong> • Số ví: <strong>${data.recipient.wallet_number}</strong>`;
        } else {
            box.className = 'mt-2 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2';
            nameEl.textContent = data.message || 'Không tìm thấy người nhận';
        }
    } catch (e) {
        console.error(e);
    }
}

async function handleTransferSubmit(e) {
    e.preventDefault();
    const recipient = document.getElementById('transferRecipientInput')?.value.trim();
    const amount = Number(document.getElementById('transferAmountInput')?.value || 0);
    const pin = document.getElementById('transferPinInput')?.value.trim();
    const message = document.getElementById('transferMessageInput')?.value.trim();

    try {
        const res = await fetch('api/wallet.php?action=transfer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: state.currentUserId,
                recipient,
                amount,
                pin,
                message
            })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Chuyển tiền thất bại', 'error');
            return;
        }

        closeModal('transferModal');
        await fetchWalletOverview();
        showReceiptModal({
            title: 'Chuyển Tiền P2P Thành Công!',
            amount: `-${formatVND(data.receipt.amount)}`,
            subtitle: `Người nhận: ${data.receipt.recipient_name}`,
            rows: [
                ['Mã giao dịch', data.receipt.tx_code],
                ['Người gửi', `${data.receipt.sender_name} (${data.receipt.sender_wallet})`],
                ['Người nhận', `${data.receipt.recipient_name} (${data.receipt.recipient_phone})`],
                ['Số ví nhận', data.receipt.recipient_wallet],
                ['Lời nhắn', data.receipt.message],
                ['Số dư còn lại', formatVND(data.receipt.balance_after)],
                ['Thời gian', data.receipt.created_at]
            ]
        });
        showToast(data.message, 'success');
    } catch (err) {
        showToast('Lỗi kết nối khi chuyển khoản', 'error');
    }
}

async function claimCashbackToMainWallet() {
    try {
        const res = await fetch('api/wallet.php?action=claim_cashback', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: state.currentUserId })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message, 'warning');
            return;
        }
        await fetchWalletOverview();
        showToast(data.message, 'success');
    } catch (e) {
        showToast('Không thể rút Cashback lúc này', 'error');
    }
}

// ============================================================================
// 3. E-COMMERCE PRODUCTS, FLASH SALE, SORTING & PRODUCT QUICK VIEW
// ============================================================================
let searchTimer = null;
function debounceSearchProducts() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        state.searchQuery = document.getElementById('productSearchInput')?.value.trim() || '';
        fetchProducts();
    }, 220);
}

function quickFilterKeyword(kw) {
    switchTab('shop');
    const inp = document.getElementById('productSearchInput');
    if (inp) {
        inp.value = kw;
        state.searchQuery = kw;
        state.activeCategory = 'all';
        fetchProducts();
    }
}

function filterCategory(cat) {
    state.activeCategory = cat;
    fetchProducts();
}

function setSortOrder(sortType) {
    state.sortBy = sortType;
    ['popular', 'newest', 'bestseller', 'price_asc', 'price_desc'].forEach(s => {
        const btn = document.getElementById(`sortBtn-${s}`);
        if (!btn) return;
        if (s === sortType) {
            btn.className = 'sort-btn px-3.5 py-1.5 rounded-lg text-xs font-bold bg-[#ee4d2d] text-white';
        } else {
            btn.className = 'sort-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200';
        }
    });
    renderProductsGrid();
}

async function fetchProducts() {
    try {
        const url = `api/shop.php?action=products&category=${encodeURIComponent(state.activeCategory)}&q=${encodeURIComponent(state.searchQuery)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) return;

        state.products = data.products || [];
        state.categories = data.categories || [];
        renderCategoryPills();
        renderFlashSaleSection();
        renderProductsGrid();
    } catch (e) {
        console.error('Error fetching products:', e);
    }
}

function renderCategoryPills() {
    const bar = document.getElementById('categoryFilterBar');
    if (!bar) return;

    const categoryIcons = {
        'all': 'layout-grid',
        'Công nghệ': 'cpu',
        'Thời trang': 'shirt',
        'Âm thanh': 'headphones',
        'Phụ kiện': 'watch',
        'Mỹ phẩm & Hương thơm': 'sparkles',
        'Đồ gia dụng thông minh': 'coffee'
    };

    const allCats = ['all', ...state.categories];
    bar.innerHTML = allCats.map(c => {
        const active = state.activeCategory === c;
        const label = c === 'all' ? 'Tất cả ngành hàng' : c;
        const iconName = categoryIcons[c] || 'tag';
        return `
            <button onclick="filterCategory('${c}')" class="px-4 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-2 border ${
                active
                ? 'bg-[#fff5f1] text-[#ee4d2d] border-[#ee4d2d] shadow-sm'
                : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200'
            }">
                <i data-lucide="${iconName}" class="w-4 h-4 ${active ? 'text-[#ee4d2d]' : 'text-slate-500'}"></i>
                <span>${label}</span>
            </button>
        `;
    }).join('');
}

function renderFlashSaleSection() {
    const fsGrid = document.getElementById('flashSaleGrid');
    if (!fsGrid) return;

    const topProducts = state.products.slice(0, 4);
    if (topProducts.length === 0) {
        fsGrid.innerHTML = '';
        return;
    }

    fsGrid.innerHTML = topProducts.map(p => {
        const disc = calcDiscountPercent(p.price, p.original_price) || 18;
        const sold = Number(p.sold_count || 45);
        const progressPct = Math.min(92, Math.max(35, (sold % 85) + 15));

        return `
            <div onclick="openProductDetailModal(${p.id})" class="cursor-pointer group bg-white rounded-xl border border-orange-100 hover:border-[#ee4d2d] p-3 transition shadow-sm hover:shadow-md flex flex-col justify-between">
                <div>
                    <div class="relative rounded-lg overflow-hidden bg-slate-50 aspect-square mb-2.5">
                        <img src="${p.image_url}" alt="${p.name}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" onerror="this.src='https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800&auto=format&fit=crop&q=80'">
                        <span class="absolute top-2 right-2 discount-badge">-${disc}%</span>
                        <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-[#0068ff] text-white text-[10px] font-bold">
                            Hoàn ${p.cashback_rate}% Ví
                        </span>
                    </div>
                    <h4 class="text-xs sm:text-sm font-semibold text-slate-800 line-clamp-1 group-hover:text-[#ee4d2d]">${p.name}</h4>
                </div>

                <div class="mt-2 space-y-2">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-base sm:text-lg font-black text-[#ee4d2d] font-number">${formatVND(p.price)}</span>
                        <span class="text-[11px] text-slate-400 line-through font-number hidden sm:inline">${formatVND(p.original_price)}</span>
                    </div>
                    <div class="flash-progress">
                        <div class="flash-progress-bar" style="width: ${progressPct}%"></div>
                        <div class="flash-progress-text">🔥 Đã bán ${sold}</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderProductsGrid() {
    const grid = document.getElementById('productsGrid');
    const countText = document.getElementById('totalProductsCountText');
    if (!grid) return;

    let sorted = [...state.products];
    if (state.sortBy === 'newest') {
        sorted.sort((a, b) => Number(b.id) - Number(a.id));
    } else if (state.sortBy === 'bestseller') {
        sorted.sort((a, b) => Number(b.sold_count) - Number(a.sold_count));
    } else if (state.sortBy === 'price_asc') {
        sorted.sort((a, b) => Number(a.price) - Number(b.price));
    } else if (state.sortBy === 'price_desc') {
        sorted.sort((a, b) => Number(b.price) - Number(a.price));
    }

    if (countText) {
        countText.textContent = `Hiển thị ${sorted.length} sản phẩm`;
    }

    if (sorted.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-slate-200 space-y-3">
                <p class="text-slate-500 text-sm">Không tìm thấy sản phẩm nào phù hợp với từ khóa.</p>
                <button onclick="openCreateProductModal()" class="px-4 py-2 rounded-lg bg-[#ee4d2d] text-white font-bold text-xs">+ Đăng bán sản phẩm mới ngay</button>
            </div>
        `;
        return;
    }

    grid.innerHTML = sorted.map(p => {
        const disc = calcDiscountPercent(p.price, p.original_price);
        const cashbackAmt = Math.round(Number(p.price) * (Number(p.cashback_rate) / 100));

        return `
            <div class="product-card group relative">
                <!-- Clickable Product Image & Info -->
                <div onclick="openProductDetailModal(${p.id})" class="cursor-pointer">
                    <div class="product-img-box">
                        <img src="${p.image_url}" alt="${p.name}" onerror="this.src='https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800&auto=format&fit=crop&q=80'">
                        <!-- Top Left Mall Badge -->
                        <div class="absolute top-2.5 left-2.5 flex flex-col gap-1">
                            <span class="mall-badge shadow-sm">Mall</span>
                        </div>
                        <!-- Top Right Discount Badge -->
                        ${disc > 0 ? `<span class="absolute top-2.5 right-2.5 discount-badge shadow-sm">-${disc}%</span>` : ''}

                        <!-- Delete Product Button (Admin Quick Action) -->
                        <button onclick="event.stopPropagation(); deleteProductItem(${p.id}, '${p.name.replace(/'/g, "\\'")}')" title="Xóa sản phẩm" class="opacity-0 group-hover:opacity-100 transition absolute bottom-2 right-2 w-7 h-7 rounded-lg bg-black/60 hover:bg-rose-600 text-white flex items-center justify-center">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>

                    <div class="p-3.5 pb-2 space-y-1.5">
                        <!-- Category & Stock -->
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>${p.category}</span>
                            <span>Kho: ${p.stock}</span>
                        </div>

                        <!-- Product Title -->
                        <h3 class="text-xs sm:text-sm font-semibold text-slate-800 line-clamp-2 min-h-[38px] group-hover:text-[#ee4d2d] transition leading-snug">
                            ${p.name}
                        </h3>

                        <!-- Cashback & Promo Voucher Tags (Chuẩn Shopee/Lazada) -->
                        <div class="flex flex-wrap items-center gap-1 pt-0.5">
                            <span class="px-1.5 py-0.5 text-[10px] font-bold bg-blue-50 text-[#0068ff] border border-blue-200 rounded">
                                Hoàn ${p.cashback_rate}% (+${formatVND(cashbackAmt)})
                            </span>
                            <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-orange-50 text-[#ee4d2d] border border-orange-200 rounded">
                                COD / VietQR
                            </span>
                        </div>

                        <!-- Price Row -->
                        <div class="pt-1 flex items-baseline gap-1.5 flex-wrap">
                            <span class="text-base sm:text-lg font-extrabold text-[#ee4d2d] font-number">${formatVND(p.price)}</span>
                            ${Number(p.original_price) > Number(p.price) ? `<span class="text-[11px] text-slate-400 line-through font-number">${formatVND(p.original_price)}</span>` : ''}
                        </div>

                        <!-- Star Rating & Sold Count -->
                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-0.5">
                            <div class="flex items-center gap-0.5 text-amber-400 font-bold">
                                <span>★★★★★</span>
                                <span class="text-slate-600 ml-0.5">${p.rating || '4.9'}</span>
                            </div>
                            <span>Đã bán ${p.sold_count || 120}</span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Buttons -->
                <div class="p-3.5 pt-2 grid grid-cols-2 gap-2">
                    <button onclick="addToCart(${p.id}, false)" class="py-2 px-2 rounded-lg bg-[#fff5f1] hover:bg-orange-100 text-[#ee4d2d] border border-[#ee4d2d]/40 font-bold text-xs transition flex items-center justify-center gap-1">
                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                        <span>Thêm Giỏ</span>
                    </button>
                    <button onclick="addToCart(${p.id}, true)" class="py-2 px-2 rounded-lg bg-[#ee4d2d] hover:bg-[#d73211] text-white font-bold text-xs transition flex items-center justify-center gap-1 shadow-sm">
                        <span>Mua Ngay</span>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    if (window.lucide) lucide.createIcons();
}

// Open Product Detail Modal (Quick View giống trang chi tiết sản phẩm Shopee/Tiki)
function openProductDetailModal(productId) {
    const p = state.products.find(item => Number(item.id) === Number(productId));
    if (!p) return;

    const disc = calcDiscountPercent(p.price, p.original_price);
    const cashbackAmt = Math.round(Number(p.price) * (Number(p.cashback_rate) / 100));

    const skuEl = document.getElementById('detailSkuText');
    if (skuEl) skuEl.textContent = `Mã SP: ${p.sku || 'TA-' + p.id}`;

    const body = document.getElementById('productDetailBody');
    if (body) {
        body.innerHTML = `
            <div class="rounded-xl overflow-hidden bg-slate-100 border border-slate-200 aspect-square">
                <img src="${p.image_url}" alt="${p.name}" class="w-full h-full object-cover">
            </div>
            <div class="flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="mall-badge">TAstore Mall</span>
                        <span class="text-xs font-semibold text-slate-500">Danh mục: ${p.category}</span>
                    </div>
                    <h2 class="text-lg sm:text-xl font-extrabold text-slate-800 leading-snug">${p.name}</h2>

                    <div class="flex items-center gap-4 text-xs text-slate-500">
                        <span class="text-amber-500 font-bold">★★★★★ ${p.rating || '4.9'}/5.0</span>
                        <span>|</span>
                        <span>Đã bán <strong class="text-slate-800">${p.sold_count || 120}</strong></span>
                        <span>|</span>
                        <span>Kho còn <strong class="text-slate-800">${p.stock}</strong></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-baseline gap-3">
                        <span class="text-2xl sm:text-3xl font-black text-[#ee4d2d] font-number">${formatVND(p.price)}</span>
                        ${Number(p.original_price) > Number(p.price) ? `<span class="text-sm text-slate-400 line-through font-number">${formatVND(p.original_price)}</span>` : ''}
                        ${disc > 0 ? `<span class="discount-badge">GIẢM ${disc}%</span>` : ''}
                    </div>

                    <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-xs text-slate-700 space-y-1">
                        <div class="font-bold text-[#0068ff] flex items-center gap-1.5">
                            <i data-lucide="wallet" class="w-4 h-4"></i>
                            Ưu đãi Ví điện tử TAPay:
                        </div>
                        <div>• Hoàn ngay <strong class="text-emerald-700">${formatVND(cashbackAmt)} (${p.cashback_rate}%)</strong> vào Ví khi thanh toán qua TAPay.</div>
                        <div>• Đổi trả & Hoàn tiền 100% về Ví trong 3 giây nếu không ưng ý.</div>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">${p.description || ''}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-200">
                    <button onclick="closeModal('productDetailModal'); addToCart(${p.id}, false);" class="py-3 px-4 rounded-xl bg-[#fff5f1] hover:bg-orange-100 text-[#ee4d2d] border border-[#ee4d2d] font-extrabold text-xs sm:text-sm transition flex items-center justify-center gap-2">
                        <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                        Thêm Vào Giỏ Hàng
                    </button>
                    <button onclick="closeModal('productDetailModal'); addToCart(${p.id}, true);" class="py-3 px-4 rounded-xl bg-[#ee4d2d] hover:bg-[#d73211] text-white font-extrabold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-md">
                        Mua Ngay
                    </button>
                </div>
            </div>
        `;
    }

    openModal('productDetailModal');
}

function openCreateProductModal() {
    openModal('createProductModal');
}

function setSampleProductPreset(name, category, price, originalPrice, cashback, imgUrl) {
    document.getElementById('newProdName').value = name;
    document.getElementById('newProdCategory').value = category;
    document.getElementById('newProdPrice').value = price;
    document.getElementById('newProdOriginalPrice').value = originalPrice;
    document.getElementById('newProdCashback').value = cashback;
    document.getElementById('newProdImage').value = imgUrl;
    document.getElementById('newProdBadge').value = `Hoàn ${cashback}%`;
}

async function handleCreateProduct(e) {
    e.preventDefault();
    const payload = {
        name: document.getElementById('newProdName')?.value.trim(),
        category: document.getElementById('newProdCategory')?.value,
        price: Number(document.getElementById('newProdPrice')?.value || 0),
        original_price: Number(document.getElementById('newProdOriginalPrice')?.value || 0),
        cashback_rate: Number(document.getElementById('newProdCashback')?.value || 5),
        stock: Number(document.getElementById('newProdStock')?.value || 50),
        badge: document.getElementById('newProdBadge')?.value.trim(),
        image_url: document.getElementById('newProdImage')?.value.trim(),
        description: document.getElementById('newProdDesc')?.value.trim()
    };

    try {
        const res = await fetch('api/shop.php?action=create_product', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Không thể tạo sản phẩm', 'error');
            return;
        }

        closeModal('createProductModal');
        state.activeCategory = 'all';
        await fetchProducts();
        showToast(data.message, 'success');
        document.getElementById('newProdName').value = '';
    } catch (err) {
        showToast('Lỗi khi tạo sản phẩm mới', 'error');
    }
}

async function deleteProductItem(productId, productName) {
    if (!confirm(`Bạn có chắc muốn xóa sản phẩm "${productName}" khỏi cửa hàng?`)) return;
    try {
        const res = await fetch('api/shop.php?action=delete_product', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'info');
            fetchProducts();
        }
    } catch (e) {
        showToast('Không thể xóa sản phẩm', 'error');
    }
}

// ============================================================================
// 4. SHOPPING CART & MULTI-METHOD CHECKOUT (WALLET, COD, BANK QR, PAY LATER)
// ============================================================================
function toggleCartDrawer(open) {
    const drawer = document.getElementById('cartDrawer');
    const backdrop = document.getElementById('cartDrawerBackdrop');
    if (!drawer || !backdrop) return;

    if (open) {
        backdrop.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        renderCart();
    } else {
        backdrop.classList.add('hidden');
        drawer.classList.add('translate-x-full');
    }
}

function addToCart(productId, openDrawerImmediately = false) {
    const product = state.products.find(p => Number(p.id) === Number(productId));
    if (!product) return;

    const existing = state.cart.find(item => Number(item.id) === Number(productId));
    if (existing) {
        existing.quantity += 1;
    } else {
        state.cart.push({
            id: Number(product.id),
            name: product.name,
            price: Number(product.price),
            cashback_rate: Number(product.cashback_rate || 5),
            image_url: product.image_url,
            quantity: 1
        });
    }

    saveCart();
    renderCart();

    if (openDrawerImmediately) {
        toggleCartDrawer(true);
    } else {
        showToast(`Đã thêm "${product.name}" vào giỏ hàng!`, 'success');
    }
}

function updateCartQty(productId, delta) {
    const idx = state.cart.findIndex(i => Number(i.id) === Number(productId));
    if (idx === -1) return;

    state.cart[idx].quantity += delta;
    if (state.cart[idx].quantity <= 0) {
        state.cart.splice(idx, 1);
    }
    saveCart();
    renderCart();
}

function saveCart() {
    localStorage.setItem('aurashop_cart', JSON.stringify(state.cart));
}

function setVoucher(code) {
    const inp = document.getElementById('checkoutVoucherInput');
    if (inp) inp.value = code;
    applyVoucherCode();
}

function applyVoucherCode() {
    const code = (document.getElementById('checkoutVoucherInput')?.value || '').trim().toUpperCase();
    state.voucherCode = code;
    renderCart();
    if (code) {
        showToast(`Đã áp dụng mã giảm giá ${code}`, 'info');
    }
}

function selectPaymentMethod(method) {
    state.selectedPaymentMethod = method;

    ['WALLET', 'VIRTUAL_COIN', 'COD', 'BANK_TRANSFER', 'PAY_LATER'].forEach(m => {
        const card = document.getElementById(`payCard-${m}`);
        if (!card) return;
        const dot = card.querySelector('.pay-radio-dot');
        if (m === method) {
            card.classList.add('active');
            if (dot) dot.classList.remove('hidden');
        } else {
            card.classList.remove('active');
            if (dot) dot.classList.add('hidden');
        }
    });

    const pinBox = document.getElementById('checkoutWalletPinBox');
    const qrBox = document.getElementById('checkoutBankQrBox');
    const payLaterBox = document.getElementById('checkoutPayLaterNotice');
    const submitBtnText = document.getElementById('submitCheckoutBtnText');

    if (pinBox) pinBox.classList.toggle('hidden', method !== 'WALLET');
    if (qrBox) qrBox.classList.toggle('hidden', method !== 'BANK_TRANSFER');
    if (payLaterBox) payLaterBox.classList.toggle('hidden', method !== 'PAY_LATER');

    if (method === 'PAY_LATER') {
        showToast('Phương thức "Mua trước trả sau (Pay Later)" hiện chưa cập nhật!', 'warning');
        if (submitBtnText) submitBtnText.textContent = 'Trả Sau Hiện Chưa Cập Nhật';
    } else if (method === 'VIRTUAL_COIN') {
        if (submitBtnText) submitBtnText.textContent = 'Thanh Toán Bằng Tiền Ảo';
    } else if (method === 'COD') {
        if (submitBtnText) submitBtnText.textContent = 'Đặt Hàng COD Ngay';
    } else if (method === 'BANK_TRANSFER') {
        if (submitBtnText) submitBtnText.textContent = 'Xác Nhận Đã Chuyển Khoản QR';
    } else {
        if (submitBtnText) submitBtnText.textContent = 'Thanh Toán Bằng Ví TAPay';
    }

    renderCart();
}

function renderCart() {
    const totalCount = state.cart.reduce((sum, i) => sum + i.quantity, 0);
    const badge = document.getElementById('cartCountBadge');
    if (badge) badge.textContent = totalCount;

    const container = document.getElementById('cartItemsContainer');
    const formSection = document.getElementById('checkoutFormSection');
    if (!container) return;

    if (state.cart.length === 0) {
        container.innerHTML = `
            <div class="py-12 text-center space-y-3">
                <div class="w-14 h-14 rounded-full bg-orange-50 text-[#ee4d2d] flex items-center justify-center mx-auto">
                    <i data-lucide="shopping-cart" class="w-7 h-7"></i>
                </div>
                <div class="text-sm font-bold text-slate-700">Giỏ hàng của bạn đang trống</div>
                <p class="text-xs text-slate-400">Hãy chọn sản phẩm yêu thích để mua sắm và nhận hoàn tiền vào Ví nhé!</p>
            </div>
        `;
        if (formSection) formSection.classList.add('hidden');
        document.getElementById('cartSubtotalText').textContent = '0 ₫';
        document.getElementById('cartDiscountText').textContent = '-0 ₫';
        document.getElementById('cartCashbackText').textContent = '+0 ₫';
        document.getElementById('cartFinalTotalText').textContent = '0 ₫';
        if (window.lucide) lucide.createIcons();
        return;
    }

    if (formSection) formSection.classList.remove('hidden');

    let subtotal = 0;
    let cashback = 0;

    container.innerHTML = state.cart.map(item => {
        const lineTotal = item.price * item.quantity;
        subtotal += lineTotal;
        cashback += Math.round(lineTotal * (item.cashback_rate / 100));

        return `
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                <img src="${item.image_url}" class="w-14 h-14 rounded-lg object-cover border border-slate-200 shrink-0" alt="">
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-800 truncate">${item.name}</div>
                    <div class="text-xs text-[#ee4d2d] font-number font-extrabold mt-0.5">${formatVND(item.price)}</div>
                    <div class="text-[10px] text-[#0068ff] font-semibold">Hoàn ${item.cashback_rate}% Ví TAPay</div>
                </div>
                <div class="flex items-center gap-1 bg-white rounded-lg p-1 border border-slate-300">
                    <button onclick="updateCartQty(${item.id}, -1)" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">-</button>
                    <span class="w-6 text-center text-xs font-bold font-number text-slate-800">${item.quantity}</span>
                    <button onclick="updateCartQty(${item.id}, 1)" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">+</button>
                </div>
            </div>
        `;
    }).join('');

    let discount = 0;
    if (state.voucherCode === 'TA50') discount = Math.min(50000, subtotal * 0.1);
    else if (state.voucherCode === 'TA200') discount = subtotal >= 2000000 ? 200000 : 50000;
    else if (state.voucherCode === 'VIP500') discount = subtotal >= 5000000 ? 500000 : 100000;

    const finalTotal = Math.max(0, subtotal - discount);
    const activeCashback = state.selectedPaymentMethod === 'WALLET' ? cashback : 0;

    document.getElementById('cartSubtotalText').textContent = formatVND(subtotal);
    document.getElementById('cartDiscountText').textContent = `-${formatVND(discount)}`;
    document.getElementById('cartCashbackText').textContent = state.selectedPaymentMethod === 'WALLET'
        ? `+${formatVND(activeCashback)}`
        : '0 ₫ (Chỉ áp dụng Ví TAPay)';
    document.getElementById('cartFinalTotalText').textContent = formatVND(finalTotal);

    const qrImg = document.getElementById('checkoutVietQrImg');
    if (qrImg) {
        qrImg.src = `https://img.vietqr.io/image/VCB-990088880001-compact2.png?amount=${finalTotal}&addInfo=${encodeURIComponent('THANH TOAN DON HANG TASTORE')}&accountName=${encodeURIComponent('TASTORE VIET NAM')}`;
    }

    if (window.lucide) lucide.createIcons();
}

async function submitCheckoutOrder() {
    if (state.cart.length === 0) {
        showToast('Giỏ hàng của bạn đang trống!', 'warning');
        return;
    }

    if (state.selectedPaymentMethod === 'PAY_LATER') {
        showToast('Phương thức "Mua trước trả sau (Pay Later)" hiện chưa cập nhật! Vui lòng chọn Ví TAPay, COD hoặc Chuyển khoản.', 'warning');
        return;
    }

    const recipientName = document.getElementById('shipName')?.value.trim();
    const recipientPhone = document.getElementById('shipPhone')?.value.trim();
    const shippingAddress = document.getElementById('shipAddress')?.value.trim();
    const pin = document.getElementById('checkoutPinInput')?.value.trim();

    if (!recipientName || !recipientPhone || !shippingAddress) {
        showToast('Vui lòng nhập đầy đủ Họ tên, SĐT và Địa chỉ nhận hàng!', 'warning');
        return;
    }

    try {
        const res = await fetch('api/shop.php?action=checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: state.currentUserId,
                items: state.cart.map(i => ({ product_id: i.id, quantity: i.quantity })),
                payment_method: state.selectedPaymentMethod,
                recipient_name: recipientName,
                recipient_phone: recipientPhone,
                shipping_address: shippingAddress,
                voucher_code: state.voucherCode,
                pin
            })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Thanh toán thất bại', 'error');
            return;
        }

        state.cart = [];
        state.voucherCode = '';
        saveCart();
        renderCart();
        toggleCartDrawer(false);

        await Promise.all([
            fetchWalletOverview(),
            fetchProducts(),
            fetchOrders()
        ]);

        const ord = data.order;
        showReceiptModal({
            title: 'Đặt Hàng Thành Công!',
            amount: formatVND(ord.final_amount),
            subtitle: `Phương thức: ${ord.payment_method_label}`,
            rows: [
                ['Mã đơn hàng', `#${ord.order_code}`],
                ['Phương thức TT', ord.payment_method_label],
                ['Người nhận', `${ord.recipient_name} (${ord.recipient_phone})`],
                ['Tiền thưởng Cashback', ord.cashback_earned > 0 ? `+${formatVND(ord.cashback_earned)}` : '0 ₫'],
                ['Số dư Ví hiện tại', formatVND(ord.balance_after)],
                ['Thời gian', ord.created_at]
            ]
        });
        showToast(data.message, 'success');
    } catch (err) {
        showToast('Lỗi kết nối khi thanh toán đơn hàng', 'error');
    }
}

// ============================================================================
// 5. ORDERS & INSTANT WALLET REFUND
// ============================================================================
async function fetchOrders() {
    try {
        const res = await fetch(`api/shop.php?action=orders&user_id=${state.currentUserId}`);
        const data = await res.json();
        if (!data.success) return;

        state.orders = data.orders || [];
        const badge = document.getElementById('ordersCountBadge');
        if (badge) badge.textContent = state.orders.length;
        renderOrdersList();
    } catch (e) {
        console.error('Error fetching orders:', e);
    }
}

function renderOrdersList() {
    const container = document.getElementById('ordersListContainer');
    if (!container) return;

    if (state.orders.length === 0) {
        container.innerHTML = `
            <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 space-y-3">
                <div class="w-14 h-14 rounded-full bg-orange-50 text-[#ee4d2d] flex items-center justify-center mx-auto">
                    <i data-lucide="package" class="w-7 h-7"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">Bạn chưa có đơn hàng nào</h3>
                <p class="text-xs text-slate-500">Hãy đặt thử 1 đơn hàng để trải nghiệm tính năng Thanh toán Ví & Hoàn tiền 100% về Ví ngay lập tức!</p>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
        return;
    }

    const methodBadges = {
        WALLET: 'Ví Điện Tử TAPay',
        COD: 'Thanh toán khi nhận hàng (COD)',
        BANK_TRANSFER: 'Chuyển khoản VietQR'
    };

    container.innerHTML = state.orders.map(o => {
        const isRefunded = o.order_status === 'REFUNDED' || o.payment_status === 'REFUNDED';
        return `
            <div class="bg-white rounded-2xl p-5 border ${isRefunded ? 'border-blue-200' : 'border-slate-200'} shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="mall-badge">Mall</span>
                        <span class="font-number font-extrabold text-sm text-slate-800">#${o.order_code}</span>
                        <span class="text-xs text-slate-400">• ${o.created_at}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                            ${methodBadges[o.payment_method] || o.payment_method}
                        </span>
                    </div>
                    <div>
                        ${isRefunded ? `
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-50 text-[#0068ff] border border-blue-200 text-xs font-bold">
                                <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                                Đã Hoàn Tiền 100% Về Ví TAPay
                            </span>
                        ` : `
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">
                                <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                                Đặt hàng thành công
                            </span>
                        `}
                    </div>
                </div>

                <!-- Order Items -->
                <div class="divide-y divide-slate-100">
                    ${(o.items || []).map(item => `
                        <div class="py-2.5 flex items-center gap-3">
                            <img src="${item.product_image}" class="w-14 h-14 rounded-lg object-cover border border-slate-200" alt="">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs sm:text-sm font-bold text-slate-800 truncate">${item.product_name}</div>
                                <div class="text-xs text-slate-500">Phân loại: Chính hãng • x${item.quantity}</div>
                            </div>
                            <div class="text-xs sm:text-sm font-bold text-[#ee4d2d] font-number">${formatVND(item.subtotal)}</div>
                        </div>
                    `).join('')}
                </div>

                <!-- Order Footer & Refund Button -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-slate-100 bg-slate-50/60 -mx-5 -mb-5 p-5 rounded-b-2xl">
                    <div class="text-xs text-slate-500 space-y-0.5">
                        <div>Người nhận: <strong class="text-slate-700">${o.recipient_name} (${o.recipient_phone})</strong> — ${o.shipping_address}</div>
                        ${isRefunded ? `<div class="text-[#0068ff] font-semibold">Lý do hoàn tiền: ${o.refund_reason} (Hoàn lúc: ${o.refunded_at})</div>` : ''}
                    </div>

                    <div class="flex items-center gap-4 self-end sm:self-center">
                        <div class="text-right">
                            <div class="text-xs text-slate-500">Thành tiền:</div>
                            <div class="text-lg font-black text-[#ee4d2d] font-number">${formatVND(o.final_amount)}</div>
                        </div>

                        ${!isRefunded ? `
                            <button onclick="openRefundModal(${o.id}, '${o.order_code}', ${o.final_amount})" class="px-4 py-2.5 rounded-xl bg-white hover:bg-blue-50 text-[#0068ff] border border-[#0068ff] font-extrabold text-xs transition flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                Trả Hàng / Hoàn Tiền Về Ví
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    if (window.lucide) lucide.createIcons();
}

function openRefundModal(orderId, orderCode, amount) {
    document.getElementById('refundOrderIdInput').value = orderId;
    document.getElementById('refundOrderCodeText').textContent = `#${orderCode}`;
    document.getElementById('refundAmountText').textContent = formatVND(amount);
    openModal('refundModal');
}

async function handleRefundSubmit(e) {
    e.preventDefault();
    const orderId = Number(document.getElementById('refundOrderIdInput')?.value || 0);
    const reason = document.getElementById('refundReasonSelect')?.value;

    try {
        const res = await fetch('api/shop.php?action=refund', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: state.currentUserId,
                order_id: orderId,
                reason
            })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Hoàn tiền thất bại', 'error');
            return;
        }

        closeModal('refundModal');
        await Promise.all([
            fetchWalletOverview(),
            fetchOrders(),
            fetchProducts()
        ]);

        showReceiptModal({
            title: 'Hoàn Tiền Về Ví Thành Công!',
            amount: `+${formatVND(data.receipt.amount)}`,
            subtitle: `Đơn hàng #${data.receipt.order_code} đã được hoàn 100% về Ví TAPay`,
            rows: [
                ['Mã GD Hoàn tiền', data.receipt.tx_code],
                ['Mã hồ sơ Refund', data.receipt.refund_code],
                ['Đơn hàng', `#${data.receipt.order_code}`],
                ['Lý do hoàn tiền', data.receipt.reason],
                ['Số dư trước hoàn', formatVND(data.receipt.balance_before)],
                ['Số dư sau hoàn', formatVND(data.receipt.balance_after)],
                ['Thời gian', data.receipt.created_at]
            ]
        });
        showToast(data.message, 'success');
    } catch (err) {
        showToast('Lỗi kết nối khi xử lý hoàn tiền', 'error');
    }
}

// ============================================================================
// 6. MODALS & DATABASE CONFIG HELPERS
// ============================================================================
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('flex');
    if (window.lucide) lucide.createIcons();
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hidden');
    el.classList.remove('flex');
    if (id === 'topupModal') stopTopupPolling();
}

// ============================================================================
// VIRTUAL MONEY (TIỀN ẢO DEMO MUA SẮM)
// ============================================================================
function openVirtualMoneyModal() {
    if (state.wallet) {
        const el = document.getElementById('virtualModalCurrentBalance');
        if (el) el.textContent = formatVND(state.wallet.virtual_balance || 0);
    }
    openModal('virtualMoneyModal');
}

function setVirtualAmount(amt) {
    const inp = document.getElementById('virtualAmountInput');
    if (inp) inp.value = amt;
}

async function quickAddVirtualMoney(amt = 5000000) {
    await executeAddVirtualMoney(amt, false);
}

async function handleAddVirtualMoneySubmit(e) {
    e.preventDefault();
    const amt = Number(document.getElementById('virtualAmountInput')?.value || 5000000);
    await executeAddVirtualMoney(amt, true);
}

async function executeAddVirtualMoney(amount, closeAfter = false) {
    try {
        const res = await fetch('api/wallet.php?action=add_virtual_money', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: state.currentUserId,
                amount
            })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Cộng tiền ảo thất bại', 'error');
            return;
        }
        await fetchWalletOverview();
        if (closeAfter) {
            closeModal('virtualMoneyModal');
        }
        showToast(data.message, 'success');
    } catch (e) {
        showToast('Lỗi kết nối khi cộng tiền ảo', 'error');
    }
}

// Accessibility: Close open modals on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    ['topupModal', 'virtualMoneyModal', 'transferModal', 'receiptModal', 'createProductModal', 'refundModal', 'dbConfigModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el && !el.classList.contains('hidden')) closeModal(id);
    });
});

function showReceiptModal({ title, amount, subtitle, rows }) {
    document.getElementById('receiptTitle').textContent = title;
    document.getElementById('receiptAmount').textContent = amount;
    document.getElementById('receiptSubtitle').textContent = subtitle;

    const list = document.getElementById('receiptDetailsList');
    if (list) {
        list.innerHTML = (rows || []).map(([k, v]) => `
            <div class="flex items-center justify-between gap-4 py-1.5 border-b border-slate-200/80 last:border-0">
                <span class="text-slate-500">${k}:</span>
                <span class="font-bold text-slate-800 text-right">${v}</span>
            </div>
        `).join('');
    }
    openModal('receiptModal');
}

async function loadDbConfigInfo() {
    try {
        const res = await fetch('api/wallet.php?action=db_config');
        const data = await res.json();
        if (!data.success) return;

        if (data.config) {
            document.getElementById('cfgSupabaseUrl').value = data.config.supabase_url || '';
            document.getElementById('cfgSupabaseKey').value = data.config.supabase_anon_key || '';
            document.getElementById('cfgPgHost').value = data.config.supabase_db_host || '';
        }
    } catch (e) {
        console.error(e);
    }
}

async function handleSaveDbConfig(e) {
    e.preventDefault();
    const payload = {
        supabase_url: document.getElementById('cfgSupabaseUrl')?.value.trim(),
        supabase_anon_key: document.getElementById('cfgSupabaseKey')?.value.trim(),
        supabase_db_host: document.getElementById('cfgPgHost')?.value.trim(),
        supabase_db_pass: document.getElementById('cfgPgPass')?.value.trim()
    };

    try {
        const res = await fetch('api/wallet.php?action=db_config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeModal('dbConfigModal');
            showToast(data.message, 'success');
            fetchWalletOverview();
        }
    } catch (err) {
        showToast('Không thể lưu cấu hình DB', 'error');
    }
}
