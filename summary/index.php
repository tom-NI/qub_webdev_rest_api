<?php
    header('Content-Type: application/json');

    // api defines a seperate functions file to mimic a true seperate server!
    require("../apifunctions.php");

    require("../part_authenticate.php"); {
        $finalDataSet = array();
        
        $seasonID = null;
        $finalCount = null;

        $mainQuery = "SELECT epl_matches.MatchID, epl_matches.MatchDate,
        epl_home_team_stats.HomeClubID, epl_home_team_stats.HTTotalGoals, epl_away_team_stats.ATTotalGoals, epl_away_team_stats.AwayClubID
        FROM epl_matches
        INNER JOIN epl_home_team_stats ON epl_matches.MatchID = epl_home_team_stats.MatchID 
        INNER JOIN epl_away_team_stats ON epl_matches.MatchID = epl_away_team_stats.MatchID";

        $orderByQuery = "ORDER BY epl_matches.MatchID DESC";

        $matchSummaryQuery = "{$mainQuery} {$orderByQuery}";

        if (isset($_GET['season'])) {
            $seasonYear = $_GET["season"];
            
            // only proceed with the query if the input matches regex constraints
            if (checkSeasonRegex($seasonYear)) {
                $seasonIdQuery = "SELECT SeasonID FROM epl_seasons WHERE SeasonYears LIKE '%{$seasonYear}%' LIMIT 1";
                $seasonIdData = dbQueryCheckReturn($seasonIdQuery);
                if (mysqli_num_rows($seasonIdData) == 0) {
                    http_response_code(404);
                } else {
                    $row = $seasonIdData->fetch_row();
                    $seasonID = $row[0];
                }
                $seasonQuery = "WHERE SeasonID = {$seasonID}";
            } else {
                http_response_code(400);
            }
            // always include a recent season to narrow the scope of the request!
            $matchSummaryQuery = "{$mainQuery} {$seasonQuery} {$orderByQuery}";
        } 
        if (isset($_GET['usersearch'])) {
            // wildcard search for main search bar!
            $userEntry = $_GET['usersearch'];
            // TODO - DO I NEED TO ADD UNDERSCORES HERE OR ON THE UI?
            // $parsedUserEntry = addUnderScores($userEntry);

            // search database to check if club exists
            $checkUserQuery = "SELECT ClubID FROM epl_clubs WHERE ClubName LIKE '%{$userEntry}%' ";
            $checkUsersData = dbQueryCheckReturn($checkUserQuery);

            if (mysqli_num_rows($checkUsersData) > 1) {
                http_response_code(400);
            } elseif (mysqli_num_rows($checkUsersData) > 0) {
                while ($row = $checkUsersData->fetch_assoc()) {
                    $usersSearchedClubID = $row['ClubID'];
                }
                if (!isset($_GET['season'])) {
                    $userClubQuery = "WHERE HomeClubID = {$usersSearchedClubID} OR AwayClubId = {$usersSearchedClubID}";
                    $matchSummaryQuery = "{$mainQuery} {$userClubQuery} {$orderByQuery}";
                } else {
                    $userClubQuery = "AND (HomeClubID = {$usersSearchedClubID} OR AwayClubID = {$usersSearchedClubID})";
                    $matchSummaryQuery = "{$mainQuery} {$seasonQuery} {$userClubQuery} {$orderByQuery}";
                }
            } else {
                http_response_code(400);
            }
        }
        if (isset($_GET['count'])) {
            $limitQuery = queryPagination();
            $matchSummaryQuery = "{$matchSummaryQuery} {$limitQuery}";
        }
        
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