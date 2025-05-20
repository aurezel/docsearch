<?php

require 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Balance;

class StripeInfoService
{
    public function __construct(string $secretKey)
    {
        Stripe::setApiKey($secretKey);
    }

    public function getAccountStatus(): array
    {
        $account = Account::retrieve();

        return [
            'id' => $account->id,
            'email' => $account->email,
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
            'details_submitted' => $account->details_submitted,
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
            'refund_amount_usd' => $totalRefundAmount / 100,
            'last_transaction_time' => $lastTransactionTime > 0 ? date('Y-m-d H:i:s', $lastTransactionTime) : null,
        ];
    }

    public function getBalance(): array
    {
        $balance = Balance::retrieve();
        $available = 0;
        $pending = 0;

        foreach ($balance->available as $item) {
            if ($item->currency === 'usd') {
                $available += $item->amount;
            }
        }

        foreach ($balance->pending as $item) {
            if ($item->currency === 'usd') {
                $pending += $item->amount;
            }
        }

        return [
            'available_usd' => $available / 100,
            'pending_usd' => $pending / 100,
            'total_usd' => ($available + $pending) / 100,
        ];
    }

    public function getAllInfo(): array
    {
        return [
            'account_status' => $this->getAccountStatus(),
            'charge_stats' => $this->getChargeStats(),
            'balance' => $this->getBalance(),
        ];
    }
}
