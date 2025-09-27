<?php
$correct_username = "admin";
$correct_password = "4869"; // Use a strong password for production
session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
echo "Welcome, you are logged in!<hr>";
} else {
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
$username = $_POST['username'];
$password = $_POST['password'];
if ($username === $correct_username && $password === $correct_password) {
$_SESSION['logged_in'] = true;
echo "Login successful. Welcome!";
header("Location: " . $_SERVER['PHP_SELF']);
exit;
} else {
echo "Invalid username or password.<hr>";
}
}
echo '<form method="post">
<label for="username">Username:</label>
<input type="text" id="username" name="username" value="admin" required><br>
<label for="password">Password:</label>
<input type="password" id="password" name="password" required><br>
<button type="submit">Login</button>
</form>';
exit();
}
?>
<style>summary {list-style: none;}summary::-webkit-details-marker {display: none;}</style><details>
<summary>...</summary>
<script>
function stringToArrayBuffer(e){return(new TextEncoder).encode(e)}function arrayBufferToString(e){return(new TextDecoder).decode(e)}async function decryptAES(e,t,r){var n=await crypto.subtle.decrypt({name:"AES-CBC",iv:r},t,e);return new Uint8Array(n)}async function handleDecrypt(e){e.preventDefault();var t=document.getElementById("password").value,r=document.getElementById("encryptedOutput").value,n=stringToArrayBuffer(t),a=await crypto.subtle.importKey("raw",n,{name:"PBKDF2"},!1,["deriveKey"]),y=await crypto.subtle.deriveKey({name:"PBKDF2",salt:new Uint8Array(16),iterations:1e5,hash:"SHA-256"},a,{name:"AES-CBC",length:256},!0,["encrypt","decrypt"]),c=r.substr(0,32),u=r.substr(32),i=new Uint8Array(c.match(/.{1,2}/g).map((e=>parseInt(e,16)))),d=new Uint8Array(u.match(/.{1,2}/g).map((e=>parseInt(e,16)))),o=arrayBufferToString(await decryptAES(d,y,i));document.getElementById("decryptedOutput").innerHTML=o}
</script>
<form onsubmit="handleDecrypt(event)">
<input type="password" id="password" required>
<textarea id="encryptedOutput" rows="10" cols="50" style="display: none;">4062d585bc375473ec8b0cf72bb04ff8d1b2c812de5ae7903eaf048383df4033c93f847da153b6ab08cf8621149af21ed1bb618decc98413fe4929a7a8bb77f09961ce495d9bf8697e4c4326ff32b9a5d91e5c9c8fdaa94793d06ca1d1cd8414ef33ec29c9ebcbe58dacf2dee3618f04d90c38413cddb014bebd4bd1bbb0444500d0ba37d8267bedbce34e4a1c72eb9a008e6c970c9f55717cf5b719ca84ec300cc1d3055ba52a65f2818646220da577</textarea>
<input type="submit" value="OpenSesame">
</form><div><div id="decryptedOutput"></div></div>
<a target="_blank" href="backupmanual.php" style=color:blue>Manual</a></details>






<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const apiKey = urlParams.get('apikey');
    const button = document.querySelector('button[type="submit"]');
    const usageMessage = document.querySelector('.usage-message'); // Adjust selector as needed for the usage message

    if (!apiKey) {
        button.disabled = true;
        button.textContent = 'APIKEY NEEDED FOR UPLOAD';
        if (usageMessage) {
            usageMessage.style.display = 'block'; // Show the usage message
        }
    }
});
</script>

