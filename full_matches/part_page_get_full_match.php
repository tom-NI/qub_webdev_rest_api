<?php
    // obtain all form values safely;
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

    // this is all the logic that can be determined for adding or editing a match to try keep data clean
    // initialise string to add any error messages to!!
    $resultString = "";

    $getSubmissionDateTime = date("Y-m-d H:i:s");
    if ($finalMatchDate > $getSubmissionDateTime) {
        $matchDateInThePast = false;
        $resultString .= "Match date appears to be in the future. ";
    } else {
        $matchDateInThePast = true;
    }

    // Teams cannot be the same team - derived from the same list!
    if ($finalHomeClubName === $finalAwayClubName) {
        $resultString .= "The Same club has been selected for both teams, please enter two different clubs. ";
        $notTheSameTeams = false;
    } else {
        $notTheSameTeams = true;
    }
    
    // Shots on target cannot be > shots
    if (($finalHomeTeamShots < $finalHomeTeamShotsOnTarget) || ($finalAwayTeamShots < $finalAwayTeamShotsOnTarget)) {
        $resultString .= "Shots cannot be greater than the shots on target.  ";
        $shotsAreGreaterThanShotsOT = false;
    } else {
        $shotsAreGreaterThanShotsOT = true;
    }

    // Half time goals cannot be > full time goals
    if (($finalHomeTeamHalfTimeGoals > $finalHomeTeamTotalGoals) || ($finalAwayTeamHalfTimeGoals > $finalAwayTeamTotalGoals)) {
        $resultString .= "Half time goals cannot be greater than full time goals.  ";
        $halfTimeGoalsLessThanFullTime = false;
    } else {
        $halfTimeGoalsLessThanFullTime = true;
    }

    // Score cannot be less than total shots on target!
    if (($finalHomeTeamShotsOnTarget < $finalHomeTeamTotalGoals) || ($finalAwayTeamShotsOnTarget < $finalAwayTeamTotalGoals)) {
        $resultString .= "Shots on Target cannot be less than goals scored.  ";
        $shotsOTisntLessThanGoals = false;
    } else {
        $shotsOTisntLessThanGoals = true;
    }

    // fouls should not be less than yellow + red cards
    if ($finalHomeTeamFouls < ($finalHomeTeamYellowCards + $finalHomeTeamRedCards) ||
        $finalAwayTeamFouls < ($finalAwayTeamYellowCards + $finalAwayTeamRedCards)) {
            $foulsLessThanTotalCards = false;
            $resultString .= "Fouls are less than yellow cards + red cards.";
    } else {
        $foulsLessThanTotalCards = true;
    }

    // accrue a final retry message to the user if any error specific error messages are accrued
    if (strlen($resultString) > 0) {
        $resultString .= "Please revise the data entered and try again.";
    }
?>