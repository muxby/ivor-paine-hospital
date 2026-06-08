<?php
$files = glob("assets/icons/*.png");

foreach ($files as $file) {
    echo "Processing $file...\n";
    $img = imagecreatefrompng($file);
    if (!$img) {
        echo "Failed to load $file\n";
        continue;
    }
    
    // Enable alpha blending
    imagealphablending($img, false);
    imagesavealpha($img, true);
    
    $width = imagesx($img);
    $height = imagesy($img);
    
    // Target background color: #0f172a (15, 23, 42)
    $targetR = 15;
    $targetG = 23;
    $targetB = 42;
    $tolerance = 60; // How far off the color can be
    
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $color = imagecolorat($img, $x, $y);
            $r = ($color >> 16) & 0xFF;
            $g = ($color >> 8) & 0xFF;
            $b = $color & 0xFF;
            
            // Calculate distance
            $dist = sqrt(pow($r - $targetR, 2) + pow($g - $targetG, 2) + pow($b - $targetB, 2));
            
            if ($dist < $tolerance) {
                // Closer to background -> more transparent
                $alpha = 127; 
                // Feathering:
                if ($dist > ($tolerance - 20)) {
                    $alpha = (int)(127 * (($tolerance - $dist) / 20));
                    $alpha = 127 - $alpha; // 127 is fully transparent, 0 is fully opaque
                }
                
                $newColor = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
                imagesetpixel($img, $x, $y, $newColor);
            }
        }
    }
    
    imagepng($img, $file);
    imagedestroy($img);
    echo "Saved $file\n";
}