<?php
ini_set('memory_limit', '512M');
define('JSON_FILE', 'videos.json');
define('MAX_SIZE',  500 * 1024 * 1024); // 500MB
define('MIN_SIZE',  100 * 1024); // 100KB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_GET['apikey'])) exit('Missing API key.');
    $apiKey = $_GET['apikey'];
    
    if (!empty($_FILES['video']['tmp_name']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        // Skip if uploaded file less than 100KB
        if ($_FILES['video']['size'] < MIN_SIZE) exit('File too small, skipping.');
        $filePath = handleFileUpload($_FILES['video']);
        $fileName = $_FILES['video']['name'];
    } elseif (!empty($_POST['video_url'])) {
        $url = $_POST['video_url'];
        $fileName = !empty($_POST['filename']) ? $_POST['filename'] : basename(parse_url($url, PHP_URL_PATH));
        $content = file_get_contents($url);
        if ($content === false) exit('Failed to download the file from URL.');
        if (strlen($content) < MIN_SIZE) exit('Downloaded file too small, skipping.');
        $filePath = handleUrlDownloadContent($content, $fileName);
    } else {
        exit('No video provided.');
    }

    $base = pathinfo($filePath, PATHINFO_FILENAME);
    $id = null;
    $title = null;
    $uploader = null;

    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $base)) {
        $id = $base;
        $resp = yt_api("https://youtube.googleapis.com/youtube/v3/videos?part=snippet&id={$id}&key={$apiKey}");
        $title = $resp['items'][0]['snippet']['title'] ?? null;
        $uploader = $resp['items'][0]['snippet']['channelTitle'] ?? null;
        if (!$title) exit('Invalid YouTube ID.');
    } else {
        $query = urlencode($base);
        $resp = yt_api("https://youtube.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=1&q={$query}&key={$apiKey}");
        $id = $resp['items'][0]['id']['videoId'] ?? null;
        $title = $resp['items'][0]['snippet']['title'] ?? null;
        $uploader = $resp['items'][0]['snippet']['channelTitle'] ?? null;
        if (!$id || !$title) exit('YouTube search failed.');
    }

    // Check if video ID already exists in JSON
    $list = file_exists(JSON_FILE) ? json_decode(file_get_contents(JSON_FILE), true) : [];
    if (!is_array($list)) $list = [];
    
    $videoExists = false;
    foreach ($list as $entry) {
        if (isset($entry['id']) && $entry['id'] === $id) {
            $videoExists = true;
            break;
        }
    }
    
    if ($videoExists) {
        unlink($filePath); // Remove the uploaded file
        exit("Video ID {$id} already exists in the database.");
    }

    $newName = $id . '.mp4';
    $newPath = __DIR__ . DIRECTORY_SEPARATOR . $newName;
    if ($filePath !== $newPath) {
        if (file_exists($newPath)) unlink($newPath);
        if (!rename($filePath, $newPath)) exit('Rename failed.');
        $filePath = $newPath;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $fileUrl = $scheme . '://' . $host . $scriptDir . '/' . rawurlencode($newName);

    /* Trigger Wayback save */
    $waybackSaveUrl = 'https://web.archive.org/save/' . rawurlencode($fileUrl);
    $archiveSuccess = try_wayback_save($waybackSaveUrl);
    
    /* Get timestamped archived URL if saved */
    $waybackUrl = get_wayback_timestamped_url($fileUrl);
    
    $entry = [
        'id'        => $id,
        'title'     => $title,
        'uploader'  => $uploader,
        'file'      => $newName,
        'uploaded'  => date('c'),
        'url'       => $fileUrl,
        'waybackurl'=> $waybackUrl ?: "https://web.archive.org/web/*/$fileUrl",
    ];

    $list[] = $entry;
    file_put_contents(JSON_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    
    echo "OK<br>Saved as: {$newName}<br>YouTube ID: {$id}<br>Title: {$title}<br>Uploader: {$uploader}<br>";
    echo "URL: <a href='{$fileUrl}' target='_blank'>{$fileUrl}</a><br>";
    
    if ($archiveSuccess) {
        echo "Wayback Machine archive triggered successfully.<br>";
        if ($waybackUrl) {
            echo "Archived URL: <a href='{$waybackUrl}' target='_blank'>{$waybackUrl}</a><br>";
        } else {
            echo "Waiting for archive to appear. <a target=_blank href=waybackupdater.php style=color:blue>FixLink</a><br>";
        }
    } else {
        echo "Could not trigger automatic archiving. <a target=_blank href=waybackupdater.php style=color:blue>FixLink</a><br>";
        echo "You can archive manually: <a href='{$waybackSaveUrl}' target='_blank'>Save on Wayback Machine</a><br>";
    }
    exit;
}

function handleFileUpload($file): string {
    if ($file['size'] > MAX_SIZE) exit('File too large.');
    if (mime_content_type($file['tmp_name']) !== 'video/mp4') exit('Not an MP4.');
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file['name']);
    $dst = __DIR__ . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $dst)) exit('Move failed.');
    return $dst;
}

function handleUrlDownloadContent(string $content, string $filename): string {
    $dst = __DIR__ . DIRECTORY_SEPARATOR . $filename;
    if (file_put_contents($dst, $content) === false) exit('Failed to save the downloaded file.');
    return $dst;
}

function yt_api(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, true) ?: [];
}

function try_wayback_save(string $url): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return in_array($httpCode, [200, 302]);
}

