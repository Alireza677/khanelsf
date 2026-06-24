<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function method(): string;

    public function initialPaymentStatus(): string;

    public function instructions(): string;
}
