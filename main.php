// main.php
<?php
require_once 'config.php';
require_once 'stripeQueryService.php';
require_once 'stripeRefundService.php';
require_once 'stripeProductService.php';

//php main.php -refund --translateId=
// 获取命令行参数
$options = getopt('', ['refund', 'transactionId:', 'amount:', 'createProduct', 'productName:', 'productPrice:','last4s:']);

if (isset($options['refund'])) {
    // 处理退款操作
    if (!isset($options['transactionId'])) {
        echo "Error: --transactionId is required for refund.\n";
        exit(1);
    }


    $amount = isset($options['amount']) ? $options['amount'] : null;

    // 创建退款服务实例
    $refundService = new StripeRefundService(STRIPE_SK,LOCAL_CURRENCY);

    if (!isset($options['transactionId'])) {
        $refundService->processRefundFromFile(TRANSACTION_FILE);
    }else{
        $transactionId = $options['transactionId'];
        $refund = $refundService->refundCharge($transactionId, $amount);
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
    if (isset($options['last4'])) {
         $param['last4s']=$options['last4'];
    }

    $smartSearch = new StripeQueryService(STRIPE_SK);
    $result = $smartSearch->smartSearch($param);

    var_dump($result);
}else {
    echo "Error: Invalid command. Use --refund for refund, or --createProduct for product creation.\n";
    exit(1);
}
