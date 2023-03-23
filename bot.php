<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_SERVER['SPAMMY_MEMPOOL_WS'])) {
    die('SPAMMY_MEMPOOL_WS env var is not defined' . PHP_EOL);
}

if (!isset($_SERVER['ORDISRESPECTOR_MEMPOOL_WS'])) {
    die('ORDISRESPECTOR_MEMPOOL_WS env var is not defined' . PHP_EOL);
}

final class MempoolData
{
    public readonly int $unconfTxs;
    public readonly float $memoryUsageMBs;
    public readonly int $noPrioFee;
    public readonly int $lowPrioFee;
    public readonly int $mediumPrioFee;
    public readonly int $highPrioFee;
    public readonly int $nMempoolBlocks;

    public function __construct(string $wsEndpoint)
    {
        $client = new WebSocket\Client($wsEndpoint);
        $client->text('{"action":"init"}');
        $client->text('{"action":"want","data":["blocks","stats","mempool-blocks","live-2h-chart","watch-mempool"]}');

        $raw = json_decode($client->receive());

        $this->unconfTxs = $raw->mempoolInfo->size;
        $this->memoryUsageMBs = round((float)($raw->mempoolInfo->usage/1000000));
        $this->noPrioFee = $raw->fees->minimumFee;
        $this->lowPrioFee = $raw->fees->hourFee;
        $this->mediumPrioFee = $raw->fees->halfHourFee;
        $this->highPrioFee = $raw->fees->fastestFee;

        $mempoolBlocks = $raw->{'mempool-blocks'};
        $lastBlock = $mempoolBlocks[array_key_last($mempoolBlocks)];
        $this->nMempoolBlocks = $lastBlock->blockVSize <= 1000000 ?
            count($mempoolBlocks) : count($mempoolBlocks) + (int)($lastBlock->blockVSize/1000000);
    }

    public static function formatDelta(int|float $a, int|float $b, float $epsilon = 1.0): string
    {
        $c = round((($a - $b) / $b) * 100);

        return abs($c) < $epsilon ? '=' : "$c%";
    }
}

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$date = $now->format(DateTimeInterface::RSS);

$mempoolSpace = new MempoolData($_SERVER['SPAMMY_MEMPOOL_WS']);
$ordisrespector = new MempoolData($_SERVER['ORDISRESPECTOR_MEMPOOL_WS']);

$txsDiff = $ordisrespector->unconfTxs - $mempoolSpace->unconfTxs;
$txsDelta = MempoolData::formatDelta($ordisrespector->unconfTxs, $mempoolSpace->unconfTxs);

$nBlocksDiff = $ordisrespector->nMempoolBlocks - $mempoolSpace->nMempoolBlocks;
$nBlocksDelta = MempoolData::formatDelta($ordisrespector->nMempoolBlocks, $mempoolSpace->nMempoolBlocks);

$nodeMemDiff = $ordisrespector->memoryUsageMBs - $mempoolSpace->memoryUsageMBs;
$nodeMemDelta = MempoolData::formatDelta($ordisrespector->memoryUsageMBs, $mempoolSpace->memoryUsageMBs);

$noPrioFeeDiff = $ordisrespector->noPrioFee - $mempoolSpace->noPrioFee;
$noPrioFeeDelta = MempoolData::formatDelta($ordisrespector->noPrioFee, $mempoolSpace->noPrioFee);

$lowPrioFeeDiff = $ordisrespector->lowPrioFee - $mempoolSpace->lowPrioFee;
$lowPrioFeeDelta = MempoolData::formatDelta($ordisrespector->lowPrioFee, $mempoolSpace->lowPrioFee);

$mediumPrioFeeDiff = $ordisrespector->mediumPrioFee - $mempoolSpace->mediumPrioFee;
$mediumPrioFeeDelta = MempoolData::formatDelta($ordisrespector->mediumPrioFee, $mempoolSpace->mediumPrioFee);

$highPrioFeeDiff = $ordisrespector->highPrioFee - $mempoolSpace->highPrioFee;
$highPrioFeeDelta = MempoolData::formatDelta($ordisrespector->highPrioFee, $mempoolSpace->highPrioFee);

$note = <<<TEXT
Date: $date

---

#### Spammy Mempool (mempool.space)

Unconfirmed TXs:	**$mempoolSpace->unconfTxs**

Unconfirmed Blocks:	**$mempoolSpace->nMempoolBlocks**

Memory Usage (MB):	**$mempoolSpace->memoryUsageMBs**

No Prio Fee (s/vB):	**$mempoolSpace->noPrioFee**

Low Prio Fee (s/vB):	**$mempoolSpace->lowPrioFee**

Medium Prio Fee (s/vB):	**$mempoolSpace->mediumPrioFee**

Max Prio Fee (s/vB):	**$mempoolSpace->highPrioFee**

---

#### Tidy Mempool (Ordisrespector node)

Unconfirmed TXs:	**$ordisrespector->unconfTxs**	*($txsDiff, $txsDelta)*

Unconfirmed Blocks:	**$ordisrespector->nMempoolBlocks**	*($nBlocksDiff, $nBlocksDelta)*

Memory Usage (MB):	**$ordisrespector->memoryUsageMBs**	*($nodeMemDiff, $nodeMemDelta)*

No Prio Fee (s/vB):	**$ordisrespector->noPrioFee**	*($noPrioFeeDiff, $noPrioFeeDelta)*

Low Prio Fee (s/vB):	**$ordisrespector->lowPrioFee**	*($lowPrioFeeDiff, $lowPrioFeeDelta)*

Medium Prio Fee (s/vB):	**$ordisrespector->mediumPrioFee**	*($mediumPrioFeeDiff, $mediumPrioFeeDelta)*

Max Prio Fee (s/vB):	**$ordisrespector->highPrioFee**	*($highPrioFeeDiff, $highPrioFeeDelta)*

TEXT;

echo $note;
