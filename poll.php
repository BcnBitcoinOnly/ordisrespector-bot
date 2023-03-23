<?php

declare(strict_types=1);

if (!isset($_SERVER['LNBITS_API_KEY'])) {
    die('LNBITS_API_KEY env var is not defined' . PHP_EOL);
}

require_once __DIR__ . '/vendor/autoload.php';

touch(__DIR__ . '/.last-payment');
$lastPayment = file_get_contents(__DIR__ . '/.last-payment');

$response = (new \GuzzleHttp\Client())->get(
    'https://legend.lnbits.com/api/v1/payments?limit=20',
    ['headers' => ['X-Api-Key' => $_SERVER['LNBITS_API_KEY']]]
);

$data = json_decode((string) $response->getBody());

$triggerBot = false;
foreach ($data as $payment) {
    if ($payment->checking_id === $lastPayment) {
        break;
    }

    if ($payment->amount >= 100000) {
        $triggerBot = true;
    }
}

if ($triggerBot) {
    echo shell_exec('php ' . __DIR__ . '/bot.php | noscl publish -');
}

if (!empty($data)) {
    file_put_contents(__DIR__ . '/.last-payment', $data[0]->checking_id);
}
