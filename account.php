<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$bd = new PDO("mysql:host=localhost;dbname=trading_journal", "root", "");
if (isset($_REQUEST["account"])) {
    $account = $_REQUEST["account"];
    $req = $bd->prepare("SELECT * FROM accounts WHERE username = :username");
    $req->bindValue(":username", $account, PDO::PARAM_STR);
    $req->execute();
    $data = $req->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}