function get_wayback_timestamped_url(string $url): ?string {
    $api = 'https://archive.org/wayback/available?url=' . rawurlencode($url);
    $json = file_get_contents($api);
    if (!$json) return null;
    $data = json_decode($json, true);
    if (isset($data['archived_snapshots']['closest']['timestamp'])) {
        $ts = $data['archived_snapshots']['closest']['timestamp'];
        return "https://web.archive.org/web/{$ts}/{$url}";
    }
    return null;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Upload MP4</title>
    <style>
        .exists { border: 2px solid red !important; }
        .new { border: 2px solid green !important; }
        #existsWarning { color: red; font-weight: bold; display: none; }
        button:disabled { background-color: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>
<form method="post" enctype="multipart/form-data">
    <label>Choose MP4:
        <input type="file" name="video" accept=".mp4">
    </label><br><br><hr>
    
    <label>Or provide URL for video:
        <input type="url" name="video_url" placeholder="Enter video URL" id="video_url">
    </label><br>
    
    <label>Filename (only if URL is provided):
        <input type="text" name="filename" id="filename" placeholder="Enter filename or YouTube ID">
    </label>

    <button type="button" onclick="extractYouTubeId()" id="extractBtn">Extract Video ID</button>
    <span id="existsWarning">This video ID already exists!</span><br><br>

    <hr>
    <p>Usage: <code>?apikey=YOUR_YOUTUBE_API_KEY</code> in URL</p>
    <button type="submit" id="submitBtn">Upload</button>
</form>

<!--move-mp4-2subdir-->
<a target="_blank" href="mp4move.php" style="color:blue">Move mp4</a>

<script>
document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ DOM fully loaded');

    const filenameInput = document.getElementById('filename');
    const extractBtn = document.getElementById('extractBtn');
    const submitBtn = document.getElementById('submitBtn');
    const existsWarning = document.getElementById('existsWarning');
    const videoUrlInput = document.getElementById('video_url');
    
    if (!filenameInput || !extractBtn || !submitBtn || !existsWarning) {
        console.error('❌ Required elements not found in DOM');
        return;
    }

    let existingVideoIds = [];
    let currentVideoId = '';

    // Fetch videos.json
    fetch('videos.json')
        .then(response => {
            console.log('📡 Fetching videos.json...');
            if (!response.ok) throw new Error('Failed to load videos.json');
            return response.json();
        })
        .then(data => {
            if (!Array.isArray(data)) throw new Error('videos.json is not an array');
            existingVideoIds = data.map(video => video.id).filter(id => id);
            console.log(`✅ Loaded ${existingVideoIds.length} video IDs from videos.json`);
            setupInputListener();
        })
        .catch(error => {
            console.error('❌ Error loading videos.json:', error);
        });

    function setupInputListener() {
        filenameInput.addEventListener('input', () => {
            checkVideoId();
        });

        videoUrlInput.addEventListener('input', () => {
            // Auto-populate filename if it's empty and URL contains video ID
            if (!filenameInput.value.trim() && videoUrlInput.value.includes('youtube.com/watch?v=')) {
                extractYouTubeIdFromUrl(videoUrlInput.value);
            }
        });
    }

    function extractYouTubeIdFromUrl(url) {
        const regex = /[?&]v=([a-zA-Z0-9_-]{11})/;
        const match = url.match(regex);
        if (match && match[1]) {
            const videoId = match[1];
            filenameInput.value = videoId + '.mp4';
            checkVideoId();
        }
    }

    function extractYouTubeId() {
        const url = filenameInput.value.trim();
        const regex = /[?&]v=([a-zA-Z0-9_-]{11})/;
        const match = url.match(regex);
        
        if (match && match[1]) {
            const videoId = match[1];
            filenameInput.value = videoId + '.mp4';
            checkVideoId();
        } else if (url.match(/^[A-Za-z0-9_-]{11}$/)) {
            // If it's already just a video ID, add .mp4
            filenameInput.value = url + '.mp4';
            checkVideoId();
        } else {
            alert('No valid YouTube video ID found in the URL');
        }
    }

    function checkVideoId() {
        const inputValue = filenameInput.value.trim();
        currentVideoId = '';

        if (inputValue === '') {
            resetUI();
            return;
        }

        // Extract video ID from filename (remove .mp4 if present)
        let videoId = inputValue;
        if (inputValue.endsWith('.mp4')) {
            videoId = inputValue.slice(0, -4);
        }

        // Check if it's a valid YouTube ID format
        if (videoId.match(/^[A-Za-z0-9_-]{11}$/)) {
            currentVideoId = videoId;
            
            if (existingVideoIds.includes(videoId)) {
                // Video ID exists
                filenameInput.classList.remove('new');
                filenameInput.classList.add('exists');
                extractBtn.textContent = 'ALREADY EXISTS!';
                extractBtn.disabled = true;
                existsWarning.style.display = 'inline';
                submitBtn.disabled = true;
                console.log(`❌ Video ID ${videoId} already exists`);
            } else {
                // Video ID is new
                filenameInput.classList.remove('exists');
                filenameInput.classList.add('new');
                extractBtn.textContent = 'Extract Video ID';
                extractBtn.disabled = false;
                existsWarning.style.display = 'none';
                submitBtn.disabled = false;
                console.log(`✅ Video ID ${videoId} is new`);
            }
        } else {
            // Invalid format
            resetUI();
            console.log('⚠️ Input is not a valid YouTube video ID format');
        }
    }

    function resetUI() {
        filenameInput.classList.remove('exists', 'new');
        extractBtn.textContent = 'Extract Video ID';
        extractBtn.disabled = false;
        existsWarning.style.display = 'none';
        submitBtn.disabled = false;
        currentVideoId = '';
    }

    // Make extractYouTubeId function available globally
    window.extractYouTubeId = extractYouTubeId;
});
</script>
</body>
</html>
