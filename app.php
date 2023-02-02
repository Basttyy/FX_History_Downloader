<?php
ini_set('memory_limit', '4000M');
ini_set("default_socket_timeout", 999999999);
ini_set('max_execution_time', 999999999);

require_once "./vendor/autoload.php";
require_once "./helpers.php";

use Github\Api\Repository\Releases;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$dest_fxt_folder = isset($argv[1]) ? $argv[1] : __DIR__."\\downloads\\fxt\\";
$dest_hst_folder = isset($argv[2]) ? $argv[2] : __DIR__."\\downloads\\hst\\";
$temp_folder = isset($argv[3]) ? $argv[3] : __DIR__."\\downloads\\temp\\";

$logger = new Logger('logger');
$logger->pushHandler(new StreamHandler(__DIR__.'\\app.log', Level::Debug));
$buffer_size = 4096;

foreach ($pairs as $pair => $years) {
    // echo $pair.PHP_EOL;
    // print_r($years);
    $releases = getReleases($gh_repo, $logger, $pair);
    
    foreach ($releases as $release) {
        if (!in_array($release['name'], $years)) {
            continue;
        }
        
        if (!setupFolders($temp_folder, $dest_fxt_folder.$pair.'\\'.$release['name'], $dest_hst_folder.$pair.'\\'.$release['name'])) {
            $logger->error('could not create working folders/directories');
            throw new \UnexpectedValueException('Could not create working folders/directories');
        }

        getReleaseAssets($release, $pair, $temp_folder, $dest_fxt_folder, $dest_hst_folder, $on_progress, $logger);
    }
}

function getReleaseAssets(array $release, string $pair, string $temp_folder, string $dest_fxt_folder, string $dest_hst_folder, Closure $on_progress, Logger $logger) {
    $i = 1;

    foreach ($release['assets'] as $asset) {  //loop through a release assets
        echo "Loop $i".PHP_EOL;
        // if ($i >= 4)
        //     break;
        // if ($i < 3) {
        //     $i++;
        //     continue;
        // }
        
        
        if (fileExists($asset, $dest_fxt_folder, $dest_hst_folder, $pair, $release['name'])) {
            echo $release['name']. strstr($asset['name'], '.gz', true) . " found".PHP_EOL;
            continue;
        }

        if (performDownloads($asset, $temp_folder, $logger, $on_progress)) {
            performExtraction($asset, $pair, $release['name'], $temp_folder, $dest_fxt_folder, $dest_hst_folder, $logger);
        }
        $i++;
    }
}

function fileExists(array $asset, string $dest_fxt_folder, string $dest_hst_folder, string $pair, string $release_name) {
    $path = strpos($asset['name'], 'fxt') !== false ? $dest_fxt_folder. $pair .'\\' .$release_name.'\\' . $release_name . strstr($asset['name'], '.gz', true) : $dest_hst_folder. $pair .'\\' .$release_name.'\\' . $release_name . strstr($asset['name'], '.gz', true);
    return file_exists($path);
}

function setupFolders($temp_folder, $dest_fxt_folder, $dest_hst_folder) {
    $resp = false;
    $resp = createDir($temp_folder) && createDir($dest_fxt_folder) && createDir($dest_hst_folder);

    return $resp;
}

function createDir(string $dir) {
    if (!file_exists($dir)) {
        return mkdir($dir, 0777, true);
    }
    return true;
}

