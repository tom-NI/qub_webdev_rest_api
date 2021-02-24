<?php
    // todo check key auth
    require("../../dbconn.php");
    require("../../apifunctions.php");

    // obtain all form values safely first;
    $finalSeasonName = htmlentities(trim($_POST['select_season']));
    $finalMatchDate = htmlentities(trim($_POST['match_date']));
    $finalKickOffTime = htmlentities(trim($_POST['kickoff_time']));
    $finalRefereeName = htmlentities(trim($_POST['select_ref']));
    $finalHomeClubName = htmlentities(trim($_POST['ht_selector']));
    $finalAwayClubName = htmlentities(trim($_POST['at_selector']));
    $finalHomeTeamHalfTimeGoals = htmlentities(trim($_POST['ht_ht_goals']));
    $finalHomeTeamTotalGoals = htmlentities(trim($_POST['ht_ft_goals']));
    $finalHomeTeamShots = htmlentities(trim($_POST['ht_total_shots']));
    $finalHomeTeamShotsOnTarget = htmlentities(trim($_POST['ht_shots_on_target']));
    $finalHomeTeamCorners = htmlentities(trim($_POST['ht_corners']));
    $finalHomeTeamFouls = htmlentities(trim($_POST['ht_total_fouls']));
    $finalHomeTeamYellowCards = htmlentities(trim($_POST['ht_yellow_cards']));
    $finalHomeTeamRedCards = htmlentities(trim($_POST['ht_red_cards']));

    $finalAwayTeamHalfTimeGoals = htmlentities(trim($_POST['at_ht_goals']));
    $finalAwayTeamTotalGoals = htmlentities(trim($_POST['at_ft_goals']));
    $finalAwayTeamShots = htmlentities(trim($_POST['at_total_shots']));
    $finalAwayTeamShotsOnTarget = htmlentities(trim($_POST['at_shots_on_target']));
    $finalAwayTeamCorners = htmlentities(trim($_POST['at_corners']));
    $finalAwayTeamFouls = htmlentities(trim($_POST['at_total_fouls']));
    $finalAwayTeamYellowCards = htmlentities(trim($_POST['at_yellow_cards']));
    $finalAwayTeamRedCards = htmlentities(trim($_POST['at_red_cards']));

    // boolean values to run through to check all user inputs prior to accepting data
    $matchDateInThePast = false;
    $notTheSameTeams = false;
    $shotsAreGreaterThanShotsOT = false;
    $halfTimeGoalsLessThanFullTime = false;
    $shotsOTisntLessThanGoals = false;
    $foulsLessThanTotalCards = false;
    $currentSeasonSelected = false;

    // TODO - CHECK THE DATE AND TIME ARE PARSED CORRECTLY!
    $getSubmissionDateTime = date("Y-m-d H:i:s");
    if ($finalMatchDate > $getSubmissionDateTime) {
        $matchDateInThePast = false;
        $resultString += "Match date appears to be in the future, please enter historical records only. ";
        die();
    } else {
        $matchDateInThePast = true;
    }

    // get current season from DB!
    $currentSeason = getCurrentSeason();
    if ($finalSeasonName != $currentSeason) {
        $currentSeasonSelected = false;
        $resultString += "Current Season has not been selected, historic seasons cannot have results added.  ";
    } else {
        $currentSeasonSelected = true;
    }

    // Teams cannot be the same team - derived from the same list!
    if ($finalHomeClubName == $finalAwayClubName) {
        $resultString += "Same club selected for both teams, please enter two different clubs.  ";
        $notTheSameTeams = false;
    } else {
        $notTheSameTeams = true;
    }
    
    // Shots on target cannot be > shots
    if (($finalHomeTeamShots < $finalHomeTeamShotsOnTarget) || ($finalAwayTeamShots < $finalHomeTeamShotsOnTarget)) {
        $resultString += "Shots cannot be greater than the shots on target, please reenter data.  ";
        $shotsAreGreaterThanShotsOT = false;
    } else {
        $shotsAreGreaterThanShotsOT = true;
    }

    // Half time goals cannot be > full time goals
    if (($finalHomeTeamHalfTimeGoals > $finalHomeTeamTotalGoals) || ($finalAwayTeamHalfTimeGoals > $finalAwayTeamTotalGoals)) {
        $resultString += "Half time goals cannot be greater than full time goals, please enter data again.  ";
        $halfTimeGoalsLessThanFullTime = false;
    } else {
        $halfTimeGoalsLessThanFullTime = true;
    }

    // Score cannot be less than total shots on target!
    if (($finalHomeTeamShotsOnTarget < $finalHomeTeamTotalGoals) || ($finalAwayTeamShotsOnTarget < $finalAwayTeamTotalGoals)) {
        $resultString += "Shots on Target cannot be less than goals scored!  Please check and enter data again.  ";
        $shotsOTisntLessThanGoals = false;
    } else {
        $shotsOTisntLessThanGoals = true;
    }

    // fouls should not be less than yellow + red cards
    if ($finalHomeTeamFouls < ($finalHomeTeamYellowCards + $finalHomeTeamRedCards) ||
        $finalAwayTeamFouls < ($finalAwayTeamYellowCards + $finalAwayTeamRedCards)) {
            $foulsLessThanTotalCards = false;
            $resultString += "Fouls are less than yellow cards + red cards, please check data and enter again.";
    } else {
        $foulsLessThanTotalCards = true;
    }

    

    // if all flags are true, fairly sure data isnt malicious, so enter new match details;
    if ($matchDateInThePast
        && $notTheSameTeams 
        && $shotsAreGreaterThanShotsOT 
        && $halfTimeGoalsLessThanFullTime 
        && $shotsOTisntLessThanGoals 
        && $foulsLessThanTotalCards
        && $currentSeasonSelected) {
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

            $stmt = $conn->prepare("
                START TRANSACTION;
                    INSERT INTO epl_matches (`SeasonID`, `MatchDate`, `KickOffTime`, `RefereeID`, `HomeClubID`, `AwayClubID`) 
                    VALUES (?,?,?,?,?,?);
                    SET @match_id = LAST_INSERT_ID();
        
                    INSERT INTO epl_home_team_stats (`MatchID`,`HTTotalGoals`,`HTHalfTimeGoals`,`HTShots`,`HTShotsOnTarget`,`HTCorners`,`HTFouls`,`HTYellowCards`,`HTRedCards`) 
                    VALUES (@match_id, ?,?,?,?,?,?,?,?);
        
                    INSERT INTO epl_away_team_stats (`MatchID`,`HTTotalGoals`,`HTHalfTimeGoals`,`HTShots`,`HTShotsOnTarget`,`HTCorners`,`HTFouls`,`HTYellowCards`,`HTRedCards`) 
                    VALUES (@match_id, ?,?,?,?,?,?,?,?);
                COMMIT; 
            ");
            // todo - get the 
            $stmt -> bind_param("sssiiiiiiiiiiiiiiiiiii", 
                            $finalSeasonName,
                            $finalMatchDate,
                            $finalKickOffTime,
                            $returnedRefereeID,
                            $homeClubID,
                            $awayClubID,
                            $finalHomeTeamTotalGoals,
                            $finalHomeTeamHalfTimeGoals,
                            $finalHomeTeamShots,
                            $finalHomeTeamShotsOnTarget,
                            $finalHomeTeamCorners,
                            $finalHomeTeamFouls,
                            $finalHomeTeamYellowCards,
                            $finalHomeTeamRedCards,
                            $finalAwayTeamTotalGoals,
                            $finalAwayTeamHalfTimeGoals,
                            $finalAwayTeamShots,
                            $finalAwayTeamShotsOnTarget,
                            $finalAwayTeamCorners,
                            $finalAwayTeamFouls,
                            $finalAwayTeamYellowCards,
                            $finalAwayTeamRedCards                            
                        );
            // $stmt -> execute();
            // $stmt -> store_result();
            // $stmt -> bind_result($MatchID);
            // $stmt -> fetch();
            print_r($stmt);
            
            $resultString = "Thank You for adding match results, Match Entry has been successful.";
    } else {
        $resultString;
        die();
    }
?>