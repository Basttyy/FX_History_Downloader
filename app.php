<?php

require_once "./vendor/autoload.php";
require_once "./helpers.php";

use Github\Api\Repository\Releases;
use Psr\Http\Message\ResponseInterface;

$dest_fxt_folder = isset($argv[1]) ? $argv[1] : __DIR__."\\downloads\\fxt\\";
$dest_hst_folder = isset($argv[2]) ? $argv[2] : __DIR__."\\downloads\\hst\\";
$temp_folder = isset($argv[3]) ? $argv[3] : __DIR__."\\downloads\\temp\\";
$buffer_size = 4096;

// $repositories = $client->api('user')->repositories('ornicar');
// var_dump($repositories);

foreach ($pairs as $pair => $years) {
    // echo $pair.PHP_EOL;
    // print_r($years);
    $releases = getReleases($gh_repo, $pair);
    
    foreach ($releases as $release) {
        if (!in_array($release['name'], $years)) {
            continue;
        }
        
        if (!setupFolders($temp_folder, $dest_fxt_folder.'\\'.$release['name'], $dest_hst_folder.'\\'.$release['name'])) {
            throw new \UnexpectedValueException('Could not create working folders/directories');
        }

        getReleaseAssets($release, $temp_folder, $dest_fxt_folder, $dest_hst_folder, $on_progress);
    }
}
exit(0);

function getReleaseAssets(array $release, string $temp_folder, string $dest_fxt_folder, string $dest_hst_folder, Closure $on_progress) {
    $i = 1;

    foreach ($release['assets'] as $asset) {  //loop through a release assets
        echo "Loop $i".PHP_EOL;
        if ($i >= 4)
            break;
        if ($i < 3) {
            $i++;
            continue;
        }
        if (performDownloads($asset, $temp_folder, $on_progress)) {
            performExtraction($asset, $release['name'], $temp_folder, $dest_fxt_folder, $dest_hst_folder);
        }
        $i++;
    }
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

function getReleases(string $gh_repo, string $pair = "EURUSD"): bool|array {
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
        echo $e->getMessage().' '.$e->getCode();
        return false;
    }
};

$prev_down_bytes = $prev_up_bytes = 0;

function performExtraction(array $asset, string $release_name, string $temp_folder, string $dest_fxt_folder, string $dest_hst_folder) {
    // open the gzip file
    $gz = gzopen($temp_folder . $asset['name'], 'rb');
    if (!$gz) {
        throw new \UnexpectedValueException('Could not open gzip file');
    }
    // open the destination file
    $dest = strpos($asset['name'], 'fxt') !== false ? fopen($dest_fxt_folder .$release_name.'\\' . $release_name . strstr($asset['name'], '.gz', true), 'wb') : fopen($dest_hst_folder .$release_name.'\\' . $release_name . strstr($asset['name'], '.gz', true), 'wb');
    if (!$dest) {
        gzclose($gz);
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
        echo ("$gz cannot be deleted due to an error".PHP_EOL);
    }
    else {
        echo ("$gz has been deleted".PHP_EOL);
    }
};

function performDownloads(array $asset, string $temp_folder, Closure $onProgress) {
    $http_client = new GuzzleHttp\Client();

    $i = 1;
    $response = $http_client->request('GET', $asset['browser_download_url'], [
        'headers'        => ['Accept-Encoding' => 'gzip'],
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
};

//strstr($asset['name'], '.gz', true)
// Pass "gzip" as the Accept-Encoding header.
