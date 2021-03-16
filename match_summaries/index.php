<?php
    header('Content-Type: application/json');
    // api defines a seperate functions file to mimic a true seperate server!
    require("../apifunctions.php");
    require("../dbconn.php");
    if (checkAPIKey()) {
        $finalDataSet = array();
        $seasonID = null;
        $filterQueryCount = 0;

        // CANT PUT THIS ONTO MULTI LINES AS IT INSERTS A NEWLINE CHAR AND BREAKS THE QUERY \n
        $mainQuery = "SELECT epl_matches.MatchID, epl_matches.MatchDate, epl_home_team_stats.HomeClubID, epl_home_team_stats.HTTotalGoals, epl_away_team_stats.ATTotalGoals, epl_away_team_stats.AwayClubID FROM epl_matches INNER JOIN epl_home_team_stats ON epl_matches.MatchID = epl_home_team_stats.MatchID INNER JOIN epl_away_team_stats ON epl_matches.MatchID = epl_away_team_stats.MatchID";

        $orderByQuery = "ORDER BY epl_matches.MatchID DESC";
        $matchSummaryQuery = "{$mainQuery} {$orderByQuery}";

        // string to add on all dynamic conditional queries to any request
        $conditionalQueries = "";

        // prepared statement variables to store datatypes and data depending on the query
        $preparedStatementTypes = "";
        $preparedStatementDataArray = array();

        if (isset($_GET['season'])) {
            // user wants a full seasons summary
            $seasonYear = htmlentities(trim($_GET["season"]));
            
            // only proceed with the query if the input matches regex constraints
            if (checkSeasonRegex($seasonYear)) {
                $seasonStmt = $conn->prepare("SELECT SeasonID FROM epl_seasons WHERE SeasonYears LIKE ? ;");
                $seasonStmt -> bind_param("s", $seasonYear);
                $seasonStmt -> execute();
                $seasonStmt -> store_result();

                if (($seasonStmt->num_rows() < 1) || ($seasonStmt->num_rows() > 1)) {
                    http_response_code(404);
                    $replyMessage = "Ambiguous Season, please reenter season years";
                    apiReply($replyMessage);
                    die();
                } else {
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
        } elseif (isset($_GET['usersearch'])) {
            // wildcard search for main search bar!
            $userSearchStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName LIKE ? ");
            $userEntry = addUnderScores(htmlentities(trim($_GET['usersearch'])));
            $userSearchStmt -> bind_param("s", $userEntry);
            $userSearchStmt -> execute();
            $userSearchStmt -> store_result();
            $userSearchStmt -> bind_result($clubID);
            $userSearchStmt -> fetch();

            // only proceed if the club exists in the database
            if ($userSearchStmt->num_rows > 1) {
                http_response_code(400);
                $errorMessage = "That club cannot be identified, please enter a new club and try again";
                apiReply($errorMessage);
                die();
            } elseif ($userSearchStmt->num_rows == 1) {
                // valid club - setup the whole query and finalise the SQL query structure
                $conditionalQueries .= "WHERE (HomeClubID = ? OR AwayClubID = ? )";
                $preparedStatementTypes = "ii";
                $preparedStatementDataArray = array($clubID,$clubID);
            } else {
                http_response_code(400);
                $errorMessage = "That club cannot be identified, please enter a new club and try again";
                apiReply($errorMessage);
                die();
            }
        } elseif (isset($_GET['filter'])) {
            // all the options for the filter panel on match search page
            $filterQueryCount = 0;

            // post the club and set the select to be the posted club
            // if an opposition team is set, but no home club, search as a club anyway and disregard the fixture
            if (isset($_GET['club']) && !isset($_GET['opposition_team'])
                || (isset($_GET['opposition_team']) && !isset($_GET['club']))) {
                $filterQueryCount++;

                // vary the club search by the provided parameter
                if ((isset($_GET['opposition_team']) && !isset($_GET['club']))) {
                    $clubFilter = htmlentities(trim($_GET['opposition_team']));
                } elseif (isset($_GET['club']) && !isset($_GET['opposition_team'])) {
                    $clubFilter = htmlentities(trim($_GET['club']));
                }

                // query club exists first
                $stmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ;");
                $stmt -> bind_param("s", $clubFilter);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($clubID);
                $stmt -> fetch();

                // concatentate the SQL query appropriately
                if ($stmt->num_rows == 1) {
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} (HomeClubID = ? OR AwayClubID = ?) ";
                    
                    $preparedStatementTypes .= "ii";
                    $preparedStatementDataArray[] = $clubID;
                    $preparedStatementDataArray[] = $clubID;
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
                $stmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ;");
                $stmt -> bind_param("s", $clubFilter);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($homeClubID);
                $stmt -> fetch();

                // query opposition club exists
                $oppositionStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ;");
                $oppositionStmt -> bind_param("s", $oppositionClubFilter);
                $oppositionStmt -> execute();
                $oppositionStmt -> store_result();
                $oppositionStmt -> bind_result($oppositionClubID);
                $oppositionStmt -> fetch();

                // concatentate the SQL query appropriately
                if ($stmt->num_rows == 1 && $oppositionStmt->num_rows == 1) {
                    $joinAdverb = provideSQLQueryJoinAdverb($conditionalQueries);
                    $conditionalQueries .= "{$joinAdverb} ((HomeClubID = ? AND AwayClubID = ?) OR (HomeClubID = ? AND AwayClubID = ?))";
                    $preparedStatementTypes .= "iiii";
                    $preparedStatementDataArray[] = $homeClubID;
                    $preparedStatementDataArray[] = $oppositionClubID;
                    $preparedStatementDataArray[] = $oppositionClubID;
                    $preparedStatementDataArray[] = $homeClubID;
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
                $refereeName = htmlentities(trim($_GET['referee']));

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
        }

        // run the full query now and then build the JSON response
        $matchSummaryQuery = "{$mainQuery} {$conditionalQueries} {$orderByQuery}";
        if (isset($_GET['count'])) {
            $limitQuery = queryPagination();
            $matchSummaryQuery .= " {$limitQuery}";
        }
        $stmt = $conn->prepare($matchSummaryQuery);
        // load in the accrued data types and data array queries above
        $stmt -> bind_param($preparedStatementTypes, ...$preparedStatementDataArray);
        $stmt -> execute();
        $stmt -> bind_result($matchID, $matchDate, $homeClubId, $htGoals, $atGoals, $awayClubId);
        $stmt -> store_result();

        while ($stmt->fetch()) {
            $homeClubNameQuery = "SELECT epl_clubs.ClubName, epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubID = {$homeClubId}";
            $awayClubNameQuery = "SELECT epl_clubs.ClubName, epl_clubs.ClubLogoURL FROM `epl_clubs` WHERE ClubID = {$awayClubId}";

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

            $matches = array(
                "id" => $matchID,
                "matchdate" => $matchDate,
                "hometeam" => $homeTeamName,
                "homescore" => $htGoals,
                "awayscore" => $atGoals,
                "awayteam" => $awayTeamName,
                "hometeamlogoURL" => $homeTeamURL,
                "awayteamlogoURL" => $awayTeamURL
            );
            $finalDataSet[] = $matches;
        }

    // encode the final data set to JSON
    echo json_encode($finalDataSet);
    }
?>