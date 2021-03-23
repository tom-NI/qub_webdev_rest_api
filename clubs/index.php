<?php
    header('Content-Type: application/json');
    require("../apifunctions.php");
    require("../dbconn.php");
    if (checkAPIKey()) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // the data set that ALL get requests will build
            $finalDataSet = array();

            if (isset($_GET['all_clubs'])) {
                // query all clubs from the database
                $allCurrentClubsQuery = "SELECT ClubName FROM `epl_clubs` ORDER BY ClubName ASC";
                $allCurrentClubsData = dbQueryCheckReturn($allCurrentClubsQuery);
                while ($row = $allCurrentClubsData->fetch_assoc()) {
                    $club = array(
                        "club" => $row["ClubName"],
                    );
                    $finalDataSet[] = $club;
                }
            } elseif (isset($_GET['current_season_clubs'])) {
                // get current season first (callback to this API)
                $seasonURL = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/seasons?current_season";
                $currentSeasonData = postDevKeyInHeader($seasonURL);
                $currentSeasonList = json_decode($currentSeasonData, true);

                foreach($currentSeasonList as $row) {
                    $currentSeason = $row["currentSeason"];
                }

                $stmt = $conn->prepare("SELECT DISTINCT epl_home_team_stats.HomeClubName FROM epl_home_team_stats INNER JOIN epl_away_team_stats ON epl_home_team_stats.MatchID = epl_away_team_stats.MatchID INNER JOIN epl_matches ON epl_home_team_stats.MatchID = epl_matches.MatchID WHERE epl_matches.SeasonYears = ? ORDER BY HomeClubName ASC; ");
                $stmt -> bind_param("s", $currentSeason);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($clubName);

                while ($stmt -> fetch()) {
                    $clubnames = array(
                        "club_name" => $clubName,
                    );
                    $finalDataSet[] = $clubnames;
                }
            }
            // encode the final dataset and echo for all GET requests
            echo json_encode($finalDataSet);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_GET['addnewclub'])) {
                $newClubName = htmlentities(trim($_POST['newclubname']));
                $newClubLogoURL = htmlentities(trim($_POST['newcluburl']));
                
                // remove extraneous characters and tidy up the club name
                $finalClubName = parseClubName($newClubName);
                
                // check DB to see if the club name already exists first
                $allClubsURL = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/clubs?all_clubs";
                $allCLubsAPIData = postDevKeyInHeader($allClubsURL);
                $allClubsList = json_decode($allCLubsAPIData, true);

                foreach ($allClubsList as $existingClub) {
                    if ($finalClubName == $existingClub['club']) {
                        http_response_code(400);
                        $replyMessage = "That Club already exists";
                        apiReply($replyMessage);
                        die();
                    }
                }
                $stmt = $conn->prepare("INSERT INTO `epl_clubs` (`ClubID`, `ClubName`, `ClubLogoURL`) VALUES (NULL, ?, ?);");
                $stmt -> bind_param("ss", $finalClubName, $newClubLogoURL);
                $stmt -> execute();
                $stmt -> fetch();
                if ($stmt) {
                    http_response_code(201);
                    $replyMessage = "Entry Successful";
                    apiReply($replyMessage);
                    die();
                } else {
                    http_response_code(500);
                    $replyMessage = "Something went wrong, please try again later";
                    apiReply($replyMessage);
                    die();
                }
                $stmt -> close();
            } elseif (isset($_GET['edit']) && isset($_POST['club_to_change'])) {
                $clubToChange = htmlentities(trim($_POST['club_to_change']));
                $newClubName = htmlentities(trim($_POST['new_club_name']));
                $finalClubName = parseClubName($newClubName);

                // get club ID to check if only one club exists, else throw an error
                $stmt = $conn->prepare("SELECT ClubID FROM `epl_clubs` WHERE ClubName = ? ;");
                $stmt -> bind_param("s", $clubToChange);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($clubID);
                $stmt -> fetch();

                // if only one club with that name exists, update, else throw an error
                if ($stmt -> num_rows == 1) {
                    $stmt = $conn->prepare("UPDATE `epl_clubs` SET `ClubName` = ? WHERE `epl_clubs`.`ClubID` = ? ;");
                    $stmt -> bind_param("si", $finalClubName, $clubID);
                    $stmt -> execute();

                    if ($stmt) {
                        http_response_code(204);
                        die();
                    } else {
                        http_response_code(500);
                        $replyMessage = "Name has not been updated, please try again";
                        apiReply($replyMessage);
                        die();
                    }
                } else {
                    http_response_code(404);
                    $replyMessage = "Club name is ambiguous or unknown, please try again";
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
    }
?>