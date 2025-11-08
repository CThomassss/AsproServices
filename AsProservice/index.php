<?php
// index.php
// Loads the static HTML export and injects the dynamic promotions section in place of markers.

// Read the HTML template
$htmlPath = __DIR__ . '/index.html';
if (!file_exists($htmlPath)) {
    http_response_code(500);
    echo "Missing index.html template.";
    exit;
}
$content = file_get_contents($htmlPath);

$startMarker = '<!-- PROMOTIONS_START -->';
$endMarker = '<!-- PROMOTIONS_END -->';

$startPos = strpos($content, $startMarker);
$endPos = strpos($content, $endMarker);

if ($startPos === false || $endPos === false || $endPos <= $startPos) {
    // Markers not found: just output the file as-is
    echo $content;
    exit;
}

$before = substr($content, 0, $startPos);
$after = substr($content, $endPos + strlen($endMarker));

// Output before marker
echo $before;

// Include dynamic promotions HTML (this file echoes the <section>...</section>)
require_once __DIR__ . '/includes/promotions.php';

// Output remaining part of page
echo $after;

?>