function getReleases(string $gh_repo, Logger $logger, string $pair = "EURUSD"): bool|array {
    try {
        $gh_client = new \Github\Client();
        
        $release = new Releases($gh_client);
        $releases = $release->all($gh_repo, "$gh_repo-$pair-DS");
        //$releases = $client->api('repo')->releases()->all('FX-Data', 'FX-Data-EURUSD-DS')[0];
        //$tags = $client->api('repo')->tags('FX-Data', 'FX-Data-EURUSD-DS');
        //$assets = $client->api('repo')->releases()->assets()->all('FX-Data', 'FX-Data-EURUSD-DS', '71651758');
    
        
        // var_dump($releases);
        // var_dump($releases['name']);
        //var_dump($releases['assets']);
        // foreach ($releases['assets'] as $asset) {
        //     echo $asset["size"] . " *** " . $asset['name'] . " *** " . $asset['browser_download_url'] .PHP_EOL;
        // }
        return $releases;
    } catch (Exception $e) {
        $logger->error($e->getMessage(), $e->getTrace());
        echo $e->getMessage().' '.$e->getCode();
        return false;
    }
};

$prev_down_bytes = $prev_up_bytes = 0;

function performExtraction(array $asset, string $pair, string $release_name, string $temp_folder, string $dest_fxt_folder, string $dest_hst_folder, Logger $logger) {
    // open the gzip file
    $gz = gzopen($temp_folder . $asset['name'], 'rb');
    if (!$gz) {
        $logger->error('could not open gzip file');
        throw new \UnexpectedValueException('Could not open gzip file'.PHP_EOL);
    }
    // open the destination file
    $dest = strpos($asset['name'], 'fxt') !== false ? fopen($dest_fxt_folder. $pair .'\\' .$release_name.'\\' . $release_name . strstr($asset['name'], '.gz', true), 'wb') : fopen($dest_hst_folder. $pair .'\\' .$release_name.'\\' . $release_name . strstr($asset['name'], '.gz', true), 'wb');
    if (!$dest) {
        gzclose($gz);
        $logger->error('could not open destination file');
        throw new \UnexpectedValueException('Could not open destination file'.PHP_EOL);
    }
    
    // transfer ...
    $name = strstr($asset['name'], '.gz', true);
    echo "extracting to $name".PHP_EOL;
    while (!gzeof($gz)) {
        fwrite($dest, gzread($gz, 4096));
    }    
    
    gzclose($gz); //Close the files once they are done with
    sleep(1);
    fclose($dest);
    sleep(1);
    // Use unlink() function to delete a file
    $stat = unlink($temp_folder . $asset['name']);
    sleep(2);
    if (!$stat) {
        $logger->warning("$gz cannot be deleted due to an error");
        echo ("$gz cannot be deleted due to an error".PHP_EOL);
    }
    else {
        echo ("$gz has been deleted".PHP_EOL);
    }
};

function performDownloads(array $asset, string $temp_folder, Logger $logger, Closure $onProgress) {
    try {
        $http_client = new GuzzleHttp\Client();

        $i = 1;
        $response = $http_client->request('GET', $asset['browser_download_url'], [
            'version' => 1.0,
            'headers'        => ['Accept-Encoding' => 'gzip, deflate'],
            'decode_content' => false,
            'sink' => $temp_folder . $asset['name'],
            'progress' => $onProgress
        ]);
        $code = $response->getStatusCode();
        echo "response is: $code".PHP_EOL;

        if ($code > 199 && $code < 300) {   //only if file was successfully downloaded
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        if ($e instanceof RequestException || $e instanceof TransferException) {
            $message = $e->getMessage(); $name = $asset['name'];
            $logger->error($message, $e->getTrace());
            echo "got error `$message` while downloading file $name".PHP_EOL;
            echo "retrying in 10 seconds".PHP_EOL;
            sleep(10);
            performDownloads($asset, $temp_folder, $logger, $onProgress);
        } else if ($e instanceof GuzzleHttp\Exception\ConnectException || $e instanceof GuzzleHttp\Exception\TooManyRedirectsException || $e instanceof GuzzleHttp\Exception\ClientException) {
            $message = $e->getMessage(); $name = $asset['name'];
            $logger->error($message, $e->getTrace());
            echo "got error `$message` while downloading file $name".PHP_EOL;
            exit (1);
        }
    }
};

//strstr($asset['name'], '.gz', true)
// Pass "gzip" as the Accept-Encoding header.
