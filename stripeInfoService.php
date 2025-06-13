<?php

require 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Balance;

class StripeInfoService
{
	private string $currency;
    public function __construct(string $secretKey, string $currency='usd')
    {
        Stripe::setApiKey($secretKey);
		$this->currency = strtolower($currency);
    }

    public function getAccountStatus(): array
    {
        $account = Account::retrieve();
		
#		$requirements = $account->disabled_reason ?? null;

		#$underReview = !empty($requirements->currently_due) || !empty($requirements->past_due);

		// 输出信息
		 

#		echo "\n原因（currently_due）：\n";
#		print_r($requirements);
		
#		$isVerified = $account->details_submitted && $account->charges_enabled && $account->payouts_enabled;

#echo "是否完成审核： " . ($isVerified ? '是' : '否') . "\n";
#echo "被禁用的原因： " . ($account->disabled_reason ?? '无') . "\n";
 
        return [
            'id' => $account->id,
            'email' => $account->email,
            'charges_enabled' => $account->charges_enabled ? "true":"false",
            'payouts_enabled' => $account->payouts_enabled ? "true":"false",
            'details_submitted' => $account->details_submitted,
            'descriptor' => $account->settings['payments']['statement_descriptor'] ?? 'N/A',
        ];
    }

    public function getChargeStats(): array
    {
        $totalCharges = 0;
        $successfulCharges = 0;
        $totalRefundAmount = 0;
        $lastTransactionTime = 0;

        $charges = Charge::all(['limit' => 100]);

        foreach ($charges->autoPagingIterator() as $charge) {
            $totalCharges++;

            if ($charge->paid && !$charge->refunded) {
                $successfulCharges++;
            }

            if ($charge->refunded) {
                $totalRefundAmount += $charge->amount_refunded;
            }

            if ($charge->created > $lastTransactionTime) {
                $lastTransactionTime = $charge->created;
            }
        }

        return [
            'total_charges' => $totalCharges,
            'successful_charges' => $successfulCharges,
            'refund_amount' => $totalRefundAmount / 100,
			'currency' => strtoupper($this->currency),
            'last_transaction_time' => $lastTransactionTime > 0 ? date('Y-m-d H:i:s', $lastTransactionTime) : null,
        ];
    }
	
	function getVisaRefundsDetailed() {
		$twoMonthsAgo = strtotime('-2 months');
		$params = [
			'limit' => 100,
			'created' => ['gte' => $twoMonthsAgo],
		];

		$results = [];
		$hasMore = true;
		$startingAfter = null;

		while ($hasMore) {
			if ($startingAfter) {
				$params['starting_after'] = $startingAfter;
			}

			$refundList = \Stripe\Refund::all($params);

			foreach ($refundList->data as $refund) {
				$charge = \Stripe\Charge::retrieve($refund->charge);

				$cardDetails = $charge->payment_method_details->card ?? null;
				$cardBrand = $cardDetails ? strtolower($cardDetails->brand) : '';

				if ($cardBrand !== 'visa') {
					continue; // 非Visa跳过
				}

				// ARN通常无直接字段，示例从metadata尝试取，需根据实际情况调整
				$arn = $charge->metadata->arn ?? ($cardDetails->arn ?? null);

				$results[] = [
					'charge_id' => $charge->id,
					'email' => $charge->billing_details->email ?? '',
					'statement_descriptor' => $charge->statement_descriptor ?? '',
					'arn' => $arn,
					'charge_amount' => $charge->amount / 100,
					'charge_currency' => strtoupper($charge->currency),
					'charge_time' => date('Y-m-d H:i:s', $charge->created),
					'refund_amount' => $refund->amount / 100,
					'refund_time' => date('Y-m-d H:i:s', $refund->created),
					'card_brand' => ucfirst($cardBrand),
				];
			}

			$hasMore = $refundList->has_more;
			if ($hasMore) {
				$startingAfter = end($refundList->data)->id;
			}
		}

		return $results;
	}
	
    public function getBalance(): array
    {
        $balance = Balance::retrieve();
        $available = 0;
        $pending = 0;

        foreach ($balance->available as $item) {
            if ($item->currency === $currency) {
                $available += $item->amount;
            }
        }

        foreach ($balance->pending as $item) {
            if ($item->currency === $this->currency) {
                $pending += $item->amount;
            }
        }

        return [
            'available' => $available / 100,
            'pending' => $pending / 100,
            'total' => ($available + $pending) / 100,
			'currency' => strtoupper($this->currency),
        ];
    }

    public function getAllInfo($type=1): array
    {
		if($type==1){
			echo "=== 账号状态 ===\n";
			print_r($this->getAccountStatus());
		}
		 
		if($type==2){ 
			echo "\n=== 余额信息 ===\n";
			print_r($this->getBalance());
			echo "\n=== 交易统计 ===\n";
			print_r($data['charge_stats']);
		}
		
		if($type==3){ 
			echo "\n=== ARN与描述符信息 ===\n";
			print_r($this->getVisaRefundsDetailed()); 
		} 
		return [];
        return [
				'account_status' => $this->getAccountStatus(),
				'charge_stats' => $this->getChargeStats(),
				'balance' => $this->getBalance(),
			];
    }
}
