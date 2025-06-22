<?php
define('JSON_FILE', 'videos.json');

function get_wayback_timestamped_url(string $url): ?string {
    $url = str_replace('\\/', '/', $url); // normalize slashes
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

$list = json_decode(file_get_contents(JSON_FILE), true);
$updates = [];
$missing = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['confirm'])) {
        // Apply selected automatic fixes
        foreach ($_POST['confirm'] as $idx) {
            $i = (int)$idx;
            $timestamped = get_wayback_timestamped_url($list[$i]['url']);
            if ($timestamped) {
                $list[$i]['waybackurl'] = $timestamped;
            }
        }
    }
    if (!empty($_POST['manual_fix'])) {
        // Apply manual fix
        foreach ($_POST['manual_fix'] as $idx => $new_url) {
            $i = (int)$idx;
            $new_url = trim($new_url);
            if ($new_url !== '') {
                $list[$i]['waybackurl'] = $new_url;
            }
        }
    }
    file_put_contents(JSON_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo "<p><strong>Updates saved successfully!</strong></p>";
}

// Prepare lists again for display
foreach ($list as $index => $entry) {
    if (!isset($entry['url'], $entry['waybackurl'])) continue;
    if (strpos($entry['waybackurl'], '*') === false) continue;

    $timestamped = get_wayback_timestamped_url($entry['url']);
    if ($timestamped) {
        $updates[] = [
            'index'    => $index,
            'title'    => $entry['title'] ?? '',
            'original' => $entry['waybackurl'],
            'updated'  => $timestamped,
        ];
    } else {
        $missing[] = [
            'index'    => $index,
            'title'    => $entry['title'] ?? '',
            'url'      => $entry['url'],
            'old_waybackurl' => $entry['waybackurl'],
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Wayback URLs</title>
</head>
<body>
<h1>Wayback URL Fixer</h1>

<?php if (empty($updates) && empty($missing)): ?>
    <p>No wildcard URLs found.</p>
<?php endif; ?>

<?php if (!empty($updates)): ?>
    <h2>Suggested Fixes</h2>
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

<?php if (!empty($missing)): ?>
    <h2>No Archive Snapshot Found - Manual Fix</h2>
    <form method="post">
        <table border="1" cellpadding="6">
            <thead>
            <tr>
                <th>Title</th>
                <th>Manual Wayback URL</th>
                <th>Old Wayback URL</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($missing as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['title']) ?></td>
                    <td>
                        <input type="text" name="manual_fix[<?= htmlspecialchars($m['index']) ?>]" size="80" placeholder="Paste new wayback URL here">
                    </td>
                    <td><a href="<?= htmlspecialchars($m['old_waybackurl']) ?>" target="_blank"><?= htmlspecialchars($m['old_waybackurl']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <input type="submit" value="Apply Manual Fixes">
    </form>
<?php endif; ?>

</body>
</html>
