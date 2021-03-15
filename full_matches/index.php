<?php
    header('Content-Type: application/json');
    require("../apifunctions.php");
    require("../dbconn.php");
    if (checkAPIKey()) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $finalDataSet = array();
            
            // get FULL data from matches
            $mainMatchQuery = "SELECT epl_matches.MatchId, epl_matches.MatchDate, epl_matches.KickOffTime, epl_referees.RefereeName, 
            epl_home_team_stats.HomeClubID, epl_away_team_stats.AwayClubID, epl_home_team_stats.HTTotalGoals, epl_home_team_stats.HTHalfTimeGoals, 
            epl_home_team_stats.HTShots, epl_home_team_stats.HTShotsOnTarget, epl_home_team_stats.HTCorners, epl_home_team_stats.HTFouls, 
            epl_home_team_stats.HTYellowCards, epl_home_team_stats.HTRedCards, epl_away_team_stats.ATTotalGoals, 
            epl_away_team_stats.ATHalfTimeGoals, epl_away_team_stats.ATShots, epl_away_team_stats.ATShotsOnTarget, 
            epl_away_team_stats.ATCorners, epl_away_team_stats.ATFouls, epl_away_team_stats.ATYellowCards, epl_away_team_stats.ATRedCards
            FROM epl_matches
            INNER JOIN epl_home_team_stats ON epl_matches.MatchID = epl_home_team_stats.MatchID 
            INNER JOIN epl_away_team_stats ON epl_matches.MatchID = epl_away_team_stats.MatchID
            INNER JOIN epl_seasons ON epl_matches.SeasonID = epl_seasons.SeasonID
            INNER JOIN epl_referees ON epl_referees.RefereeID = epl_matches.RefereeID
            INNER JOIN epl_clubs ON epl_clubs.ClubID = epl_home_team_stats.HomeClubID";

            $orderQuery = "ORDER BY MatchID DESC";

            if (isset($_GET['onematch'])) {
                // check id exists in DB before proceeding!
                // prepared statement
                $stmt = $conn->prepare("SELECT MatchID FROM epl_matches WHERE MatchID = ?");

                // return one match from the users entry, escape htmlentities!
                $singleMatchID = (int) htmlentities(trim($_GET['onematch']));
                $stmt -> bind_param("i", $singleMatchID);
                $stmt -> execute();
                $stmt -> store_result();
        
                if ($stmt -> num_rows() > 0) {
                    $stmt -> bind_result($matchID);
                    $stmt -> fetch();

                    // get both clubIDs from the match in question!
                    $clubIdStmt = $conn->prepare("SELECT epl_home_team_stats.HomeClubID, epl_away_team_stats.AwayClubID 
                        FROM epl_home_team_stats INNER JOIN epl_away_team_stats 
                        ON epl_away_team_stats.MatchID = epl_home_team_stats.MatchID
                        WHERE epl_away_team_stats.MatchID = ? && epl_home_team_stats.MatchID = ? ");
                    $clubIdStmt -> bind_param("ii", $matchID, $matchID);
                    $clubIdStmt -> execute();
                    $clubIdStmt -> store_result();
                    $clubIdStmt -> bind_result($homeClubID, $awayClubId);
                    $clubIdStmt -> fetch();

                    $conditionQuery = "WHERE epl_matches.MatchId = {$matchID}";
                    $finalQuery = "{$mainMatchQuery} {$conditionQuery} {$orderQuery}";
                } else {
                    http_response_code(404);
                    $errorMessage = "Match ID doesnt exist, please try again.";
                    apiReply($errorMessage);
                    die();
                }
            } elseif (isset($_GET['fullseason'])) {
                // if the user requests a full seasons matches
                // first check the season input and check it exists within the DB before proceeding (incase user can change on client)
                $seasonStmt = $conn->prepare("SELECT SeasonID FROM epl_seasons WHERE SeasonYears LIKE ? ");
                $seasonYears = htmlentities(trim($_GET['fullseason']));
                if (checkSeasonRegex($seasonYears)) {
                    if (is_numeric($seasonYears)) {
                        $seasonStmt->bind_param("i", $seasonYears);
                    } else {
                        $seasonStmt->bind_param("s", $seasonYears);
                    }
                    $seasonStmt->execute();
                    $seasonStmt->store_result();
        
                    // only proceed if the season exists in the database
                    if (($seasonStmt->num_rows() < 1) || ($seasonStmt->num_rows() > 1)) {
                        http_response_code(400);
                        $errorMessage = "Season doesnt exist or is ambiguous, please try again.";
                        apiReply($errorMessage);
                        die();
                    } else {
                        $seasonStmt->bind_result($seasonID);
                        $seasonStmt->fetch();
                        $conditionQuery = "WHERE epl_seasons.SeasonID = {$seasonID}";
                        $finalQuery = "{$mainMatchQuery} {$conditionQuery} {$orderQuery}";
                    }
                } else {
                    http_response_code(400);
                    $errorMessage = "Requested season format is unrecognised, please try again using the format YYYY-YYYY.";
                    apiReply($errorMessage);
                    die();
                }
            } elseif (isset($_GET['fixture'])) {
                // 1 fixture - get all records throughout history for stats analysis!
                $fixtureValue = htmlentities(trim($_GET['fixture']));

                // split the value into two teams with the ~ delimiter and remove underscores
                trim($fixtureValue);
                $newFixtureValue = removeUnderScores($fixtureValue);
                $fixtureValueArray = explode("~", $newFixtureValue);
                $homeTeamNameSearch = $fixtureValueArray[0];
                $awayTeamNameSearch = $fixtureValueArray[1];

                if (($homeTeamNameSearch != null) && (strlen($homeTeamNameSearch) > 0) 
                    && ($awayTeamNameSearch != null) && (strlen($awayTeamNameSearch) > 0)) {
                    $homeStmt = $conn->prepare("SELECT ClubID FROM `epl_clubs` WHERE ClubName LIKE ? ;");
                    $homeStmt->bind_param("s", $homeTeamNameSearch);
                    $homeStmt->execute();
                    $homeStmt->store_result();

                    $awayStmt = $conn->prepare("SELECT ClubID FROM `epl_clubs` WHERE ClubName LIKE ? ;");
                    $awayStmt->bind_param("s", $awayTeamNameSearch);
                    $awayStmt->execute();
                    $awayStmt->store_result();
                    
                    if ($homeStmt->num_rows() > 0 && $awayStmt->num_rows() > 0) {
                        $homeStmt->bind_result($homeTeamID);
                        $homeStmt->fetch();
                        $awayStmt->bind_result($awayTeamID);
                        $awayStmt->fetch();

                        $defaultTeamQuery = "WHERE (epl_home_team_stats.HomeClubID = {$homeTeamID} AND epl_away_team_stats.AwayClubID = {$awayTeamID})
                        OR (epl_home_team_stats.HomeClubID = {$awayTeamID} AND epl_away_team_stats.AwayClubID = {$homeTeamID})";

                        if (isset($_GET['strict'])) {
                            $teamQuery = "WHERE epl_home_team_stats.HomeClubID = {$homeTeamID} AND epl_away_team_stats.AwayClubID = {$awayTeamID}";
                        } else {
                            $teamQuery = $defaultTeamQuery;
                        }
                        
                        if (isset($_GET['count'])) {
                            $limitQuery = queryPagination();
                        } else {
                            $limitQuery = "";
                        }

                        $finalQuery = "{$mainMatchQuery} {$teamQuery} {$orderQuery} {$limitQuery}";
                    } else {
                        http_response_code(404);
                        $errorMessage = "One of those clubs cannot be identified, please reenter and try again.";
                        apiReply($errorMessage);
                        die();
                    }
                }
            } else {
                http_response_code(400);
                $errorMessage = "Query key not recognised, please enter a query key and value and try again.";
                apiReply($errorMessage);
                die();
            }

            $matchData = dbQueryCheckReturn($finalQuery);

            // get club names and logo URLS from the database
            while ($row = $matchData->fetch_assoc()) {
                $homeClubID = $row["HomeClubID"];
                $awayClubID = $row["AwayClubID"];

                $homeClubNameQuery = "SELECT epl_clubs.ClubName, epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubID = {$homeClubID}";
                $awayClubNameQuery = "SELECT epl_clubs.ClubName, epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubID = {$awayClubID}";

                $homeClubValue = dbQueryCheckReturn($homeClubNameQuery);
                $awayClubValue = dbQueryCheckReturn($awayClubNameQuery);
                $homeTeamName;
                $homeTeamURL;
                $awayTeamName;
                $awayTeamURL;

                while ($homeTeamRow = $homeClubValue->fetch_assoc()) {
                    $homeTeamName = $homeTeamRow["ClubName"];
                    $homeTeamURL = $homeTeamRow["ClubLogoURL"];
                }

                while ($awayTeamRow = $awayClubValue->fetch_assoc()) {
                    $awayTeamName = $awayTeamRow["ClubName"];
                    $awayTeamURL = $awayTeamRow["ClubLogoURL"];
                }
                
                $singlematch = array(
                    "matchdate" => $row["MatchDate"],
                    "kickofftime" => $row["KickOffTime"],
                    "refereename" => $row["RefereeName"],
                    "hometeam" => $homeTeamName,
                    "awayteam" => $awayTeamName,
                    "hometeamlogoURL" => $homeTeamURL,
                    "awayteamlogoURL" => $awayTeamURL,
                    "hometeamtotalgoals" => $row["HTTotalGoals"],
                    "hometeamhalftimegoals" => $row["HTHalfTimeGoals"],
                    "hometeamshots" => $row["HTShots"],
                    "hometeamshotsontarget" => $row["HTShotsOnTarget"],
                    "hometeamcorners" => $row["HTCorners"],
                    "hometeamfouls" => $row["HTFouls"],
                    "hometeamyellowcards" => $row["HTYellowCards"],
                    "hometeamredcards" => $row["HTRedCards"],
                    "awayteamtotalgoals" => $row["ATTotalGoals"],
                    "awayteamhalftimegoals" => $row["ATHalfTimeGoals"],
                    "awayteamshots" => $row["ATShots"],
                    "awayteamshotsontarget" => $row["ATShotsOnTarget"],
                    "awayteamcorners" => $row["ATCorners"],
                    "awayteamfouls" => $row["ATFouls"],
                    "awayteamyellowcards" => $row["ATYellowCards"],
                    "awayteamredcards" => $row["ATRedCards"]
                );
                $finalDataSet[] = $singlematch;
            }

            // encode the final data set to JSON
            echo json_encode($finalDataSet);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_GET['addnewresult'])) {
                require("part_page_get_full_match.php");
        
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
            } elseif (isset($_GET['editmatch'])) {
                require("part_page_get_full_match.php");
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
    }
?>