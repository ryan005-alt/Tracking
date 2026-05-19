<?php
try {
    $bd = new PDO('mysql:host=localhost;dbname=trading_journal', 'root', '');
    $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = 'SELECT t.*, a.initial_balance FROM trades t INNER JOIN accounts a ON t.account_name = a.account_name WHERE t.account_name = :accountName ORDER BY t.trade_date ASC';
    $req = $bd->prepare($sql);
    $req->bindValue(':accountName', 'Brams', PDO::PARAM_STR);
    $req->execute();
    $trades = $req->fetchAll(PDO::FETCH_ASSOC);

    echo 'Trades pour Brams: ' . count($trades) . PHP_EOL;
    foreach ($trades as $trade) {
        echo 'ID: ' . $trade['id'] . ', Date: ' . $trade['trade_date'] . ', P&L: ' . $trade['pnl'] . ', Entry: ' . $trade['entry_price'] . ', SL: ' . $trade['stop_loss'] . ', TP: ' . $trade['take_profit'] . ', Exit: ' . $trade['exit_price'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage() . PHP_EOL;
}
?>