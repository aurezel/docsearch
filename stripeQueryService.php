<?php

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Charge;

class StripeQueryService
{
    public function __construct($apiKey)
    {
        Stripe::setApiKey($apiKey);
    }

    /**
     * 综合查询：邮箱、卡后四位、交易号、时间
     * 支持组合任意查询
     * @param array $params 结构示例：
     *  [
     *    'emails' => ['a@a.com', ...], // 可选
     *    'last4s' => ['4242','8888'],   // 可选，多个后四位
     *    'transaction_ids' => ['ch_xxx','ch_yyy'], // 可选
     *    'type'  => 1 // 时间区间类型，见getDateRangeByType
     *  ]
     */
    public function smartSearch(array $params = []): array
    {
        $results = [];

        // 处理参数
        $emails   = array_map('strtolower', $params['emails'] ?? []);
        $last4s   = $params['last4s'] ?? [];
        $txnIds   = $params['transactionIds'] ?? [];
        $type     = $params['type'] ?? 0;
        [$startTime, $endTime] = $this->getDateRangeByType($type);

        // 1. 精准交易号查，优先
        if (!empty($txnIds)) {
            foreach ($txnIds as $txnId) {
                try {
                    $charge = Charge::retrieve($txnId);
                    if ($this->chargeMatch($charge, $emails, $last4s, $startTime, $endTime)) {
                        $results[] = $this->formatCharge($charge);
                    }
                } catch (\Exception $e) {
                    // 交易号查不到/不存在时跳过
                }
            }
            return $results;
        }

        // 2. 邮箱分组处理
        $emailToCustomerId = [];
        $noCustomerEmails = [];
        if(!empty($emails)){
            foreach ($emails as $email) {
                $customers = \Stripe\Customer::all(['email' => $email]);
                if (!empty($customers->data)) {
                    $emailToCustomerId[$email] = $customers->data[0]->id;
                } else {
                    $noCustomerEmails[] = $email;
                }
            }
        }


        // 3. 通过客户ID查交易
        if(!empty($emailToCustomerId)){
            foreach ($emailToCustomerId as $email => $customerId) {
                $chargeParams = [
                    'customer' => $customerId,
                    'limit'    => 100,
                    'created'  => ['gte' => $startTime, 'lte' => $endTime]
                ];
                foreach (Charge::all($chargeParams)->autoPagingIterator() as $charge) {
                    if ($this->chargeMatch($charge, [], $last4s)) {
                        $results[] = $this->formatCharge($charge);
                    }
                }
            }
        }

        // 4. 没有客户ID的邮箱，遍历全表查邮箱
        if (!empty($noCustomerEmails) || !empty($last4s)) {
            $emailSet = array_flip($noCustomerEmails);
            $last4Set = array_flip($last4s);
            $chargeParams = [
                'limit'   => 100,
                'created' => ['gte' => $startTime, 'lte' => $endTime]
            ];
            foreach (Charge::all($chargeParams)->autoPagingIterator() as $charge) {
                $email = strtolower($charge->billing_details->email ?? $charge->receipt_email ?? '');echo $email."\n";
                $matchEmail = empty($emailSet) ? true : isset($emailSet[$email]); var_dump("emails:",$matchEmail);
                $matchLast4 = empty($last4s) ? true : isset($last4Set[$charge->payment_method_details->card->last4 ?? '']); var_dump("last4:",$matchLast4);
                if (($matchEmail || $matchLast4)) {
                    $results[] = $this->formatCharge($charge);
                }
            }
        }

        // 5. 仅查卡后四位（未传邮箱/交易号）
//        if (empty($emails) && !empty($last4s)) {
//            $last4Set = array_flip($last4s);
//            $chargeParams = [
//                'limit'   => 100,
//                'created' => ['gte' => $startTime, 'lte' => $endTime]
//            ];
//            foreach (Charge::all($chargeParams)->autoPagingIterator() as $charge) {
//                $matchLast4 = isset($last4Set[$charge->payment_method_details->card->last4 ?? '']);
//                if ($matchLast4) {
//                    $results[] = $this->formatCharge($charge);
//                }
//            }
//        }

        return $results;
    }

    /**
     * 判断交易是否满足邮箱/后四位/时间范围
     */
    private function chargeMatch($charge, $emails = [], $last4s = [], $startTime = null, $endTime = null)
    {
        // 交易时间
        if ($startTime && $charge->created < $startTime) return false;
        if ($endTime && $charge->created > $endTime) return false;
        // 邮箱
        if (!empty($emails)) {
            $email = strtolower($charge->billing_details->email ?? $charge->receipt_email ?? '');
            if (!in_array($email, $emails)) return false;
        }
        // 卡后四位
        if (!empty($last4s)) {
            $last4 = $charge->payment_method_details->card->last4 ?? '';
            if (!in_array($last4, $last4s)) return false;
        }
        return true;
    }

    /**
     * type=1: 近15天，2: 近30天，3: 近60天，4: 60~120天前，5: 120~180天前，默认7天
     */
    private function getDateRangeByType($type): array
    {
        $now = strtotime('today') + 86399; // 今天23:59:59
        switch ($type) {
            case 1: return [$now - 14 * 86400, $now];              // 近15天
            case 2: return [$now - 29 * 86400, $now];              // 近30天
            case 3: return [$now - 59 * 86400, $now];              // 近60天
            case 4: return [$now - 119 * 86400, $now - 60 * 86400];// 60~120天前
            case 5: return [$now - 179 * 86400, $now - 120 * 86400];//120~180天前
            default: return [$now - 6 * 86400, $now];              // 近7天
        }
    }

    private function formatCharge($charge)
    {
        $refundStatus = 'none';
        $refundAmount = 0;
        if ($charge->amount_refunded > 0) {
            $refundAmount = $charge->amount_refunded / 100;
            $refundStatus = ($refundAmount == ($charge->amount / 100)) ? 'fully_refunded' : 'partially_refunded';
        }
        return [
            $charge->billing_details->email ?? $charge->receipt_email ?? '',
            $charge->id,
            number_format($charge->amount / 100, 2, '.', ''),
            strtoupper($charge->currency),
            $charge->status,
            $charge->payment_intent ?? '',
            $refundStatus,
            number_format($refundAmount, 2, '.', ''),
            date('Y-m-d H:i:s', $charge->created)
        ];
    }

    public function toCsv(array $data)
    {
        $lines = [];
        $lines[] = 'email,transaction_id,amount,currency,status,paymentIntent,refundStatus,refundAmount,created_at';
        foreach ($data as $row) {
            $lines[] = implode(',', array_map(function($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row));
        }
        echo  implode("\n", $lines);
        $this->saveCSV(TRANSACTION_FILE,$lines);
    }

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

//// ====== 示例调用 ======
//
//$stripeApiKey = 'sk_live_xxx';
//$stripe = new StripeQueryService($stripeApiKey);
//
//$params = [
//    'emails'         => ['test1@xx.com', 'test2@xx.com'],
//    'last4s'         => ['4242', '8888'],
//    'transaction_ids'=> [], // 或 ['ch_xxx']，精准查
//    'type'           => 3,  // 60天
//];
//
//$data = $stripe->smartSearch($params);
//echo $stripe->toCsv($data);

