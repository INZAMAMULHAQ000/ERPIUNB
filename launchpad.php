<?php
$files = [];
foreach (glob(__DIR__ . '/*.php') as $path) {
    $files[] = basename($path);
}
sort($files);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHP Launchpad</title>
<style>
    body { font-family: system-ui, sans-serif; margin: 2rem; background: #f5f5f5; color: #222; }
    h1 { font-size: 1.4rem; }
    #filter { width: 100%; padding: 0.6rem; font-size: 1rem; margin-bottom: 1rem; box-sizing: border-box; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.5rem; }
    a.btn {
        display: block; text-decoration: none; padding: 0.7rem 0.9rem;
        background: #fff; border: 1px solid #ccc; border-radius: 6px;
        color: #1a4f8b; font-size: 0.85rem; word-break: break-all;
        transition: background .15s, border-color .15s;
    }
    a.btn:hover { background: #eaf2fb; border-color: #1a4f8b; }
    .count { color: #666; font-size: 0.85rem; margin-bottom: 1rem; }
</style>
</head>
<body>
    <h1>PHP File Launchpad</h1>
    <input id="filter" type="text" placeholder="Filter files..." oninput="filterList(this.value)">
    <div class="count" id="count"></div>
    <div class="grid" id="grid">
        <?php foreach ($files as $f): ?>
        <a class="btn" href="<?= htmlspecialchars($f) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($f) ?></a>
        <?php endforeach; ?>
    </div>
    <script>
        const grid = document.getElementById('grid');
        const count = document.getElementById('count');
        const buttons = Array.from(grid.querySelectorAll('a.btn'));
        function filterList(q) {
            q = q.toLowerCase();
            let visible = 0;
            buttons.forEach(b => {
                const show = b.textContent.toLowerCase().includes(q);
                b.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            count.textContent = visible + ' of ' + buttons.length + ' files';
        }
        filterList('');
    </script>
</body>
</html>
