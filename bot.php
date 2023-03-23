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
    public readonly int $minimumFee;
    public readonly int $noPrioFee;
    public readonly int $lowPrioFee;
    public readonly int $mediumPrioFee;
    public readonly int $highPrioFee;
    public readonly int $nMempoolBlocks;

    public function __construct(string $serverEndpoint)
    {
        $client = new WebSocket\Client("$serverEndpoint/api/v1/ws");
        $client->text('{"action":"init"}');
        $client->text('{"action":"want","data":["blocks","stats","mempool-blocks","live-2h-chart","watch-mempool"]}');

        $raw = json_decode($client->receive());

        $this->unconfTxs = $raw->mempoolInfo->size;
        $this->memoryUsageMBs = round((float)($raw->mempoolInfo->usage/1000000));
        $this->minimumFee = $raw->fees->minimumFee;
        $this->noPrioFee = $raw->fees->economyFee;
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

    public function purgingText(MempoolData $spammy = null): string
    {
        $text = $this->minimumFee > 1 ?
            "Purging TXs below (s/vB):	**$this->minimumFee**" : 'Not purging TXs (**1** s/vB default)';

        if (null !== $spammy) {
            $minimumFeeDiff = $this->minimumFee - $spammy->minimumFee;
            $minimumFeeDelta = self::formatDelta($this->minimumFee, $spammy->minimumFee);

            $text .= "	*($minimumFeeDiff, $minimumFeeDelta)*";
        }

        return $text;
    }
}

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$date = $now->format(DateTimeInterface::RSS);

$spammyMempool = new MempoolData($_SERVER['SPAMMY_MEMPOOL_WS']);
$ordisrespector = new MempoolData($_SERVER['ORDISRESPECTOR_MEMPOOL_WS']);

$txsDiff = $ordisrespector->unconfTxs - $spammyMempool->unconfTxs;
$txsDelta = MempoolData::formatDelta($ordisrespector->unconfTxs, $spammyMempool->unconfTxs);

$nBlocksDiff = $ordisrespector->nMempoolBlocks - $spammyMempool->nMempoolBlocks;
$nBlocksDelta = MempoolData::formatDelta($ordisrespector->nMempoolBlocks, $spammyMempool->nMempoolBlocks);

$nodeMemDiff = $ordisrespector->memoryUsageMBs - $spammyMempool->memoryUsageMBs;
$nodeMemDelta = MempoolData::formatDelta($ordisrespector->memoryUsageMBs, $spammyMempool->memoryUsageMBs);

$noPrioFeeDiff = $ordisrespector->noPrioFee - $spammyMempool->noPrioFee;
$noPrioFeeDelta = MempoolData::formatDelta($ordisrespector->noPrioFee, $spammyMempool->noPrioFee);

$lowPrioFeeDiff = $ordisrespector->lowPrioFee - $spammyMempool->lowPrioFee;
$lowPrioFeeDelta = MempoolData::formatDelta($ordisrespector->lowPrioFee, $spammyMempool->lowPrioFee);

$mediumPrioFeeDiff = $ordisrespector->mediumPrioFee - $spammyMempool->mediumPrioFee;
$mediumPrioFeeDelta = MempoolData::formatDelta($ordisrespector->mediumPrioFee, $spammyMempool->mediumPrioFee);

$highPrioFeeDiff = $ordisrespector->highPrioFee - $spammyMempool->highPrioFee;
$highPrioFeeDelta = MempoolData::formatDelta($ordisrespector->highPrioFee, $spammyMempool->highPrioFee);

$note = <<<TEXT
Date: $date

---

#### Spammy Mempool (mempool.space)

Unconfirmed TXs:	**$spammyMempool->unconfTxs**

Unconfirmed Blocks:	**$spammyMempool->nMempoolBlocks**

Memory Usage (MB):	**$spammyMempool->memoryUsageMBs**

No Prio Fee (s/vB):	**$spammyMempool->noPrioFee**

Low Prio Fee (s/vB):	**$spammyMempool->lowPrioFee**

Medium Prio Fee (s/vB):	**$spammyMempool->mediumPrioFee**

Max Prio Fee (s/vB):	**$spammyMempool->highPrioFee**

{$spammyMempool->purgingText()}

---

#### Tidy Mempool (Ordisrespector node)

Unconfirmed TXs:	**$ordisrespector->unconfTxs**	*($txsDiff, $txsDelta)*

Unconfirmed Blocks:	**$ordisrespector->nMempoolBlocks**	*($nBlocksDiff, $nBlocksDelta)*

Memory Usage (MB):	**$ordisrespector->memoryUsageMBs**	*($nodeMemDiff, $nodeMemDelta)*

No Prio Fee (s/vB):	**$ordisrespector->noPrioFee**	*($noPrioFeeDiff, $noPrioFeeDelta)*

Low Prio Fee (s/vB):	**$ordisrespector->lowPrioFee**	*($lowPrioFeeDiff, $lowPrioFeeDelta)*

Medium Prio Fee (s/vB):	**$ordisrespector->mediumPrioFee**	*($mediumPrioFeeDiff, $mediumPrioFeeDelta)*

Max Prio Fee (s/vB):	**$ordisrespector->highPrioFee**	*($highPrioFeeDiff, $highPrioFeeDelta)*

{$ordisrespector->purgingText($spammyMempool)}

TEXT;

echo $note;
