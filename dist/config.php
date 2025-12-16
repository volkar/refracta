<?php

// --- Configuration ---

// Directories
const ROOT_DIR = __DIR__;
const REFRACTA_DIR = __DIR__ . '/_refracta_files';
const ASSET_DIR = __DIR__ . '/_refracta_assets';
const SOURCES_DIR = REFRACTA_DIR . '/sources';
const DATA_DIR = REFRACTA_DIR . '/data';
const INCL_DIR = REFRACTA_DIR . '/includes';
// Access for main gallery
const GALLERY_ACCESS = []; // Array of allowed groups. Empty array means public access.
// Themes
const THEMES = ['dark', 'light'];
const GALLERY_THEME = 'dark';
const ADMIN_THEME = 'dark';
// Processing
const EXTRACT_EXIF = true;
const IMAGE_MAX_WH = 2000;
const PREVIEW_MAX_WH = 500;
const CONVERSION_QUALITY = 60;
const FFMPEG_PATH = 'ffmpeg';
const FFPROBE_PATH = 'ffprobe';
// Date format
const DATE_FORMAT = "M d, 'y";

// --- End of Configuration ---

$realDocRoot = realpath($_SERVER['DOCUMENT_ROOT']);
$realDirPath = realpath(__DIR__);
$suffix = str_replace($realDocRoot, '', $realDirPath);
$prefix = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
$refractaUrl = $prefix . $_SERVER['HTTP_HOST'] . $suffix;

define('REFRACTA_URL', "{$refractaUrl}");
define('ASSET_URL', $refractaUrl . "/" . basename(ASSET_DIR));
define('ASSET_CACHE', '1.0.1');