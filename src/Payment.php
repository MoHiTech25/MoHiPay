<?php

namespace MoHiTech\MoHiPay;

use Illuminate\Support\Facades\Http;
use MoHiTech\MoHiPay\Config;
use MoHiTech\MoHiPay\Models\PaymentModel;
use Illuminate\Support\Facades\DB;
use Exception;

class Payment
{
    private string $base_url;
    private string $api_key;

    public function __construct(Config $config)
    {
        $this->base_url = $config->base_url;
        $this->api_key = $config->api_key;
    }

    public function createPayment(int $user_id, float $amount, string $email, string $callback_url): object
    {
        $this->validatePaymentInputs($amount, $email, $callback_url);

        $data = $this->postApi('/api/checkout/request', [
            "name"         => '',
            "email"        => $email ?? '',
            "phone"        => '',
            "address"      => '',
            "amount"       => $amount,
            "redirect_url" => $callback_url,
        ]);

        if (!isset($data->status, $data->payment_id, $data->payment_url, $data->amount) || $data->status !== true) {
            throw new Exception("We were unable to process your payment request. Please try again.");
        }

        PaymentModel::updateOrCreate(
            ['transaction_id' => $data->payment_id],
            [
                'user_id'       => $user_id,
                'amount'        => $data->amount,
                'status'        => PaymentModel::STATUS_PENDING,
                'response_data' => json_encode($data),
            ]
        );

        return $data;
    }

    public function callback(string $payment_id): object
    {
        $payment = PaymentModel::where('transaction_id', $payment_id)->first();
        if (!$payment) {
            throw new Exception("The payment could not be found. Please contact support.");
        }

        $data = $this->postApi('/api/checkout/verify', ['payment_id' => $payment_id]);

        $status = $data->data->status ?? null;

        if (!in_array($status, [PaymentModel::STATUS_SUCCESS, PaymentModel::STATUS_FAILED])) {
            throw new Exception("We could not verify your payment at this time. Please try again later.");
        }
        
        DB::transaction(function () use ($payment, $data, $status) {
            $payment->payment_gateway = "MoHiPay";
            $payment->status = $status;
            $payment->paid_at = now();
            $payment->response_data = json_encode($data);
            $payment->save();
        });

        if ($status === PaymentModel::STATUS_FAILED) {
            throw new Exception("Your payment was not successful. Please try again or use a different method.");
        }

        return $payment;
    }

    private function validatePaymentInputs(float $amount, string $email, string $callback_url): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please provide a valid email address.");
        }

        if ($amount <= 0) {
            throw new Exception("Please enter a valid amount greater than zero.");
        }

        if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
            throw new Exception("The provided return URL is not valid.");
        }
    }

    private function postApi(string $endpoint, array $payload): object
    {
        $response = Http::acceptJson()
                        ->contentType('application/json')
                        ->withHeaders([
                            'x-api-key' => $this->api_key,
                        ])
                        ->post($this->base_url . $endpoint, $payload);

        if (!$response->successful()) {
            throw new Exception("We are unable to connect to the payment server. Please try again later.");
        }

        $data = $response->object();

        if (isset($data->status) && $data->status === false) {
            $message = $data->message ?? 'The payment request was not successful.';
            throw new Exception($message);
        }

        return $data;
    }
}
