<?php

namespace App\Services;

/** Internal evidence of a validated server-to-server REST verification, never request input. */
final readonly class ZarinpalVerification
{
    public function __construct(
        public int $attemptId,
        public int $orderId,
        public string $environment,
        public string $merchantContext,
        public string $authority,
        public string $amount,
        public string $unit,
        public string $reference,
        public int $code,
    ) {}
}
