<?php

require_once __DIR__ . '/vendor/autoload.php';

use splitbrain\RemarkableAPI\RemarkableAPI;
use splitbrain\RemarkableAPI\RemarkableFS;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$user = getenv('ZOTERO_USER');
$zoteroKey = getenv('ZOTERO_API_KEY');
$collection = getenv('ZOTERO_COLLECTION');
$webdavUrl = getenv('WEBDAV_URL');
$webdavAuth = getenv('WEBDAV_AUTH');
$reMarkableToken = getenv('REMARKABLE_TOKEN');

$api = new RemarkableAPI();
$api->init($reMarkableToken);
$fs = new RemarkableFS($api);
$parent = $fs->mkdirP("/Zotero");

// Zotero API client
$client = new GuzzleHttp\Client([
    'base_uri' => 'https://api.zotero.org/users/'. $user . '/',
    'headers' => [
        'Zotero-API-Version' => 3,
        'Zotero-API-Key' => $zoteroKey,
        'Content-Type' => 'application/json'
    ]
]);

// WebDAV HTTP client for downloading zips
$webDAVClient = new GuzzleHttp\Client([
    'base_uri' => $webdavUrl,
    'auth' => explode(':', $webdavAuth)
]);

$zipper = new \Chumper\Zipper\Zipper;
$tmp_dir = sys_get_temp_dir();

echo "Fetching items from Zotero...\n";

$to_process = [];
$titles = [];
$collections = [];
$versions = [];
$response = $client->get('collections/' . $collection . '/items');
foreach (json_decode($response->getBody()) as $item) {
    // Store data to access parent info later
    $titles[$item->data->key] = $item->data->title;
    if (property_exists($item->data, 'collections')) {
        $collections[$item->data->key] = $item->data->collections;
    }
    $versions[$item->data->key] = $item->data->version;

    // Only upload PDF attachments
    if ($item->data->itemType == 'attachment' && $item->data->contentType == 'application/pdf') {
        $to_process[] = $item;
    }
}

echo count($to_process), " items found.\n";

$to_remove = [];
foreach ($to_process as $item) {
    echo 'Processing item ', $item->data->key, "\n";

    // Store data for future removal
    $parentItem = $item->data->parentItem;
    if (!array_key_exists($parentItem, $titles)) {
        echo "  Skipping since no parent was found!\n";
    }

    $title = $titles[$parentItem];
    $to_remove[] = [
        'key' => $parentItem,
        'version' => $versions[$parentItem],
        'collections' => array_diff($collections[$parentItem], [$collection])
    ];

    // Download the zip file from WebDAV
    echo "  Downloading zip...\n";
    $zip_file = $item->data->key . '.zip';
    $tmp_file = $tmp_dir . '/' . $zip_file;
    $zip_resp = $webDAVClient->get($zip_file, ['save_to' => $tmp_file]);

    // Extract the PDF
    echo "  Extracting PDF...\n";
    $zip = $zipper->make($tmp_file);
    $content = $zip->getFileContent($item->data->filename);

    // Upload to reMarkable and delete locally
    echo "  Uploading to reMarkable...\n";
    $api->uploadPDF($content, $title, $parent);
    unlink($tmp_file);
}

// Remove the items from the collection
echo "Removing items from Zotero collection...\n";
foreach (array_chunk($to_remove, 50) as $remove_chunk) {
    $client->post('items', ['body' => json_encode($remove_chunk)]);
}

?>
