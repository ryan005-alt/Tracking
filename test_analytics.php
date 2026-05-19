<?php
// Test de l'endpoint analytics
echo "=== Test endpoint analytics ===\n\n";

$opts = array('http' => array('method' => 'GET'));
$context = stream_context_create($opts);
$result = file_get_contents('http://localhost/www/Tracking-journal/trades.php?analytics=Ryan', false, $context);

echo 'Response: ' . $result . PHP_EOL . PHP_EOL;

$json = json_decode($result, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ JSON valide\n";
    echo 'Keys: ' . implode(', ', array_keys($json)) . "\n";
    echo 'Overall stats keys: ' . implode(', ', array_keys($json['overall'])) . "\n";
} else {
    echo "✗ JSON invalide: " . json_last_error_msg() . "\n";
}
?>