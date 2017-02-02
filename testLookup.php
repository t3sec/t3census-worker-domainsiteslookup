<?php
require_once __DIR__.'/vendor/autoload.php';

$domain = 'royal-canin.de';

$objLookup = new T3sec\BingScraper\ScraperSearch();
$objLookup->setEndpoint('http://www.bing.com/search')->setMaxResults(1000);
$results = $objLookup->setQuery('site:' . $domain)->getResults();
print_r($results);