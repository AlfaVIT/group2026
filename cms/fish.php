<?php
// Favicon сайта — рыба (Ихтис)
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
$gold = '#b08a2e';
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"'
    . ' stroke="' . $gold . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">';
echo '<path d="M2 7.5 5 12l-3 4.5"/>';
echo '<path d="M11 5.5c4.2 0 7.3 2.4 8.4 6.5-1.1 4.1-4.2 6.5-8.4 6.5s-7.3-2.4-8.4-6.5c1.1-4.1 4.2-6.5 8.4-6.5z"/>';
echo '<circle cx="16" cy="9.6" r="1.05" fill="' . $gold . '" stroke="none"/>';
echo '</svg>';