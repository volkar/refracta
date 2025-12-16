<?php
/*
 * Refracta 1.0.1
 * Refracta's Admin Panel script.
 *
 * https://refracta.syntheticsymbiosis.com
 * Copyright 2025 Sergey Volkar ⣿ SyntheticSymbiosis
 *
 * Released under the MIT License
 */

declare(strict_types=1);

require_once 'config.php';
require_once INCL_DIR . '/refracta.functions.php';

$dataFilename = getDataFilename();

// First time setup
if ($dataFilename === false) {
    // Make sure directories exist
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    if (!is_dir(SOURCES_DIR)) {
        mkdir(SOURCES_DIR, 0755, true);
    }

    // Create admin access key
    $key = generateRandomString();
    // Save to session
    $_SESSION['refracta_auth_key'] = $key;
    // Default groups
    $groups = ['admin', 'family', 'friends'];
    // Default keys
    $keys = [$key => ['admin']];
    // Create default keys file
    $dataFilename = generateRandomString() . '.json';
    file_put_contents(DATA_DIR . '/' . $dataFilename, json_encode(['groups' => $groups,'keys' => $keys], JSON_PRETTY_PRINT));

    // Output success page
    echo getHeader('Refracta Setup', 'dark', 'admin');
    ?>
    <div class="layout">
        <div class="card">
            <h2>First Time Setup</h2>
            <p>Refracta is now installed and ready to use!</p>
            <p>Your admin access link is:<br><strong class="dashed"><?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/admin.php?key=' . $_SESSION['refracta_auth_key']; ?></strong><br>
            Copy it and save it somewhere safe.</p>
            <p class="mt-4"><a href="admin.php"><button class="btn-primary">Start</button></a></p>
        </div>
    </div>
    <?php
    echo getFooter('admin');
    exit;
}

// Check if user is admin
$data = checkPageAccess(['admin'], $dataFilename);

