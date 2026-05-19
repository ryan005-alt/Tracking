<?php
// Test script pour vérifier les endpoints
header("Content-Type: application/json");

echo "=== Test des endpoints PHP ===\n\n";

// Test connexion DB
try {
    $bd = new PDO("mysql:host=localhost;dbname=trading_journal", "root", "");
    $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connexion DB réussie\n";
} catch (Exception $e) {
    echo "✗ Erreur connexion DB: " . $e->getMessage() . "\n";
    exit;
}

// Test récupération comptes
try {
    $req = $bd->query("SELECT account_name FROM accounts LIMIT 5");
    $accounts = $req->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Comptes trouvés: " . count($accounts) . "\n";
    if (count($accounts) > 0) {
        echo "  Comptes: " . implode(", ", array_column($accounts, 'account_name')) . "\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur récupération comptes: " . $e->getMessage() . "\n";
}

// Test analytics pour le premier compte
if (count($accounts) > 0) {
    $accountName = $accounts[0]['account_name'];
    echo "\n=== Test analytics pour '$accountName' ===\n";

    try {
        $sql = "SELECT t.*, a.initial_balance FROM trades t
                INNER JOIN accounts a ON t.account_name = a.account_name
                WHERE t.account_name = :accountName
                ORDER BY t.trade_date ASC";
        $req = $bd->prepare($sql);
        $req->bindValue(":accountName", $accountName, PDO::PARAM_STR);
        $req->execute();
        $trades = $req->fetchAll(PDO::FETCH_ASSOC);
        echo "✓ Trades trouvés: " . count($trades) . "\n";
    } catch (Exception $e) {
        echo "✗ Erreur analytics: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Test terminé ===";
?>