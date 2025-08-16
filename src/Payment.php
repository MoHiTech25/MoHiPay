<?php

namespace MoHiTech\MoHiPay;

use Illuminate\Support\Facades\Http;
use MoHiTech\MoHiPay\Config;
use MoHiTech\MoHiPay\Models\PaymentModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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
            throw new Exception("Payment creation failed: No valid payment ID returned from API.");
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
            throw new Exception("Invalid transaction: No payment found with ID {$payment_id}.");
        }

        $data = $this->postApi('/api/checkout/verify', ['payment_id' => $payment_id]);

        $status = $data->data->status ?? null;

        if (!in_array($status, [PaymentModel::STATUS_SUCCESS, PaymentModel::STATUS_FAILED])) {
            throw new Exception("Payment verification failed: Invalid status '{$status}' received.");
        }
        
        DB::transaction(function () use ($payment, $data, $status) {
            $payment->payment_gateway = "MoHiPay";
            $payment->status = $status;
            $payment->paid_at = now();
            $payment->response_data = json_encode($data);
            $payment->save();
        });

        if ($status === PaymentModel::STATUS_FAILED) {
            throw new Exception("Payment unsuccessful: Please check your details and try again.");
        }

        return $payment;
    }

    private function validatePaymentInputs(float $amount, string $email, string $callback_url): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address: {$email}");
        }

        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0.");
        }

        if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid callback URL: {$callback_url}");
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
            throw new Exception("API request failed with status {$response->status()}.");
        }

        if (isset($data->status) && $data->status === false) {
            $message = $data->message ?? 'API request returned status false.';
            throw new Exception("API Error: {$message}");
        }

        return $response->object();
    }
}
