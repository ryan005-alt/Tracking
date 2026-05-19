<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$bd = new PDO("mysql:host=localhost;dbname=trading_journal", "root", "");

$username = isset($_GET["username"]) ? $_GET["username"] : "";
$email = isset($_GET["email"]) ? $_GET["email"] : "";

$response = [
    "usernameExists" => false,
    "emailExists" => false
];

// Vérifier si le username existe
if (!empty($username)) {
    $req = $bd->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username");
    $req->bindValue(":username", $username, PDO::PARAM_STR);
    $req->execute();
    $result = $req->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        $response["usernameExists"] = true;
    }
}

// Vérifier si l'email existe
if (!empty($email)) {
    $req = $bd->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email");
    $req->bindValue(":email", $email, PDO::PARAM_STR);
    $req->execute();
    $result = $req->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        $response["emailExists"] = true;
    }
}

echo json_encode($response);
