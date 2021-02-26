<?php
    // add authentication key checks

    require("../../apifunctions.php");
    require("../../dbconn.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['addnewseason'])) {
        $userSeasonEntry = htmlentities(trim($_POST['newseason']));

        // todo - check the order of the season entry!
        $seasonYearsCorrectOrder = checkSeasonYearOrder($userSeasonEntry);
        
        if (checkSeasonRegex($userSeasonEntry) && $seasonYearsCorrectOrder) {
            // check if the season already exists before adding it twice;
            include("../../api_auth.php");
            $allSeasonsAPIurl = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/?list=all_seasons_list";
            $allSeasonsAPIdata = file_get_contents($allSeasonsAPIurl);
            $fixtureList = json_decode($allSeasonsAPIdata, true);

            foreach ($fixtureList as $existingSeason) {
                if ($userSeasonEntry == $existingSeason['season']) {
                    http_response_code(400);
                    // todo review all echo statements from an API
                    echo "that season already exists";
                    die();
                }
            }
            // get the suggested next season to add!
            $suggestedNextSeason = findNextSuggestedSeason();

            if ($userSeasonEntry != $suggestedNextSeason) {
                http_response_code(400);
                // todo - check if i do echo to respond to http response codes
                echo "you didnt enter the next required season!";
            } else {
                $stmt = $conn->prepare("INSERT INTO `epl_seasons` (`SeasonID`, `SeasonYears`) VALUES (NULL, ?)");
                $stmt -> bind_param("s", $userSeasonEntry);
                $stmt -> execute();
                $stmt -> fetch();
                if ($stmt) {
                    http_response_code(201);
                } else {
                    http_response_code(500);
                }
            }
        } else {
            http_response_code(400);
        }
    }




?>