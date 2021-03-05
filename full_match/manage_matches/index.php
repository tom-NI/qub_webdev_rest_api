<?php
    // todo check key auth
    header('Content-Type: application/json');
    require("../../dbconn.php");
    require("../../apifunctions.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_GET['deletematch'])) {
            $matchID = (int) htmlentities(trim($_POST['id']));

            // get refereeID
            $stmt = $conn->prepare("SELECT MatchID FROM `epl_matches` WHERE MatchID = ? ;");
            $stmt -> bind_param("i", $matchID);
            $stmt -> execute();
            $totalRows = $stmt->num_rows;
            $stmt->close();

            if ($totalRows == 1) {
                $conn->autocommit(false);

                $homeStmt = $conn->prepare("DELETE FROM `epl_home_team_stats` WHERE `epl_home_team_stats`.`HomeTeamStatID` = ? ;");
                $homeStmt -> bind_param("i", $matchID);
                $homeStmt -> execute();

                $awayStmt = $conn->prepare("DELETE FROM `epl_away_team_stats` WHERE `epl_away_team_stats`.`AwayTeamStatID` = ? ;");
                $awayStmt -> bind_param("i", $matchID);
                $awayStmt -> execute();

                $matchStmt = $conn->prepare("DELETE FROM `epl_matches` WHERE `epl_matches`.`MatchID` = ? ;");
                $matchStmt -> bind_param("i", $matchID);
                $matchStmt -> execute();

                if ($homeStmt && $awayStmt && $matchStmt) {
                    http_response_code(204);
                    $conn->autocommit(true);
                    $replyMessage = "Deletion was successful";
                    apiReply($replyMessage);
                    die();
                } else {
                    $conn->rollback();
                    $conn->autocommit(true);
                    http_response_code(500);
                    $replyMessage = "Deletion was unsuccessful, please try later";
                    apiReply($replyMessage);
                    die();
                }
            } else {
                http_response_code(400);
                $replyMessage = "ID is ambiguous or unknown, please check query parameters and try again";
                apiReply($replyMessage);
                die();
            }
        } elseif (isset($_GET['editmatch'])) {
            
            

        } else {
            http_response_code(400);
            $replyMessage = "Unknown Request, please check parameters and try again";
            apiReply($replyMessage);
            die();
        }

        
    }

    
?>