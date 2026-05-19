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

/**
 * RR initial planifié : distance vers le TP / distance vers le SL (entry, SL, TP uniquement).
 */
function calculateInitialRr($entry, $sl, $tp) {
    if ($entry === null || $sl === null || $tp === null || $entry === '' || $sl === '' || $tp === '') {
        return null;
    }
    $entry = floatval($entry);
    $sl = floatval($sl);
    $tp = floatval($tp);
    if (!is_finite($entry) || !is_finite($sl) || !is_finite($tp)) {
        return null;
    }
    $risk = abs($entry - $sl);
    if ($risk < 1e-12) {
        return null;
    }
    $reward = abs($tp - $entry);
    return round($reward / $risk, 2);
}

if (isset($_REQUEST["analytics"])) {
    $accountName = $_REQUEST["analytics"];
    
    try {
        // Récupère tous les trades du compte avec dates
        $sql = "SELECT t.*, a.initial_balance FROM trades t 
                INNER JOIN accounts a ON t.account_name = a.account_name 
                WHERE t.account_name = :accountName
                ORDER BY t.trade_date ASC";
        $req = $bd->prepare($sql);
        $req->bindValue(":accountName", $accountName, PDO::PARAM_STR);
        $req->execute();
        $trades = $req->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($trades)) {
            echo json_encode([
                'daily' => [],
                'monthly' => [],
                'yearly' => [],
                'overall' => [
                    'total_trades' => 0,
                    'winning_trades' => 0,
                    'losing_trades' => 0,
                    'total_pnl' => 0,
                    'win_rate' => 0,
                    'avg_win' => 0,
                    'avg_loss' => 0,
                    'profit_factor' => 0,
                    'max_drawdown' => 0,
                    'sharpe_ratio' => 0
                ]
            ]);
            exit;
        }
        
        $analytics = [
            'daily' => [],
            'monthly' => [],
            'yearly' => [],
            'overall' => [
                'total_trades' => 0,
                'winning_trades' => 0,
                'losing_trades' => 0,
                'total_pnl' => 0,
                'win_rate' => 0,
                'avg_win' => 0,
                'avg_loss' => 0,
                'profit_factor' => 0,
                'max_drawdown' => 0,
                'sharpe_ratio' => 0
            ]
        ];
        
        $dailyStats = [];
        $weeklyStats = [];
        $monthlyStats = [];
        $yearlyStats = [];
        $symbolStats = [];
        $dailySymbolStats = [];
        $weeklySymbolStats = [];
        $monthlySymbolStats = [];
        $yearlySymbolStats = [];
        $wins = [];
        $losses = [];
        $cumulativePnl = 0;
        $peak = 0;
        $maxDrawdown = 0;
        
        foreach ($trades as $trade) {
            // Validation des données
            if (!isset($trade['pnl']) || !is_numeric($trade['pnl']) || empty($trade['symbol'])) {
                continue; // Skip ce trade si données invalides
            }

            $date = $trade['trade_date'];
            $pnl = floatval($trade['pnl']);
            $symbol = trim($trade['symbol']);
            $cumulativePnl += $pnl;
            
            // Calcul du drawdown
            if ($cumulativePnl > $peak) {
                $peak = $cumulativePnl;
            }
            $drawdown = $peak - $cumulativePnl;
            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
            }
            
            // Statistiques globales
            $analytics['overall']['total_trades']++;
            $analytics['overall']['total_pnl'] += $pnl;
            if ($pnl > 0) {
                $analytics['overall']['winning_trades']++;
                $wins[] = $pnl;
            } elseif ($pnl < 0) {
                $analytics['overall']['losing_trades']++;
                $losses[] = abs($pnl);
            }
            
            // Statistiques par actif global
            if (!isset($symbolStats[$symbol])) {
                $symbolStats[$symbol] = [
                    'symbol' => $symbol,
                    'trades' => 0,
                    'pnl' => 0,
                    'wins' => 0,
                    'losses' => 0
                ];
            }
            $symbolStats[$symbol]['trades']++;
            $symbolStats[$symbol]['pnl'] += $pnl;
            if ($pnl > 0) {
                $symbolStats[$symbol]['wins']++;
            } elseif ($pnl < 0) {
                $symbolStats[$symbol]['losses']++;
            }
            
            // Vérifier si la date est valide avant de l'utiliser
            if (!empty($date) && strtotime($date) !== false) {
                // Statistiques journalières
                $dayKey = date('Y-m-d', strtotime($date));
                if (!isset($dailyStats[$dayKey])) {
                    $dailyStats[$dayKey] = [
                        'date' => $dayKey,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $dailyStats[$dayKey]['trades']++;
                $dailyStats[$dayKey]['pnl'] += $pnl;
                if ($pnl > 0) $dailyStats[$dayKey]['wins']++;
                elseif ($pnl < 0) $dailyStats[$dayKey]['losses']++;

                // Expositions journalières par actif
                if (!isset($dailySymbolStats[$dayKey])) {
                    $dailySymbolStats[$dayKey] = [];
                }
                if (!isset($dailySymbolStats[$dayKey][$symbol])) {
                    $dailySymbolStats[$dayKey][$symbol] = [
                        'period' => $dayKey,
                        'symbol' => $symbol,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $dailySymbolStats[$dayKey][$symbol]['trades']++;
                $dailySymbolStats[$dayKey][$symbol]['pnl'] += $pnl;
                if ($pnl > 0) {
                    $dailySymbolStats[$dayKey][$symbol]['wins']++;
                } elseif ($pnl < 0) {
                    $dailySymbolStats[$dayKey][$symbol]['losses']++;
                }
                
                // Statistiques hebdomadaires
                $weekKey = date('o-\WW', strtotime($date));
                if (!isset($weeklyStats[$weekKey])) {
                    $weeklyStats[$weekKey] = [
                        'week' => $weekKey,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $weeklyStats[$weekKey]['trades']++;
                $weeklyStats[$weekKey]['pnl'] += $pnl;
                if ($pnl > 0) $weeklyStats[$weekKey]['wins']++;
                elseif ($pnl < 0) $weeklyStats[$weekKey]['losses']++;

                // Expositions hebdomadaires par actif
                if (!isset($weeklySymbolStats[$weekKey])) {
                    $weeklySymbolStats[$weekKey] = [];
                }
                if (!isset($weeklySymbolStats[$weekKey][$symbol])) {
                    $weeklySymbolStats[$weekKey][$symbol] = [
                        'period' => $weekKey,
                        'symbol' => $symbol,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $weeklySymbolStats[$weekKey][$symbol]['trades']++;
                $weeklySymbolStats[$weekKey][$symbol]['pnl'] += $pnl;
                if ($pnl > 0) {
                    $weeklySymbolStats[$weekKey][$symbol]['wins']++;
                } elseif ($pnl < 0) {
                    $weeklySymbolStats[$weekKey][$symbol]['losses']++;
                }
                
                // Statistiques mensuelles
                $monthKey = date('Y-m', strtotime($date));
                if (!isset($monthlyStats[$monthKey])) {
                    $monthlyStats[$monthKey] = [
                        'month' => $monthKey,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $monthlyStats[$monthKey]['trades']++;
                $monthlyStats[$monthKey]['pnl'] += $pnl;
                if ($pnl > 0) $monthlyStats[$monthKey]['wins']++;
                elseif ($pnl < 0) $monthlyStats[$monthKey]['losses']++;

                // Expositions mensuelles par actif
                if (!isset($monthlySymbolStats[$monthKey])) {
                    $monthlySymbolStats[$monthKey] = [];
                }
                if (!isset($monthlySymbolStats[$monthKey][$symbol])) {
                    $monthlySymbolStats[$monthKey][$symbol] = [
                        'period' => $monthKey,
                        'symbol' => $symbol,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $monthlySymbolStats[$monthKey][$symbol]['trades']++;
                $monthlySymbolStats[$monthKey][$symbol]['pnl'] += $pnl;
                if ($pnl > 0) {
                    $monthlySymbolStats[$monthKey][$symbol]['wins']++;
                } elseif ($pnl < 0) {
                    $monthlySymbolStats[$monthKey][$symbol]['losses']++;
                }

                // Statistiques annuelles
                $yearKey = date('Y', strtotime($date));
                if (!isset($yearlyStats[$yearKey])) {
                    $yearlyStats[$yearKey] = [
                        'year' => $yearKey,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $yearlyStats[$yearKey]['trades']++;
                $yearlyStats[$yearKey]['pnl'] += $pnl;
                if ($pnl > 0) $yearlyStats[$yearKey]['wins']++;
                elseif ($pnl < 0) $yearlyStats[$yearKey]['losses']++;

                // Expositions annuelles par actif
                if (!isset($yearlySymbolStats[$yearKey])) {
                    $yearlySymbolStats[$yearKey] = [];
                }
                if (!isset($yearlySymbolStats[$yearKey][$symbol])) {
                    $yearlySymbolStats[$yearKey][$symbol] = [
                        'period' => $yearKey,
                        'symbol' => $symbol,
                        'trades' => 0,
                        'pnl' => 0,
                        'wins' => 0,
                        'losses' => 0
                    ];
                }
                $yearlySymbolStats[$yearKey][$symbol]['trades']++;
                $yearlySymbolStats[$yearKey][$symbol]['pnl'] += $pnl;
                if ($pnl > 0) {
                    $yearlySymbolStats[$yearKey][$symbol]['wins']++;
                } elseif ($pnl < 0) {
                    $yearlySymbolStats[$yearKey][$symbol]['losses']++;
                }
            }
        }
        
        // Calcul des métriques finales
        $totalTrades = $analytics['overall']['total_trades'];
        $winningTrades = $analytics['overall']['winning_trades'];
        $losingTrades = $analytics['overall']['losing_trades'];
        
        $analytics['overall']['win_rate'] = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;
        $analytics['overall']['avg_win'] = count($wins) > 0 ? round(array_sum($wins) / count($wins), 2) : 0;
        $analytics['overall']['avg_loss'] = count($losses) > 0 ? round(array_sum($losses) / count($losses), 2) : 0;
        $analytics['overall']['profit_factor'] = array_sum($losses) > 0 ? round(array_sum($wins) / array_sum($losses), 2) : 0;
        $analytics['overall']['max_drawdown'] = round($maxDrawdown, 2);
        $analytics['overall']['sharpe_ratio'] = ($analytics['overall']['total_pnl'] > 0 && $maxDrawdown > 0) ? round($analytics['overall']['total_pnl'] / $maxDrawdown, 2) : 0;
        
        // Trier et formater les statistiques
        $analytics['daily'] = array_values(array_map(function($day) {
            $day['win_rate'] = $day['trades'] > 0 ? round(($day['wins'] / $day['trades']) * 100, 1) : 0;
            return $day;
        }, $dailyStats));
        
        $analytics['weekly'] = array_values(array_map(function($week) {
            $week['win_rate'] = $week['trades'] > 0 ? round(($week['wins'] / $week['trades']) * 100, 1) : 0;
            return $week;
        }, $weeklyStats));

        $analytics['daily'] = array_values(array_map(function($day) {
            $day['win_rate'] = $day['trades'] > 0 ? round(($day['wins'] / $day['trades']) * 100, 1) : 0;
            return $day;
        }, $dailyStats));
        
        $analytics['monthly'] = array_values(array_map(function($month) {
            $month['win_rate'] = $month['trades'] > 0 ? round(($month['wins'] / $month['trades']) * 100, 1) : 0;
            return $month;
        }, $monthlyStats));
        
        $analytics['yearly'] = array_values(array_map(function($year) {
            $year['win_rate'] = $year['trades'] > 0 ? round(($year['wins'] / $year['trades']) * 100, 1) : 0;
            return $year;
        }, $yearlyStats));
        
        // Expositions par actif
        $analytics['symbol_exposure'] = array_values(array_map(function($symbolData) {
            $symbolData['win_rate'] = $symbolData['trades'] > 0 ? round(($symbolData['wins'] / $symbolData['trades']) * 100, 1) : 0;
            return $symbolData;
        }, $symbolStats));

        $analytics['daily_symbol_exposure'] = [];
        foreach ($dailySymbolStats as $dayKey => $item) {
            foreach ($item as $symbolData) {
                $symbolData['win_rate'] = $symbolData['trades'] > 0 ? round(($symbolData['wins'] / $symbolData['trades']) * 100, 1) : 0;
                $analytics['daily_symbol_exposure'][] = $symbolData;
            }
        }

        $analytics['weekly_symbol_exposure'] = [];
        foreach ($weeklySymbolStats as $weekKey => $item) {
            foreach ($item as $symbolData) {
                $symbolData['win_rate'] = $symbolData['trades'] > 0 ? round(($symbolData['wins'] / $symbolData['trades']) * 100, 1) : 0;
                $analytics['weekly_symbol_exposure'][] = $symbolData;
            }
        }

        $analytics['monthly_symbol_exposure'] = [];
        foreach ($monthlySymbolStats as $monthKey => $item) {
            foreach ($item as $symbolData) {
                $symbolData['win_rate'] = $symbolData['trades'] > 0 ? round(($symbolData['wins'] / $symbolData['trades']) * 100, 1) : 0;
                $analytics['monthly_symbol_exposure'][] = $symbolData;
            }
        }

        $analytics['yearly_symbol_exposure'] = [];
        foreach ($yearlySymbolStats as $yearKey => $item) {
            foreach ($item as $symbolData) {
                $symbolData['win_rate'] = $symbolData['trades'] > 0 ? round(($symbolData['wins'] / $symbolData['trades']) * 100, 1) : 0;
                $analytics['yearly_symbol_exposure'][] = $symbolData;
            }
        }

        // Trier par performance
        usort($analytics['daily'], function($a, $b) { return $b['pnl'] <=> $a['pnl']; });
        usort($analytics['weekly'], function($a, $b) { return $b['pnl'] <=> $a['pnl']; });
        usort($analytics['monthly'], function($a, $b) { return $b['pnl'] <=> $a['pnl']; });
        usort($analytics['yearly'], function($a, $b) { return $b['pnl'] <=> $a['pnl']; });
        usort($analytics['symbol_exposure'], function($a, $b) { return $b['pnl'] <=> $a['pnl']; });
        usort($analytics['daily_symbol_exposure'], function($a, $b) {
            if ($a['period'] === $b['period']) {
                return $b['pnl'] <=> $a['pnl'];
            }
            return strcmp($a['period'], $b['period']);
        });
        usort($analytics['weekly_symbol_exposure'], function($a, $b) {
            if ($a['period'] === $b['period']) {
                return $b['pnl'] <=> $a['pnl'];
            }
            return strcmp($a['period'], $b['period']);
        });
        usort($analytics['monthly_symbol_exposure'], function($a, $b) {
            if ($a['period'] === $b['period']) {
                return $b['pnl'] <=> $a['pnl'];
            }
            return strcmp($a['period'], $b['period']);
        });
        usort($analytics['yearly_symbol_exposure'], function($a, $b) {
            if ($a['period'] === $b['period']) {
                return $b['pnl'] <=> $a['pnl'];
            }
            return strcmp($a['period'], $b['period']);
        });
        
        echo json_encode($analytics);
        
    } catch (Exception $e) {
        echo json_encode(["error" => "Analytics calculation failed: " . $e->getMessage()]);
    }
    exit;
}

if (isset($_REQUEST["trades"])) {
    $accountName = $_REQUEST["trades"];
    
    try {
        // Récupère les trades du compte spécifique avec le capital
        $sql = "SELECT t.*, a.initial_balance FROM trades t 
                INNER JOIN accounts a ON t.account_name = a.account_name 
                WHERE t.account_name = :accountName
                ORDER BY t.id DESC";
        $req = $bd->prepare($sql);
        $req->bindValue(":accountName", $accountName, PDO::PARAM_STR);
        $req->execute();
        $trades = $req->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer RR et % pour chaque trade
        $totalPnl = 0;
        $totalRr = 0;
        $capital = 0;
        foreach ($trades as &$trade) {
            $trade['rr_initial'] = calculateInitialRr(
                $trade['entry_price'] ?? null,
                $trade['stop_loss'] ?? null,
                $trade['take_profit'] ?? null
            );

            // Validation des données pour RR réalisé et %
            if (!isset($trade['entry_price']) || !isset($trade['stop_loss']) || !isset($trade['exit_price']) || !isset($trade['pnl'])) {
                continue;
            }

            $entry = floatval($trade['entry_price']);
            $sl = floatval($trade['stop_loss']);
            $pnl = floatval($trade['pnl']);
            $capital = floatval($trade['initial_balance']);

            $risk = $entry - $sl;
            $exit = floatval($trade['exit_price']);
            if ($risk != 0) {
                $trade['rr'] = round(($exit - $entry) / $risk, 2);
            } else {
                $trade['rr'] = 0;
            }
            $totalRr += $trade['rr'];

            $trade['pct'] = $capital > 0 ? round(($pnl / $capital) * 100, 2) : 0;

            $totalPnl += $pnl;
        }
        
        // Ajouter la ligne Total
        $totalPct = $capital > 0 ? round(($totalPnl / $capital) * 100, 2) : 0;
        $total = [
            'symbol' => 'TOTAL',
            'type' => '',
            'trade_date' => '',
            'entry_price' => '',
            'stop_loss' => '',
            'take_profit' => '',
            'exit_price' => '',
            'pnl' => $totalPnl,
            'rr' => $totalRr,
            'pct' => $totalPct,
            'session' => '',
            'created_at' => ''
        ];
        $trades[] = $total;
        
        echo json_encode($trades);
    } catch (Exception $e) {
        echo json_encode(["error" => "Trades loading failed: " . $e->getMessage()]);
    }
    exit;
}

// Si pas de paramètre, retourne tous les trades
try {
    $req = $bd->query("SELECT * FROM trades ORDER BY id DESC");
    $data = $req->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(["error" => "General query failed: " . $e->getMessage()]);
}
