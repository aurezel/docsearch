// main.php
<?php
require_once 'config.php';
require_once 'stripeQueryService.php';
require_once 'stripeRefundService.php';
require_once 'stripeProductService.php';


//php main.php -refund --translateId=
// 获取命令行参数
$options = getopt('', ['refund', 'transactionId:', 'amount:', 'product', 'prices:','count:','search','last4s:','emails:','transIds:','type:','date:','edate:','link:','arn:','all','info','stat:','currency:']);

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
        echo "Refund status: " . json_encode($refund) . "\n";
    }


} elseif (isset($options['product'])) {
    // 处理产品创建
    if (isset($options['prices'])) {
       $prices = $options['prices'];
    }
	if (isset($options['count'])) {
       $count = $options['count'];
    }else{
		$count = 3;
    }
    if (!isset($options['names'])) {
        $productName = ["Entire Total","Full Total","Overall Total","Complete Total","Whole Total","Sum Total","Gross Total","Final Amount","Complete Sum","Grand Total","Entire Sum","Full Amount","Overall Sum","Whole Amount","Final Total","Aggregate Total","Final Sum","Net Total","Total Amount","Total Sum","Final Figure","Entire Amount","Final Value","Gross Amount","Grand Sum","Complete Figure","Cumulative Total","Complete Amount","Whole Figure","Net Amount","Full Sum","Absolute Total","Total Balance","Total Charge","Invoice Total","Final Count","Whole Count","Full Balance","Complete Balance","Total Value","Grand Figure","Final Payment","Total Quantity","Entire Balance","Final Settlement","Total Payable","Sum Amount","Final Gross","Gross Sum","Total Result","Total Revenue","Overall Charge","Overall Amount","Whole Charge","Total Collection","Total Number","Final Collection","Grand Amount","Complete Revenue","Final Charge","Entire Value","Full Count","Total Line","Full Settlement","Final Invoice","Total Cost","Final Output","Net Sum","Complete Output","Entire Figure","Whole Sum","Final Result","Total Due","Entire Invoice","Whole Payment","Overall Figure","Total Funds","Invoice Amount","Net Figure","Total Payment","Full Revenue","Invoice Sum","Final Total Value","Accumulated Total","Final Calculation","Summed Total","Finalized Amount","Full Gross","Calculated Total","Rounded Total","Fixed Total","Grand Invoice","Full Invoice","Closing Total","Statement Total","Entire Payable","Net Charge","Collected Total","Cleared Total","Statement Amount"];
    }

    // 创建产品服务实例 type1 product.csv,type2 product.csv product_prices.csv,
    $productService = new StripeProductService(STRIPE_SK,PRODUCT_PRICE,LOCAL_CURRENCY,$productName,$count,1);
    $product = $productService->createProducts();

    echo "Product created: " . $product->name . "\n";
    echo "Product ID: " . $product->id . "\n";
} elseif (isset($options['arnList'])) {
    if (isset($options['arn'])) {
        $param['arn']=anyToArray($options['arn']);
    }
}elseif(isset($options['info'])){	//--info --currency=eur --stat
	require_once 'stripeInfoService.php';
	$currency='usd';
	if(isset($options['currency'])){
		$currency = $options['currency'];
	}
	$service = new StripeInfoService(STRIPE_SK,$currency);
	$type=1;
	if(isset($options['stat']) && in_array($options['stat'],[2,3])){ 
		$type=$options['stat'];
	} 
	$service->getAllInfo($type);

	

} elseif (isset($options['search'])) {
    $param = [];
    if (isset($options['last4s'])) {
        $param['last4s']=anyToArray($options['last4s']);
    }
    if (isset($options['emails'])) {
        $param['emails']=anyToArray($options['emails']);
    }
    if (isset($options['arn'])) {
        $param['arn']=true;//anyToArray($options['arn']);
    }
    if (isset($options['all'])) {
        $param['all']=true;
    }
    if (isset($options['transIds'])) {
        $param['transactionIds']=anyToArray($options['transIds']);
    }
    if (isset($options['type'])) {
        $param['type']=$options['type'];
    }
    if (isset($options['date'])) {
        $param['date']=$options['date'];
    }
    if (isset($options['edate'])) {
        $param['edate']=$options['edate'];
    }
    if (isset($options['link'])) {
        $param['link']=$options['link'];
    }
    $smartSearch = new StripeQueryService(STRIPE_SK);
    $result = $smartSearch->smartSearch($param);
    if(!empty($result)){
        $smartSearch->toCsv($result);
    }else{
		echo "It's nothing in the search\n";
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