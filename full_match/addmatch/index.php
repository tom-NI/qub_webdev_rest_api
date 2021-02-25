<?php
    // todo check key auth
    require("../../dbconn.php");
    require("../../apifunctions.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // obtain all form values safely first;
        $finalSeasonName = htmlentities(trim($_POST['season']));
        $finalMatchDate = htmlentities(trim($_POST['date']));
        $finalKickOffTime = htmlentities(trim($_POST['time']));
        $finalRefereeName = htmlentities(trim($_POST['refereename']));

        $finalHomeClubName = htmlentities(trim($_POST['homeclub']));
        $finalHomeTeamHalfTimeGoals = htmlentities(trim($_POST['ht_halftimegoals']));
        $finalHomeTeamTotalGoals = htmlentities(trim($_POST['ht_totalgoals']));
        $finalHomeTeamShots = htmlentities(trim($_POST['ht_shots']));
        $finalHomeTeamShotsOnTarget = htmlentities(trim($_POST['ht_shotsontarget']));
        $finalHomeTeamCorners = htmlentities(trim($_POST['ht_corners']));
        $finalHomeTeamFouls = htmlentities(trim($_POST['ht_fouls']));
        $finalHomeTeamYellowCards = htmlentities(trim($_POST['ht_yellowcards']));
        $finalHomeTeamRedCards = htmlentities(trim($_POST['ht_redcards']));

        $finalAwayClubName = htmlentities(trim($_POST['awayclub']));
        $finalAwayTeamHalfTimeGoals = htmlentities(trim($_POST['at_halftimegoals']));
        $finalAwayTeamTotalGoals = htmlentities(trim($_POST['at_totalgoals']));
        $finalAwayTeamShots = htmlentities(trim($_POST['at_shots']));
        $finalAwayTeamShotsOnTarget = htmlentities(trim($_POST['at_shotsontarget']));
        $finalAwayTeamCorners = htmlentities(trim($_POST['at_corners']));
        $finalAwayTeamFouls = htmlentities(trim($_POST['at_fouls']));
        $finalAwayTeamYellowCards = htmlentities(trim($_POST['at_yellowcards']));
        $finalAwayTeamRedCards = htmlentities(trim($_POST['at_redcards']));

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

        // if all flags are true, fairly sure data isnt poor quality, so enter new match details;
        if ($matchDateInThePast
            && $notTheSameTeams 
            && $shotsAreGreaterThanShotsOT 
            && $halfTimeGoalsLessThanFullTime 
            && $shotsOTisntLessThanGoals 
            && $foulsLessThanTotalCards
            && $currentSeasonSelected) {
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
                
                // do an SQL transaction programmatically in PHP to accurately insert a single match into all relevent tables;
                $stmtSuccessful = true;
                $conn->autocommit(false);

                // setup one statement per table, track if entry if successful for each or not
                $matchStatement = $conn->prepare("INSERT INTO `epl_matches` (`MatchID`, `SeasonID`, `MatchDate`, `KickOffTime`, `RefereeID`) VALUES (NULL, ?, ?, ?, ?);");
                $matchStatement -> bind_param("issi",
                            $finalSeasonID,
                            $finalMatchDate,
                            $finalKickOffTime,
                            $returnedRefereeID);
                $matchStatement -> execute();
                if (!$matchStatement) {
                    $stmtSuccessful = false;
                }
                $matchStatement->close();
                $lastID = $conn->insert_id;

                $homeStmt = $conn->prepare("INSERT INTO `epl_home_team_stats` (`HomeTeamStatID`, `HomeClubID`, `MatchID`, `HTTotalGoals`, `HTHalfTimeGoals`, `HTShots`, `HTShotsOnTarget`, `HTCorners`, `HTFouls`, `HTYellowCards`, `HTRedCards`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
                $homeStmt -> bind_param("iiiiiiiiii",
                            $homeClubID,
                            $lastID,
                            $finalHomeTeamTotalGoals,
                            $finalHomeTeamHalfTimeGoals,
                            $finalHomeTeamShots,
                            $finalHomeTeamShotsOnTarget,
                            $finalHomeTeamCorners,
                            $finalHomeTeamFouls,
                            $finalHomeTeamYellowCards,
                            $finalHomeTeamRedCards);
                $homeStmt -> execute();
                if (!$homeStmt) {
                    $stmtSuccessful = false;
                }
                $homeStmt->close();
                $last_id = $conn->insert_id;

                $awayStmt = $conn->prepare("INSERT INTO `epl_away_team_stats` (`AwayTeamStatID`, `AwayClubID`, `MatchID`, `ATTotalGoals`, `ATHalfTimeGoals`, `ATShots`, `ATShotsOnTarget`, `ATCorners`, `ATFouls`, `ATYellowCards`, `ATRedCards`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
                $awayStmt -> bind_param("iiiiiiiiii",
                            $awayClubID,
                            $lastID,
                            $finalAwayTeamTotalGoals,
                            $finalAwayTeamHalfTimeGoals,
                            $finalAwayTeamShots,
                            $finalAwayTeamShotsOnTarget,
                            $finalAwayTeamCorners,
                            $finalAwayTeamFouls,
                            $finalAwayTeamYellowCards,
                            $finalAwayTeamRedCards
                        );
                $awayStmt -> execute();
                if (!$awayStmt) {
                    $stmtSuccessful = false;
                }
                $awayStmt->close();

                // if all three statements above didnt work, rollback for this connection
                if (!$stmtSuccessful) {
                    $conn->rollback();
                    echo "Entry error, please try again";
                }
                $conn->autocommit(true);
                $conn->close();
        } else {
            // something wrong with the data quality, dont submit to DB
            $resultString;
            die();
        }
    }
?>