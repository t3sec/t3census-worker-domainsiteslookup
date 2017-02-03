<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Gelf\Publisher;
use Gelf\Transport\UdpTransport;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\GelfHandler;
use T3sec\BingScraper\ScraperSearch;
use T3sec\BingScraper\Exception\EmptyBodyException;

$logfile = __DIR__ . '/../t3census-worker-domainsiteslookup.log';


// create a log channel
$logger = new Logger('t3census-worker-domainsiteslookup');
$logger->pushHandler(new StreamHandler($logfile, Logger::WARNING));
$logger->pushHandler(new GelfHandler(new Publisher(new UdpTransport('127.0.0.1', 12201)), Logger::DEBUG));

$worker = new GearmanWorker();
$worker->addServer('127.0.0.1', 4730);
$worker->addFunction('DomainSitesLookup', 'fetchSites');
$worker->setTimeout(5000);

while (1) {
    try {
        $worker->work();
    } catch (Exception $e) {
        fwrite(STDERR, sprintf('ERROR: Job-Worker: %s (Errno: %u)' . PHP_EOL, $e->getMessage(), $e->getCode()));
        $logger->addError($e->getMessage(), array('errorcode' => $e->getCode()));
        exit(1);
    }

    if ($worker->returnCode() == GEARMAN_TIMEOUT) {
        //do some other work here
        continue;
    }
    if ($worker->returnCode() != GEARMAN_SUCCESS) {
        // do some error handling here
        exit(1);
    }
}


function fetchSites(GearmanJob $job)
{
    global $logger;

    $domain = $job->workload();
    $logger->addDebug('Processing domain', array('domain' => $domain));

    try {
        $objLookup = new ScraperSearch();
        $objLookup->setEndpoint('http://www.bing.com/search')->setMaxResults(1000);
        $results = $objLookup->setQuery('site:' . $domain)->getResults();
        unset($objLookup);
    } catch (EmptyBodyException $e) {
        $logger->addWarning($e->getMessage(), array('errorcode' => $e->getCode(), 'domain' => $domain));
        $job->sendData(Logger::WARNING . ' ' . $e->getMessage());
        $job->sendFail();
        return;
    } catch (\Exception $e) {
        $logger->addError($e->getMessage(), array('errorcode' => $e->getCode(), 'domain' => $domain));
        $job->sendData(Logger::ERROR . ' ' . $e->getMessage());
        $job->sendException($e->getMessage());
        return;
    }

    if (!empty($results)) {
        $logger->addInfo('Retrieved sites for domain', array('domain' => $domain, 'urls' => $results, 'count' => count($results)));
    }

    return json_encode($results);
}

?>