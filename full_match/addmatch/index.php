<?php
    // todo check key auth
    header('Content-Type: application/json');
    require("../../dbconn.php");
    require("../../apifunctions.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['addnewresult'])) {
        require("../part_page_get_full_match.php");

        $finalSeasonName = htmlentities(trim($_POST['season']));

        // get current season from DB and check
        $currentSeason = getCurrentSeason();
        if ($finalSeasonName != $currentSeason) {
            $currentSeasonSelected = false;
            $resultString .= "Current Season has not been selected, historic seasons cannot have results added. ";
        } else {
            $currentSeasonSelected = true;
        }

        // if all flags are true, fairly sure data isnt poor quality, so enter new match details;
        if ($matchDateInThePast
            && $notTheSameTeams 
            && $shotsAreGreaterThanShotsOT 
            && $halfTimeGoalsLessThanFullTime 
            && $shotsOTisntLessThanGoals 
            && $foulsLessThanTotalCards
            && $currentSeasonSelected) {
                // setup control variable
                $allEntriesSuccessful = false;

                // fetch seasonID from DB
                $seasonStmt = $conn->prepare("SELECT SeasonID FROM epl_seasons WHERE SeasonYears = ? ");
                $seasonStmt -> bind_param("s", $finalSeasonName);
                $seasonStmt -> execute();
                $seasonStmt -> store_result();
                if ($seasonStmt -> num_rows > 0) {
                    $seasonStmt -> bind_result($finalSeasonID);
                    $seasonStmt -> fetch();
                }

                // fetch refereeID from DB
                $refStmt = $conn->prepare("SELECT RefereeID FROM epl_referees WHERE RefereeName = ? ");
                $refStmt -> bind_param("s", $finalRefereeName);
                $refStmt -> execute();
                $refStmt -> store_result();
                $refStmt -> bind_result($returnedRefereeID);
                $refStmt -> fetch();

                // fetch home club ID from the DB
                $homeStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ");
                $homeStmt -> bind_param("s", $finalHomeClubName);
                $homeStmt -> execute();
                $homeStmt -> store_result();
                if ($homeStmt -> num_rows > 0) {
                    $homeStmt -> bind_result($homeClubID);
                    $homeStmt -> fetch();
                }

                // fetch away club ID from the DB
                $awayStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ");
                $awayStmt -> bind_param("s", $finalAwayClubName);
                $awayStmt -> execute();
                $awayStmt -> store_result();
                if ($awayStmt -> num_rows > 0) {
                    $awayStmt -> bind_result($awayClubID);
                    $awayStmt -> fetch();
                }

                $matchStatement = $conn->prepare("INSERT INTO `epl_matches` (`MatchID`, `SeasonID`, `MatchDate`, `KickOffTime`, `RefereeID`) VALUES (NULL, ?, ?, ?, ?);");
                $matchStatement -> bind_param("issi",
                            $finalSeasonID,
                            $finalMatchDate,
                            $finalKickOffTime,
                            $returnedRefereeID);
                $matchStatement -> execute();
                if ($matchStatement === false) {
                    http_response_code(500);
                    $replyMessage = "There was a problem with entering Match data, please review and try again";
                    apiReply($replyMessage);
                    die();
                } else {
                    $lastEnteredMatchID = $conn->insert_id;
                }

                $homeDataEntryStmt = $conn->prepare("INSERT INTO `epl_home_team_stats` (`HomeTeamStatID`, `HomeClubID`, `MatchID`, `HTTotalGoals`, `HTHalfTimeGoals`, `HTShots`, `HTShotsOnTarget`, `HTCorners`, `HTFouls`, `HTYellowCards`, `HTRedCards`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
                $homeDataEntryStmt -> bind_param("iiiiiiiiii",
                            $homeClubID,
                            $lastEnteredMatchID,
                            $finalHomeTeamTotalGoals,
                            $finalHomeTeamHalfTimeGoals,
                            $finalHomeTeamShots,
                            $finalHomeTeamShotsOnTarget,
                            $finalHomeTeamCorners,
                            $finalHomeTeamFouls,
                            $finalHomeTeamYellowCards,
                            $finalHomeTeamRedCards);
                $homeDataEntryStmt -> execute();

                if ($homeDataEntryStmt === false) {
                    http_response_code(500);
                    $replyMessage = "There was a problem with entering data, please review and try again";
                    apiReply($replyMessage);
                    die();
                } else {
                    $lastEnteredHomeID = (int) $conn->insert_id;
                    $homeMatchIDStmt = $conn->prepare("SELECT MatchID FROM `epl_home_team_stats` WHERE HomeTeamStatID = ? ;");
                    $homeMatchIDStmt -> bind_param("i", $lastEnteredHomeID);
                    $homeMatchIDStmt -> execute();
                    $homeMatchIDStmt -> store_result();
                    if ($homeMatchIDStmt -> num_rows > 0) {
                        $homeMatchIDStmt -> bind_result($homeMatchId);
                        $homeMatchIDStmt -> fetch();
                        $homeMatchIDStmt ->close();
                    } else {
                        http_response_code(500);
                        $replyMessage = "There was a problem with entering data, please review and try again";
                        apiReply($replyMessage);
                        die();
                    }
                }

                $awayDataEntryStmt = $conn->prepare("INSERT INTO `epl_away_team_stats` (`AwayTeamStatID`, `AwayClubID`, `MatchID`, `ATTotalGoals`, `ATHalfTimeGoals`, `ATShots`, `ATShotsOnTarget`, `ATCorners`, `ATFouls`, `ATYellowCards`, `ATRedCards`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
                $awayDataEntryStmt -> bind_param("iiiiiiiiii",
                            $awayClubID,
                            $homeMatchId,
                            $finalAwayTeamTotalGoals,
                            $finalAwayTeamHalfTimeGoals,
                            $finalAwayTeamShots,
                            $finalAwayTeamShotsOnTarget,
                            $finalAwayTeamCorners,
                            $finalAwayTeamFouls,
                            $finalAwayTeamYellowCards,
                            $finalAwayTeamRedCards);
                $awayDataEntryStmt -> execute();
                
                if ($awayDataEntryStmt === false) {
                    http_response_code(500);
                    $replyMessage = "There was a problem with entering Match data, please review and try again";
                    apiReply($replyMessage);
                    die();
                } else {
                    $allEntriesSuccessful = true;
                }

                if ($allEntriesSuccessful) {
                    http_response_code(201);
                    $replyMessage = "Match was successfully input, thank you for your contribution";
                    apiReply($replyMessage);
                } else {
                    http_response_code(500);
                    $replyMessage = "Match was not successfully input, please try again";
                    apiReply($replyMessage);
                }
        } else {
            http_response_code(400);
            $replyMessage = "There was an issue with the data submitted - ";
            $replyMessage .= $resultString;
            apiReply($replyMessage);
            die();
        }
    } else {
        http_response_code(400);
        $replyMessage = "Unknown Request";
        apiReply($replyMessage);
        die();
    }
?>