<?php

namespace MoHiTech\MoHiPay\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MoHiTech\MoHiPay\Config;
use MoHiTech\MoHiPay\Payment;

class PayController extends Controller
{
    protected $mohitech;

    public function __construct()
    {
        $config = new Config();
        $config->base_url = 'https://pay.devoo.fun';

        $this->mohitech = new Payment($config);
    }

    public function pay($user_id, $amount, $email, $callback)
    {
        try {
            return $this->mohitech->createPayment($user_id, $amount, $email, $callback);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function callback($payment_id)
    {
        try {
            return $this->mohitech->callback($payment_id);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

}