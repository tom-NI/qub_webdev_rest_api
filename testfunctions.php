<?php
    require("api_auth.php");
    $allSeasonsAPIurl = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/list?all_seasons_list";
    $allSeasonsAPIdata = file_get_contents($allSeasonsAPIurl);
    $seasonList = json_decode($allSeasonsAPIdata, true);

    if ($seasonList->num_rows > 0) {
        $dbHighestSeasonEntered = $seasonList->fetch_row();
    }

    print_r($dbHighestSeasonEntered);

    $seasonYearsArray = explode("-", $dbHighestSeasonEntered);
    print_r($seasonYearsArray);
    $seasonEndYear = (int) $seasonYearsArray[1];
    echo "<p>{$seasonEndYear}</p>";
    $nextSeasonEndYear = $seasonEndYear + 1;
    echo "<p>{$nextSeasonEndYear}</p>";
    // return "{$seasonEndYear}-{$nextSeasonEndYear}";
    echo "{$seasonEndYear}-{$nextSeasonEndYear}";

?>