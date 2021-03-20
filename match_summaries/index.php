<?php
    header('Content-Type: application/json');
    // api defines a seperate functions file to mimic a true seperate server!
    require("../apifunctions.php");
    require("../dbconn.php");
    if (checkAPIKey()) {
        $finalDataSet = array();
        $seasonID = null;

        // control var to see if there needs to be any data added for prepared statements
        $filterQueryCount = 0;

        // CANT PUT THIS ONTO MULTI LINES AS IT INSERTS A NEWLINE CHAR AND BREAKS THE QUERY \n
        $mainQuery = "SELECT epl_matches.MatchID, epl_matches.MatchDate, epl_home_team_stats.HomeClubName, epl_home_team_stats.HTTotalGoals, epl_away_team_stats.ATTotalGoals, epl_away_team_stats.AwayClubName FROM epl_matches INNER JOIN epl_home_team_stats ON epl_matches.MatchID = epl_home_team_stats.MatchID INNER JOIN epl_away_team_stats ON epl_matches.MatchID = epl_away_team_stats.MatchID INNER JOIN epl_seasons ON epl_matches.SeasonYears = epl_seasons.SeasonYears";

        $orderByQuery = "ORDER BY epl_matches.MatchID DESC";
        $matchSummaryQuery = "{$mainQuery} {$orderByQuery}";

        // string to add on all dynamic conditional queries to any request
        $conditionalQueries = "";

        // prepared statement variables to store datatypes and data depending on the query
        $preparedStatementTypes = "";
        $preparedStatementDataArray = array();

        if (isset($_GET['usersearch'])) {
            // wildcard search for main search bar!
            $userSearchStmt = $conn->prepare("SELECT ClubName FROM epl_clubs WHERE ClubName LIKE ? ");
            $userEntry = addUnderScores(htmlentities(trim($_GET['usersearch'])));
            $userSearchStmt -> bind_param("s", $userEntry);
            $userSearchStmt -> execute();
            $userSearchStmt -> store_result();
            $userSearchStmt -> bind_result($clubName);
            $userSearchStmt -> fetch();

            // only proceed if the club exists in the database
            if ($userSearchStmt->num_rows > 1) {
                http_response_code(400);
                $errorMessage = "That club cannot be identified, please enter a new club and try again";
                apiReply($errorMessage);
                die();
            } elseif ($userSearchStmt->num_rows == 1) {
                // valid club - setup the whole query and finalise the SQL query structure
                $filterQueryCount++;
                $conditionalQueries .= "WHERE (HomeClubName = ? OR AwayClubName = ? )";
                $preparedStatementTypes = "ss";
                $preparedStatementDataArray = array($clubName, $clubName);
            } else {
                http_response_code(400);
                $errorMessage = "That club cannot be identified, please enter a new club and try again";
                apiReply($errorMessage);
                die();
            }
        } elseif (isset($_GET['filter'])) {
            // post the club and set the select to be the posted club
            // if an opposition team is set, but no home club, search as a club anyway and disregard the fixture
            if (isset($_GET['club']) && !isset($_GET['opposition_team'])
                || (isset($_GET['opposition_team']) && !isset($_GET['club']))) {
                
                // vary the club search by the provided parameter
                if ((isset($_GET['opposition_team']) && !isset($_GET['club']))) {
                    $clubFilter = htmlentities(trim($_GET['opposition_team']));
                } elseif (isset($_GET['club']) && !isset($_GET['opposition_team'])) {
                    $clubFilter = htmlentities(trim($_GET['club']));
                }

                // query club exists first
                $stmt = $conn->prepare("SELECT ClubName FROM epl_clubs WHERE ClubName = ? ;");
                $stmt -> bind_param("s", $clubFilter);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($clubName);
                $stmt -> fetch();

                // concatentate the SQL query appropriately
                if ($stmt->num_rows == 1) {
                    $filterQueryCount++;
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} (HomeClubName = ? OR AwayClubName = ?) ";
                    $preparedStatementTypes .= "ss";
                    $preparedStatementDataArray[] = $clubName;
                    $preparedStatementDataArray[] = $clubName;
                } else {
                    http_response_code(400);
                    $errorMessage = "That club cannot be identified or is ambiguous, please enter a new club and try again";
                    apiReply($errorMessage);
                    die();
                }
            } elseif (isset($_GET['opposition_team']) && isset($_GET['club'])) {
                // need to find a fixture, so two clubs needed
                $filterQueryCount++;
                $clubFilter = removeUnderScores(htmlentities(trim($_GET['club'])));
                $oppositionClubFilter = removeUnderScores(htmlentities(trim($_GET['opposition_team'])));

                // query club exists
                $stmt = $conn->prepare("SELECT ClubName FROM epl_clubs WHERE ClubName = ? ;");
                $stmt -> bind_param("s", $clubFilter);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($homeClubName);
                $stmt -> fetch();

                // query opposition club exists
                $oppositionStmt = $conn->prepare("SELECT ClubName FROM epl_clubs WHERE ClubName = ? ;");
                $oppositionStmt -> bind_param("s", $oppositionClubFilter);
                $oppositionStmt -> execute();
                $oppositionStmt -> store_result();
                $oppositionStmt -> bind_result($oppositionClubName);
                $oppositionStmt -> fetch();

                // concatentate the SQL query appropriately
                if ($stmt->num_rows == 1 && $oppositionStmt->num_rows == 1) {
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} ((HomeClubName = ? AND AwayClubName = ?) OR (HomeClubName = ? AND AwayClubName = ?))";
                    $preparedStatementTypes .= "ssss";
                    $preparedStatementDataArray[] = $homeClubName;
                    $preparedStatementDataArray[] = $oppositionClubName;
                    $preparedStatementDataArray[] = $oppositionClubName;
                    $preparedStatementDataArray[] = $homeClubName;
                } else {
                    http_response_code(400);
                    $errorMessage = "One of those clubs cannot be identified or is ambiguous, please try again";
                    apiReply($errorMessage);
                    die();
                }
            }

            // season filter
            if (isset($_GET['season'])) {
                $filterQueryCount++;
                $season = htmlentities(trim($_GET['season']));

                $stmt = $conn->prepare("SELECT SeasonID FROM epl_seasons WHERE SeasonYears = ? ;");
                $stmt -> bind_param("s", $season);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($seasonID);
                $stmt -> fetch();

                if ($stmt->num_rows > 0) {
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} SeasonID = ? ";

                    $preparedStatementTypes .= "i";
                    $preparedStatementDataArray[] = $seasonID;
                } else {
                    http_response_code(400);
                    $errorMessage = "That season cannot be identified or is ambiguous, please enter a new season and try again";
                    apiReply($errorMessage);
                    die();
                }
            }

            if (isset($_GET['htresult']) && (isset($_GET['atresult']))) {
                $filterQueryCount++;
                $htResult = (int) htmlentities(trim($_GET['htresult']));
                $atResult = (int) htmlentities(trim($_GET['atresult']));

                if (is_numeric($htResult) && is_numeric($atResult) && $htResult >= 0 && $atResult >= 0) {
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} (HTTotalGoals = ? AND ATTotalGoals = ? )";

                    $preparedStatementTypes .= "ii";
                    $preparedStatementDataArray[] = $htResult;
                    $preparedStatementDataArray[] = $atResult;
                } else {
                    http_response_code(400);
                    $errorMessage = "Those club results are not in the correct format, please try again";
                    apiReply($errorMessage);
                    die();
                }
            } elseif (isset($_GET['htresult']) || isset($_GET['atresult'])) {
                // one or the other parameter is set
                $filterQueryCount++;
                $result = 0;
                if (isset($_GET['htresult'])) {
                    $result = (int) htmlentities(trim($_GET['htresult']));
                    if (is_numeric($result) && $result >= 0) {
                        $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                        $conditionalQueries .= "{$joinAdverb} (HTTotalGoals = ? )";
                    } else {
                        http_response_code(400);
                        $errorMessage = "That result is not in the correct format, please try again";
                        apiReply($errorMessage);
                        die();
                    }
                } else {
                    $result = (int) htmlentities(trim($_GET['atresult']));
                    if (is_numeric($result) && $result >= 0) {
                        $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                        $conditionalQueries .= "{$joinAdverb} (ATTotalGoals = ? )";
                    } else {
                        http_response_code(400);
                        $errorMessage = "That result is not in the correct format, please try again";
                        apiReply($errorMessage);
                        die();
                    }
                }

                $preparedStatementTypes .= "i";
                $preparedStatementDataArray[] = $result;
            }
    
            if (isset($_GET['margin'])) {
                $filterQueryCount++;
                if (isset($_GET['htresult']) && (isset($_GET['atresult']))) {
                    http_response_code(400);
                    $errorMessage = "Please remove either the Home Team Result or the Away Team result to search by Margin";
                    apiReply($errorMessage);
                    die();
                } else {
                    $margin = (int) htmlentities(trim($_GET['margin']));
                    if ($margin >= 0 && $margin <= 12) {
                        $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                        $conditionalQueries .= "{$joinAdverb} (GREATEST(HTTotalGoals, ATTotalGoals) - LEAST(HTTotalGoals, ATTotalGoals) = ? )";
    
                        $preparedStatementTypes .= "i";
                        $preparedStatementDataArray[] = $margin;
                    } else {
                        http_response_code(400);
                        $errorMessage = "Please enter a lower (positive) goal difference and try again";
                        apiReply($errorMessage);
                        die();
                    }
                }
            }
    
            if (isset($_GET['month'])) {
                $filterQueryCount++;
                $month = (int) htmlentities(trim($_GET['month']));
                if ($month >= 01 && $month <= 12) {
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} EXTRACT(MONTH FROM MatchDate) = ? ";
                    $preparedStatementTypes .= "i";
                    $preparedStatementDataArray[] = $month;
                } else {
                    http_response_code(400);
                    $errorMessage = "Please enter a month number between 01 and 12 and try again";
                    apiReply($errorMessage);
                    die();
                }
            }

            if (isset($_GET['referee'])) {
                $filterQueryCount++;
                $refereeName = removeUnderScores(htmlentities(trim($_GET['referee'])));
                
                // query referee exists
                $stmt = $conn->prepare("SELECT RefereeID FROM epl_referees WHERE RefereeName = ? ;");
                $stmt -> bind_param("s", $refereeName);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($refID);
                $stmt -> fetch();

                // if ref exists, concatentate the SQL query appropriately
                if ($stmt->num_rows == 1) {
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} RefereeID = ? ";
                    $preparedStatementTypes .= "i";
                    $preparedStatementDataArray[] = $refID;
                } else {
                    http_response_code(400);
                    $errorMessage = "Unknown Referee, please try again";
                    apiReply($errorMessage);
                    die();
                }
            }
        } elseif (isset($_GET['season'])) {
            // user wants a full seasons summary on its own (not a filtered query)
            $seasonYear = htmlentities(trim($_GET["season"]));
            
            // only proceed with the query if the input matches regex constraints
            if (checkSeasonRegex($seasonYear)) {
                $seasonStmt = $conn->prepare("SELECT SeasonID FROM epl_seasons WHERE SeasonYears LIKE ? ;");
                $seasonStmt -> bind_param("s", $seasonYear);
                $seasonStmt -> execute();
                $seasonStmt -> store_result();
                
                // and then check the season exists at all!
                if (($seasonStmt->num_rows < 1) || ($seasonStmt->num_rows > 1)) {
                    http_response_code(404);
                    $replyMessage = "Ambiguous Season, please reenter season years";
                    apiReply($replyMessage);
                    die();
                } else {
                    $filterQueryCount++;
                    $seasonStmt->bind_result($seasonID);
                    $seasonStmt->fetch();
                    $conditionalQueries .= "WHERE SeasonID = ? ";
                    $preparedStatementTypes = "i";
                    $preparedStatementDataArray = array($seasonID);
                }
            } else {
                http_response_code(400);
                $errorMessage = "Requested season format is unrecognised, please try again using the format YYYY-YYYY.";
                apiReply($errorMessage);
                die();
            }
        }

        // run the full query now and then build the JSON response
        $matchSummaryQuery = "{$mainQuery} {$conditionalQueries} {$orderByQuery}";
        if (isset($_GET['count'])) {
            $limitQuery = queryPagination();
            $matchSummaryQuery .= " {$limitQuery}";
        }
        $stmt = $conn->prepare($matchSummaryQuery);
        if ($filterQueryCount > 0) {
            // only load in the accrued data types and data array queries above if required for any given query
            $stmt -> bind_param($preparedStatementTypes, ...$preparedStatementDataArray);
        }
        $stmt -> execute();
        $stmt -> bind_result($matchID, $matchDate, $homeClubName, $htGoals, $atGoals, $awayClubName);
        $stmt -> store_result();

        while($stmt->fetch()) {
            // get home club LOGO url
            $homeURLstmt = $conn->prepare("SELECT epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubName = ? ");
            $homeURLstmt -> bind_param("s", $homeClubName);
            $homeURLstmt -> execute();
            $homeURLstmt -> store_result();
            $homeURLstmt -> bind_result($homeClubURL);
            $homeURLstmt -> fetch();

            // get away club LOGO url
            $awayURLstmt = $conn->prepare("SELECT epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubName = ? ");
            $awayURLstmt -> bind_param("s", $awayClubName);
            $awayURLstmt -> execute();
            $awayURLstmt -> store_result();
            $awayURLstmt -> bind_result($awayClubURL);
            $awayURLstmt -> fetch();

            $matches = array(
                "id" => $matchID,
                "matchdate" => $matchDate,
                "hometeam" => $homeClubName,
                "homescore" => $htGoals,
                "awayscore" => $atGoals,
                "awayteam" => $awayClubName,
                "hometeamlogoURL" => $homeClubURL,
                "awayteamlogoURL" => $awayClubURL
            );
            $finalDataSet[] = $matches;
        }

    // encode the final data set to JSON
    echo json_encode($finalDataSet);
    }
?>