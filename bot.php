<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

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

$mempoolSpace = new MempoolData('wss://mempool.space/api/v1/ws');
$ordisrespector = new MempoolData('wss://blackbox.vpn:4200/api/v1/ws');

$txsDelta = MempoolData::formatDelta($ordisrespector->unconfTxs, $mempoolSpace->unconfTxs);
$nBlocksDelta = MempoolData::formatDelta($ordisrespector->nMempoolBlocks, $mempoolSpace->nMempoolBlocks);
$nodeMemDelta = MempoolData::formatDelta($ordisrespector->memoryUsageMBs, $mempoolSpace->memoryUsageMBs);
$noPrioFeeDelta = MempoolData::formatDelta($ordisrespector->noPrioFee, $mempoolSpace->noPrioFee);
$lowPrioFeeDelta = MempoolData::formatDelta($ordisrespector->lowPrioFee, $mempoolSpace->lowPrioFee);
$mediumPrioFeeDelta = MempoolData::formatDelta($ordisrespector->mediumPrioFee, $mempoolSpace->mediumPrioFee);
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

Unconfirmed TXs:	**$ordisrespector->unconfTxs**	*($txsDelta)*

Unconfirmed Blocks:	**$ordisrespector->nMempoolBlocks**	*($nBlocksDelta)*

Memory Usage (MB):	**$ordisrespector->memoryUsageMBs**	*($nodeMemDelta)*

No Prio Fee (s/vB):	**$ordisrespector->noPrioFee**	*($noPrioFeeDelta)*

Low Prio Fee (s/vB):	**$ordisrespector->lowPrioFee**	*($lowPrioFeeDelta)*

Medium Prio Fee (s/vB):	**$ordisrespector->mediumPrioFee**	*($mediumPrioFeeDelta)*

Max Prio Fee (s/vB):	**$ordisrespector->highPrioFee**	*($highPrioFeeDelta)*

TEXT;

echo $note;