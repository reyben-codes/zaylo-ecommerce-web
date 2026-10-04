<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZAYLO · Admin Reports</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
    <style>
        .report-main { flex:1; min-width:0; padding:34px 42px; }
        .report-heading { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; margin-bottom:24px; }
        .report-heading h1 { margin:0; color:var(--dark); font:600 1.9rem 'Playfair Display',serif; }
        .report-heading p { margin:7px 0 0; color:var(--muted); font-size:.84rem; }
        .report-type-tabs { display:flex; gap:10px; margin:0 0 18px; border-bottom:1px solid var(--border); }
        .report-type-button { display:flex; align-items:center; gap:9px; padding:12px 16px; border:0; border-bottom:3px solid transparent; background:transparent; color:var(--muted); font:500 .8rem 'Inter',sans-serif; cursor:pointer; }
        .report-type-button.active { border-bottom-color:var(--accent); color:var(--dark); font-weight:600; }
        .report-controls { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; padding:18px; margin-bottom:20px; background:var(--white); border:1px solid var(--border); }
        .report-date-field { display:flex; flex-direction:column; gap:6px; }
        .report-date-field label { color:var(--muted); font-size:.7rem; font-weight:600; }
        .report-date-field input { min-height:40px; padding:8px 10px; border:1px solid var(--input-border); background:var(--cream); color:var(--dark); font:400 .78rem 'Inter',sans-serif; }
        .report-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:40px; padding:10px 15px; border:1px solid var(--dark); background:var(--dark); color:var(--white); font:600 .72rem 'Inter',sans-serif; cursor:pointer; }
        .report-button:hover { background:var(--accent); border-color:var(--accent); }
        .report-button.secondary { background:var(--white); color:var(--dark); border-color:var(--border); }
        .report-period-note { margin-left:auto; color:var(--muted); font-size:.7rem; }
        .report-card { margin-bottom:20px; padding:22px; background:var(--white); border:1px solid var(--border); }
        .report-card-heading { margin-bottom:18px; }
        .report-card-heading h2 { margin:0; color:var(--dark); font:600 1.2rem 'Playfair Display',serif; }
        .report-card-heading p { margin:5px 0 0; color:var(--muted); font-size:.72rem; }
        .report-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
        .report-stat { padding:17px; background:var(--cream); border:1px solid var(--border); }
        .report-stat-label { color:var(--muted); font-size:.68rem; font-weight:600; }
        .report-stat-value { margin-top:9px; color:var(--dark); font:600 1.4rem 'Playfair Display',serif; overflow-wrap:anywhere; }
        .report-stat-help { margin-top:5px; color:var(--light-brown); font-size:.65rem; }
        .report-table-wrap { overflow-x:auto; }
        .report-chart-card { padding:20px; margin-bottom:20px; background:var(--white); border:1px solid var(--border); }
        .report-chart-heading { margin:0 0 5px; color:var(--dark); font:600 1.05rem 'Playfair Display',serif; }
        .report-chart-caption { margin:0 0 14px; color:var(--muted); font-size:.7rem; }
        .report-chart-svg { display:block; width:100%; height:auto; }
        .report-chart-legend { display:flex; flex-wrap:wrap; gap:10px 18px; margin-top:10px; color:var(--brown); font-size:.68rem; }
        .report-chart-legend span { display:inline-flex; align-items:center; gap:7px; }
        .report-chart-legend i { display:inline-block; width:18px; height:3px; background:var(--legend-color); }
        .report-chart-empty { padding:30px 12px; color:var(--muted); text-align:center; font-size:.78rem; }
        .report-table { width:100%; border-collapse:collapse; text-align:left; }
        .report-table th { padding:11px 12px; background:var(--soft-cream); color:var(--brown); font-size:.65rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
        .report-table td { padding:12px; border-bottom:1px solid var(--border); color:var(--text); font-size:.74rem; }
        .report-table tfoot th { padding:12px; background:var(--cream); font-size:.7rem; }
        .report-table .money { text-align:right; white-space:nowrap; }
        .report-empty { padding:36px 15px; color:var(--muted); text-align:center; font-size:.8rem; }
        .report-footnote { margin:15px 0 0; color:var(--muted); font-size:.68rem; line-height:1.6; }
        .report-toast { position:fixed; right:24px; bottom:24px; z-index:9999; padding:14px 18px; background:var(--dark); color:var(--white); font-size:.75rem; box-shadow:0 5px 18px #0002; }
        @media(max-width:1100px) { .report-main { padding:28px; } .report-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:900px) { .report-main { padding:24px 20px; } .report-heading { align-items:flex-start; flex-direction:column; } }
        @media(max-width:600px) { .report-main { padding:18px 14px; } .report-type-tabs { gap:0; } .report-type-button { flex:1; justify-content:center; padding:11px 7px; font-size:.68rem; } .report-controls { align-items:stretch; flex-direction:column; } .report-date-field input,.report-controls .report-button { width:100%; } .report-period-note { margin-left:0; } .report-card { padding:16px; } .report-stats { gap:8px; } .report-stat { padding:12px; } .report-stat-value { font-size:1.05rem; } }
        @media print {
            .navbar,.sidebar,.report-heading,.report-type-tabs,.report-controls,.top-line { display:none !important; }
            .dashboard-wrapper { display:block !important; min-height:0 !important; }
            .report-main { padding:0 !important; }
            .report-card { border:0; padding:0; }
            .report-stat { break-inside:avoid; }
            body { background:#fff !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        @include('admin.partials.header', ['searchId' => 'headerSearch', 'searchPlaceholder' => 'Search admin pages...', 'searchOnInput' => ''])
        <div class="dashboard-wrapper">
            @include('admin.partials.sidebar')
            <main class="report-main">
                <header class="report-heading">
                    <div><h1>Reports</h1><p>Review platform sales and the 10% commission collected on completed orders.</p></div>
                </header>
                <div class="report-type-tabs" role="tablist" aria-label="Report type">
                    <button type="button" class="report-type-button active" data-type="sales" role="tab" aria-selected="true"><i class="fas fa-chart-line"></i> Sales Summary</button>
                    <button type="button" class="report-type-button" data-type="commission" role="tab" aria-selected="false"><i class="fas fa-percent"></i> Commission Report</button>
                </div>
                <section class="report-controls" aria-label="Report controls">
                    <div class="report-date-field"><label for="dateFrom">From</label><input type="date" id="dateFrom"></div>
                    <div class="report-date-field"><label for="dateTo">To</label><input type="date" id="dateTo"></div>
                    <button type="button" class="report-button" id="generateReport"><i class="fas fa-filter"></i> Generate Report</button>
                    <button type="button" class="report-button secondary" id="downloadPdf"><i class="fas fa-file-pdf"></i> Download PDF</button>
                    <span class="report-period-note" id="reportPeriodNote"></span>
                </section>
                <section class="report-card" id="reportCard" aria-live="polite"></section>
            </main>
        </div>
    </div>
    <script>
        const commissionRate = 0.10;
        const reportGeneratedBy = @json(auth()->user()?->name ?? 'Administrator');
        const orderData = [
            { date: '2026-09-02', order: 'ZY-260902-1041', buyer: 'Mia Santos', seller: 'ZAYLO Fashion Hub', category: 'Clothing & Apparel', amount: 4250 },
            { date: '2026-09-04', order: 'ZY-260904-1058', buyer: 'Daniel Cruz', seller: 'Daily Essentials', category: 'Home & Lifestyle', amount: 2180 },
            { date: '2026-09-07', order: 'ZY-260907-1093', buyer: 'Alyssa Reyes', seller: 'ZAYLO Fashion Hub', category: 'Clothing & Apparel', amount: 5790 },
            { date: '2026-09-10', order: 'ZY-260910-1120', buyer: 'Noah Garcia', seller: 'Premium Finds', category: 'Bags & Accessories', amount: 6890 },
            { date: '2026-09-12', order: 'ZY-260912-1152', buyer: 'Ella Lim', seller: 'Daily Essentials', category: 'Home & Lifestyle', amount: 1675 },
            { date: '2026-09-15', order: 'ZY-260915-1187', buyer: 'Liam Flores', seller: 'Modern Closet', category: 'Shoes & Footwear', amount: 3490 },
            { date: '2026-09-17', order: 'ZY-260917-1214', buyer: 'Sofia Tan', seller: 'Premium Finds', category: 'Bags & Accessories', amount: 8125 },
            { date: '2026-09-19', order: 'ZY-260919-1239', buyer: 'Lucas Mendoza', seller: 'ZAYLO Fashion Hub', category: 'Clothing & Apparel', amount: 2890 }
        ];

        let selectedType = 'sales';
        let currentRows = [];
        let currentSummary = {};

        function escapeHtml(value) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(value).replace(/[&<>"']/g, character => map[character]);
        }

        function money(amount) {
            return 'PHP ' + Number(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function buildChartModel(rows) {
            const storeMap = new Map();
            const dateSet = new Set();
            rows.forEach(row => {
                dateSet.add(row.date);
                if (!storeMap.has(row.seller)) storeMap.set(row.seller, new Map());
                const storeValues = storeMap.get(row.seller);
                storeValues.set(row.date, (storeValues.get(row.date) || 0) + row.value);
            });
            const dates = Array.from(dateSet).sort();
            const stores = Array.from(storeMap.entries()).sort((a, b) => a[0].localeCompare(b[0])).map(entry => ({
                name: entry[0],
                values: dates.map(date => entry[1].get(date) || 0)
            }));
            const totals = dates.map((date, index) => stores.reduce((sum, store) => sum + store.values[index], 0));
            const palette = ['#526d58', '#8a9b78', '#b28b6f', '#6b5f54', '#8c7771'];
            stores.forEach((store, index) => {
                store.color = isDeclining(store.values) ? '#b84a4a' : palette[index % palette.length];
            });
            return { dates: dates, stores: stores, totals: totals, totalColor: isDeclining(totals) ? '#b84a4a' : '#526d58' };
        }

        function isDeclining(values) {
            if (values.length < 2) return false;
            const middleX = (values.length - 1) / 2;
            const middleY = values.reduce((sum, value) => sum + value, 0) / values.length;
            return values.reduce((slope, value, index) => slope + (index - middleX) * (value - middleY), 0) < 0;
        }

        function renderAreaChart(rows, title, valueLabel) {
            if (!rows.length) {
                return '<section class="report-chart-card"><h3 class="report-chart-heading">' + title + '</h3><div class="report-chart-empty">No data to chart for this date range.</div></section>';
            }

            const model = buildChartModel(rows);
            const maxValue = Math.max(...model.totals, 1);
            const left = 82;
            const right = 850;
            const top = 25;
            const bottom = 225;
            const xFor = index => model.dates.length === 1 ? (left + right) / 2 : left + index * (right - left) / (model.dates.length - 1);
            const minValue = 0;
            const yFor = value => bottom - ((value - minValue) / (maxValue - minValue || 1)) * (bottom - top);
            const totalPath = model.totals.map((value, index) => xFor(index) + ',' + yFor(value)).join(' L ');
            const grid = Array.from({ length: 5 }, (_, index) => {
                const y = top + index * (bottom - top) / 4;
                const value = maxValue * (4 - index) / 4;
                return '<g><line x1="' + left + '" y1="' + y + '" x2="' + right + '" y2="' + y + '" stroke="#ece4db" stroke-width="1"/><text x="' + (left - 10) + '" y="' + (y + 4) + '" text-anchor="end" fill="#8a7a6b" font-size="11">' + escapeHtml(compactMoney(value)) + '</text></g>';
            }).join('');
            const labels = model.dates.map((date, index) => '<text x="' + xFor(index) + '" y="250" text-anchor="middle" fill="#6b5f54" font-size="10">' + escapeHtml(formatDate(date).replace(/, \d{4}$/, '')) + '</text>').join('');
            const storeLines = model.stores.map(store => {
                const path = store.values.map((value, index) => xFor(index) + ',' + yFor(value)).join(' L ');
                const dots = store.values.map((value, index) => '<circle cx="' + xFor(index) + '" cy="' + yFor(value) + '" r="3" fill="' + store.color + '"><title>' + escapeHtml(store.name + ' · ' + formatDate(model.dates[index]) + ': ' + money(value)) + '</title></circle>').join('');
                return '<path d="M ' + path + '" fill="none" stroke="' + store.color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' + dots;
            }).join('');
            const totalDots = model.totals.map((value, index) => '<circle cx="' + xFor(index) + '" cy="' + yFor(value) + '" r="4" fill="' + model.totalColor + '" stroke="#fff" stroke-width="2"><title>Total · ' + escapeHtml(formatDate(model.dates[index]) + ': ' + money(value)) + '</title></circle>').join('');
            const legendItems = [{ name: 'Total', color: model.totalColor }, ...model.stores].map(item => '<span><i style="--legend-color:' + item.color + '"></i>' + escapeHtml(item.name) + '</span>').join('');

            return '<section class="report-chart-card"><h3 class="report-chart-heading">' + title + '</h3>' +
                '<p class="report-chart-caption">' + valueLabel + ' over time. Each line is labeled by seller.</p>' +
                '<svg class="report-chart-svg" viewBox="0 0 900 270" role="img" aria-label="' + escapeHtml(title) + '">' +
                grid + '<path d="M ' + totalPath + '" fill="none" stroke="' + model.totalColor + '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>' + storeLines + totalDots + labels +
                '</svg><div class="report-chart-legend">' + legendItems + '</div></section>';
        }

        function compactMoney(value) {
            if (value >= 1000000) return 'PHP ' + (value / 1000000).toFixed(1) + 'm';
            if (value >= 1000) return 'PHP ' + (value / 1000).toFixed(value >= 10000 ? 0 : 1) + 'k';
            return 'PHP ' + Math.round(value).toLocaleString('en-PH');
        }

        function formatDate(value) {
            if (!value) return '—';
            return new Date(value + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function localInputDate(date) {
            const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
            return local.toISOString().slice(0, 10);
        }

        function getFilteredOrders() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            if (from && to && from > to) {
                showToast('The start date must be before the end date.');
                return null;
            }
            return orderData.filter(order => (!from || order.date >= from) && (!to || order.date <= to));
        }

        function renderReport() {
            const orders = getFilteredOrders();
            if (orders === null) return;
            currentRows = orders;
            const sales = orders.reduce((sum, order) => sum + order.amount, 0);
            const commission = sales * commissionRate;
            const average = orders.length ? sales / orders.length : 0;
            currentSummary = { sales: sales, commission: commission, average: average, orders: orders.length };
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            document.getElementById('reportPeriodNote').textContent = formatDate(from) + ' – ' + formatDate(to);

            const stats = '<div class="report-stats">' +
                '<div class="report-stat"><div class="report-stat-label">Gross sales</div><div class="report-stat-value">' + money(sales) + '</div><div class="report-stat-help">Completed orders in selected period</div></div>' +
                '<div class="report-stat"><div class="report-stat-label">Completed orders</div><div class="report-stat-value">' + orders.length.toLocaleString() + '</div><div class="report-stat-help">Order count</div></div>' +
                '<div class="report-stat"><div class="report-stat-label">Average order value</div><div class="report-stat-value">' + money(average) + '</div><div class="report-stat-help">Gross sales divided by orders</div></div>' +
                '<div class="report-stat"><div class="report-stat-label">Platform commission (10%)</div><div class="report-stat-value">' + money(commission) + '</div><div class="report-stat-help">Calculated from gross sales</div></div></div>';

            let title;
            let description;
            let table;
            const chartRows = orders.map(order => ({ date: order.date, seller: order.seller, value: selectedType === 'sales' ? order.amount : order.amount * commissionRate }));
            const chartTitle = selectedType === 'sales' ? 'Sales Trend' : 'Commission Trend';
            if (selectedType === 'sales') {
                const bySeller = new Map();
                orders.forEach(order => {
                    const row = bySeller.get(order.seller) || { seller: order.seller, category: order.category, orders: 0, sales: 0 };
                    row.orders += 1;
                    row.sales += order.amount;
                    bySeller.set(order.seller, row);
                });
                const rows = Array.from(bySeller.values()).sort((a, b) => a.seller.localeCompare(b.seller));
                title = 'Sales Summary Report';
                description = 'Sales totals grouped by seller for the selected date range.';
                if (rows.length) {
                    const body = rows.map(row => '<tr><td>' + escapeHtml(row.seller) + '</td><td>' + escapeHtml(row.category) + '</td><td>' + row.orders + '</td><td class="money">' + money(row.sales) + '</td><td class="money">' + money(row.sales / row.orders) + '</td><td class="money">' + money(row.sales * commissionRate) + '</td></tr>').join('');
                    table = '<div class="report-table-wrap"><table class="report-table"><thead><tr><th>Seller</th><th>Category</th><th>Orders</th><th class="money">Gross sales</th><th class="money">Average order</th><th class="money">10% commission</th></tr></thead><tbody>' + body + '</tbody><tfoot><tr><th colspan="2">Total</th><th>' + orders.length + '</th><th class="money">' + money(sales) + '</th><th class="money">' + money(average) + '</th><th class="money">' + money(commission) + '</th></tr></tfoot></table></div>';
                } else table = '<div class="report-empty">No completed sales in this date range.</div>';
            } else {
                const rows = orders.slice().sort((a, b) => a.date.localeCompare(b.date) || a.order.localeCompare(b.order));
                title = 'Commission Report';
                description = 'Platform commission calculated at 10% per completed order.';
                if (rows.length) {
                    const body = rows.map(order => '<tr><td>' + formatDate(order.date) + '</td><td>' + escapeHtml(order.order) + '</td><td>' + escapeHtml(order.seller) + '</td><td>' + escapeHtml(order.category) + '</td><td class="money">' + money(order.amount) + '</td><td class="money">' + money(order.amount * commissionRate) + '</td></tr>').join('');
                    table = '<div class="report-table-wrap"><table class="report-table"><thead><tr><th>Date</th><th>Order</th><th>Seller</th><th>Category</th><th class="money">Eligible sales</th><th class="money">Commission (10%)</th></tr></thead><tbody>' + body + '</tbody><tfoot><tr><th colspan="4">Total</th><th class="money">' + money(sales) + '</th><th class="money">' + money(commission) + '</th></tr></tfoot></table></div>';
                } else table = '<div class="report-empty">No commission records in this date range.</div>';
            }

            document.getElementById('reportCard').innerHTML =
                '<header class="report-card-heading"><h2>' + title + '</h2><p>' + description + '</p></header>' +
                stats + renderAreaChart(chartRows, chartTitle, selectedType === 'sales' ? 'Gross sales' : 'Platform commission') + table +
                '<p class="report-footnote">Amounts are in Philippine pesos (PHP). Commission is 10% of completed order sales. Sample figures are shown until this page is connected to live order and payment records.</p>';
        }

        function escapePdf(value) {
            const slash = String.fromCharCode(92);
            return String(value).normalize('NFKD')
                .replace(/[^\x20-\x7E]/g, '')
                .replaceAll(slash, slash + slash)
                .replaceAll('(', slash + '(')
                .replaceAll(')', slash + ')');
        }

        function buildPdf(lines) {
            const chunks = [];
            for (let index = 0; index < lines.length; index += 42) chunks.push(lines.slice(index, index + 42));
            if (!chunks.length) chunks.push(['No data available.']);
            const objects = [];
            objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
            objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
            const pageIds = chunks.map((chunk, index) => 4 + index * 2);
            objects[2] = '<< /Type /Pages /Kids [' + pageIds.map(id => id + ' 0 R').join(' ') + '] /Count ' + chunks.length + ' >>';
            chunks.forEach((chunk, index) => {
                const pageId = 4 + index * 2;
                const contentId = pageId + 1;
                let stream = 'BT /F1 18 Tf 50 755 Td (ZAYLO MARKETPLACE) Tj /F1 9 Tf 0 -20 Td (ADMINISTRATIVE REPORT) Tj 0 -24 Td ';
                chunk.forEach((line, lineIndex) => {
                    stream += (lineIndex === 0 && index === 0 ? '/F1 14 Tf ' : '/F1 9 Tf ') + '(' + escapePdf(line) + ') Tj 0 -15 Td ';
                });
                stream += 'ET';
                const length = new TextEncoder().encode(stream).length;
                objects[pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents ' + contentId + ' 0 R >>';
                objects[contentId] = '<< /Length ' + length + ' >>\nstream\n' + stream + '\nendstream';
            });
            let pdf = '%PDF-1.4\n';
            const offsets = [0];
            for (let id = 1; id < objects.length; id++) {
                offsets[id] = new TextEncoder().encode(pdf).length;
                pdf += id + ' 0 obj\n' + objects[id] + '\nendobj\n';
            }
            const xref = new TextEncoder().encode(pdf).length;
            pdf += 'xref\n0 ' + objects.length + '\n0000000000 65535 f \n';
            for (let id = 1; id < objects.length; id++) pdf += String(offsets[id]).padStart(10, '0') + ' 00000 n \n';
            pdf += 'trailer\n<< /Size ' + objects.length + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF';
            return new Blob([pdf], { type: 'application/pdf' });
        }

        function pdfColor(hex, mode) {
            const value = hex.replace('#', '');
            const red = parseInt(value.slice(0, 2), 16) / 255;
            const green = parseInt(value.slice(2, 4), 16) / 255;
            const blue = parseInt(value.slice(4, 6), 16) / 255;
            return red.toFixed(3) + ' ' + green.toFixed(3) + ' ' + blue.toFixed(3) + ' ' + mode + '\n';
        }

        function pdfText(text, x, y, size, color, bold, serif) {
            const font = serif ? (bold ? 'F4' : 'F3') : (bold ? 'F2' : 'F1');
            return 'BT /' + font + ' ' + size + ' Tf ' + pdfColor(color, 'rg') + x + ' ' + y + ' Td (' + escapePdf(text) + ') Tj ET\n';
        }

        function pdfRect(x, y, width, height, fill, stroke) {
            let command = 'q\n';
            if (fill) command += pdfColor(fill, 'rg');
            if (stroke) command += pdfColor(stroke, 'RG');
            command += x + ' ' + y + ' ' + width + ' ' + height + ' re ' + (fill && stroke ? 'B' : fill ? 'f' : 'S') + '\nQ\n';
            return command;
        }

        async function loadPdfLogo() {
            return new Promise(resolve => {
                const logo = new Image();
                logo.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = logo.naturalWidth;
                    canvas.height = logo.naturalHeight;
                    const context = canvas.getContext('2d');
                    context.fillStyle = '#f5f0ea';
                    context.fillRect(0, 0, canvas.width, canvas.height);
                    context.drawImage(logo, 0, 0);
                    const encoded = atob(canvas.toDataURL('image/jpeg', 0.92).split(',')[1]);
                    const bytes = new Uint8Array(encoded.length);
                    for (let index = 0; index < encoded.length; index++) bytes[index] = encoded.charCodeAt(index);
                    resolve({ bytes: bytes, width: canvas.width, height: canvas.height });
                };
                logo.onerror = () => resolve(null);
                logo.src = '{{ asset('images/logo.png') }}';
            });
        }

        function bytesToBinaryString(bytes) {
            let binary = '';
            const chunkSize = 0x8000;
            for (let offset = 0; offset < bytes.length; offset += chunkSize) {
                binary += String.fromCharCode(...bytes.subarray(offset, offset + chunkSize));
            }
            return binary;
        }

        function serializeOnePagePdf(stream, logoImage) {
            const objects = [];
            objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
            objects[2] = '<< /Type /Pages /Kids [6 0 R] /Count 1 >>';
            objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
            objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
            objects[8] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>';
            objects[9] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold >>';
            const imageId = logoImage ? 5 : null;
            const pageId = 6;
            const contentId = 7;
            objects[imageId] = logoImage
                ? '<< /Type /XObject /Subtype /Image /Width ' + logoImage.width + ' /Height ' + logoImage.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + logoImage.bytes.length + ' >>\nstream\n' + bytesToBinaryString(logoImage.bytes) + '\nendstream'
                : '<< >>';
            objects[pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 8 0 R /F4 9 0 R >> ' + (logoImage ? '/XObject << /Im1 5 0 R >> ' : '') + '>> /Contents 7 0 R >>';
            objects[contentId] = '<< /Length ' + new TextEncoder().encode(stream).length + ' >>\nstream\n' + stream + 'endstream';
            let binaryPdf = '%PDF-1.4\n';
            const offsets = [0];
            for (let id = 1; id < objects.length; id++) {
                offsets[id] = binaryPdf.length;
                binaryPdf += id + ' 0 obj\n' + objects[id] + '\nendobj\n';
            }
            const xref = binaryPdf.length;
            binaryPdf += 'xref\n0 ' + objects.length + '\n0000000000 65535 f \n';
            for (let id = 1; id < objects.length; id++) binaryPdf += String(offsets[id]).padStart(10, '0') + ' 00000 n \n';
            binaryPdf += 'trailer\n<< /Size ' + objects.length + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF';
            const pdfBytes = new Uint8Array(binaryPdf.length);
            for (let index = 0; index < binaryPdf.length; index++) pdfBytes[index] = binaryPdf.charCodeAt(index);
            return new Blob([pdfBytes], { type: 'application/pdf' });
        }

        async function buildStyledPdf() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            const title = selectedType === 'sales' ? 'Sales Summary Report' : 'Commission Report';
            const chartRows = currentRows.map(order => ({ date: order.date, seller: order.seller, value: selectedType === 'sales' ? order.amount : order.amount * commissionRate }));
            const chartModel = buildChartModel(chartRows);
            const accent = chartModel.totalColor;
            const logoImage = await loadPdfLogo();
            let stream = '';

            stream += pdfRect(0, 742, 612, 50, '#f5f0ea');
            stream += pdfText('ADMIN REPORT', 38, 763, 8, '#6b5f54', true);
            if (logoImage) {
                const logoHeight = 36;
                const logoWidth = logoHeight * logoImage.width / logoImage.height;
                stream += 'q ' + logoWidth + ' 0 0 ' + logoHeight + ' ' + ((612 - logoWidth) / 2) + ' 749 cm /Im1 Do Q\n';
            }
            stream += pdfRect(38, 742, 536, 1, '#ded5cc');

            const generatedAt = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
            stream += pdfText(title, 38, 718, 23, '#1a1714', true, true);
            stream += pdfText('Period: ' + formatDate(from) + ' to ' + formatDate(to), 38, 694, 10, '#51463f', false);
            stream += pdfText('Generated: ' + generatedAt, 420, 694, 8, '#6b5f54', false);

            const cards = [
                ['GROSS SALES', money(currentSummary.sales)],
                ['COMPLETED ORDERS', String(currentSummary.orders)],
                ['AVERAGE ORDER', money(currentSummary.average)],
                ['COMMISSION · 10%', money(currentSummary.commission)]
            ];
            cards.forEach((card, index) => {
                const x = 38 + index * 136;
                stream += pdfRect(x, 600, 128, 76, '#f5f0ea', '#ece4db');
                stream += pdfText(card[0], x + 10, 656, 6.5, '#6b5f54', true);
                stream += pdfText(card[1], x + 10, 628, 10, '#1a1714', true, true);
                stream += pdfText(index === 3 ? 'Platform share' : 'Selected period', x + 10, 612, 6.5, '#8a7a6b', false);
            });

            stream += pdfText(selectedType === 'sales' ? 'Sales Trend' : 'Commission Trend', 38, 573, 13, '#1a1714', true, true);
            stream += pdfText(selectedType === 'sales' ? 'Gross sales by date' : 'Platform commission by date', 38, 559, 7, '#6b5f54', false);
            stream += pdfRect(38, 380, 536, 165, '#ffffff', '#ece4db');

            const plotLeft = 86;
            const plotRight = 557;
            const plotBottom = 444;
            const plotTop = 519;
            const maxValue = Math.max(...chartModel.totals, 1);
            let gridCommands = '';
            for (let index = 0; index <= 4; index++) {
                const y = plotBottom + index * (plotTop - plotBottom) / 4;
                const value = maxValue * index / 4;
                gridCommands += pdfColor('#ece4db', 'RG') + '0.5 w  ' + plotLeft + ' ' + y + ' m ' + plotRight + ' ' + y + ' l S\n';
                gridCommands += pdfText(compactMoney(value), 42, y - 2, 6.5, '#6b5f54', false);
            }
            if (chartModel.dates.length) {
                const xFor = index => chartModel.dates.length === 1 ? (plotLeft + plotRight) / 2 : plotLeft + index * (plotRight - plotLeft) / (chartModel.dates.length - 1);
                const yFor = value => plotBottom + (value / maxValue) * (plotTop - plotBottom);
                let line = 'q\n' + pdfColor(accent, 'RG') + '2 w\n';
                chartModel.totals.forEach((value, index) => {
                    const x = xFor(index);
                    const y = yFor(value);
                    line += x + ' ' + y + (index ? ' l ' : ' m ');
                });
                line += 'S\nQ\n';
                stream += gridCommands + line;
                chartModel.stores.forEach(store => {
                    let storeLine = 'q\n' + pdfColor(store.color, 'RG') + '1.4 w\n';
                    store.values.forEach((value, index) => {
                        const x = xFor(index);
                        const y = yFor(value);
                        storeLine += x + ' ' + y + (index ? ' l ' : ' m ');
                    });
                    stream += storeLine + 'S\nQ\n';
                });
                chartModel.totals.forEach((value, index) => {
                    const x = xFor(index);
                    const y = yFor(value);
                    stream += pdfColor(accent, 'rg') + x + ' ' + y + ' 2.5 2.5 re f\n';
                    if (index === 0 || index === chartModel.dates.length - 1 || index % 2 === 0) {
                        stream += pdfText(formatDate(chartModel.dates[index]).replace(/, \d{4}$/, ''), Math.max(plotLeft - 5, x - 16), 429, 6, '#6b5f54', false);
                    }
                });
                const legendItems = [{ name: 'Total', color: accent }, ...chartModel.stores];
                legendItems.forEach((item, index) => {
                    const column = index % 2;
                    const row = Math.floor(index / 2);
                    const x = 65 + column * 250;
                    const y = 411 - row * 13;
                    stream += pdfColor(item.color, 'RG') + '2 w ' + x + ' ' + y + ' m ' + (x + 16) + ' ' + y + ' l S\n';
                    stream += pdfText(item.name.slice(0, 35), x + 22, y - 2, 6.5, '#51463f', false);
                });
            } else {
                stream += gridCommands;
                stream += pdfText('No data for this reporting period.', 210, 425, 10, '#6b5f54', false);
            }

            stream += pdfText(selectedType === 'sales' ? 'Sales by seller' : 'Commission by order', 38, 357, 13, '#1a1714', true, true);
            stream += pdfText('Completed orders · Philippine pesos (PHP)', 38, 343, 7, '#6b5f54', false);
            stream += pdfRect(38, 315, 536, 20, '#e9e4d8');

            if (selectedType === 'sales') {
                const grouped = new Map();
                currentRows.forEach(order => {
                    const row = grouped.get(order.seller) || { orders: 0, sales: 0 };
                    row.orders++;
                    row.sales += order.amount;
                    grouped.set(order.seller, row);
                });
                stream += pdfText('SELLER', 48, 323, 6.5, '#51463f', true) + pdfText('ORDERS', 263, 323, 6.5, '#51463f', true) + pdfText('GROSS SALES', 339, 323, 6.5, '#51463f', true) + pdfText('COMMISSION', 465, 323, 6.5, '#51463f', true);
                let y = 298;
                Array.from(grouped.entries()).sort((a, b) => a[0].localeCompare(b[0])).forEach(entry => {
                    stream += pdfText(entry[0], 48, y, 7, '#1a1714', false) + pdfText(String(entry[1].orders), 267, y, 7, '#1a1714', false) + pdfText(money(entry[1].sales), 339, y, 7, '#1a1714', false) + pdfText(money(entry[1].sales * commissionRate), 465, y, 7, '#1a1714', false);
                    stream += pdfRect(38, y - 7, 536, 0.6, '#ece4db');
                    y -= 20;
                });
            } else {
                stream += pdfText('DATE', 47, 323, 6.5, '#51463f', true) + pdfText('ORDER', 112, 323, 6.5, '#51463f', true) + pdfText('SELLER', 228, 323, 6.5, '#51463f', true) + pdfText('GROSS', 397, 323, 6.5, '#51463f', true) + pdfText('COMMISSION', 480, 323, 6.5, '#51463f', true);
                let y = 298;
                currentRows.slice().sort((a, b) => a.date.localeCompare(b.date)).forEach(order => {
                    stream += pdfText(formatDate(order.date).replace(/, \d{4}$/, ''), 47, y, 6.5, '#1a1714', false) + pdfText(order.order.slice(-8), 112, y, 6.5, '#1a1714', false) + pdfText(order.seller, 228, y, 6.5, '#1a1714', false) + pdfText(money(order.amount), 397, y, 6.5, '#1a1714', false) + pdfText(money(order.amount * commissionRate), 480, y, 6.5, '#1a1714', false);
                    stream += pdfRect(38, y - 7, 536, 0.6, '#ece4db');
                    y -= 18;
                });
            }

            stream += pdfText('Commission is calculated at 10% of completed order sales. Figures are sample data until connected to live sales and payment records.', 38, 83, 6.5, '#6b5f54', false);
            stream += pdfRect(38, 60, 536, 1, '#526d58');
            stream += pdfText('Generated by: ' + reportGeneratedBy, 38, 44, 6.5, '#6b5f54', true);
            stream += pdfText(title + '  |  Page 1 of 1', 430, 44, 6.5, '#6b5f54', false);
            return serializeOnePagePdf(stream, logoImage);
        }

        async function downloadReportPdf() {
            if (getFilteredOrders() === null) return;
            renderReport();
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            const filename = 'zaylo-' + selectedType + '-report-' + from + '-to-' + to + '.pdf';
            const fileUrl = URL.createObjectURL(await buildStyledPdf());
            const link = document.createElement('a');
            link.href = fileUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(fileUrl), 1000);
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'report-toast';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3200);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date();
            const end = localInputDate(today);
            const start = new Date(today);
            start.setDate(today.getDate() - 29);
            document.getElementById('dateFrom').value = localInputDate(start);
            document.getElementById('dateTo').value = end;
            document.querySelectorAll('.report-type-button').forEach(button => button.addEventListener('click', () => {
                selectedType = button.dataset.type;
                document.querySelectorAll('.report-type-button').forEach(tab => {
                    const active = tab === button;
                    tab.classList.toggle('active', active);
                    tab.setAttribute('aria-selected', String(active));
                });
                renderReport();
            }));
            document.getElementById('generateReport').addEventListener('click', renderReport);
            document.getElementById('downloadPdf').addEventListener('click', downloadReportPdf);
            renderReport();
        });
    </script>
    <script src="{{ asset('js/admin-shell.js') }}"></script>
</body>
</html>
