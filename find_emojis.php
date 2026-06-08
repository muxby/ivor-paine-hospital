<?php
$files = glob("*.php") + glob("components/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Find all emojis (roughly U+1F300 to U+1FAFF, plus some others like U+2600-U+27BF)
    if (preg_match_all('/[\x{1F300}-\x{1FAD6}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F1E6}-\x{1F1FF}]/u', $content, $matches, PREG_OFFSET_CAPTURE)) {
        echo "Found emojis in $file:\n";
        foreach ($matches[0] as $m) {
            $emoji = $m[0];
            $offset = $m[1];
            // get context
            $context = substr($content, max(0, $offset - 20), 40);
            $context = str_replace(["\n", "\r"], ["\\n", ""], $context);
            echo "  $emoji at offset $offset: $context\n";
        }
    }
}
