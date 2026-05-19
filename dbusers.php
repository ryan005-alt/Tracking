<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Désactiver l'affichage des erreurs PHP pour éviter les sorties HTML
ini_set('display_errors', 0);
error_reporting(0);

try {
    $bd = new PDO("mysql:host=localhost;dbname=trading_journal", "root", "");
    $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}
if (isset($_POST["inscription"])) {
  addBaseUsers();
}
elseif (isset($_POST["addtrade"])) {
  addBaseTrades();
}
elseif (isset($_POST["deleteTrade"])) {
  deleteTrade();
}
elseif (isset($_REQUEST["savechanges"])) {
  updateTrades();
}
elseif (isset($_REQUEST["connexion"])) {
  $connexion = $_REQUEST["connexion"];
  $req = $bd->query("SELECT * FROM users WHERE username='$connexion'");
  $data = $req->fetch(PDO::FETCH_ASSOC);
  if ($data) {
    $req_account = $bd->query("SELECT COUNT(*) as has_account FROM accounts WHERE username='" . $data['username'] . "'");
    $account_data = $req_account->fetch(PDO::FETCH_ASSOC);
    $data['has_account'] = $account_data['has_account'] > 0;
  }
  echo json_encode($data); 
} 
if (isset($_POST["creatAccount"])) {
  var_dump($_POST);
  creaAccount();
} 
function addBaseUsers()
{
  $username = $_POST["username"];
  $email = $_POST["email"];
  
  // Vérifier si le username existe
  $checkUsername = $GLOBALS['bd']->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username");
  $checkUsername->bindValue(":username", $username, PDO::PARAM_STR);
  $checkUsername->execute();
  $resultUsername = $checkUsername->fetch(PDO::FETCH_ASSOC);
  
  // Vérifier si l'email existe
  $checkEmail = $GLOBALS['bd']->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email");
  $checkEmail->bindValue(":email", $email, PDO::PARAM_STR);
  $checkEmail->execute();
  $resultEmail = $checkEmail->fetch(PDO::FETCH_ASSOC);
  
  // Si username ou email existe, retourner une erreur
  if ($resultUsername['count'] > 0) {
    echo json_encode(["error" => "username_exists"]);
    return;
  }
  
  if ($resultEmail['count'] > 0) {
    echo json_encode(["error" => "email_exists"]);
    return;
  }
  
  // Si pas d'erreur, insérer les données
  $sql = "INSERT INTO users (nom, prenom, email, username, password) VALUES (:nom, :prenom, :email, :username, :password)";
  $req = $GLOBALS['bd']->prepare($sql);
  $req->bindValue(":nom", $_POST["nom"], PDO::PARAM_STR);
  $req->bindValue(":prenom", $_POST["prenom"], PDO::PARAM_STR);
  $req->bindValue(":email", $email, PDO::PARAM_STR);
  $req->bindValue(":username", $username, PDO::PARAM_STR);
  $req->bindValue(":password", $_POST["password"], PDO::PARAM_STR);
  $resultat = $req->execute();
  $req->closeCursor();
  if ($resultat > 0) {
    echo json_encode(["success" => true]);
  } else {
    echo json_encode(["error" => "insertion_failed"]);
  }
}
function addBaseTrades ()
{
  $sql = "INSERT INTO trades (account_name, symbol, type, entry_price, stop_loss, take_profit, exit_price, pnl, trade_date, session) VALUES (:account_name, :symbol, :type, :entry_price, :stop_loss, :take_profit, :exit_price, :pnl, :trade_date, :session)";
  $req = $GLOBALS['bd']->prepare($sql);
  $req->bindValue(":account_name", $_POST["account_name"], PDO::PARAM_STR);
  $req->bindValue(":symbol", $_POST["symbol"], PDO::PARAM_STR);
  $req->bindValue(":type", $_POST["type"], PDO::PARAM_STR);
  $req->bindValue(":entry_price", $_POST["entry_price"], PDO::PARAM_STR);
  $req->bindValue(":stop_loss", $_POST["stop_loss"], PDO::PARAM_STR);
  $req->bindValue(":take_profit", $_POST["take_profit"], PDO::PARAM_STR);
  $req->bindValue(":exit_price", $_POST["exit_price"], PDO::PARAM_STR);
  $req->bindValue(":pnl", $_POST["pnl"], PDO::PARAM_STR);
  $req->bindValue(":trade_date", $_POST["trade_date"], PDO::PARAM_STR);
  $req->bindValue(":session", $_POST["session"], PDO::PARAM_STR);
  $resultat = $req->execute();
  $req->closeCursor();
  if ($resultat > 0) {
    header("Location: home.html");
  } else {
    echo "Error";
  }
}
function creaAccount()
{
  $sql = "INSERT INTO accounts (username, account_name, broker, initial_balance) VALUES (:username, :account_name, :broker, :initial_balance)";
  $req = $GLOBALS['bd']->prepare($sql);
  $req->bindValue(":username", $_POST["username"], PDO::PARAM_STR);
  $req->bindValue(":account_name", $_POST["account_name"], PDO::PARAM_STR);
  $req->bindValue(":broker", $_POST["broker"], PDO::PARAM_STR);
  $req->bindValue(":initial_balance", $_POST["initial_balance"], PDO::PARAM_STR);
  $resultat = $req->execute();
  $req->closeCursor();
  if ($resultat > 0) {
    header("Location: home.html");
  } else {
    echo "Error";
  }
}

