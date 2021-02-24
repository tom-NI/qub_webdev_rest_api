<?php
    header('Content-Type: application/json');

    // API has a seperate functions file to mimic a true seperate server!
    require("../apifunctions.php");

    require("../part_authenticate.php"); {
    $finalDataSet = array();

    if (isset($_GET['ref_list'])) {
        // all referees query
        $refereeNameQuery = "SELECT RefereeName FROM `epl_referees` ORDER BY RefereeName ASC;";
        $refereeQueryData = dbQueryCheckReturn($refereeNameQuery);
        while ($row = $refereeQueryData->fetch_assoc()) {
            $ref = array(
                "refereename" => $row["RefereeName"],
            );
            $finalDataSet[] = $ref;
        }
    } elseif (isset($_GET['current_season'])) {
        // get the current device calendar month and year to search for the current season.
        $getCurrentMonth = date("m");
        $getYear = date("Y");
        if ($getCurrentMonth < 07) {
            $firstYear = (int) $getYear - 1;
            $seasonSearch = "{$firstYear}-{$getYear}";
        } else {
            $secondYear = (int) $getYear + 1;
            $seasonSearch = "{$getYear}-{$secondYear}";
        }
        // the search to see if the current season exists in the DB
        $currentSeason = "SELECT SeasonYears FROM `epl_seasons` WHERE SeasonYears LIKE '%{$seasonSearch}%';";
        $currentSeasonQueryData = dbQueryCheckReturn($currentSeason);

        // todo - change to a single row query!
        while ($row = $currentSeasonQueryData->fetch_assoc()) {
            $season = array(
                "currentSeason" => $row["SeasonYears"],
            );
            $finalDataSet[] = $season;
        }
    } elseif (isset($_GET['current_season_clubs'])) {
        // query all current clubs from current season
        $currentSeasonIDquery = "SELECT SeasonID FROM `epl_seasons` ORDER BY SeasonID DESC LIMIT 1";
        $currentSeasonIDData = dbQueryCheckReturn($currentSeasonIDquery);
        while ($row = $currentSeasonIDData->fetch_assoc()) {
            $seasonID = $row["SeasonID"];
        }
        
        $clubNameQuery = "SELECT DISTINCT epl_clubs.ClubName FROM `epl_clubs` 
        INNER JOIN epl_home_team_stats ON epl_home_team_stats.HomeClubID = epl_clubs.ClubID
        INNER JOIN epl_away_team_stats ON epl_away_team_stats.AwayClubID = epl_clubs.ClubID
        INNER JOIN epl_matches ON epl_matches.MatchID = epl_home_team_stats.MatchID
        INNER JOIN epl_seasons ON epl_matches.SeasonID = epl_seasons.SeasonID
        WHERE epl_seasons.SeasonID = {$seasonID} ORDER BY ClubName ASC;";

        $clubQueryData = dbQueryCheckReturn($clubNameQuery);
        while ($row = $clubQueryData->fetch_assoc()) {
            $clubnames = array(
                "clubname" => $row["ClubName"],
            );
            $finalDataSet[] = $clubnames;
        }
    } elseif (isset($_GET['all_seasons_list'])) {
        $seasonQuery = "SELECT SeasonYears FROM `epl_seasons` ORDER BY SeasonYears DESC;";
        $seasonQueryData = dbQueryCheckReturn($seasonQuery);
        while ($row = $seasonQueryData->fetch_assoc()) {
            $season = array(
                "season" => $row["SeasonYears"],
            );
            $finalDataSet[] = $season;
        }
    } else {
        http_response_code(400);
    }

    // encode the final data set to JSON
    echo json_encode($finalDataSet);
    }
?>