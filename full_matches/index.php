<?php
    header('Content-Type: application/json');
    require("../apifunctions.php");
    require("../dbconn.php");
    
    if (checkAPIKey()) {
        // for edits to matches, record what user added / modified data
        // if the user id is available from the website, grab it for the insert
        if (isset($_POST['userid'])) {
            $userID = htmlentities(trim($_POST['userid']));
        } else {
            // else grab the API key and use for the user insert for non website additions
            $userID = $_SERVER['PHP_AUTH_PW'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $finalDataSet = array();
            
            // get FULL data from matches
            $mainMatchQuery = "SELECT epl_matches.MatchId, epl_matches.MatchDate, epl_matches.KickOffTime, epl_matches.RefereeName, 
            epl_home_team_stats.HomeClubName, epl_away_team_stats.AwayClubName, epl_home_team_stats.HTTotalGoals, epl_home_team_stats.HTHalfTimeGoals, 
            epl_home_team_stats.HTShots, epl_home_team_stats.HTShotsOnTarget, epl_home_team_stats.HTCorners, epl_home_team_stats.HTFouls, 
            epl_home_team_stats.HTYellowCards, epl_home_team_stats.HTRedCards, epl_away_team_stats.ATTotalGoals, 
            epl_away_team_stats.ATHalfTimeGoals, epl_away_team_stats.ATShots, epl_away_team_stats.ATShotsOnTarget, 
            epl_away_team_stats.ATCorners, epl_away_team_stats.ATFouls, epl_away_team_stats.ATYellowCards, epl_away_team_stats.ATRedCards
            FROM epl_matches
            INNER JOIN epl_home_team_stats ON epl_matches.MatchID = epl_home_team_stats.MatchID 
            INNER JOIN epl_away_team_stats ON epl_matches.MatchID = epl_away_team_stats.MatchID
            INNER JOIN epl_seasons ON epl_matches.SeasonYears = epl_seasons.SeasonYears";

            $orderQuery = "ORDER BY epl_matches.MatchDate DESC";

            if (isset($_GET['onematch'])) {
                // return one match from the users entry, escape htmlentities!
                $capturedID = urldecode($_GET['onematch']);
                $singleMatchID = concealAndRevealIDs(false, $capturedID);
                
                // check id exists in DB before proceeding!
                // prepared statement
                $stmt = $conn->prepare("SELECT MatchID FROM epl_matches WHERE MatchID = ?");
                $stmt -> bind_param("i", $singleMatchID);
                $stmt -> execute();
                $stmt -> store_result();
        
                if ($stmt -> num_rows() > 0) {
                    $stmt -> bind_result($matchID);
                    $stmt -> fetch();

                    // get both clubIDs from the match in question!
                    $clubNamesStmt = $conn->prepare("SELECT epl_home_team_stats.HomeClubName, epl_away_team_stats.AwayClubName 
                        FROM epl_home_team_stats INNER JOIN epl_away_team_stats 
                        ON epl_away_team_stats.MatchID = epl_home_team_stats.MatchID
                        WHERE epl_away_team_stats.MatchID = ? && epl_home_team_stats.MatchID = ? ");
                    $clubNamesStmt -> bind_param("ii", $matchID, $matchID);
                    $clubNamesStmt -> execute();
                    $clubNamesStmt -> store_result();
                    $clubNamesStmt -> bind_result($homeClubName, $awayClubName);
                    $clubNamesStmt -> fetch();

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
                $providedSeasonYears = htmlentities(trim($_GET['fullseason']));
                if (checkSeasonRegex($providedSeasonYears)) {
                    $seasonStmt = $conn->prepare("SELECT SeasonYears FROM epl_seasons WHERE SeasonYears LIKE ? ");
                    if (is_numeric($providedSeasonYears)) {
                        $seasonStmt->bind_param("i", $providedSeasonYears);
                    } else {
                        $seasonStmt->bind_param("s", $providedSeasonYears);
                    }
                    if ($seasonStmt->execute()) {
                        $seasonStmt->execute();
                        $seasonStmt->store_result();
                    }
        
                    // only proceed if the season exists in the database
                    if (($seasonStmt->num_rows() < 1) || ($seasonStmt->num_rows() > 1)) {
                        http_response_code(400);
                        $errorMessage = "Season doesnt exist or is ambiguous, please try again using the format YYYY-YYYY.";
                        apiReply($errorMessage);
                        die();
                    } else {
                        $seasonStmt->bind_result($seasonYears);
                        $seasonStmt->fetch();
                        $conditionQuery = "WHERE epl_seasons.SeasonYears = '{$seasonYears}'";
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
                if (strpos($fixtureValue, "~") !== false) {
                    $newFixtureValue = removeUnderScores($fixtureValue);
                    $fixtureValueArray = explode("~", $newFixtureValue);
                    $homeTeamNameSearch = trim($fixtureValueArray[0]);
                    $awayTeamNameSearch = trim($fixtureValueArray[1]);

                    if (($homeTeamNameSearch != null) && (strlen($homeTeamNameSearch) > 0) 
                        && ($awayTeamNameSearch != null) && (strlen($awayTeamNameSearch) > 0)) {
                        $homeStmt = $conn->prepare("SELECT * FROM `epl_home_team_stats` WHERE HomeClubName = ? ;");
                        $homeStmt->bind_param("s", $homeTeamNameSearch);
                        $homeStmt->execute();
                        $homeStmt->store_result();

                        $awayStmt = $conn->prepare("SELECT * FROM `epl_away_team_stats` WHERE AwayClubName = ? ");
                        $awayStmt->bind_param("s", $awayTeamNameSearch);
                        $awayStmt->execute();
                        $awayStmt->store_result();
                        
                        if ($homeStmt->num_rows > 0 && $awayStmt->num_rows > 0) {
                            if (isset($_GET['strict'])) {
                                $teamQuery = "WHERE epl_home_team_stats.HomeClubName = '{$homeTeamNameSearch}' AND epl_away_team_stats.AwayClubName = '{$awayTeamNameSearch}'";
                            } else {
                                $teamQuery = "WHERE ((epl_home_team_stats.HomeClubName = '{$homeTeamNameSearch}' AND epl_away_team_stats.AwayClubName = '{$awayTeamNameSearch}')
                                OR (epl_home_team_stats.HomeClubName = '{$awayTeamNameSearch}' AND epl_away_team_stats.AwayClubName = '{$homeTeamNameSearch}'))";
                            }
                            
                            if (isset($_GET['count'])) {
                                $limitQuery = queryPagination();
                            } else {
                                $limitQuery = "";
                            }

                            if (isset($_GET['pre_date'])) {
                                $precedingDate = htmlentities(trim($_GET['pre_date']));
                                $precedingDateQuery = "AND epl_matches.MatchDate < '{$precedingDate}' ";
                                $finalQuery = "{$mainMatchQuery} {$teamQuery} {$precedingDateQuery} {$orderQuery} {$limitQuery}";
                            } else {
                                $finalQuery = "{$mainMatchQuery} {$teamQuery} {$orderQuery} {$limitQuery}";
                            }
                        } else {
                            http_response_code(404);
                            $errorMessage = "One of those clubs cannot be identified, please reenter and try again.";
                            apiReply($errorMessage);
                            die();
                        }
                    }
                } else {
                    http_response_code(400);
                    $errorMessage = "Please enter two club names seperated by a tilde '~' ";
                    apiReply($errorMessage);
                    die();
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
                $homeClubName = $row["HomeClubName"];
                $awayClubName = $row["AwayClubName"];

                // get home club LOGO url
                $stmt = $conn->prepare("SELECT epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubName = ? ");
                $stmt -> bind_param("s", $homeClubName);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($homeClubURL);
                $stmt -> fetch();

                // get away club LOGO url
                $awayLogostmt = $conn->prepare("SELECT epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubName = ? ");
                $awayLogostmt -> bind_param("s", $awayClubName);
                $awayLogostmt -> execute();
                $awayLogostmt -> store_result();
                $awayLogostmt -> bind_result($awayClubURL);
                $awayLogostmt -> fetch();
                
                $singlematch = array(
                    "match_date" => $row["MatchDate"],
                    "kick_off_time" => $row["KickOffTime"],
                    "referee_name" => $row["RefereeName"],
                    "home_team" => $row['HomeClubName'],
                    "away_team" => $row['AwayClubName'],
                    "home_team_logo_URL" => $homeClubURL,
                    "away_team_logo_URL" => $awayClubURL,
                    "home_team_total_goals" => $row["HTTotalGoals"],
                    "home_team_half_time_goals" => $row["HTHalfTimeGoals"],
                    "home_team_shots" => $row["HTShots"],
                    "home_team_shots_on_target" => $row["HTShotsOnTarget"],
                    "home_team_corners" => $row["HTCorners"],
                    "home_team_fouls" => $row["HTFouls"],
                    "home_team_yellow_cards" => $row["HTYellowCards"],
                    "home_team_red_cards" => $row["HTRedCards"],
                    "away_team_total_goals" => $row["ATTotalGoals"],
                    "away_team_half_time_goals" => $row["ATHalfTimeGoals"],
                    "away_team_shots" => $row["ATShots"],
                    "away_team_shots_on_target" => $row["ATShotsOnTarget"],
                    "away_team_corners" => $row["ATCorners"],
                    "away_team_fouls" => $row["ATFouls"],
                    "away_team_yellow_cards" => $row["ATYellowCards"],
                    "away_team_red_cards" => $row["ATRedCards"]
                );
                $finalDataSet[] = $singlematch;
            }

            // encode the final data set to JSON
            echo json_encode($finalDataSet);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // otherwise someone is pushing data to the API;
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

                        // check referee, clubs and season exists in the DB first before proceeding
                        require("part_check_ref_clubs.php");

                        // check season exists in DB
                        $seasonStmt = $conn->prepare("SELECT SeasonYears FROM epl_seasons WHERE SeasonYears = ? ");
                        $seasonStmt -> bind_param("s", $finalSeasonName);
                        if ($seasonStmt -> execute()) {
                            $seasonStmt -> store_result();
                            if ($seasonStmt -> num_rows == 0) {
                                http_response_code(404);
                                $replyMessage = "There was a problem with the Season Entered, please review and try again.  If the season doesnt exist, please add to the database first";
                                apiReply($replyMessage);
                                die();
                            }
                        }

                        $matchStatement = $conn->prepare("INSERT INTO `epl_matches` (`MatchID`, `SeasonYears`, `MatchDate`, `KickOffTime`, `RefereeName`, `AddedByUserID`) VALUES (NULL, ?, ?, ?, ?, ?);");
                        $matchStatement -> bind_param("sssss",
                                                $finalSeasonName,
                                                $finalMatchDate,
                                                $finalKickOffTime,
                                                $finalRefereeName,
                                                $userID);
                        if (!$matchStatement -> execute()) {
                            http_response_code(500);
                            $replyMessage = "There was a problem with entering Match data, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }

                        // get the match id for both the home and away team stat tables
                        $lastEnteredMatchID = (int) $conn->insert_id;
        
                        $homeDataEntryStmt = $conn->prepare("INSERT INTO `epl_home_team_stats` (`HomeTeamStatID`, `HomeClubName`, `MatchID`, `HTTotalGoals`, `HTHalfTimeGoals`, `HTShots`, `HTShotsOnTarget`, `HTCorners`, `HTFouls`, `HTYellowCards`, `HTRedCards`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
                        $homeDataEntryStmt -> bind_param("siiiiiiiii",
                                    $homeClubName,
                                    $lastEnteredMatchID,
                                    $finalHomeTeamTotalGoals,
                                    $finalHomeTeamHalfTimeGoals,
                                    $finalHomeTeamShots,
                                    $finalHomeTeamShotsOnTarget,
                                    $finalHomeTeamCorners,
                                    $finalHomeTeamFouls,
                                    $finalHomeTeamYellowCards,
                                    $finalHomeTeamRedCards);
                        if (!$homeDataEntryStmt -> execute()) {
                            http_response_code(500);
                            $replyMessage = "There was a problem with entering home team data, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }
        
                        $awayDataEntryStmt = $conn->prepare("INSERT INTO `epl_away_team_stats` (`AwayTeamStatID`, `AwayClubName`, `MatchID`, `ATTotalGoals`, `ATHalfTimeGoals`, `ATShots`, `ATShotsOnTarget`, `ATCorners`, `ATFouls`, `ATYellowCards`, `ATRedCards`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
                        $awayDataEntryStmt -> bind_param("siiiiiiiii",
                                    $awayClubName,
                                    $lastEnteredMatchID,
                                    $finalAwayTeamTotalGoals,
                                    $finalAwayTeamHalfTimeGoals,
                                    $finalAwayTeamShots,
                                    $finalAwayTeamShotsOnTarget,
                                    $finalAwayTeamCorners,
                                    $finalAwayTeamFouls,
                                    $finalAwayTeamYellowCards,
                                    $finalAwayTeamRedCards);
                        if (!$awayDataEntryStmt -> execute()) {
                            http_response_code(500);
                            $replyMessage = "There was a problem with entering away team data, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }
                        http_response_code(201);
                        $replyMessage = "Match was successfully added, thank you for your contribution";
                        apiReply($replyMessage);
                } else {
                    // accrue all the error messages and send them back here
                    http_response_code(400);
                    $replyMessage = "There was an issue with the data submitted - ";
                    $replyMessage .= $resultString;
                    apiReply($replyMessage);
                    die();
                }
            } elseif (isset($_GET['editmatch'])) {
                require("part_page_get_full_match.php");

                // if all flags are true, fairly sure data isnt poor quality, so enter new match details;
                if ($matchDateInThePast && $notTheSameTeams && $shotsAreGreaterThanShotsOT && $halfTimeGoalsLessThanFullTime 
                    && $shotsOTisntLessThanGoals  && $foulsLessThanTotalCards) {
                        $capturedID = urldecode($_POST['id']);
                        $editedMatchID = concealAndRevealIDs(false, $capturedID);
                        
                        $justificationForChange = htmlentities(trim($_POST['change_justification']));

                        // check the match exists first, just in case!
                        $stmt = $conn->prepare("SELECT MatchID FROM epl_matches WHERE MatchID = ? ;");
                        $stmt -> bind_param("i", $editedMatchID);
                        $stmt -> execute();
                        $stmt -> store_result();
                        if ($stmt->num_rows == 0) {
                            http_response_code(500);
                            $replyMessage = "That Match doesnt exist, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }

                        // now check referee, clubs and season exists in the DB first before proceeding
                        require("part_check_ref_clubs.php");
                        
                        $editMatchStatement = $conn->prepare("UPDATE `epl_matches` SET `MatchDate` = ? , `KickOffTime` = ? , `RefereeName` = ?, `AddedByUserID` = ? WHERE `epl_matches`.`MatchID` = ? ;");
                        $editMatchStatement -> bind_param("ssssi",
                                $finalMatchDate,
                                $finalKickOffTime,
                                $finalRefereeName,
                                $userID,
                                $editedMatchID
                            );
                        if (!$editMatchStatement -> execute()) {
                            http_response_code(500);
                            $replyMessage = "There was a problem with updating Match data, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }

                        $editHomeMatchStmt = $conn->prepare("UPDATE `epl_home_team_stats` SET `HomeClubName` = ? , `HTTotalGoals` = ? , `HTHalfTimeGoals` = ? , `HTShots` = ? , `HTShotsOnTarget` = ? , `HTCorners` = ? , `HTFouls` = ? , `HTYellowCards` = ? , `HTRedCards` = ? WHERE `epl_home_team_stats`.`MatchID` = ? ;");
                        $editHomeMatchStmt -> bind_param("siiiiiiiii",
                                $finalHomeClubName,
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
                        if (!$editHomeMatchStmt -> execute()) {
                            http_response_code(500);
                            $replyMessage = "There was a problem with updating home team data, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }

                        $editAwayMatchStmt = $conn->prepare("UPDATE `epl_away_team_stats` SET `AwayClubName` = ? , `ATTotalGoals` = ? , `ATHalfTimeGoals` = ? , `ATShots` = ? , `ATShotsOnTarget` = ? , `ATCorners` = ? , `ATFouls` = ? , `ATYellowCards` = ? , `ATRedCards` = ? WHERE `epl_away_team_stats`.`MatchID` = ? ;");
                        $editAwayMatchStmt -> bind_param("siiiiiiiii",
                                $finalAwayClubName,
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
                        if (!$editAwayMatchStmt -> execute()) {
                            http_response_code(500);
                            $replyMessage = "There was a problem with updating away team data, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }

                        $currentDateTime = date("Y-m-d H:i:s");
                        $recordMatchEditsStmt = $conn->prepare("INSERT INTO `epl_match_edits` (`EditID`, `MatchID`, `EditedByUserID`, `EditDescription`, `EditedDate`) VALUES (NULL, ?, ?, ?, ? );");
                        $recordMatchEditsStmt -> bind_param("isss",
                                $editedMatchID,
                                $userID,
                                $justificationForChange,
                                $currentDateTime
                            );
                        if (!$recordMatchEditsStmt -> execute()) {
                            http_response_code(500);
                            $replyMessage = "There was a problem with entering data, please review and try again";
                            apiReply($replyMessage);
                            die();
                        }
                        http_response_code(201);
                        $replyMessage = "Match records updated successfully, thank you for keeping our data accurate";
                        apiReply($replyMessage);
                    } else {
                        // accrue all the error messages and reply
                        http_response_code(400);
                        $replyMessage = "There was an issue with the data submitted - ";
                        $replyMessage .= $resultString;
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