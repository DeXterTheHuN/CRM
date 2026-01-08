<?php
require_once 'config.php';

header('Content-Type: application/json');

$county_id = $_GET['county_id'] ?? 0;

// Települések listájának cache-elése (repository használatával)
// Mivel a települések ritkán változnak, 24 órás TTL-t használunk.
$cache_key = 'settlements_' . $county_id;
$settlements = cache_get($cache_key, CACHE_TTL_SETTLEMENTS);
if ($settlements === false) {
    $settlementRepo = new SettlementRepository($pdo);
    $settlements = $settlementRepo->getSettlementsByCounty((int) $county_id);
    cache_set($cache_key, $settlements);
}

echo json_encode($settlements);
?>