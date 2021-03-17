<?php
    header('Content-Type: application/json');
    require("../apifunctions.php");
    require("../dbconn.php");
    if (checkAPIKey()) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $finalDataSet = array();
            if (isset($_GET['current_season'])) {
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
                $replyMessage = "Unknown request";
                apiReply($replyMessage);
                die();
            }
            // encode the final data set to JSON
            echo json_encode($finalDataSet);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_GET['addnewseason'])) {
                $userSeasonEntry = htmlentities(trim($_POST['newseason']));

                // todo - check the order of the season entry!
                $seasonYearsCorrectOrder = checkSeasonYearOrder($userSeasonEntry);
                
                if (checkSeasonRegex($userSeasonEntry) && $seasonYearsCorrectOrder) {
                    // check if the season already exists before adding it twice;
                    $allSeasonsAPIurl = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/seasons?all_seasons_list";
                    $allSeasonsAPIdata = postDevKeyInHeader($allSeasonsAPIurl);
                    $fixtureList = json_decode($allSeasonsAPIdata, true);

                    foreach ($fixtureList as $existingSeason) {
                        if ($userSeasonEntry == $existingSeason['season']) {
                            http_response_code(400);
                            $replyMessage = "{$userSeasonEntry} season already exists";
                            apiReply($replyMessage);
                            die();
                        }
                    }
                    // get the suggested next season to add!
                    $suggestedNextSeason = findNextSuggestedSeason();

                    if ($userSeasonEntry != $suggestedNextSeason) {
                        http_response_code(400);
                        $replyMessage = "{$userSeasonEntry} has not been added, the next required season is - {$suggestedNextSeason}";
                        apiReply($replyMessage);
                        die();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO `epl_seasons` (`SeasonID`, `SeasonYears`) VALUES (NULL, ?)");
                        $stmt -> bind_param("s", $userSeasonEntry);
                        $stmt -> execute();
                        $stmt -> fetch();
                        if ($stmt) {
                            http_response_code(201);
                            $replyMessage = "Season successfully added";
                            apiReply($replyMessage);
                            die();
                        } else {
                            http_response_code(500);
                            $replyMessage = "Unknown error, please try again later";
                            apiReply($replyMessage);
                            die();
                        }
                    }
                } else {
                    http_response_code(400);
                    $replyMessage = "Season has not been added - Please enter season in the format YYYY-YYYY, first year first";
                    apiReply($replyMessage);
                    die();
                }
            } else {
                http_response_code(400);
                $replyMessage = "Unknown request";
                apiReply($replyMessage);
                die();
            }
        } else {
            http_response_code(400);
            $replyMessage = "Unknown request";
            apiReply($replyMessage);
            die();
        }
    }
?>