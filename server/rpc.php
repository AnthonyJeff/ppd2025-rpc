<?php

    
    header("Content-Type: application/json");

    $input = json_decode(file_get_contents("php://input"), true);
    $method = $input["method"] ?? null;
    $params = $input["params"] ?? [];

    $response = [
        "jsonrpc" => "2.0",
        "id" => $input["id"] ?? null,
    ];

    $stateFile = __DIR__ . "/../data/game_state.json";
    $chatFile = __DIR__ . "/../data/chat_log.json";

    // Inicializa chat se não existir
    if (!file_exists($chatFile)) {
        file_put_contents($chatFile, json_encode([]));
    }


    function loadGameState($file) {
        return json_decode(file_get_contents($file), true);
    }

    function saveGameState($file, $state) {
        file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT));
    }

    function playerCanMove($state, $player) {
        foreach ($state["board"] as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === "P$player") {
                    foreach ([[0,1],[0,-1],[1,0],[-1,0]] as [$dr,$dc]) {
                        $nr = $r + $dr;
                        $nc = $c + $dc;
                        if (
                            $nr >= 0 && $nr <= 4 &&
                            $nc >= 0 && $nc <= 4 &&
                            empty($state["board"][$nr][$nc])
                        ) {
                            return true; // achou uma peça com movimento válido
                        }
                    }
                }
            }
        }
        return false;
    }

    // Inicializa o estado se não existir
    if (!file_exists($stateFile)) {
        file_put_contents($stateFile, json_encode([
            "turn" => 1,
            "placingPhase" => true,
            "player1Count" => 0,
            "player2Count" => 0,
            "board" => array_fill(0, 5, array_fill(0, 5, null))
        ], JSON_PRETTY_PRINT));
    }

    if ($method === "getState") {
        $response["result"] = loadGameState($stateFile);
    } elseif ($method === "resetGame") {
        $player = (int)($params["player"] ?? 0);
        $oldState = loadGameState($stateFile);

        // Se já havia jogo em andamento, atribui vitória ao adversário
        if (empty($oldState["winner"]) && !$oldState["placingPhase"]) {
            $oldState["winner"] = $player === 1 ? 2 : 1;
            $oldState["reason"] = "reset";
            saveGameState($stateFile, $oldState);
            $response["result"] = $oldState;
            return;
        }

        $newState = [
            "turn" => 1,
            "placingPhase" => true,
            "player1Count" => 0,
            "player2Count" => 0,
            "player1Captures" => 0,
            "player2Captures" => 0,
            "board" => array_fill(0, 5, array_fill(0, 5, null))
        ];
        saveGameState($stateFile, $newState);
        $response["result"] = $newState;
    } elseif ($method === "placePiece") {
        $row = (int)($params["row"] ?? -1);
        $col = (int)($params["col"] ?? -1);
        $player = (int)($params["player"] ?? 0);
        $state = loadGameState($stateFile);

        if ($row < 0 || $row > 4 || $col < 0 || $col > 4) {
            $response["error"] = ["code" => 104, "message" => "Coordenadas inválidas."];
        } elseif ($state["placingPhase"] && $row === 2 && $col === 2) {
            $response["error"] = ["code" => 105, "message" => "A casa central está bloqueada durante o posicionamento."];
        } elseif (!$state["placingPhase"]) {
            $response["error"] = ["code" => 100, "message" => "Fase de posicionamento encerrada."];
        } elseif (!in_array($player, [1, 2])) {
            $response["error"] = ["code" => 101, "message" => "Jogador inválido."];
        } elseif ($state["turn"] !== $player) {
            $response["error"] = ["code" => 102, "message" => "Não é o turno do jogador."];
        } elseif (!empty($state["board"][$row][$col])) {
            $response["error"] = ["code" => 103, "message" => "Casa ocupada."];
        } else {
            $state["board"][$row][$col] = "P$player";
            $key = $player === 1 ? "player1Count" : "player2Count";
            $state[$key]++;
            if ($state[$key] % 2 === 0) {
                $state["turn"] = $player === 1 ? 2 : 1;
            }
            if ($state["player1Count"] === 12 && $state["player2Count"] === 12) {
                $state["placingPhase"] = false;
            }
            saveGameState($stateFile, $state);
            $response["result"] = $state;
        }
    } elseif ($method === "movePiece") {
        $from = $params["from"] ?? null;
        $to = $params["to"] ?? null;
        $player = (int)($params["player"] ?? 0);
        $state = loadGameState($stateFile);

        if ($state["placingPhase"]) {
            $response["error"] = ["code" => 200, "message" => "A fase de movimentação ainda não começou."];
        } elseif (!in_array($player, [1, 2])) {
            $response["error"] = ["code" => 201, "message" => "Jogador inválido."];
        } elseif ($state["turn"] !== $player) {
            $response["error"] = ["code" => 202, "message" => "Não é o turno do jogador."];
        } elseif (
            !isset($from["row"], $from["col"], $to["row"], $to["col"]) ||
            $from["row"] < 0 || $from["row"] > 4 || $from["col"] < 0 || $from["col"] > 4 ||
            $to["row"] < 0 || $to["row"] > 4 || $to["col"] < 0 || $to["col"] > 4
        ) {
            $response["error"] = ["code" => 203, "message" => "Coordenadas inválidas."];
        } elseif ($state["board"][$from["row"]][$from["col"]] !== "P$player") {
            $response["error"] = ["code" => 204, "message" => "A peça não pertence ao jogador."];
        } elseif (!empty($state["board"][$to["row"]][$to["col"]])) {
            $response["error"] = ["code" => 205, "message" => "Casa de destino ocupada."];
        } elseif (
            abs($from["row"] - $to["row"]) + abs($from["col"] - $to["col"]) !== 1
        ) {
            $response["error"] = ["code" => 206, "message" => "Movimento inválido. Apenas 1 casa na horizontal ou vertical."];
        } else {
            // Movimento
            $state["board"][$to["row"]][$to["col"]] = "P$player";
            $state["board"][$from["row"]][$from["col"]] = null;

            // Captura por flanqueamento
            $dirs = [[-1,0], [1,0], [0,-1], [0,1]];
            $opponent = $player === 1 ? 2 : 1;

            foreach ($dirs as [$dr, $dc]) {
                $r1 = $to["row"] + $dr;
                $c1 = $to["col"] + $dc;
                $r2 = $to["row"] + 2 * $dr;
                $c2 = $to["col"] + 2 * $dc;

                if (
                    $r1 >= 0 && $r1 <= 4 && $c1 >= 0 && $c1 <= 4 &&
                    $r2 >= 0 && $r2 <= 4 && $c2 >= 0 && $c2 <= 4 &&
                    $state["board"][$r1][$c1] === "P$opponent" &&
                    $state["board"][$r2][$c2] === "P$player"
                ) {
                    $state["board"][$r1][$c1] = null;
                }
            }

            // Verificar fim de jogo por peças restantes
            $remainingP1 = 0;
            $remainingP2 = 0;
            foreach ($state["board"] as $row) {
                foreach ($row as $cell) {
                    if ($cell === "P1") $remainingP1++;
                    if ($cell === "P2") $remainingP2++;
                }
            }

            if ($remainingP1 === 0) {
                $state["winner"] = 2;
                $state["reason"] = "elim";
            } elseif ($remainingP2 === 0) {
                $state["winner"] = 1;
                $state["reason"] = "elim";
            } elseif (!playerCanMove($state, $player === 1 ? 2 : 1)) {
                $state["winner"] = $player;
                $state["reason"] = "block";
            } else {
                $state["turn"] = $player === 1 ? 2 : 1;
            }

            saveGameState($stateFile, $state);
            $response["result"] = $state;
        }
    } elseif ($method === "sendMessage") {
        $player = (int)($params["player"] ?? 0);
        $text = trim($params["text"] ?? "");

        if (!in_array($player, [1, 2]) || $text === "") {
            $response["error"] = ["code" => 300, "message" => "Mensagem inválida."];
        } else {
            $chat = json_decode(file_get_contents($chatFile), true);
            $chat[] = [
                "player" => $player,
                "text" => $text,
                "timestamp" => date("c") // ISO 8601
            ];
            file_put_contents($chatFile, json_encode($chat, JSON_PRETTY_PRINT));
            $response["result"] = true;
        }
    } elseif ($method === "getChat") {
        $chat = json_decode(file_get_contents($chatFile), true);
        $response["result"] = $chat;
    } elseif ($method === "giveUp") {
        $player = (int)($params["player"] ?? 0);
        if (!in_array($player, [1, 2])) {
            $response["error"] = ["code" => 400, "message" => "Jogador inválido para desistência."];
        } else {
            $state = loadGameState($stateFile);
            $state["winner"] = $player === 1 ? 2 : 1;
            $state["reason"] = "giveup";
            saveGameState($stateFile, $state);
            $response["result"] = true;
        }
    } else {
        $response["error"] = ["code" => -32601, "message" => "Método não encontrado"];
    }

    echo json_encode($response);


