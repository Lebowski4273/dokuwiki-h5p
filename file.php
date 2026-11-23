<?php
/**
 * DokuWiki Plugin h5p - file delivery endpoint
 *
 * The H5P player appends paths to its base URLs, so this script expects
 * path-style URLs:
 *
 *   lib/plugins/h5p/file.php/c/<encoded media id>/h5p.json
 *   lib/plugins/h5p/file.php/c/<encoded media id>/content/content.json
 *   lib/plugins/h5p/file.php/l/<Library-1.2>/library.json
 *
 * Content requires read permission on the .h5p media file.
 * Libraries are code shared by all contents and are delivered without ACL check.
 * 
 * Todo: how to handel Embed??
 */

if (!defined('DOKU_INC')) define('DOKU_INC', dirname(__DIR__, 3) . '/');
require_once DOKU_INC . 'inc/init.php';
session_write_close();

use dokuwiki\plugin\h5p\Store;

/**
 * Path after "file.php", works with and without PATH_INFO support
 */
function plugin_h5p_requestPath(): string
{
    $path = (string)($_SERVER['PATH_INFO'] ?? '');
    if ($path !== '') return $path;
    $uri = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $pos = strpos($uri, '/file.php/');
    return $pos === false ? '' : rawurldecode(substr($uri, $pos + strlen('/file.php')));
}

function plugin_h5p_fail(int $code): void
{
    http_status($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $code === 403 ? 'Forbidden' : 'Not Found';
    exit;
}

/** @var helper_plugin_h5p $helper */
$helper = plugin_load('helper', 'h5p');
if (!$helper) plugin_h5p_fail(404);
$store = $helper->getStore();

$parts = explode('/', ltrim(plugin_h5p_requestPath(), '/'), 3);
$file = null;

if ($parts[0] === 'c' && count($parts) === 3) {
    $mediaId = $helper->decodeId($parts[1]);
    if ($mediaId === null || !$helper->isH5PFile($mediaId)) plugin_h5p_fail(404);
    if (auth_quickaclcheck($mediaId) < AUTH_READ) plugin_h5p_fail(403);
    try {
        $dir = $helper->prepare($mediaId);
    } catch (\Exception $e) {
        plugin_h5p_fail(404);
    }
    $file = $store->resolveFile($dir, $parts[2], Store::contentExtensions());
    $cacheControl = 'private, no-cache';
} elseif ($parts[0] === 'l' && count($parts) === 3) {
    $file = $store->resolveLibraryFile($parts[1] . '/' . $parts[2]);
    $cacheControl = 'public, max-age=3600';
}

if ($file === null) plugin_h5p_fail(404);

$mime = Store::mimeType($file);
header('Content-Type: ' . $mime . (strpos($mime, 'text/') === 0 || $mime === 'application/json' ? '; charset=utf-8' : ''));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: ' . $cacheControl);
if ($mime === 'image/svg+xml') {
    // SVGs opened directly must not be able to run scripts in the wiki's origin
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
}

http_conditionalRequest(filemtime($file)); // sends 304 and exits if the client copy is current

$fh = fopen($file, 'rb');
if (!$fh) plugin_h5p_fail(404);
http_rangeRequest($fh, filesize($file), $mime); // supports seeking in videos
