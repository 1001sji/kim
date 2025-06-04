<?php
if (!defined('_GNUBOARD_')) exit; // Direct access prohibited

// Attempt to include the GnuBoard common head, if it's available and appropriate for a widget
// This might not be standard for all widget types, adjust if needed.
// if (function_exists('g5_path')) {
//     include_once(g5_path().'/head.sub.php');
// }

// Define a unique ID for the widget wrapper to scope styles if necessary
$widget_id = 'kimchi_premium_widget_' . uniqid();
?>

<link href="https://cdn.tailwindcss.com?plugins=forms,container-queries" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&amp;display=swap" rel="stylesheet"/>
<style>
    /* Scoped styles for the widget */
    #<?php echo $widget_id; ?> body, #<?php echo $widget_id; ?> { /* Apply to widget root or body if it's a full document fragment */
        font-family: 'Noto Sans KR', sans-serif;
        background-color: #121212; /* Default background for the widget area */
        color: #FFFFFF;
    }
    #<?php echo $widget_id; ?> .bg-surface {
        background-color: #1E1E1E;
    }
    #<?php echo $widget_id; ?> .text-primary { color: #FFFFFF; }
    #<?php echo $widget_id; ?> .text-secondary { color: #B3B3B3; }
    #<?php echo $widget_id; ?> .text-accent-positive { color: #18C683; }
    #<?php echo $widget_id; ?> .text-accent-negative { color: #F44336; }
    #<?php echo $widget_id; ?> .table-header th {
        background-color: #1E1E1E;
        border-bottom: 1px solid #333;
        color: #B3B3B3;
        font-weight: normal;
        padding: 0.5rem; /* Adjusted padding */
        font-size: 0.875rem; /* Adjusted font size */
    }
    #<?php echo $widget_id; ?> .table-row td {
        padding: 0.5rem; /* Adjusted padding */
        font-size: 0.875rem; /* Adjusted font size */
        border-bottom: 1px solid #2A2A2A;
    }
    #<?php echo $widget_id; ?> .table-row-odd { background-color: #2A2A2A; }
    #<?php echo $widget_id; ?> .table-row-even { background-color: #1E1E1E; }
    #<?php echo $widget_id; ?> .table-row:hover { background-color: #363636; }
    #<?php echo $widget_id; ?> .align-right { text-align: right; }
    #<?php echo $widget_id; ?> .align-left { text-align: left; }
    #<?php echo $widget_id; ?> .text-primary-color { color: #BB86FC; }

    /* Simplified table for widget */
    #<?php echo $widget_id; ?> .kimchi-premium-table {
        width: 100%;
        border-collapse: collapse;
    }
    #<?php echo $widget_id; ?> .kimchi-premium-table th,
    #<?php echo $widget_id; ?> .kimchi-premium-table td {
        padding: 8px;
        text-align: left;
    }
    #<?php echo $widget_id; ?> .last-updated {
        font-size: 0.75rem;
        color: #B3B3B3;
        text-align: right;
        padding-top: 5px;
    }
</style>

