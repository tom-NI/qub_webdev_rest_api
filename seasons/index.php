<?php
    header('Content-Type: application/json');
    require("../apifunctions.php");
    require("../dbconn.php");
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
        if (isset($_POST['deleted_season'])) {
            $seasonToDelete = htmlentities(trim($_POST['deleted_season']));

            $stmt = $conn->prepare("SELECT * FROM `epl_matches`
                INNER JOIN epl_seasons ON epl_seasons.SeasonID = epl_matches.SeasonID
                WHERE epl_seasons.SeasonYears = ? ;");
            $stmt -> bind_param("s", $seasonToDelete);
            $stmt -> execute();
            $stmt -> store_result();

            $totalRows = (int) $stmt -> num_rows;
            $stmt -> close();

            if ($totalRows > 0) {
                http_response_code(403);
                $replyMessage = "This season is part of {$totalRows} match records, please delete all associated records first.  The season has not been deleted";
                apiReply($replyMessage);
                die();
            } else {
                $stmt = $conn->prepare("SELECT SeasonID FROM epl_seasons WHERE SeasonYears = ? ");
                $stmt -> bind_param("s", $seasonToDelete);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($seasonID);
                $stmt -> fetch();
                $totalRows = (int) $stmt->num_rows;

                if ($totalRows == 0) {
                    http_response_code(400);
                    $replyMessage = "Unknown or non-existent season, please enter season years in the format YYYY-YYYY";
                    apiReply($replyMessage);
                    die();
                } else {
                    $finalStmt = $conn->prepare("DELETE FROM `epl_seasons` WHERE `epl_seasons`.`SeasonID` = ? ;");
                    $finalStmt -> bind_param("i", $seasonID);
                    $finalStmt -> execute();

                    if ($finalStmt) {
                        http_response_code(204);
                        die();
                    } else {
                        http_response_code(500);
                        $replyMessage = "Season has not been deleted, please try again later";
                        apiReply($replyMessage);
                        die();
                    }
                }
            }
        } elseif (isset($_GET['addnewseason'])) {
            $userSeasonEntry = htmlentities(trim($_POST['newseason']));

            // todo - check the order of the season entry!
            $seasonYearsCorrectOrder = checkSeasonYearOrder($userSeasonEntry);
            
            if (checkSeasonRegex($userSeasonEntry) && $seasonYearsCorrectOrder) {
                // check if the season already exists before adding it twice;
                require("../api_auth.php");
                $allSeasonsAPIurl = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/seasons?all_seasons_list";
                $allSeasonsAPIdata = file_get_contents($allSeasonsAPIurl);
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
                    $replyMessage = "you didnt enter the next required season!";
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
?>