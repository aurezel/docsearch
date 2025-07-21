<?php

require_once 'config.php';
require_once 'stripeQueryService.php';
require_once 'stripeRefundService.php';
require_once 'stripeProductService.php';
require_once 'stripeWebhookService.php';
require_once 'stripeInfoService.php';

// 获取 CLI 参数
$options = getopt('', [
    'refund',
    'transactionId:',
    'amount:',
    'product',
    'param:',
    'prices:',
    'count:',
    'names:',
    'search',
    'last4s:',
    'emails:',
    'transIds:',
    'type:',
    'date:',
    'edate:',
    'link:',
    'arn:',
    'all',
    'info',
    'stat:',
    'currency:',
    'settings',
    'webhook:',
    'domain:',
    'path:',
]);

//# 发起退款
//php main.php --refund --transactionId=ch_123 --amount=1000
//
//# 批量产品创建
//php main.php --product --param=create
//
//# 查看价格列表
//php main.php --product --param=priceList
//
//# 创建 Webhook
//php main.php --settings --param=webhook --domain='https://checkout.example.com' --path='v1/StripeBankNotify'
//
//# 查询账户余额
//php main.php --info --currency=eur --param=balance
//
//# 搜索
//php main.php --search --last4s=1234,5678 --emails=test@example.com

// === 退款处理 ===
if (isset($options['refund'])) {
    handleRefund($options);

// === 产品处理 ===
} elseif (isset($options['product'])) {
    handleProduct($options);

// === 创建 Webhook ===
} elseif (isset($options['settings']) && ($options['param'] ?? '') === 'webhook') {
    handleWebhook($options);

// === 信息查询（账户、余额、ARN 等）===
} elseif (isset($options['info'])) {
    handleInfo($options);

// === 搜索交易 ===
} elseif (isset($options['search'])) {
    handleSearch($options);

} else {
    echo "⚠️ Error: Invalid command.\n";
    echo "Available: --refund | --product | --settings --param=webhook | --info | --search\n";
    exit(1);
}

//
// ========== 以下为功能封装 ==========
//

function handleRefund(array $options)
{
    $refundService = new StripeRefundService(STRIPE_SK, LOCAL_CURRENCY);
    $amount = $options['amount'] ?? null;

    if (!isset($options['transactionId'])) {
        $refundService->processRefundFromFile(TRANSACTION_FILE);
    } else {
        $transactionId = $options['transactionId'];
        $refund = $refundService->processRefundManually($transactionId, $amount);
        echo "✅ Refund processed for transaction ID: $transactionId\n";
        echo "🧾 Refund response: " . json_encode($refund, JSON_PRETTY_PRINT) . "\n";
    }
}

function handleProduct(array $options)
{
    $count = $options['count'] ?? 3;
    $prices = $options['prices'] ?? null;
    $productNames = $options['names'] ?? getDefaultProductNames();

    $productService = new StripeProductService(STRIPE_SK, PRODUCT_PRICE, LOCAL_CURRENCY, $productNames, $count, 1);

    if (($options['param'] ?? '') === 'priceList') {
        $productService->priceList();
        return;
    }

    if (($options['param'] ?? '') === 'create') {
        $product = $productService->createProducts();
        print_r($product);
    }
}

function handleWebhook(array $options)
{
    $domain = $options['domain'] ?? '';
    $path = $options['path'] ?? '';
    $event = []; // 可从 CLI 传入事件支持扩展

    if (!$domain || !$path) {
        echo "⚠️ Error: Please provide both --domain and --path\n";
        exit(1);
    }

    $webhookService = new StripeWebhookService(STRIPE_SK);
    $result = $webhookService->createWebhook($domain, $path, $event);
    print_r($result);
}

function handleInfo(array $options)
{
    $currency = $options['currency'] ?? 'usd';
    $param = $options['param'] ?? 'account';
    $param = in_array($param, ['account', 'balance', 'arn', 'payout','analysis'], true) ? $param : 'account';

    $infoService = new StripeInfoService(STRIPE_SK, $currency);
    $infoService->getAllInfo($param);
}

function handleSearch(array $options)
{
    $searchParams = [
        'last4s' => anyToArray($options['last4s'] ?? ''),
        'emails' => anyToArray($options['emails'] ?? ''),
        'transactionIds' => anyToArray($options['transIds'] ?? ''),
        'type' => $options['type'] ?? null,
        'date' => $options['date'] ?? null,
        'edate' => $options['edate'] ?? null,
        'link' => $options['link'] ?? null,
        'arn' => isset($options['arn']),
        'all' => isset($options['all']),
    ];

    $queryService = new StripeQueryService(STRIPE_SK);
    $result = $queryService->smartSearch($searchParams);

    if (!empty($result)) {
        $queryService->toCsv($result);
    } else {
        echo "🔍 No search results found.\n";
    }
}

function anyToArray($input)
{
    if (is_array($input)) {
        return array_filter(array_merge(...array_map(fn($x) => explode(',', $x), $input)), fn($i) => trim($i) !== '');
    }
    return $input ? array_filter(array_map('trim', explode(',', $input))) : [];
}

function getDefaultProductNames(): array
{
    return [
        "Entire Total", "Full Total", "Overall Total", "Complete Total", "Whole Total", "Sum Total",
        "Gross Total", "Final Amount", "Complete Sum", "Grand Total", "Entire Sum", "Full Amount",
        "Overall Sum", "Whole Amount", "Final Total", "Aggregate Total", "Final Sum", "Net Total",
        "Total Amount", "Total Sum", "Final Figure", "Entire Amount", "Final Value", "Gross Amount",
        "Grand Sum", "Complete Figure", "Cumulative Total", "Complete Amount", "Whole Figure",
        "Net Amount", "Full Sum", "Absolute Total", "Total Balance", "Total Charge", "Invoice Total",
        "Final Count", "Whole Count", "Full Balance", "Complete Balance", "Total Value", "Grand Figure",
        "Final Payment", "Total Quantity", "Entire Balance", "Final Settlement", "Total Payable",
        "Sum Amount", "Final Gross", "Gross Sum", "Total Result", "Total Revenue", "Overall Charge"
    ];
}
