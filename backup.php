<?php
define('JSON_FILE', 'videos.json');
define('MAX_SIZE',  500 * 1024 * 1024); // 500MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_GET['apikey'])) exit('Missing API key.');
    $apiKey = $_GET['apikey'];

    if (empty($_FILES['video']['tmp_name']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        exit('Upload failed.');
    }

    if ($_FILES['video']['size'] > MAX_SIZE) exit('File too large.');
    if (mime_content_type($_FILES['video']['tmp_name']) !== 'video/mp4') exit('Not an MP4.');

    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $_FILES['video']['name']);
    $dst = __DIR__ . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($_FILES['video']['tmp_name'], $dst)) exit('Move failed.');

    $base = pathinfo($safeName, PATHINFO_FILENAME);

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

    $newName = $id . '.mp4';
    $newPath = __DIR__ . DIRECTORY_SEPARATOR . $newName;
    if ($safeName !== $newName) {
        if (file_exists($newPath)) unlink($newPath);
        if (!rename($dst, $newPath)) exit('Rename failed.');
        $safeName = $newName;
        $dst = $newPath;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $fileUrl = $scheme . '://' . $host . $scriptDir . '/' . rawurlencode($safeName);

    /* Trigger Wayback save */
    $waybackSaveUrl = 'https://web.archive.org/save/' . rawurlencode($fileUrl);
    $archiveSuccess = try_wayback_save($waybackSaveUrl);

    /* Get timestamped archived URL if saved */
    $waybackUrl = get_wayback_timestamped_url($fileUrl);

    $entry = [
        'id'        => $id,
        'title'     => $title,
        'uploader'  => $uploader,
        'file'      => $safeName,
        'uploaded'  => date('c'),
        'url'       => $fileUrl,
        'waybackurl'=> $waybackUrl ?: "https://web.archive.org/web/*/$fileUrl",
    ];

    $list = file_exists(JSON_FILE) ? json_decode(file_get_contents(JSON_FILE), true) : [];
    if (!is_array($list)) $list = [];
    $list[] = $entry;
    file_put_contents(JSON_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    echo "OK<br>Saved as: {$safeName}<br>YouTube ID: {$id}<br>Title: {$title}<br>Uploader: {$uploader}<br>";
    echo "URL: <a href='{$fileUrl}' target='_blank'>{$fileUrl}</a><br>";

    if ($archiveSuccess) {
        echo "Wayback Machine archive triggered successfully.<br>";
        if ($waybackUrl) {
            echo "Archived URL: <a href='{$waybackUrl}' target='_blank'>{$waybackUrl}</a><br>";
        } else {
            echo "Waiting for archive to appear.<br>";
        }
    } else {
        echo "Could not trigger automatic archiving.<br>";
        echo "You can archive manually: <a href='{$waybackSaveUrl}' target='_blank'>Save on Wayback Machine</a><br>";
    }

    exit;
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
    // Query Wayback Machine CDX API for latest snapshot
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
<head><meta charset="utf-8"><title>Upload MP4</title></head>
<body>
<form method="post" enctype="multipart/form-data">
    <label>Choose MP4:
        <input type="file" name="video" accept=".mp4" required>
    </label><br>
    <p>Usage: <code>?apikey=YOUR_YOUTUBE_API_KEY</code> in URL</p>
    <button type="submit">Upload</button>
</form>
</body>
</html>
