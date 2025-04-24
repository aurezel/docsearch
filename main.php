// main.php
<?php
require_once 'config.php';
require_once 'stripeQueryService.php';
require_once 'stripeRefundService.php';
require_once 'stripeProductService.php';

//php main.php -refund --translateId=
// 获取命令行参数
$options = getopt('', ['refund', 'transactionId:', 'amount:', 'createProduct', 'productName:', 'productPrice:','search','last4s:','emails','transactionIds','type:']);

if (isset($options['refund'])) {

    $amount = isset($options['amount']) ? $options['amount'] : null;

    // 创建退款服务实例
    $refundService = new StripeRefundService(STRIPE_SK,LOCAL_CURRENCY);

    if (!isset($options['transactionId'])) {
        $refundService->processRefundFromFile(TRANSACTION_FILE);
    }else{
        $transactionId = $options['transactionId'];
        $refund = $refundService->processRefundManually($transactionId, $amount);
        echo "Refund processed for transaction ID: $transactionId\n";
        echo "Refund status: " . $refund->status . "\n";
    }


} elseif (isset($options['createProduct'])) {
    // 处理产品创建
    if (!isset($options['productPrice'])) {
        echo "Error: --productName and --productPrice are required for product creation.\n";
        exit(1);
    }
    if (!isset($options['productName'])) {
        $productName = [
            "Entire Total", "Full Total", "Overall Total", "Complete Total", "Whole Total",
            "Sum Total", "Gross Total", "Final Amount", "Complete Sum", "Grand Total"
        ];
    }

    $productPrice = $options['productPrice'];

    // 创建产品服务实例
    $productService = new StripeProductService(STRIPE_SK,PRODUCT_PRICE,LOCAL_CURRENCY,$productName,3,1);
    $product = $productService->createProduct();

    echo "Product created: " . $product->name . "\n";
    echo "Product ID: " . $product->id . "\n";
} elseif (isset($options['arn'])) {
    // 处理产品创建
    if (!isset($options['productPrice'])) {
        echo "Error: --productName and --productPrice are required for product creation.\n";
        exit(1);
    }
    if (!isset($options['productName'])) {
        $productName = [
            "Entire Total", "Full Total", "Overall Total", "Complete Total", "Whole Total",
            "Sum Total", "Gross Total", "Final Amount", "Complete Sum", "Grand Total"
        ];
    }

    $productPrice = $options['productPrice'];

    // 创建产品服务实例
    $productService = new StripeProductService(STRIPE_SK,PRODUCT_PRICE,LOCAL_CURRENCY,$productName,3,1);
    $product = $productService->createProduct();

    echo "Product created: " . $product->name . "\n";
    echo "Product ID: " . $product->id . "\n";
} elseif (isset($options['search'])) {
    $param = [];
    if (isset($options['last4s'])) {
        $param['last4s']=anyToArray($options['last4s']);
    }
    if (isset($options['emails'])) {
        $param['emails']=anyToArray($options['emails']);
    }
    if (isset($options['transactionIds'])) {
        $param['transactionIds']=anyToArray($options['transactionIds']);
    }
    if (isset($options['type'])) {
        $param['type']=$options['type'];
    }
    $smartSearch = new StripeQueryService(STRIPE_SK);
    $result = $smartSearch->smartSearch($param);
    if(!empty($result)){
        $smartSearch->toCsv($result);
    }
}else {
    echo "Error: Invalid command. Use --refund for refund, or --createProduct for product creation.\n";
    exit(1);
}

function anyToArray($input) {
    if (is_array($input)) {
        // 支持多次 --last4s=xxxx，合并所有
        $result = [];
        foreach ($input as $item) {
            foreach (explode(',', $item) as $piece) {
                if (trim($piece) !== '') {
                    $result[] = trim($piece);
                }
            }
        }
        return $result;
    }
    if ($input === null || $input === '') return [];
    // 单值或逗号分隔
    return array_filter(array_map('trim', explode(',', $input)));
}