<?php
// Test de l'endpoint analytics pour tous les comptes
echo "=== Test endpoint analytics pour tous les comptes ===\n\n";

$accounts = ['Ryan', 'Ken', 'Brams'];

foreach ($accounts as $account) {
    echo "Test pour $account:\n";

    $opts = array('http' => array('method' => 'GET'));
    $context = stream_context_create($opts);
    $result = file_get_contents("http://localhost/www/Tracking-journal/trades.php?analytics=$account", false, $context);

    $json = json_decode($result, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($json['error'])) {
            echo "  ✗ Erreur: " . $json['error'] . "\n";
        } else {
            echo "  ✓ JSON valide - Trades: " . $json['overall']['total_trades'] . ", P&L: " . $json['overall']['total_pnl'] . "\n";
        }
    } else {
        echo "  ✗ JSON invalide: " . json_last_error_msg() . "\n";
        echo "  Response: " . substr($result, 0, 200) . "...\n";
    }
    echo "\n";
}
?>