// API Router & Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'data' => []];

    try {
        $action = $_POST['action'];

        switch ($action) {

            case 'load_data':
                // Load data from file
                $response['data'] = loadDataFile();
                $response['success'] = true;
                break;

            case 'load_albums':
                // Load albums from file
                $response['data'] = loadAlbumsList(false);
                $response['success'] = true;
                break;

            case 'generate_key_string':
                // Generate a random 32-character hex string
                $response['data'] = ['key' => generateRandomString()];
                $response['success'] = true;
                break;

            case 'create_group':
                // Create a new group
                if (isset($_POST['group_name']) && !empty($_POST['group_name'])) {
                    // Format group name
                    $groupName = preg_replace('/[^a-zA-Z0-9_]/', '', basename(trim($_POST['group_name'])));
                    // Ensure data is actual
                    $data = loadDataFile();
                    if ($groupName !== '' && !in_array($groupName, $data['groups'])) {
                        $data['groups'][] = $groupName;
                        // Sort groups alphabetically
                        sort($data['groups']);
                        saveDataFile();
                        $response['success'] = true;
                        $response['data'] = $data;
                    } else {
                        throw new Exception('Group name is invalid or group already exists.');
                    }
                }
                break;

            case 'delete_group':
                // Delete a group
                if (isset($_POST['group_name']) && !empty($_POST['group_name'])) {
                    // Format group name
                    $groupName = preg_replace('/[^a-zA-Z0-9_]/', '', basename(trim($_POST['group_name'])));
                    if ($groupName === 'admin') {
                        throw new Exception('Cannot delete admin group.');
                    }
                    // Ensure data is actual
                    $data = loadDataFile();
                    if ($groupName !== '' && in_array($groupName, $data['groups'])) {
                        $data['groups'] = array_diff($data['groups'], [$groupName]);
                        saveDataFile();
                        $response['success'] = true;
                        $response['data'] = $data;
                    } else {
                        throw new Exception("Group name is invalid or group does not exist.");
                    }
                }
                break;

            case 'save_key':
                $key = $_POST['key'] ?? '';
                $groups = $_POST['groups'] ?? [];
                if (strlen($key) !== 32) throw new Exception("Invalid Key format.");
                // Ensure data is actual
                $data = loadDataFile();
                $data['keys'][$key] = array_unique($groups);
                saveDataFile();
                $response['success'] = true;
                $response['message'] = 'Key saved successfully.';
                break;

            case 'delete_key':
                $key = $_POST['key'] ?? '';
                // Ensure data is actual
                $data = loadDataFile();
                if ($key && isset($data['keys'][$key])) {
                    unset($data['keys'][$key]);
                    saveDataFile();
                }
                $response['success'] = true;
                break;

            // --- Main Form Submission ---
            case 'create_album':
                $title = $_POST['title'] ?? '';
                $subtitle = $_POST['subtitle'] ?? '';
                $slug = trim(basename($_POST['slug'] ?? ''));
                $date = $_POST['date'] ?? '';
                $imgDir = trim(basename($_POST['img_dir'] ?? ''));
                $theme = $_POST['theme'] ?? 'dark';
                $allowedGroups = $_POST['allowed_groups'] ?? [];

                $processImages = $_POST['process_images'] ?? 'yes';
                $processImage = $processImages === 'yes';
                $extractExif = $_POST['extract_exif'] ?? 'yes';
                $extractExif = $extractExif === 'yes';
                $maxImageWH = $_POST['max_image_wh'] ?? 2000;
                $maxPreviewWH = $_POST['max_preview_wh'] ?? 500;
                $conversionQuality = $_POST['conversion_quality'] ?? 60;

                if (strlen($title) === 0) throw new Exception('Title is required.');
                if (strlen($slug) === 0) throw new Exception('Slug is required.');
                if (strlen($imgDir) === 0) throw new Exception('Image directory is required.');
                if (strlen($date) === 0) throw new Exception('Date is required.');
                if (strlen($theme) === 0) throw new Exception('Theme is required.');
                if (!is_array($allowedGroups)) throw new Exception('Allowed groups are required.');
                if ($maxImageWH < 100) throw new Exception('Max image width/height must be at least 100.');
                if ($maxPreviewWH < 100) throw new Exception('Max preview width/height must be at least 100.');
                if ($conversionQuality < 10 || $conversionQuality > 100) throw new Exception('Conversion quality must be between 10 and 100.');

                // Dest directory exists
                if (is_dir(__DIR__ . '/' . $slug)) throw new Exception("Invalid or existing slug (directory).");
                // Source directory not exists
                if (!is_dir(SOURCES_DIR . '/' . $imgDir)) throw new Exception("Source directory missing.");
                // Source directory is empty
                if (empty(scandir(SOURCES_DIR . '/' . $imgDir))) throw new Exception("Source directory is empty.");

                // Create the Directory
                if (!mkdir(__DIR__ . '/' . $slug, 0755, true)) {
                    throw new Exception("Failed to create directory.");
                }

                handleNewAlbum($title, $subtitle, $slug, $date, $imgDir, $theme, $allowedGroups, $processImage, $extractExif, $maxImageWH, $maxPreviewWH, $conversionQuality);

                $response['success'] = true;
                $response['message'] = "Album '$title' created successfully!";
                break;

            // --- Update Album Form Submission ---
            case 'update_album':
                $title = $_POST['title'] ?? '';
                $subtitle = $_POST['subtitle'] ?? '';
                $date = $_POST['date'] ?? '';
                $theme = $_POST['theme'] ?? 'dark';
                $allowedGroups = $_POST['allowed_groups'] ?? [];

                if (strlen($title) === 0) throw new Exception('Title is required.');
                if (strlen($date) === 0) throw new Exception('Date is required.');
                if (strlen($theme) === 0) throw new Exception('Theme is required.');
                if (!is_array($allowedGroups)) throw new Exception('Allowed groups are required.');

                $slug = trim(basename($_POST['album']));
                $album = loadAlbumData($slug);
                if (!$album) throw new Exception("Album not found.");

                // Update album data
                // Format date
                list($year, $month, $day) = explode('-', $date);
                $timestamp = mktime(0, 0, 0, (int)$month, (int)$day, (int)$year);
                // Format images
                $images = $_POST['images'] ?? [];
                $finalImages = [];
                for ($i = 0; $i < count($images); $i++) {
                    if ($images[$i] === 'title' || $images[$i] === 'text') {
                        // Get next image
                        $type = $images[$i];
                        $i++;
                        $text = $images[$i];
                        $finalImages[] = ['type' => $type, 'text' => $text];
                    } else if ($images[$i][0] === '{') {
                        // Normal Image in JSON
                        $finalImages[] = json_decode($images[$i], true);
                    }
                }
                $album['title'] = $title;
                $album['subtitle'] = $subtitle;
                $album['date'] = $timestamp;
                $album['theme'] = $theme;
                $album['access'] = $allowedGroups;
                $album['images'] = $finalImages;
                updateAlbumData($slug, $album);

                $response['success'] = true;
                $response['message'] = "Album '$title' updated successfully!";
                break;

            // --- Delete Album ---
            case 'delete_album':
                $album = $_POST['album'] ?? '';
                $album = trim(basename($album));

                if (strlen($album) === 0 || $album[0] === '.') throw new Exception('Album is required.');
                if ($album === basename(ASSET_DIR) || $album === basename(REFRACTA_DIR)) throw new Exception('Album is required.');
                if (!is_dir(ROOT_DIR . '/' . $album)) throw new Exception("Album not found.");

                // Delete album
                if (!deleteDirectory(ROOT_DIR . '/' . $album)) throw new Exception("Failed to delete album.");

                $response['success'] = true;
                $response['message'] = "Album deleted successfully!";
                break;
            default:
                throw new Exception("Invalid action.");
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

echo getHeader('Refracta Admin', ADMIN_THEME, 'admin');
if (empty($_GET['album'])) {
    // Admin panel
?>
<script>
    const refractaURL = "<?php echo REFRACTA_URL; ?>";
    const adminPanel = true;
</script>
<div class="layout">
    <div class="card">
        <h2><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" d="M432 112V96a48.14 48.14 0 0 0-48-48H64a48.14 48.14 0 0 0-48 48v256a48.14 48.14 0 0 0 48 48h16"/><rect width="400" height="336" x="96" y="128" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" rx="45.99" ry="45.99"/><ellipse cx="372.92" cy="219.64" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32" rx="30.77" ry="30.55"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M342.15 372.17L255 285.78a30.93 30.93 0 0 0-42.18-1.21L96 387.64M265.23 464l118.59-117.73a31 31 0 0 1 41.46-1.87L496 402.91"/></svg> Create New Album</h2>
        <form id="albumForm" autocomplete="off" onsubmit="createAlbum(); return false;">
            <input type="hidden" name="action" value="create_post">

            <div class="form-group">
                <label>Title</label>
                <input id="title" type="text" name="title" required placeholder="My Amazing Album">
            </div>

            <div class="form-group">
                <label>Subtitle (optional)</label>
                <input id="subtitle" type="text" name="subtitle" placeholder="It will be used as a subtitle">
            </div>

            <div class="form-group">
                <label>Slug (Directory Name)</label>
                <input type="text" id="slug" name="slug" placeholder="my-amazing-album">
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" id="date" name="date" required>
            </div>

            <div class="form-group">
                <label>Image Directory</label>
                <select id="img_dir" name="img_dir" required>
                    <option value="" disabled selected>Select an image directory</option>
                    <?php
                    $directories = glob(SOURCES_DIR . '/*', GLOB_ONLYDIR);
                    foreach ($directories as $directory) {
                        $dirName = basename($directory);
                        echo "<option value='{$dirName}'>{$dirName}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Theme</label>
                <select id="theme" name="theme" required>
                    <?php foreach (THEMES as $theme) { ?>
                        <option value="<?php echo $theme; ?>"><?php echo ucfirst($theme); ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label>Allowed User Groups</label>
                <div id="allowed_groups_display" class="tags-wrapper">
                    <span class="no-items">Open for everyone</span>
                </div>
                <div id="allowed_groups_inputs"></div>

                <div style="display: flex; gap: 10px;">
                    <select id="group_selector">
                        <option value="" disabled selected>Select Group</option>
                    </select>
                    <button type="button" onclick="addAllowedGroup()" class="btn-primary" style="width: auto; margin-top: 0;">Add</button>
                </div>
            </div>

            <div class="form-group">
                <label>Maximum Image Width/Height</label>
                <input id="max_image_wh" type="number" name="max_image_wh" placeholder="Maximum large image width/height" value="2000">
            </div>

            <div class="form-group">
                <label>Maximum Preview Width/Height</label>
                <input id="max_preview_wh" type="number" name="max_preview_wh" placeholder="Maximum preview image width/height" value="500">
            </div>

            <div class="form-group">
                <label>Resize Large Images</label>
                <select id="process_images" name="process_images" required>
                    <option value="yes">Yes (process images)</option>
                    <option value="no">No (copy images)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Conversion quality</label>
                <input id="conversion_quality" type="number" name="conversion_quality" placeholder="Conversion quality" value="60">
            </div>

            <div class="form-group">
                <label>Extract EXIF data</label>
                <select id="extract_exif" name="extract_exif" required>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Create Album</button>
        </form>

        <h3>Existing Albums :: <a href="#" onclick="toggleElementDisplay('albums_list'); this.innerHTML = this.innerHTML === 'Hide' ? 'Show' : 'Hide'; return false;">Hide</a></h3>
        <div id="albums_list">
            <div class="no-items">Loading...</div>
        </div>
    </div>

    <div class="card">
        <h2><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3.128a4 4 0 0 1 0 7.744M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></g></svg> User Groups</h2>

        <div class="form-group">
            <label>New User Group</label>
            <input type="text" id="new_group_name" required placeholder="work">
        </div>

        <button type="button" onclick="createGroup()" class="btn-primary">Create Group</button>

        <h3>Existing User Groups :: <a href="#" onclick="toggleElementDisplay('groups_list'); this.innerHTML = this.innerHTML === 'Hide' ? 'Show' : 'Hide'; return false;">Hide</a></h3>
        <div id="groups_list">
            <div class="no-items">Loading...</div>
        </div>

        <hr>

        <h2><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="currentColor" fill-rule="evenodd" d="M426.667 320v-64H384v-85.333c0-70.305-57.51-128-128-128s-128 57.695-128 128V256H85.333v213.334h128v-42.667H128v-128h256v32.749c12.551-7.26 27.124-11.416 42.667-11.416m-256-149.333c0-46.338 38.002-85.333 85.333-85.333s85.333 38.995 85.333 85.333V256H170.667zm320 234.667c0-35.347-28.654-64-64-64c-27.866 0-51.573 17.809-60.359 42.666H234.667v42.667H256v42.667h64v-42.667h46.308c8.786 24.857 32.493 42.667 60.359 42.667c35.346 0 64-28.654 64-64m-96 0c0-17.673 14.327-32 32-32s32 14.327 32 32s-14.327 32-32 32s-32-14.327-32-32" clip-rule="evenodd"/></svg> Access Keys</h2>

        <label>New Access Key</label>
        <div style="display:flex; align-items: center; gap:10px;" class="form-group">
            <input type="text" id="new_key_display" readonly placeholder="Click Generate" style="cursor: not-allowed;">
            <button type="button" class="btn-primary" onclick="generateKey()" style="width: auto; margin: 0;">Generate</button>
        </div>

        <label>Assign Groups</label>
        <div id="key_groups_display" class="tags-wrapper">
            <span class="no-items">No groups assigned</span>
        </div>

        <div style="display: flex; gap: 10px;" class="form-group">
            <select id="key_group_selector">
                <option value="" disabled selected>Select Group</option>
            </select>
            <button type="button" onclick="addKeyGroup()" class="btn-primary" style="width: auto; margin-top: 0;">Add</button>
        </div>

        <button type="button" onclick="saveKey()" class="btn-primary">Create Key</button>

        <h3>Existing Keys :: <a href="#" onclick="toggleElementDisplay('keys_list'); this.innerHTML = this.innerHTML === 'Hide' ? 'Show' : 'Hide'; return false;">Hide</a></h3>
        <div id="keys_list">
            <div class="no-items">Loading...</div>
        </div>
    </div>

</div>
<?php
} else {
    // Album page
    $albumUrl = trim(basename($_GET["album"]));
    $album = loadAlbumData($albumUrl);
    if (!$album) {
        echo "<h1>Album not found</h1>";
        echo getFooter('admin');
        exit;
    }
    ?>
<script>
    const refractaURL = "<?php echo REFRACTA_URL; ?>";
    const adminPanel = false;
</script>
<div class="scaled">
    <div class="back">
        <a href="admin.php"><button class="back">Back to Admin Panel</button></a>
    </div>
</div>
<div class="layout">
    <div class="card">
        <h2><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" d="M432 112V96a48.14 48.14 0 0 0-48-48H64a48.14 48.14 0 0 0-48 48v256a48.14 48.14 0 0 0 48 48h16"/><rect width="400" height="336" x="96" y="128" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" rx="45.99" ry="45.99"/><ellipse cx="372.92" cy="219.64" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32" rx="30.77" ry="30.55"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M342.15 372.17L255 285.78a30.93 30.93 0 0 0-42.18-1.21L96 387.64M265.23 464l118.59-117.73a31 31 0 0 1 41.46-1.87L496 402.91"/></svg> Edit Album :: <?php echo $albumUrl; ?></h2>
        <form id="updateAlbumForm" autocomplete="off" onsubmit="updateAlbum(); return false;">
            <input type="hidden" name="album" id="updateAlbumSlug" value="<?php echo $albumUrl; ?>">

            <div class="form-group">
                <label>Title</label>
                <input id="title" type="text" value="<?php echo htmlspecialchars($album["title"]); ?>" name="title" required placeholder="My Amazing Album">
            </div>

            <div class="form-group">
                <label>Subtitle (optional)</label>
                <input id="subtitle" type="text" value="<?php echo htmlspecialchars($album["subtitle"]); ?>" name="subtitle" placeholder="It will be used as a subtitle">
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" id="date" value="<?php echo date("Y-m-d", $album["date"]) ?>" name="date" required>
            </div>

            <div class="form-group">
                <label>Theme</label>
                <select id="theme" name="theme" required>
                    <?php foreach (THEMES as $theme) { ?>
                        <option value="<?php echo $theme; ?>" <?php echo $album["theme"] === $theme ? "selected" : ""; ?>><?php echo ucfirst($theme); ?></option>
                    <?php } ?>
                </select>
            </div>

            <script>
                let albumAllowedGroups = <?php echo json_encode($album["access"]); ?>;
            </script>
            <div class="form-group">
                <label>Allowed User Groups</label>
                <div id="allowed_groups_display" class="tags-wrapper">
                    <span class="no-items">Open for everyone</span>
                </div>
                <div id="allowed_groups_inputs"></div>

                <div style="display: flex; gap: 10px;">
                    <select id="group_selector">
                        <option value="" disabled selected>Select Group</option>
                    </select>
                    <button type="button" onclick="addAllowedGroup()" class="btn-primary" style="width: auto; margin-top: 0;">Add</button>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="margin-bottom: 1rem;">Update Album</button>
            <button type="button" onclick="deleteAlbum('<?php echo $albumUrl; ?>')" class="btn-danger">Delete Album</button>
        </form>

    </div>

    <div class="card">
        <h2><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" d="M432 112V96a48.14 48.14 0 0 0-48-48H64a48.14 48.14 0 0 0-48 48v256a48.14 48.14 0 0 0 48 48h16"/><rect width="400" height="336" x="96" y="128" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" rx="45.99" ry="45.99"/><ellipse cx="372.92" cy="219.64" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32" rx="30.77" ry="30.55"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M342.15 372.17L255 285.78a30.93 30.93 0 0 0-42.18-1.21L96 387.64M265.23 464l118.59-117.73a31 31 0 0 1 41.46-1.87L496 402.91"/></svg> Album Images</h2>

        <div id="admin_images">
        <?php
        foreach ($album['images'] as $image) {
            if ($image['type'] === 'image' || $image['type'] === 'video') {
                echo '<div class="admin-image-item-wrapper">';
                echo '<div class="admin-image-item">';
                if ($image['type'] === 'video') {
                    echo '<div class="play-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="m10.775 15.475l4.6-3.05q.225-.15.225-.425t-.225-.425l-4.6-3.05q-.25-.175-.513-.038T10 8.926v6.15q0 .3.263.438t.512-.038M12 22q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9T2 12q0-.8.125-1.6T2.5 8.825q.125-.4.513-.537t.737.062q.375.2.538.588t.037.812q-.15.55-.238 1.113T4 12q0 3.35 2.325 5.675T12 20t5.675-2.325T20 12t-2.325-5.675T12 4q-.6 0-1.187.087T9.65 4.35q-.425.125-.8-.025T8.3 3.8t-.013-.762t.563-.513q.75-.275 1.55-.4T12 2q2.075 0 3.9.788t3.175 2.137T21.213 8.1T22 12t-.788 3.9t-2.137 3.175t-3.175 2.138T12 22M5.5 7q-.625 0-1.062-.437T4 5.5t.438-1.062T5.5 4t1.063.438T7 5.5t-.437 1.063T5.5 7m6.5 5"/></svg></div>';
                }
                if (!empty($image['preview'])) {
                    echo '<img src="' . REFRACTA_URL . '/' . $albumUrl . '/' . $image['preview'] . '">';
                }
                echo '</div>';
                echo '<input type="hidden" name="images[]" value="' . htmlspecialchars(json_encode($image)) . '">';
                echo '<div class="admin-image-item-add-element" onclick="addImageElement(event)">+</div>';
                echo '<div class="admin-image-item-delete-element" onclick="deleteImageElement(event)">␡</div>';
                echo '<div class="item-move-next" onclick="moveImageElement(event, \'next\')"><span>➜</span></div>';
                echo '<div class="item-move-prev" onclick="moveImageElement(event, \'prev\')"><span>➜</span></div>';
                echo '</div>';
            } else if ($image['type'] === 'title' || $image['type'] === 'text') {
                echo '<div class="admin-text-item-wrapper">';
                echo '<div class="admin-text-item">';
                if ($image['type'] === 'title') {
                    echo '<h2>' . $image['text'] . '</h2>';
                } else {
                    echo '<p>' . $image['text'] . '</p>';
                }
                echo '</div>';
                echo '<input type="hidden" name="images[]" value="' . htmlspecialchars(json_encode($image)) . '">';
                echo '<div class="admin-image-item-add-element" onclick="addImageElement(event)">+</div>';
                echo '<div class="admin-image-item-delete-element" onclick="deleteImageElement(event)">␡</div>';
                echo '<div class="item-move-next" onclick="moveImageElement(event, \'next\')"><span>➜</span></div>';
                echo '<div class="item-move-prev" onclick="moveImageElement(event, \'prev\')"><span>➜</span></div>';
                echo '</div>';
            }
        }
        ?>
        </div>
    </div>

</div>
    <?php
}

echo getFooter('admin');