<?php

require 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

class StripeProductService
{
    private $currency;
    private $productNames;
    private $priceArray;
    private $productCount;
    private $type;

    public function __construct($apiKey, $priceArray, $currency = 'usd', $productNames = [], $productCount = 3, $type = 1)
    {
        Stripe::setApiKey($apiKey);
        $this->currency = $currency;
        $this->priceArray = $priceArray;
        $this->productNames = $productNames ?: [
            "Entire Total", "Full Total", "Overall Total", "Complete Total", "Whole Total",
            "Sum Total", "Gross Total", "Final Amount", "Complete Sum", "Grand Total"
        ];
        $this->productCount = $productCount;
        $this->type = $type;
    }

    /**
     * 创建指定数量的产品及对应价格
     * @return array
     */
    public function createProducts()
    {
        $names = $this->productNames;
        shuffle($names);  // 随机打乱产品名
        $chosenNames = array_slice($names, 0, $this->productCount);

        // 将价格数组按顺序随机切割成若干区间
        $priceChunks = $this->getRandomPriceChunks();

        $result = [];
        foreach ($chosenNames as $index => $productName) {
            // 获取该产品的价格数组
            $productPrices = $priceChunks[$index];

            // 创建产品
            $product = Product::create([
                'name' => $productName, 
            ]);

            // 为产品创建多个价格
            $productInfo = [
                'product_name' => $productName,
                'product_id'   => $product->id,
                'prices'        => []
            ];

            foreach ($productPrices as $priceValue) {
                // 为每个价格创建价格对象
                $price = Price::create([
                    'product' => $product->id,
                    'unit_amount' => intval(round($priceValue * 100)), // 美分
                    'currency' => $this->currency,
                ]);

                $productInfo['prices'][] = [
                    'amount' => number_format($priceValue, 2, '.', ''),
                    'unit_amount' => intval(round($priceValue * 100)),
                    'currency' => $this->currency,
                    'stripe_price_id' => $price->id,
                    'product_id' => $product->id,
                    'product_name' => $productName,
                    'product_statement_descriptor' => $productName,
                    'product_tax_code' => 'tax_code', // This is just a placeholder
                    'description' => 'Product description here', // Example, replace with actual description
                    'created_at' => date('Y-m-d H:i:s', $price->created),
                    'interval' => 'month', // Example, replace with actual interval if needed
                    'interval_count' => 1,
                    'usage_type' => 'licensed',
                    'aggregate_usage' => null, // Placeholder, replace if needed
                    'billing_scheme' => 'per_unit',
                    'trial_period_days' => null, // Placeholder
                    'tax_behavior' => 'exclusive', // Placeholder
                ];
            }

            $result[] = $productInfo;
        }

        // 根据type生成不同的CSV
        $this->generateCSV($result);

        return $result;
    }

    /**
     * 随机将价格数组切分成若干区间
     * @return array
     */
    private function getRandomPriceChunks()
    {
        $priceCount = count($this->priceArray);
        $remainingPrices = $this->priceArray;
        $priceChunks = [];

        // 随机分配价格区间
        for ($i = 0; $i < $this->productCount; $i++) {
            // 随机选择切分点，确保价格分布在产品间
            $splitPoint = rand(1, count($remainingPrices) - 1);
            $productPrices = array_splice($remainingPrices, 0, $splitPoint);
            $priceChunks[] = $productPrices;
        }

        // 最后一个产品，剩下的所有价格
        if (count($remainingPrices) > 0) {
            $priceChunks[] = $remainingPrices;
        }

        return $priceChunks;
    }

    /**
     * 根据type生成不同的CSV文件
     * type=1 生成一个CSV，只含价格和价格ID
     * type=2 生成两个CSV文件，一个含价格和价格ID，另一个含产品信息和价格
     */
    private function generateCSV($data)
    {
        if ($this->type == 1) {
            // 生成价格和价格ID的CSV
            $csvData = [];
            #$csvData[] = 'price_id,price';
            foreach ($data as $row) {
                foreach ($row['prices'] as $price) {
                    $csvData[] = "{$price['stripe_price_id']},{$price['amount']}";
                }
            }
            $this->saveCSV('product.csv', $csvData);
        } elseif ($this->type == 2) {
            // 生成两个CSV，一个包含价格和价格ID，一个包含产品详细信息
            // 价格和价格ID CSV
            $csvDataPrices = [];
            $csvDataPrices[] = 'price_id,price';
            foreach ($data as $row) {
                foreach ($row['prices'] as $price) {
                    $csvDataPrices[] = "{$price['stripe_price_id']},{$price['amount']}";
                }
            }
            $this->saveCSV('product.csv', $csvDataPrices);

            // 产品详细信息和价格 CSV
            $csvDataDetails = [];
            $csvDataDetails[] = 'Price ID,Product ID,Product Name,Product Statement Descriptor,Product Tax Code,Description,Created (UTC),Amount,Currency,Interval,Interval Count,Usage Type,Aggregate Usage,Billing Scheme,Trial Period Days,Tax Behavior';
            foreach ($data as $row) {
                foreach ($row['prices'] as $price) {
                    $csvDataDetails[] = "{$price['stripe_price_id']},{$price['product_id']},{$price['product_name']},{$price['product_statement_descriptor']},{$price['product_tax_code']},{$price['description']},{$price['created_at']},{$price['amount']},{$price['currency']},{$price['interval']},{$price['interval_count']},{$price['usage_type']},{$price['aggregate_usage']},{$price['billing_scheme']},{$price['trial_period_days']},{$price['tax_behavior']}";
                }
            }
            $this->saveCSV('product_prices.csv', $csvDataDetails);
        }
    }

    /**
     * 保存CSV文件到本地
     * @param string $filename 文件名
     * @param array $data 数据
     */
    private function saveCSV($filename, $data)
    {
        $file = fopen($filename, 'w');
        foreach ($data as $row) {
            fputcsv($file, explode(',', $row));
        }
        fclose($file);
        echo "CSV file '$filename' generated successfully!\n";
    }
}

//// ========== 用法示例 ==========
//
//$apiKey = 'sk_live_xxx'; // 替换为你的实际Stripe密钥
//$priceArray = range(5, 12); // 提供的价格数组，如 [5,6,7,8,9,10,11,12]
//$productNames = [
//    "Entire Total", "Full Total", "Overall Total", "Complete Total", "Whole Total",
//    "Sum Total", "Gross Total", "Final Amount", "Complete Sum", "Grand Total"
//];
//$productCount = 2; // 生成2个产品
//$type = 2; // type=1 生成一个CSV文件，type=2 生成两个CSV文件
//
//$generator = new StripeProductService($apiKey, $priceArray, 'usd', $productNames, $productCount, $type);
//$products = $generator->createProducts();
//print_r($products); // 输出产品信息和价格
