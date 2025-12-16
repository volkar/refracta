<?php
/*
 * Refracta 1.0.1
 * Refracta's Gallery script.
 * Searches all subdirectories and compiles a list of allowed albums.
 *
 * https://refracta.syntheticsymbiosis.com
 * Copyright 2025 Sergey Volkar ⣿ SyntheticSymbiosis
 *
 * Released under the MIT License
 */

require_once 'config.php';
require_once INCL_DIR . '/refracta.functions.php';

$data = checkPageAccess(GALLERY_ACCESS);

echo getHeader('Refracta Gallery', GALLERY_THEME, 'gallery');

$albumsAll = loadAlbumsList();
$albums = [];

foreach ($albumsAll as $album) {
    // Get album access permission
    if (checkAccess($album['data']['access'], $data['keys'])) {
        // Add album data to albums array
        $albums[] = [
            'dir' => $album['dir'],
            'title' => $album['data']['title'] ?? $album['dir'],
            'subtitle' => $album['data']['subtitle'] ?? '',
            'date' => isset($album['data']['date']) ? date(DATE_FORMAT, $album['data']['date']) : '',
            'timestamp' => isset($album['data']['date']) ? $album['data']['date'] : 0,
            'images' => isset($album['data']['images']) ? $album['data']['images'] : [],
        ];
    }
}

if (count($albums) > 0) {
    // Render albums
    $lastYear = false;
    $years = [];
    echo '<div class="gallery">';
    foreach ($albums as $album) {
        $currentAlbumYear = date('Y', $album['timestamp']);
        if ($currentAlbumYear !== $lastYear) {
            $lastYear = $currentAlbumYear;
            $years[] = $currentAlbumYear;
            $yearAlbum = true;
        } else {
            $yearAlbum = false;
        }
        ?>
        <div class="album scaled"<?php echo ' id="album-year-' . $lastYear . '"' ?>>
            <a href="<?php echo REFRACTA_URL . '/' . $album['dir'] ?>">
                <h1><?php echo $album['title'] ?></h1>
                <h3><?php echo $album['date'] ?></h3>
                <div class="album-cover">
                    <?php
                    if ($yearAlbum) {
                        echo '<div class="album-year" data-year="' . $lastYear . '">' . $lastYear . '</div>';
                    }
                    // Get preview
                    $preview = false;
                    foreach($album['images'] as $image) {
                        if (!empty($image['preview'])) {
                            $preview = REFRACTA_URL . '/' . $album['dir'] . '/' . $image['preview'];
                            break;
                        }
                    }
                    if ($preview !== false) {
                        echo "<img src=\"$preview\">";
                    }
                    ?>
                </div>
            </a>
        </div>
    <?php
    }
    echo '</div>';

    if (count($albums) > 3) {
        echo '<div id="timeline"><div class="timeline-scroll"><div class="timeline-wrapper">';
        for ($i = 0; $i < 30; $i++) {
            //$years[] = 2000 + $i;
        }
        for ($i = 0; $i < count($years); $i++) {
            $year = $years[$i];
            echo '<div class="timeline-year" id="timeline-year-' . $year . '"><a href="#album-year-' . $year . '"' . ($i == 0 ? ' class="active"' : '') . '>' . $year . '</a></div>';
        }
        echo '</div></div></div>';
    }
} else {
    // No albums found
    echo '<div style="text-align: center; color: var(--muted); margin-top: 4rem;">No albums found.</div>';
}

echo getFooter('gallery');
