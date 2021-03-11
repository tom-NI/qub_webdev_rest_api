<?php
    header('Content-Type: application/json');
    // api defines a seperate functions file to mimic a true seperate server!
    require("../apifunctions.php");
    require("../dbconn.php");
    if (checkAPIKey()) {
        $finalDataSet = array();
        $seasonID = null;
        $finalCount = null;

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
        }
        
        if (isset($_GET['usersearch'])) {
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

                if (isset($_GET['filter'])) {
                    $queryCount = 0;
                    // all the options for the filter panel

                    // post the club and set the select to be the posted club
                    if (isset($_GET['club_checkbox'])) {
                        $queryCount++;
                        if (isset($_GET['home_checkbox'])) {
                            $queryCount++;
                            
                        } elseif (isset($_GET['away_checkbox'])) {
                            $queryCount++;
                
                        } else {

                        }
                    }

                    // season filter
                    if (isset($_GET['filter_season_checkbox'])) {
                        $queryCount++;
                        $userClubQuery = "WHERE HomeClubID = {$usersSearchedClubID} OR AwayClubId = {$usersSearchedClubID}";
                        $matchSummaryQuery = "{$mainQuery} {$userClubQuery} {$orderByQuery}";
                    } else {
                        $userClubQuery = "AND (HomeClubID = {$usersSearchedClubID} OR AwayClubID = {$usersSearchedClubID})";
                        $matchSummaryQuery = "{$mainQuery} {$seasonQuery} {$userClubQuery} {$orderByQuery}";
                    }
            
                    // 
                    if (isset($_GET['fixture_checkbox'])) {
                        $queryCount++;
                        
                    }

                    if (isset($_GET['result_checkbox'])) {
                        $queryCount++;
                        
                    }
            
                    if (isset($_GET['margin_checkbox'])) {
                        $queryCount++;
                        
                    }
            
                    if (isset($_GET['filter_month_search'])) {
                        $queryCount++;
                        
                    }

                    if (isset($_GET['day_checkbox'])) {
                        $queryCount++;
                        
                    }

                    if (isset($_GET['filter_month_search'])) {
                        $queryCount++;
                        
                    }

                }
            } else {
                http_response_code(400);
                $errorMessage = "That club cannot be identified, please enter a new club and try again";
                apiReply($errorMessage);
                die();
            }
        }

        if (isset($_GET['count'])) {
            $limitQuery = queryPagination();
            $matchSummaryQuery = "{$matchSummaryQuery} {$limitQuery}";
        }

        // run the query, return the data, build the JSON
        $matchSummaryData = dbQueryCheckReturn($matchSummaryQuery);
        while ($row = $matchSummaryData->fetch_assoc()) {
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

            $matches = array(
                "id" => $row["MatchID"],
                "matchdate" => $row["MatchDate"],
                "hometeam" => $homeTeamName,
                "homescore" => $row["HTTotalGoals"],
                "awayscore" => $row["ATTotalGoals"],
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