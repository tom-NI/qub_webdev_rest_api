<?php
    $resultString = "";
    // obtain all form values safely first;
    $finalMatchDate = htmlentities(trim($_POST['date']));
    $finalKickOffTime = htmlentities(trim($_POST['time']));
    $finalRefereeName = htmlentities(trim($_POST['refereename']));

    $homeClubName = htmlentities(trim($_POST['homeclub']));
    $finalHomeClubName = removeUnderScores($homeClubName);
    $finalHomeTeamHalfTimeGoals = htmlentities(trim($_POST['ht_halftimegoals']));
    $finalHomeTeamTotalGoals = htmlentities(trim($_POST['ht_totalgoals']));
    $finalHomeTeamShots = htmlentities(trim($_POST['ht_shots']));
    $finalHomeTeamShotsOnTarget = htmlentities(trim($_POST['ht_shotsontarget']));
    $finalHomeTeamCorners = htmlentities(trim($_POST['ht_corners']));
    $finalHomeTeamFouls = htmlentities(trim($_POST['ht_fouls']));
    $finalHomeTeamYellowCards = htmlentities(trim($_POST['ht_yellowcards']));
    $finalHomeTeamRedCards = htmlentities(trim($_POST['ht_redcards']));

    $awayClubName = htmlentities(trim($_POST['awayclub']));
    $finalAwayClubName = removeUnderScores($awayClubName);
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
        $resultString .= "Match date appears to be in the future, please enter historical records only. ";
    } else {
        $matchDateInThePast = true;
    }

    // Teams cannot be the same team - derived from the same list!
    if ($finalHomeClubName === $finalAwayClubName) {
        $resultString .= "Same club selected for both teams, please enter two different clubs. ";
        $notTheSameTeams = false;
    } else {
        $notTheSameTeams = true;
    }
    
    // Shots on target cannot be > shots
    if (($finalHomeTeamShots < $finalHomeTeamShotsOnTarget) || ($finalAwayTeamShots < $finalHomeTeamShotsOnTarget)) {
        $resultString .= "Shots cannot be greater than the shots on target, please reenter data.  ";
        $shotsAreGreaterThanShotsOT = false;
    } else {
        $shotsAreGreaterThanShotsOT = true;
    }

    // Half time goals cannot be > full time goals
    if (($finalHomeTeamHalfTimeGoals > $finalHomeTeamTotalGoals) || ($finalAwayTeamHalfTimeGoals > $finalAwayTeamTotalGoals)) {
        $resultString .= "Half time goals cannot be greater than full time goals, please enter data again.  ";
        $halfTimeGoalsLessThanFullTime = false;
    } else {
        $halfTimeGoalsLessThanFullTime = true;
    }

    // Score cannot be less than total shots on target!
    if (($finalHomeTeamShotsOnTarget < $finalHomeTeamTotalGoals) || ($finalAwayTeamShotsOnTarget < $finalAwayTeamTotalGoals)) {
        $resultString .= "Shots on Target cannot be less than goals scored!  Please check and enter data again.  ";
        $shotsOTisntLessThanGoals = false;
    } else {
        $shotsOTisntLessThanGoals = true;
    }

    // fouls should not be less than yellow + red cards
    if ($finalHomeTeamFouls < ($finalHomeTeamYellowCards + $finalHomeTeamRedCards) ||
        $finalAwayTeamFouls < ($finalAwayTeamYellowCards + $finalAwayTeamRedCards)) {
            $foulsLessThanTotalCards = false;
            $resultString .= "Fouls are less than yellow cards + red cards, please check data and enter again.";
    } else {
        $foulsLessThanTotalCards = true;
    }
?>