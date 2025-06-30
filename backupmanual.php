

<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    function extractVideoId(string $url): ?string {
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        return $q['v'] ?? null;
    }

    function fetchYoutubeData(string $id, string $key): array {
        $api = "https://www.googleapis.com/youtube/v3/videos?part=snippet&id={$id}&key={$key}";
        $json = @file_get_contents($api);
        if ($json === false) throw new RuntimeException('YouTube API request failed');
        $data = json_decode($json, true);
        if (empty($data['items'][0]['snippet'])) throw new RuntimeException('Video not found');
        return $data['items'][0]['snippet'];
    }

    function buildObject(string $id, array $snippet): array {
        $ts  = gmdate('Y-m-d\TH:i:sP');
        $url = "https://alcea-wisteria.de/ytarchive/{$id}.mp4";
        $wb  = gmdate('YmdHis');
        return [
            'id'         => $id,
            'title'      => $snippet['title'],
            'uploader'   => $snippet['channelTitle'],
            'file'       => "{$id}.mp4",
            'uploaded'   => $ts,
            'url'        => $url,
            'waybackurl' => "https://web.archive.org/web/{$wb}/{$url}"
        ];
    }

    function appendToJson(array $obj, string $file = 'videos.json'): void {
        $list = [];
        if (file_exists($file) && filesize($file) > 0) {
            $raw  = file_get_contents($file);
            $list = json_decode($raw, true) ?? [];
        }
        $list[] = $obj;
        $json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $fp = fopen($file, 'c+');
        if (!$fp) throw new RuntimeException('Cannot open videos.json');
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    try {
        $url    = $_POST['url']    ?? null;
        $apikey = $_POST['apikey'] ?? null;
        $app    = isset($_POST['appwend']);

        if (!$url || !$apikey) throw new Exception('Missing url or apikey');
        $id = extractVideoId($url);
        if (!$id) throw new Exception('Invalid YouTube URL');

        $data = buildObject($id, fetchYoutubeData($id, $apikey));
        if ($app) appendToJson($data);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>YouTube Metadata App</title>
  <style>
    body { font-family: sans-serif; background: #f5f5f5; padding: 2em; }
    input[type="text"] { width: 100%; padding: 0.5em; margin-bottom: 1em; }
    button { padding: 0.5em 1.5em; margin-right: 1em; }
    pre { background: #fff; padding: 1em; border: 1px solid #ccc; }
  </style>
</head>
<body>
  <h1>YouTube Metadata Generator</h1>
  <form id="ytform">
    <label>YouTube URL:<br>
      <input type="text" name="url" id="url" placeholder="https://www.youtube.com/watch?v=..." required>
    </label><br>
    <label>API Key:<br>
      <input type="text" name="apikey" id="apikey" placeholder="Your API key" required>
    </label><br>
    <button type="submit">Generate</button>
    <button type="button" onclick="submitForm(true)">appwend</button>
  </form>

  <h2>Generated JSON:</h2>
  <div id="result"></div>

  <script>
    window.addEventListener('DOMContentLoaded', () => {
      const params = new URLSearchParams(location.search);
      const apikey = params.get('apikey');
      if (apikey) {
        document.getElementById('apikey').value = apikey;
      }
    });

    function submitForm(appwend = false) {
      const url = document.getElementById('url').value.trim();
      const apikey = document.getElementById('apikey').value.trim();
      const formData = new FormData();
      formData.append('ajax', '1');
      formData.append('url', url);
      formData.append('apikey', apikey);
      if (appwend) formData.append('appwend', '1');

      fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
          const out = document.getElementById('result');
          if (res.success) {
            const json = JSON.stringify(res.data, null, 2);
            const msg = appwend ? '<strong style="color:green;">✔ Appended successfully</strong><br>' : '';
            out.innerHTML = msg + `<pre>${json}</pre>`;
          } else {
            out.innerHTML = `<pre style="color:red;">Error: ${res.error}</pre>`;
          }
        })
        .catch(err => {
          document.getElementById('result').innerHTML = `<pre style="color:red;">JS Fetch error: ${err}</pre>`;
        });
    }

    document.getElementById('ytform').addEventListener('submit', function(e) {
      e.preventDefault();
      submitForm(false);
    });
  </script>
</body>
</html>
