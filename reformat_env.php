<?php
$content = file_get_contents('.env');
// Find the JSON block
if (preg_match('/GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=\{(.*?)\n\}/s', $content, $matches)) {
    $json = '{' . $matches[1] . "\n}";
    // Remove the original block and replace with a quoted version
    $quotedJson = "'" . str_replace("'", "\\'", $json) . "'";
    $newContent = str_replace($json, $quotedJson, $content);
    file_put_contents('.env', $newContent);
    echo "JSON reformateado en .env\n";
} else {
    echo "No se encontro el bloque JSON en .env o ya esta formateado.\n";
}