function deleteTrade()
{
    try {
        $tradeId = $_POST["deleteTrade"];
        $sql = "DELETE FROM trades WHERE id = :id";
        $req = $GLOBALS['bd']->prepare($sql);
        $req->bindValue(":id", $tradeId, PDO::PARAM_INT);
        $resultat = $req->execute();
        $req->closeCursor();
        if ($resultat) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => "delete_failed"]);
        }
    } catch (Exception $e) {
        echo json_encode(["error" => "Delete operation failed: " . $e->getMessage()]);
    }
}

function updateTrades() {
  try {
    if (!isset($_POST["trade_id"]) || !is_numeric($_POST["trade_id"])) {
      echo json_encode(["error" => "invalid_trade_id"]);
      return;
    }

    $sql = "UPDATE trades 
            SET account_name = :account_name,
                symbol = :symbol,
                type = :type,
                entry_price = :entry_price,
                stop_loss = :stop_loss,
                take_profit = :take_profit,
                exit_price = :exit_price,
                pnl = :pnl,
                trade_date = :trade_date,
                session = :session
            WHERE id = :trade_id";
    $req = $GLOBALS['bd']->prepare($sql);
    $req->bindValue(":account_name", $_POST["account_name"], PDO::PARAM_STR);
    $req->bindValue(":symbol", $_POST["symbol"], PDO::PARAM_STR);
    $req->bindValue(":type", $_POST["type"], PDO::PARAM_STR);
    $req->bindValue(":entry_price", $_POST["entry_price"], PDO::PARAM_STR);
    $req->bindValue(":stop_loss", $_POST["stop_loss"], PDO::PARAM_STR);
    $req->bindValue(":take_profit", $_POST["take_profit"], PDO::PARAM_STR);
    $req->bindValue(":exit_price", $_POST["exit_price"], PDO::PARAM_STR);
    $req->bindValue(":pnl", $_POST["pnl"], PDO::PARAM_STR);
    $req->bindValue(":trade_date", $_POST["trade_date"], PDO::PARAM_STR);
    $req->bindValue(":session", $_POST["session"], PDO::PARAM_STR);
    $req->bindValue(":trade_id", $_POST["trade_id"], PDO::PARAM_INT);
    $resultat = $req->execute();
    $req->closeCursor();

    if ($resultat) {
      echo json_encode(["success" => true]);
    } else {
      echo json_encode(["error" => "update_failed"]);
    }
  } catch (Exception $e) {
    echo json_encode(["error" => "Update operation failed: " . $e->getMessage()]);
  }
}
