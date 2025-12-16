<?php

// Start session
session_start();

// Checks if user have access to the page, if not die with 404
function checkPageAccess($access, $dataFilename = false) {
    // Check if link have auth key
    checkGetKey();
    // Get data file
    if ($dataFilename === false) {
        $dataFilename = getDataFilename();
    }
    if ($dataFilename === false) {
        // No data file.
        die("Error: No data file found.");
    }
    $data = loadDataFile($dataFilename);
    // Check if user have access
    if (!checkAccess($access, $data['keys'])) {
        // No permission. Die with 404
        header("HTTP/1.0 404 Not Found");
        die();
    }
    // Return data
    return $data;
}
// Checks if link have auth key and apply it to session
function checkGetKey() {
    // If link have auth key, save it to session and redirect back to the same page
    if (!empty($_GET['key']) && strlen($_GET['key']) === 32) {
        // Apply to session
        $_SESSION['refracta_auth_key'] = $_GET['key'];
        // Redirect back to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        die();
    } else if (isset($_GET['key'])) {
        // Invalid or empty key
        unset($_SESSION['refracta_auth_key']);
    }
}
// Checks if user have access
function checkAccess($access, $keys)
{
    if (count($access) === 0) {
        // No groups provided. Allow public access
        return true;
    }

    // Check access key
    if (!isset($_SESSION['refracta_auth_key']) || !isset($keys[$_SESSION['refracta_auth_key']])) {
        return false;
    }

    $userGroups = $keys[$_SESSION['refracta_auth_key']];

    if (in_array('admin', $userGroups)) {
        // Admin. Allow access
        return true;
    }

    // Compare all user's keys with allowed groups
    foreach ($userGroups as $group) {
        if (in_array($group, $access)) {
            return true;
        }
    }

    return false;
}
// Returns data filename
function getDataFilename()
{
    // Find single .json file in "assets directory
    $files = glob(DATA_DIR . '/*.json');
    if (count($files) !== 1) {
        return false;
    }
    return basename($files[0]);
}
// Loads data file
function loadDataFile($dataFilename = false)
{
    if ($dataFilename === false) {
        $dataFilename = getDataFilename();
    }
    if ($dataFilename !== false) {
        $file = DATA_DIR . '/' . $dataFilename;
        $fp = fopen($file, 'r');
        if ($fp && flock($fp, LOCK_SH)) {
            $json = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return json_decode($json, true);
        }
    }
    return false;
}
// Saves data file
function saveDataFile()
{
    global $dataFilename, $data;
    if ($dataFilename !== false) {
        $file = DATA_DIR . '/' . $dataFilename;
        $fp = fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }
        if ($fp) fclose($fp);
    }
    return false;
}
// Loads album data from given directory slug
function loadAlbumData($albumSlug) {
    // Get album data
    $albumSlug = trim(basename($albumSlug));
    $subDirs = array_filter(glob(ROOT_DIR . '/' . $albumSlug . '/*'), function ($f) {
        return is_dir($f);
    });
    if (sizeof($subDirs) !== 1) {
        return false;
    }
    $albumID = $subDirs[0];
    $albumSettings = $albumID . '/refracta.json';

    if (!file_exists($albumSettings)) {
        return false;
    }
    return json_decode(file_get_contents($albumSettings), true);
}
function updateAlbumData($albumSlug, $albumData) {
    // Get album data
    $subDirs = array_filter(glob(ROOT_DIR . '/' . $albumSlug . '/*'), function ($f) {
        return is_dir($f);
    });
    if (sizeof($subDirs) !== 1) {
        throw new \Exception("Album directory not found.");
    }
    $albumID = $subDirs[0];
    $albumSettings = $albumID . '/refracta.json';

    if (!file_exists($albumSettings)) {
        throw new \Exception("Album settings file not found.");
    }

    // Encode and save JSON
    $jsonContent = json_encode($albumData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonContent === false) {
        // Output error message
        throw new \Exception("Error: Could not encode data to JSON.");
    }

    // Delete unused images
    $oldAlbum = loadAlbumData($albumSlug);
    if ($oldAlbum !== false) {
        $oldImages = $oldAlbum['images'];
        $newImages = $albumData['images'];
        foreach ($oldImages as $oldImage) {
            if (!in_array($oldImage, $newImages)) {
                // Delete image
                if (!empty($oldImage['src']) && file_exists(ROOT_DIR . '/' . $albumSlug . '/' . $oldImage['src'])) {
                    unlink(ROOT_DIR . '/' . $albumSlug . '/' . $oldImage['src']);
                }
                if (!empty($oldImage['preview']) && file_exists(ROOT_DIR . '/' . $albumSlug . '/' . $oldImage['preview'])) {
                    unlink(ROOT_DIR . '/' . $albumSlug . '/' . $oldImage['preview']);
                }
            }
        }
    }

    if (file_put_contents($albumSettings, $jsonContent) === false) {
        // Output error message
        throw new \Exception("Error: Could not write to $albumSettings. Check write permissions.");
    }

    return true;
}
// Generates random string (32 characters)
function generateRandomString()
{
    return bin2hex(random_bytes(16));
}
// Handles new album creation
function handleNewAlbum($title, $subtitle, $slug, $date, $imgDir, $theme, $allowedGroups, $processImage, $extractExif, $maxImageWH, $maxPreviewWH, $conversionQuality) {
    $prwDirName = 'previews';
    $imgDirName = 'images';
    // Gather information
    $albumDir = ROOT_DIR . '/' . $slug;
    $srcDir = SOURCES_DIR . '/' . $imgDir;
    $albumID = generateRandomString();
    $dataDir = $albumDir . '/' . $albumID;
    $prwDir = $dataDir . '/' . $prwDirName;
    $imgDir = $dataDir . '/' . $imgDirName;
    $dataFile = $dataDir . '/refracta.json';

    // Create preview directory
    if (!is_dir($prwDir) && !mkdir($prwDir, 0755, true)) {
        throw new \Exception("Error: Could not create previews directory $prwDir.");
    }

    // Create images directory
    if (!is_dir($imgDir) && !mkdir($imgDir, 0755, true)) {
        throw new \Exception("Error: Could not create images directory $imgDir.");
    }

    // Generate image files
    $imageFiles = @scandir($srcDir);
    if ($imageFiles === false) {
        throw new \Exception("Error: Could not scan directory $srcDir.");
    }

    // Initialize data array
    $dataArray = [];

    foreach ($imageFiles as $filename) {
        if ($filename == '.' || $filename == '..') {
            continue;
        }

        // Get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (isImage($filename)) {
            $sourceImage = $srcDir . '/' . $filename;

            // Get image width and height
            $imageSize = getimagesize($sourceImage);
            $width = $imageSize[0];
            $height = $imageSize[1];

            $willBeProcessed = false;
            if ($processImage && ($width > $maxImageWH || $height > $maxImageWH)) {
                $willBeProcessed = true;
            }

            // Generate random file names
            $random_str = generateRandomString();
            $prwFilename = $random_str . '_preview.jpg';
            if ($willBeProcessed) {
                $imgFilename = $random_str . '_image.jpg';
            } else {
                $imgFilename = $random_str . '_image.' . $extension;
            }

            $prwPath = $prwDir . '/' . $prwFilename;
            $imgPath = $imgDir . '/' . $imgFilename;

            // Create preview
            createPreview($sourceImage, $prwPath, $maxPreviewWH, $conversionQuality);
            // Create image
            if ($willBeProcessed) {
                // Process image
                $result = createPreview($sourceImage, $imgPath, $maxImageWH, $conversionQuality);
            } else {
                // Copy as is
                copy($sourceImage, $imgPath);
                $result = [$width, $height];
            }

            if ($result !== false) {
                $width = $result[0];
                $height = $result[1];

                // Extract EXIF

                $exifData = [];
                if ($extractExif) {
                    $exif = @exif_read_data($sourceImage);

                    if (!empty($exif['DateTimeOriginal'])) {
                        $exifData['DateTimeOriginal'] = $exif['DateTimeOriginal'];
                    }

                    if (!empty($exif['FNumber'])) {
                        $exifData['FNumber'] = getNumber($exif['FNumber']);
                    }

                    if (!empty($exif['ExposureTime'])) {
                        $exifData['ExposureTime'] = getNumber($exif['ExposureTime']);
                    }
                    if (!empty($exif['ISOSpeedRatings'])) {
                        $exifData['ISO'] = getNumber($exif['ISOSpeedRatings']);
                    }
                    if (!empty($exif['FocalLength'])) {
                        $exifData['FocalLength'] = getNumber($exif['FocalLength']);
                    }

                    if (!empty($exif['Make'])) {
                        $exifData['Make'] = $exif['Make'];
                    }
                    if (!empty($exif['Model'])) {
                        $exifData['Model'] = $exif['Model'];
                    }
                    if (!empty($exif['LensModel'])) {
                        $exifData['LensModel'] = $exif['LensModel'];
                    } else {
                        if (!empty($exif['Model'])) {
                            $exifData['LensModel'] = "";
                        }
                    }

                    // GPS coordinates

                    $gps = getGPS($exif);

                    if (!empty($gps['latitude'])) {
                        $exifData['latitude'] = $gps['latitude'];
                    }
                    if (!empty($gps['longitude'])) {
                        $exifData['longitude'] = $gps['longitude'];
                    }

                    // Final dimensions
                    if (count($exifData) > 0) {
                        $exifData['ExifImageWidth'] = $width;
                        $exifData['ExifImageHeight'] = $height;
                    }
                }

                $srcFilePath = $albumID . '/' . $imgDirName . '/' . $imgFilename;
                $prwFilePath = $albumID . '/' . $prwDirName . '/' . $prwFilename;

                $tmpDataArray = [
                    'type' => 'image',
                    'src' => $srcFilePath,
                    'preview' => $prwFilePath,
                    'width' => $width,
                    'height' => $height
                ];

                if (count($exifData) > 0) {
                    $tmpDataArray['exif'] = $exifData;
                }
                $dataArray[] = $tmpDataArray;
            }
        } else if (isVideo($filename)) {
            $sourceVideo = $srcDir . '/' . $filename;
            $random_str = generateRandomString();
            $prwFilename = $random_str . '_preview.jpg';
            $imgFilename = $random_str . '_video.' . $extension;

            $prwPath = $prwDir . '/' . $prwFilename;
            $videoPath = $imgDir . '/' . $imgFilename;

            copy($sourceVideo, $videoPath);

            $srcFilePath = $albumID . '/' . $imgDirName . '/' . $imgFilename;
            $prwFilePath = $albumID . '/' . $prwDirName . '/' . $prwFilename;

            $tmpDataArray = [
                'type' => 'video',
                'src' => $srcFilePath,
                'width' => 1600,
                'height' => 1000,
            ];

            // Try to create video thumbnail and get dimensions
            if (FFPROBE_PATH !== false && FFMPEG_PATH !== false) {
                // Preview
                $thumbnailPath = $prwPath . '_thumb.jpg';
                $cmdPreview = FFMPEG_PATH . " -i " . escapeshellarg($videoPath) . " -ss 00:00:02 -vframes 1 " . escapeshellarg($thumbnailPath) . " 2>&1";
                exec($cmdPreview, $outputLines, $returnCode);

                if ($returnCode === 0) {
                    // Thumbnail created successfully
                    // Recreate normal preview based on the thumbnail
                    [$width, $height] = createPreview($thumbnailPath, $prwPath, $maxPreviewWH, $conversionQuality);
                    // Delete thumbnail
                    unlink($thumbnailPath);
                    $tmpDataArray['preview'] = $prwFilePath;
                    $tmpDataArray['width'] = $width;
                    $tmpDataArray['height'] = $height;
                } else {
                    // Something went wrong. No preview generated.
                }
            }

            $dataArray[] = $tmpDataArray;
        }
    }

    // Format date
    list($year, $month, $day) = explode('-', $date);
    $timestamp = mktime(0, 0, 0, (int)$month, (int)$day, (int)$year);

    $dataArray = [
        'id' => (string)$albumID,
        'title' => (string)$title,
        'subtitle' => (string)$subtitle,
        'date' => (int)$timestamp,
        'theme' => (string)$theme,
        'access' => (array)$allowedGroups,
        'images' => $dataArray
    ];

    // Encode and save JSON
    $jsonContent = json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonContent === false) {
        // Output error message
        die("Error: Could not encode data to JSON.");
    }

    if (file_put_contents($dataFile, $jsonContent) === false) {
        // Output error message
        die("Error: Could not write to $dataFile. Check write permissions.");
    }

    // Copy index.tpl.php to the album directory
    copy(REFRACTA_DIR . '/templates/index.tpl.php', $albumDir . '/index.php');
}
// Returns sorted list of albums
function loadAlbumsList($fullData = true) {
    // List all galleries
    $galleries = scandir(ROOT_DIR);
    $galleries = array_diff($galleries, ['assets', 'refracta']);

    $albums = [];

    foreach($galleries as $gallery) {
        if ($gallery[0] !== '.' && is_dir(ROOT_DIR . '/' . $gallery)) {
            // Get the subdirectory name
            $files = scandir(ROOT_DIR . '/' . $gallery);
            $subdirs = [];
            foreach($files as $file) {
                if ($file[0] !== '.' && is_dir(ROOT_DIR . '/' . $gallery . '/' . $file)) {
                    $subdirs[] = $file;
                }
            }
            if (count($subdirs) === 1) {
                // Valid, add to list
                $data = json_decode(file_get_contents(ROOT_DIR . '/' . $gallery . '/' . $subdirs[0] . '/refracta.json'), true);
                if ($fullData === false) {
                    $data['datestr'] = date(DATE_FORMAT, $data['date']);
                    $data['count'] = 0;
                    foreach($data['images'] as $image) {
                        if (empty($image['type']) || $image['type'] === 'image' || $image['type'] === 'video') {
                            $data['count'] += 1;
                        }
                    }
                    unset($data['images']);
                    unset($data['theme']);
                    unset($data['subtitle']);
                }
                $albums[] = [
                    'dir' => $gallery,
                    'data' => $data,
                ];
            }
        }
    }

    // Sort albums by timestamp
    usort($albums, function ($a, $b) {
        return $b['data']['date'] <=> $a['data']['date'];
    });

    return $albums;
}
// Recursive directory deletion function
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}
// Get latitude, longitude coordinates from EXIF
function getGPS($exif_data)
{
    if (!isset($exif_data['GPSLatitude']) || !isset($exif_data['GPSLongitude'])) {
        return null;
    }
    $latitude = GPStoDecimal($exif_data['GPSLatitude'], $exif_data['GPSLatitudeRef']);
    $longitude = GPStoDecimal($exif_data['GPSLongitude'], $exif_data['GPSLongitudeRef']);
    return array('latitude' => $latitude, 'longitude' => $longitude);
}
function GPStoDecimal($coord, $ref)
{
    $degrees = count($coord) > 0 ? getNumber($coord[0]) : 0;
    $minutes = count($coord) > 1 ? getNumber($coord[1]) : 0;
    $seconds = count($coord) > 2 ? getNumber($coord[2]) : 0;

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
    return ($ref == 'S' || $ref == 'W') ? -$decimal : $decimal;
}
// Get number from EXIF strings ("280/100" => 2.8)
function getNumber($input)
{
    if (strstr($input, '/')) {
        list($a, $b) = explode('/', $input);
        return $a / $b;
    } else {
        return $input;
    }
}
// Resizes an image, preserving the aspect ratio, based on the longest side.
function createPreview($sourceFile, $destFile, $maxWH, $quality)
{
    if (extension_loaded('imagick')) {
        // Imagick extension found
        return createPreviewImagick($sourceFile, $destFile, $maxWH, $quality);
    } elseif (extension_loaded('gd')) {
        // GD extension found
        return createPreviewGD($sourceFile, $destFile, $maxWH, $quality);
    } else {
        die("Error: Neither Imagick nor GD are installed for image processing.");
    }
}
// Resizes an image using Imagick, preserving the aspect ratio, based on the longest side.
function createPreviewImagick($sourceFile, $destFile, $maxWH, $quality)
{
    try {
        $imagick = new Imagick($sourceFile);

        $sourceWidth = $imagick->getImageWidth();
        $sourceHeight = $imagick->getImageHeight();

        // Thumbnail the image
        $imagick->setImageCompressionQuality($quality);
        $imagick->thumbnailImage(min($sourceWidth, $maxWH), min($sourceHeight, $maxWH), true);

        // Save the image to the specified file
        $imagick->writeImage($destFile);

        $newWidth = $imagick->getImageWidth();
        $newHeight = $imagick->getImageHeight();

        // Free up memory
        $imagick->clear();
        $imagick->destroy();

        return [$newWidth, $newHeight];
    } catch (ImagickException $e) {
        die("Imagick Error: " . $e->getMessage());
    }
}
// Resizes an image using GD, preserving the aspect ratio, based on the longest side.
function createPreviewGD($sourceFile, $destFile, $maxWH, $quality)
{
    // Get image information
    $imageInfo = getimagesize($sourceFile);
    if (!$imageInfo) {
        return false;
    }

    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mime = $imageInfo['mime'];
    $ratio = $sourceWidth / $sourceHeight;

    // Determine new dimensions, preserving the aspect ratio
    if ($sourceWidth >= $sourceHeight) {
        // Landscape or square
        $newWidth = min($sourceWidth, $maxWH);
        $newHeight = round($newWidth / $ratio);
    } else {
        // Portrait
        $newHeight = min($sourceHeight, $maxWH);
        $newWidth = round($newHeight * $ratio);
    }

    // Create an image resource
    $sourceImage = null;

    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = @imagecreatefromjpeg($sourceFile);
            break;
        case 'image/png':
            $sourceImage = @imagecreatefrompng($sourceFile);
            break;
        case 'image/gif':
            $sourceImage = @imagecreatefromgif($sourceFile);
            break;
        default:
            return false; // Unsupported type
    }

    if (!$sourceImage) {
        return false;
    }

    // Create a new image with the desired dimensions
    $destImage = imagecreatetruecolor($newWidth, $newHeight);

    // Copy and resize with resampling for better quality
    $result = imagecopyresampled(
        $destImage,
        $sourceImage,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $sourceWidth,
        $sourceHeight
    );

    if (!$result) {
        unset($sourceImage);
        unset($destImage);
        return false;
    }

    // Save the preview
    $saveSuccess = imagejpeg($destImage, $destFile, $quality);

    // Free up memory
    unset($sourceImage);
    unset($destImage);

    return $saveSuccess ? [$newWidth, $newHeight] : false;
}
// Checks if file is image
function isImage($filename)
{
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed_ext);
}
// Checks if file is video
function isVideo($filename)
{
    $allowed_ext = ['mp4', 'webm', 'mov', 'avi', 'wmv'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed_ext);
}
// Renders gallery markup
function renderGallery($slug, $images)
{
    echo '<script>';
    echo 'let images = [];';
    echo '</script>';
    $tmpImages = [];
    $currentID = 0;
    foreach($images as $image) {
        if ($image['type'] === 'image' || $image['type'] === 'video') {
            // Add image to tmp
            if (!empty($image['src'])) {
                $image['src'] = REFRACTA_URL . '/' . $slug . '/' . $image['src'];
            }
            if (!empty($image['preview'])) {
                $image['preview'] = REFRACTA_URL . '/' . $slug . '/' . $image['preview'];
            }
            $tmpImages[] = $image;
        } else if ($image['type'] === 'text' || $image['type'] === 'title') {
            // Text or title
            if (count($tmpImages) > 0) {
                // Previous images. Generate gallery data
                echo '<script>';
                echo 'images.push(' . json_encode($tmpImages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ');';
                echo '</script>';
                echo "<div id='lumosaic-$currentID' class='album-inline-gallery'></div>";
                $currentID++;
                // Reset images
                $tmpImages = [];
            }
            if ($image['type'] === 'title') {
                echo '<div class="album-inline-title scaled"><h2>' . $image['text'] . '</h2></div>';
            } else {
                echo '<div class="album-inline-text scaled"><p>' . $image['text'] . '</p></div>';
            }
        }
    }
    if (count($tmpImages) > 0) {
        // Last images. Generate gallery data
        echo '<script>';
        echo 'images.push(' . json_encode($tmpImages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ');';
        echo '</script>';
        echo "<div id='lumosaic-$currentID' class='album-inline-gallery'></div>";
        $currentID++;
    }
    echo '<script>';
    echo "const containers = $currentID;";
    echo '</script>';
}
// Returns header HTML
function getHeader($title, $theme, $mode)
{
    ?>
<!DOCTYPE html>
<html lang="en" class="theme-<?php echo $theme; ?>">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

    <title><?php echo $title; ?></title>

<?php
    if ($mode === 'gallery' || $mode === 'album') {
        echo '    <link rel="stylesheet" href="' . ASSET_URL . '/lumosaic/lumosaic.css?c=' . ASSET_CACHE . '">' . "\n";
    }
    if ($mode === 'album') {
        echo '    <link rel="stylesheet" href="' . ASSET_URL . '/obsidium/obsidium.css?c=' . ASSET_CACHE . '">' . "\n";
    }
    echo '    <link rel="stylesheet" href="' . ASSET_URL . '/app/app.css?c=' . ASSET_CACHE . '">' . "\n";
    if ($mode === 'admin') {
        echo '    <link rel="stylesheet" href="' . ASSET_URL . '/app/admin.css?c=' . ASSET_CACHE . '">' . "\n";
    }
    ?>
</head>

<body>
    <?php
}
// Returns footer HTML
function getFooter($mode)
{
    if ($mode === 'gallery' || $mode === 'album') {
        echo '    <script src="' . ASSET_URL . '/lumosaic/lumosaic.js?c=' . ASSET_CACHE . '"></script>' . "\n";
    }
    if ($mode === 'gallery') {
        echo '    <script src="' . ASSET_URL . '/app/gallery.js?c=' . ASSET_CACHE . '"></script>' . "\n";
    }
    if ($mode === 'album') {
        echo '    <script src="' . ASSET_URL . '/obsidium/obsidium.js?c=' . ASSET_CACHE . '"></script>' . "\n";
        echo '    <script src="' . ASSET_URL . '/app/album.js?c=' . ASSET_CACHE . '"></script>' . "\n";
    }
    if ($mode === 'admin') {
        echo '    <script src="' . ASSET_URL . '/app/admin.js?c=' . ASSET_CACHE . '"></script>' . "\n";
    }
    ?>
</body>
</html>
<?php
}