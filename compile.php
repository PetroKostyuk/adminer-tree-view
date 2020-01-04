<?php

function getFiles($dir): array
{
    $files = [];

    foreach (scandir($dir) as $file) {
        if (preg_match('/.+\.(js|php)/', $file)) {
            $files[] = $dir . '/' . $file;
        }
    }

    return $files;
}

function generateScript(): void
{
    $phpScript = file_get_contents('./src/index.php');
    $jsScript = file_get_contents('./src/script.js');

    file_put_contents('./index.php', str_replace('<<<%SCRIPT_JS%>>>', str_replace('"', '\\"', $jsScript), $phpScript));

    echo "generated script at " . date('H:i:s') . "\n";
}

function getDirectoryHash($dir)
{
    $hashes = [];

    $files = getFiles($dir);
    foreach ($files as $file) {
        $hashes[] = md5_file($file);
    }

    sort($hashes);
    $hash = md5(implode('|', $hashes));

    return $hash;
}

function generateScriptOnChange(): void
{
    echo "Watching scripts directory content \n";

    $hash = '';
    while (true) {
        if (($newHash = getDirectoryHash('src')) !== $hash) {
            generateScript();
            $hash = $newHash;
            echo '  current hash is ' . $hash . "\n";
        }
        sleep(1);
    }
}

if (isset($argv[1]) && $argv[1] === "loop") {
    generateScriptOnChange();
} else {
    generateScript();
}