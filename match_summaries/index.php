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

        if (isset($_GET['season'])) {
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
                    $seasonQuery = "WHERE SeasonID = {$seasonID}";
                }
            } else {
                http_response_code(400);
                $errorMessage = "Requested season format is unrecognised, please try again using the format YYYY-YYYY.";
                apiReply($errorMessage);
                die();
            }
            $matchSummaryQuery = "{$mainQuery} {$seasonQuery} {$orderByQuery}";
        } elseif (isset($_GET['usersearch'])) {
            // wildcard search for main search bar!
            $userSearchStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName LIKE ? ");
            $userEntry = addUnderScores(htmlentities(trim($_GET['usersearch'])));
            $userSearchStmt -> bind_param("s", $userEntry);
            $userSearchStmt -> execute();
            $userSearchStmt -> store_result();
            
            // only proceed if the club exists in the database
            if ($userSearchStmt->num_rows > 1) {
                http_response_code(400);
                $errorMessage = "That club cannot be identified, please enter a new club and try again";
                apiReply($errorMessage);
                die();
            } elseif ($userSearchStmt->num_rows > 0) {
                $userSearchStmt -> bind_result($usersSearchedClubID);
                $userSearchStmt -> fetch();
            } else {
                http_response_code(400);
                $errorMessage = "That club cannot be identified, please enter a new club and try again";
                apiReply($errorMessage);
                die();
            }
        } elseif (isset($_GET['filter'])) {
            // all the options for the filter panel
            // count the total number of queries
            $filterQueryCount = 0;
            $allFilterQueries = "";

            $preparedStatementTypes = "";
            $preparedStatementDataArray = array();

            // post the club and set the select to be the posted club
            if (isset($_GET['club'])) {
                $filterQueryCount++;
                $clubFilter = htmlentities(trim($_GET['club']));
                $allFilterQueries = "";

                // query club exists
                $stmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ;");
                $stmt -> bind_param("s", $clubFilter);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($clubID);
                $stmt -> fetch();

                // concatentate the SQL query appropriately
                if ($stmt->num_rows == 1) {
                    $joinAdverb = provideSQLQueryJoinAdverb($allFilterQueries);
                    $allFilterQueries .= "{$joinAdverb} (HomeClubID = ? OR AwayClubID = ?) ";
                    
                    $preparedStatementTypes .= "ii";
                    $preparedStatementDataArray[] = $clubID;
                    $preparedStatementDataArray[] = $clubID;
                    // add ClubID to the statement
                    // add on the datatype to the query array
                } else {
                    http_response_code(400);
                    $errorMessage = "That club cannot be identified or is ambiguous, please enter a new club and try again";
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
                    $joinAdverb = provideSQLQueryJoinAdverb($allFilterQueries);
                    $allFilterQueries .= "{$joinAdverb} SeasonID = ? ";

                    $preparedStatementTypes .= "i";
                    $preparedStatementDataArray[] = $seasonID;
                } else {
                    http_response_code(400);
                    $errorMessage = "That season cannot be identified or is ambiguous, please enter a new season and try again";
                    apiReply($errorMessage);
                    die();
                }
            }
            
            // if (isset($_GET['fixture']) && !isset($_GET['club'])) {
                
            // } elseif (isset($_GET['club']) && isset($_GET['fixture'])) {

            // }

            if (isset($_GET['htresult']) && (isset($_GET['atresult']))) {
                $filterQueryCount++;
                $htResult = (int) htmlentities(trim($_GET['htresult']));
                $atResult = (int) htmlentities(trim($_GET['atresult']));

                if (is_numeric($htResult) && is_numeric($atResult) && $htResult >= 0 && $atResult >= 0) {
                    $joinAdverb = provideSQLQueryJoinAdverb($allFilterQueries);
                    $allFilterQueries .= "{$joinAdverb} (HTTotalGoals = ? AND ATTotalGoals = ? )";

                    $preparedStatementTypes .= "ii";
                    $preparedStatementDataArray[] = $htResult;
                    $preparedStatementDataArray[] = $atResult;
                } else {
                    http_response_code(400);
                    $errorMessage = "Those club results are not in the correct format, please try again";
                    apiReply($errorMessage);
                    die();
                }
            }
    
            if (isset($_GET['margin'])) {
                $filterQueryCount++;
                $margin = (int) htmlentities(trim($_GET['margin']));
                if ($margin > 0 && $margin <= 12) {
                    $joinAdverb = provideSQLQueryJoinAdverb($allFilterQueries);
                    $allFilterQueries .= "{$joinAdverb} GREATEST(HTTotalGoals, ATTotalGoals) - LEAST(HTTotalGoals, ATTotalGoals) = ? ";

                    $preparedStatementTypes .= "i";
                    $preparedStatementDataArray[] = $margin;
                } else {
                    http_response_code(400);
                    $errorMessage = "Please enter a lower (positive) goal difference and try again";
                    apiReply($errorMessage);
                    die();
                }
            }
    
            if (isset($_GET['month'])) {
                $filterQueryCount++;
                $month = (int) htmlentities(trim($_GET['month']));
                if ($month >= 01 && $month <= 12) {
                    $joinAdverb = provideSQLQueryJoinAdverb($allFilterQueries);
                    $allFilterQueries .= "{$joinAdverb} EXTRACT(MONTH FROM MatchDate) = ? ";
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
                    $joinAdverb = provideSQLQueryJoinAdverb($allFilterQueries);
                    $allFilterQueries .= "{$joinAdverb} RefereeID = ? ";
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
        if ($filterQueryCount > 0) {
            $matchSummaryQuery = "{$mainQuery} {$allFilterQueries} {$orderByQuery}";
            if (isset($_GET['count'])) {
                $limitQuery = queryPagination();
                $matchSummaryQuery .= " {$limitQuery}";
            }

            // PROBLEM GETTING THE DATA ARRAY INTO THE PREPARED STATEMENT BIND_PARAM
            // echo "<p>{$matchSummaryQuery}</p>";
            // echo "<p>$preparedStatementTypes</p>";

            // print_r(...$preparedStatementDataArray);
            // print_r($preparedStatementDataArray);

            $stmt = $conn->prepare($matchSummaryQuery);
            $stmt -> bind_param($preparedStatementTypes, ...$preparedStatementDataArray);
            
        } else {
            if (isset($_GET['count'])) {
                $limitQuery = queryPagination();
                $matchSummaryQuery .= " {$limitQuery}";
            }
            $stmt = $conn->prepare($matchSummaryQuery);
        }
        $stmt -> bind_result($matchID, $matchDate, $homeClubId, $htGoals, $atGoals, $awayClubId); 
        $stmt -> execute();
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