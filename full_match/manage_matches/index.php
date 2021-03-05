<?php
    // todo check key auth
    header('Content-Type: application/json');
    require("../../dbconn.php");
    require("../../apifunctions.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_GET['deletematch'])) {
            $matchID = (int) htmlentities(trim($_POST['id']));

            // get refereeID
            $stmt = $conn->prepare("SELECT MatchID FROM `epl_matches` WHERE MatchID = ? ");
            $stmt -> bind_param("i", $matchID);
            $stmt -> execute();
            $stmt -> store_result();
            $totalRows = $stmt->num_rows;
            $stmt -> close();
            
            if ($totalRows === 1) {
                $homeStmt = $conn->prepare("DELETE FROM `epl_home_team_stats` WHERE `epl_home_team_stats`.`MatchID` = ? ;");
                $homeStmt -> bind_param("i", $matchID);
                $homeStmt -> execute();

                $awayStmt = $conn->prepare("DELETE FROM `epl_away_team_stats` WHERE `epl_away_team_stats`.`MatchID` = ? ;");
                $awayStmt -> bind_param("i", $matchID);
                $awayStmt -> execute();

                $matchStmt = $conn->prepare("DELETE FROM `epl_matches` WHERE `epl_matches`.`MatchID` = ? ;");
                $matchStmt -> bind_param("i", $matchID);
                $matchStmt -> execute();

                if ($homeStmt && $awayStmt && $matchStmt) {
                    // no written JSON response as 204 doesnt support it!
                    http_response_code(204); 
                } else {
                    http_response_code(500);
                    $replyMessage = "Deletion was unsuccessful, please try later";
                    apiReply($replyMessage);
                }
            } else {
                http_response_code(400);
                $replyMessage = "ID is ambiguous or unknown, please check query parameters and try again";
                apiReply($replyMessage);
                die();
            }
        } elseif (isset($_GET['editmatch'])) {
            require("../part_page_get_full_match.php");
            $editedMatchID = htmlentities(trim($_POST['id']));
            $justificationForChange = htmlentities(trim($_POST['change_justification']));

            // get current matches season first (non editable)
            $stmt = $conn->prepare("SELECT SeasonID FROM epl_matches WHERE MatchID = ? ;");
            $stmt -> bind_param("i", $editedMatchID);
            $stmt -> execute();
            $stmt -> store_result();
            $stmt -> bind_result($finalSeasonID);
            $stmt -> fetch();

            // get home team ID
            $homeStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ;");
            $homeStmt -> bind_param("s", $finalHomeClubName);
            $homeStmt -> execute();
            $homeStmt -> store_result();
            $homeStmt -> bind_result($homeClubID);
            $homeStmt -> fetch();

            // get away team ID
            $homeStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ;");
            $homeStmt -> bind_param("s", $finalAwayClubName);
            $homeStmt -> execute();
            $homeStmt -> store_result();
            $homeStmt -> bind_result($awayClubID);
            $homeStmt -> fetch();

            // get referee ID
            $homeStmt = $conn->prepare("SELECT RefereeID FROM epl_referees WHERE RefereeName = ? ;");
            $homeStmt -> bind_param("s", $finalRefereeName);
            $homeStmt -> execute();
            $homeStmt -> store_result();
            $homeStmt -> bind_result($returnedRefereeID);
            $homeStmt -> fetch();

            $matchStatement = $conn->prepare("UPDATE `epl_matches` SET `SeasonID` = ?, `MatchDate` = ? , `KickOffTime` = ? , `RefereeID` = ? WHERE `epl_matches`.`MatchID` = ? ;");
            $matchStatement -> bind_param("issii",
                    $finalSeasonID,
                    $finalMatchDate,
                    $finalKickOffTime,
                    $returnedRefereeID,
                    $editedMatchID
                );
            $matchStatement -> execute();
            if ($matchStatement === false) {
                http_response_code(500);
                $replyMessage = "There was a problem with entering Match data, please review and try again";
                apiReply($replyMessage);
                die();
            }

            $homeDataEntryStmt = $conn->prepare("UPDATE `epl_home_team_stats` SET `HomeClubID` = ? , `HTTotalGoals` = ? , `HTHalfTimeGoals` = ? , `HTShots` = ? , `HTShotsOnTarget` = ? , `HTCorners` = ? , `HTFouls` = ? , `HTYellowCards` = ? , `HTRedCards` = ? WHERE `epl_home_team_stats`.`MatchID` = ? ;");
            $homeDataEntryStmt -> bind_param("iiiiiiiiii",
                    $homeClubID,
                    $finalHomeTeamTotalGoals,
                    $finalHomeTeamHalfTimeGoals,
                    $finalHomeTeamShots,
                    $finalHomeTeamShotsOnTarget,
                    $finalHomeTeamCorners,
                    $finalHomeTeamFouls,
                    $finalHomeTeamYellowCards,
                    $finalHomeTeamRedCards,
                    $editedMatchID
                );
            $homeDataEntryStmt -> execute();
            if ($homeDataEntryStmt === false) {
                http_response_code(500);
                $replyMessage = "There was a problem with entering data, please review and try again";
                apiReply($replyMessage);
                die();
            }

            $awayDataEntryStmt = $conn->prepare("UPDATE `epl_away_team_stats` SET `AwayClubID` = ? , `ATTotalGoals` = ? , `ATHalfTimeGoals` = ? , `ATShots` = ? , `ATShotsOnTarget` = ? , `ATCorners` = ? , `ATFouls` = ? , `ATYellowCards` = ? , `ATRedCards` = ? WHERE `epl_away_team_stats`.`MatchID` = ? ;");
            $awayDataEntryStmt -> bind_param("iiiiiiiiii",
                    $awayClubID,
                    $finalAwayTeamTotalGoals,
                    $finalAwayTeamHalfTimeGoals,
                    $finalAwayTeamShots,
                    $finalAwayTeamShotsOnTarget,
                    $finalAwayTeamCorners,
                    $finalAwayTeamFouls,
                    $finalAwayTeamYellowCards,
                    $finalAwayTeamRedCards,
                    $editedMatchID
                );
            $awayDataEntryStmt -> execute();
            if ($awayDataEntryStmt === false) {
                http_response_code(500);
                $replyMessage = "There was a problem with entering data, please review and try again";
                apiReply($replyMessage);
                die();
            }

            // TODO - get user ID
            $tempDummyUserID = 50000;
            // session ID = $userID;
            // fetch user id from name?

            $currentDateTime = date("Y-m-d H:i:s");
            $editMatchStmt = $conn->prepare("INSERT INTO `epl_match_edits` (`EditID`, `MatchID`, `EditedByUserID`, `EditDescription`, `EditedDate`) VALUES (NULL, ?, ?, ?, ? );");
            $editMatchStmt -> bind_param("iiss",
                    $editedMatchID,
                    $tempDummyUserID,
                    $justificationForChange,
                    $currentDateTime
                );
            $editMatchStmt -> execute();
            if ($editMatchStmt === false) {
                http_response_code(500);
                $replyMessage = "There was a problem with entering data, please review and try again";
                apiReply($replyMessage);
                die();
            }

            if ($matchStatement && $homeDataEntryStmt && $awayDataEntryStmt && $editMatchStmt) {
                $matchStatement -> close();
                $homeDataEntryStmt -> close();
                $awayDataEntryStmt -> close();
                $editMatchStmt -> close();
                http_response_code(200);
                $replyMessage = "Match records updated successfully";
                apiReply($replyMessage);
                die();
            }
        } else {
            http_response_code(400);
            $replyMessage = "Unknown Request, please check parameters and try again";
            apiReply($replyMessage);
            die();
        }
    }    
?>