<div id="<?php echo $widget_id; ?>" class="bg-surface p-3 rounded-lg shadow-md">
    <h2 class="text-xl font-semibold text-primary-color mb-3">김치 프리미엄 (BTC)</h2>
    <table class="kimchi-premium-table w-full">
        <thead class="table-header">
            <tr>
                <th class="align-left">항목</th>
                <th class="align-right">가격</th>
            </tr>
        </thead>
        <tbody>
            <tr class="table-row table-row-odd">
                <td data-label="항목">업비트 (KRW)</td>
                <td data-label="업비트 가격" id="kimp-upbit-price" class="align-right">불러오는 중...</td>
            </tr>
            <tr class="table-row table-row-even">
                <td data-label="항목">바이낸스 (USDT)</td>
                <td data-label="바이낸스 USDT 가격" id="kimp-binance-usdt-price" class="align-right">불러오는 중...</td>
            </tr>
            <tr class="table-row table-row-odd">
                <td data-label="항목">바이낸스 (KRW)</td>
                <td data-label="바이낸스 KRW 가격" id="kimp-binance-krw-price" class="align-right">불러오는 중...</td>
            </tr>
            <tr class="table-row table-row-even">
                <td data-label="항목">환율 (USD/KRW)</td>
                <td data-label="환율" id="kimp-exchange-rate" class="align-right">불러오는 중...</td>
            </tr>
            <tr class="table-row table-row-odd">
                <td data-label="항목">김치 프리미엄</td>
                <td data-label="프리미엄" id="kimp-premium" class="align-right font-bold">불러오는 중...</td>
            </tr>
        </tbody>
    </table>
    <div id="kimp-timestamp" class="last-updated"></div>
    <div id="kimp-error" class="text-accent-negative mt-2"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const widgetElement = document.getElementById('<?php echo $widget_id; ?>');
    if (!widgetElement) {
        console.error('Kimchi Premium Widget container not found: #<?php echo $widget_id; ?>');
        return;
    }

    const upbitPriceEl = widgetElement.querySelector('#kimp-upbit-price');
    const binanceUsdtPriceEl = widgetElement.querySelector('#kimp-binance-usdt-price');
    const binanceKrwPriceEl = widgetElement.querySelector('#kimp-binance-krw-price');
    const exchangeRateEl = widgetElement.querySelector('#kimp-exchange-rate');
    const premiumEl = widgetElement.querySelector('#kimp-premium');
    const timestampEl = widgetElement.querySelector('#kimp-timestamp');
    const errorEl = widgetElement.querySelector('#kimp-error');

    function formatPrice(price) {
        if (typeof price !== 'number') return price;
        return price.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
    }

    function updateWidget() {
        // Adjust the path to kimchi_premium.ajax.php according to your GnuBoard structure
        // G5_PLUGIN_URL should be available if GnuBoard's environment is properly loaded.
        // If not, you might need to hardcode or derive it.
        const ajaxUrl = (typeof G5_PLUGIN_URL !== 'undefined' ? G5_PLUGIN_URL : './plugin') + '/kimchi_premium/kimchi_premium.ajax.php';

        fetch(ajaxUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                errorEl.textContent = ''; // Clear previous errors
                if (data.error) {
                    errorEl.textContent = '데이터 로딩 실패: ' + data.error;
                    premiumEl.textContent = '오류';
                    premiumEl.className = 'align-right font-bold'; // Reset class
                    // Clear other fields or set to error state
                    upbitPriceEl.textContent = '-';
                    binanceUsdtPriceEl.textContent = '-';
                    binanceKrwPriceEl.textContent = '-';
                    exchangeRateEl.textContent = '-';
                    timestampEl.textContent = '마지막 업데이트: 오류';
                    return;
                }

                upbitPriceEl.textContent = data.upbit_krw_btc ? formatPrice(data.upbit_krw_btc) + ' KRW' : '-';
                binanceUsdtPriceEl.textContent = data.binance_btc_usdt ? formatPrice(data.binance_btc_usdt) + ' USDT' : '-';
                binanceKrwPriceEl.textContent = data.binance_krw_btc ? formatPrice(data.binance_krw_btc) + ' KRW' : '-';
                exchangeRateEl.textContent = data.usd_krw_rate ? formatPrice(data.usd_krw_rate) : '-';

                premiumEl.textContent = data.premium + '%';
                if (data.premium > 0) {
                    premiumEl.className = 'align-right font-bold text-accent-positive';
                } else if (data.premium < 0) {
                    premiumEl.className = 'align-right font-bold text-accent-negative';
                } else {
                    premiumEl.className = 'align-right font-bold text-primary';
                }

                const date = new Date(data.timestamp * 1000);
                timestampEl.textContent = '마지막 업데이트: ' + date.toLocaleString('ko-KR');
            })
            .catch(error => {
                console.error('Error fetching kimchi premium data:', error);
                errorEl.textContent = '데이터 로딩 중 오류가 발생했습니다: ' + error.message;
                premiumEl.textContent = '오류';
                premiumEl.className = 'align-right font-bold'; // Reset class
                timestampEl.textContent = '마지막 업데이트: 오류';
                 // Clear other fields
                upbitPriceEl.textContent = '-';
                binanceUsdtPriceEl.textContent = '-';
                binanceKrwPriceEl.textContent = '-';
                exchangeRateEl.textContent = '-';
            });
    }

    updateWidget(); // Initial call
    setInterval(updateWidget, 10000); // Update every 10 seconds
});
</script>
