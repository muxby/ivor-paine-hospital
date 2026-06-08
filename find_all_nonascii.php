<?php
$files = glob("*.php");
$files = array_merge($files, glob("components/*.php"));
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (preg_match_all('/[^\x00-\x7F]+/u', $content, $matches)) {
        $found = [];
        foreach ($matches[0] as $m) {
            $hex = bin2hex($m);
            if (strpos($hex, 'e294') === false && strpos($hex, 'e295') === false && strpos($hex, 'efbfbd') === false) {
                $found[$m] = true;
            }
        }
        if (!empty($found)) {
            echo "$file: " . implode(" ", array_keys($found)) . "\n";
        }
    }
}
