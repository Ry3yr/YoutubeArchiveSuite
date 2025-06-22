<?php
define('JSON_FILE', 'videos.json');

function get_wayback_timestamped_url(string $url): ?string {
    $api = 'https://archive.org/wayback/available?url=' . rawurlencode($url);
    $json = @file_get_contents($api);
    if (!$json) return null;
    $data = json_decode($json, true);
    if (isset($data['archived_snapshots']['closest']['timestamp'])) {
        $ts = $data['archived_snapshots']['closest']['timestamp'];
        return "https://web.archive.org/web/{$ts}/{$url}";
    }
    return null;
}

// Load JSON
$list = json_decode(file_get_contents(JSON_FILE), true);
$updates = [];

foreach ($list as $index => $entry) {
    if (!isset($entry['url'], $entry['waybackurl'])) continue;

    // SIMPLIFIED: Check if waybackurl contains a '*'
    if (strpos($entry['waybackurl'], '*') !== false) {
        $timestamped = get_wayback_timestamped_url($entry['url']);
        if ($timestamped && $timestamped !== $entry['waybackurl']) {
            $updates[] = [
                'index' => $index,
                'title' => $entry['title'] ?? '',
                'original' => $entry['waybackurl'],
                'updated' => $timestamped,
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmed = $_POST['confirm'] ?? [];
    foreach ($confirmed as $idx) {
        $i = (int)$idx;
        $timestamped = get_wayback_timestamped_url($list[$i]['url']);
        if ($timestamped) {
            $list[$i]['waybackurl'] = $timestamped;
        }
    }
    file_put_contents(JSON_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo "<p><strong>Updates saved successfully!</strong></p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Wayback URLs</title>
</head>
<body>
    <h1>Wayback URL Fixer</h1>

    <?php if (empty($updates)): ?>
        <p>No updates needed.</p>
    <?php else: ?>
        <form method="post">
            <table border="1" cellpadding="6">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Title</th>
                        <th>Original URL</th>
                        <th>Suggested Fix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($updates as $u): ?>
                        <tr>
                            <td><input type="checkbox" name="confirm[]" value="<?= htmlspecialchars($u['index']) ?>" checked></td>
                            <td><?= htmlspecialchars($u['title']) ?></td>
                            <td><a href="<?= htmlspecialchars($u['original']) ?>" target="_blank">Old</a></td>
                            <td><a href="<?= htmlspecialchars($u['updated']) ?>" target="_blank">New</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <input type="submit" value="Apply Selected Fixes">
        </form>
    <?php endif; ?>
</body>
</html>
