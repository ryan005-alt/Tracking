<?php
// Test détaillé de l'endpoint analytics pour Brams
echo "=== Test détaillé pour Brams ===\n\n";

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simuler l'appel analytics comme le fait le script PHP
$_REQUEST['analytics'] = 'Brams';

try {
    ob_start();
    include 'trades.php';
    $output = ob_get_clean();

    echo "Output: " . $output . "\n\n";

    $json = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ JSON valide\n";
        if (isset($json['error'])) {
            echo "Mais contient une erreur: " . $json['error'] . "\n";
        } else {
            echo "Données valides trouvées\n";
        }
    } else {
        echo "✗ JSON invalide: " . json_last_error_msg() . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>