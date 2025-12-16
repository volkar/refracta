<?php
/*
 * Refracta 1.0.1
 * Refracta's album script.
 * Loads refracta.json and renders the album with lightbox functionality
 *
 * https://refracta.syntheticsymbiosis.com
 * Copyright 2025 Sergey Volkar ⣿ SyntheticSymbiosis
 *
 * Released under the MIT License
 */

if (basename($_SERVER['SCRIPT_FILENAME']) !== 'index.php') {
    // Not index.php. Die.
    header("HTTP/1.0 404 Not Found");
    die();
}

require_once '../config.php';
require_once INCL_DIR . '/refracta.functions.php';

$slug = basename(__DIR__);
$album = loadAlbumData($slug);
if (!$album) {
    header("HTTP/1.0 404 Not Found");
    die();
}

// Album data
$title = (string)$album['title'];
$subtitle = (string)$album['subtitle'];
$date = date(DATE_FORMAT, (int)$album['date']);
$images = (array)$album['images'];
$theme = (string)$album['theme'];
$access = (array)$album['access'];

// Check access
$data = checkPageAccess($access);

echo getHeader($title, $theme, 'album');

echo '<div class="album-title scaled">';
if (checkAccess(GALLERY_ACCESS, $data['keys'])) {
    echo '<div class="back"><a href="' . REFRACTA_URL . '"><button class="back"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="currentColor" d="m4 10l9 9l1.4-1.5L7 10l7.4-7.5L13 1z"/></svg> Back</button></a></div>';
}
if ($title): echo "<h1>$title</h1>"; endif;
if ($subtitle): echo "<h2>$subtitle</h2>"; endif;
if ($date): echo "<h3>$date</h3>"; endif;
echo '</div>';

renderGallery($slug, $images);

echo getFooter('album');