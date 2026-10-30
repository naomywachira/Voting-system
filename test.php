<?php
echo "<h1>✅ Apache is Working!</h1>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Path: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";

echo "<h3>Files in htdocs:</h3>";
$files = scandir(__DIR__);
echo "<ul>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $type = is_dir(__DIR__ . '/' . $file) ? '📁' : '📄';
        echo "<li>$type $file</li>";
    }
}
echo "</ul>";

if (is_dir('voting_system')) {
    echo "<h3 style='color:green;'>✅ voting_system folder found!</h3>";
    echo "<p><a href='voting_system/index.php'>Go to voting_system/index.php</a></p>";
} else {
    echo "<h3 style='color:red;'>❌ voting_system folder NOT found!</h3>";
    echo "<p>Your files should be in: " . __DIR__ . "/voting_system/</p>";
}
?>