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

                $stmt = $conn->prepare("SELECT SeasonID FROM `epl_seasons` WHERE SeasonYears = ? ");
                $stmt -> bind_param("s", $currentSeason);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($SeasonID);
                $stmt -> fetch();
                
                $clubNameQuery = "SELECT DISTINCT epl_clubs.ClubName FROM `epl_clubs` 
                INNER JOIN epl_home_team_stats ON epl_home_team_stats.HomeClubID = epl_clubs.ClubID
                INNER JOIN epl_away_team_stats ON epl_away_team_stats.AwayClubID = epl_clubs.ClubID
                INNER JOIN epl_matches ON epl_matches.MatchID = epl_home_team_stats.MatchID
                INNER JOIN epl_seasons ON epl_matches.SeasonID = epl_seasons.SeasonID
                WHERE epl_seasons.SeasonID = {$SeasonID} ORDER BY ClubName ASC;";

                $clubQueryData = dbQueryCheckReturn($clubNameQuery);
                while ($row = $clubQueryData->fetch_assoc()) {
                    $clubnames = array(
                        "clubname" => $row["ClubName"],
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
                require("../api_auth.php");
                $allClubsURL = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/clubs?all_clubs";
                $allCLubsAPIData = file_get_contents($allClubsURL);
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
            } elseif (isset($_GET['delete']) && isset($_POST['deleted_club'])) {
                $clubToDelete = htmlentities(trim($_POST['deleted_club']));
                $finalClubName = removeUnderScores($clubToDelete);

                // check if any home or away team has a record against this club pre deletion?
                $stmt = $conn->prepare("SELECT ClubID FROM epl_clubs INNER JOIN epl_home_team_stats ON epl_clubs.ClubID = epl_home_team_stats.HomeClubID where epl_clubs.ClubName = ? ");
                $stmt -> bind_param("s", $finalClubName);
                $stmt -> execute();
                $stmt -> store_result();
                
                $awayStmt = $conn->prepare("SELECT ClubID FROM epl_clubs INNER JOIN epl_away_team_stats ON epl_clubs.ClubID = epl_away_team_stats.AwayClubID WHERE epl_clubs.ClubName = ? ");
                $awayStmt -> bind_param("s", $finalClubName);
                $awayStmt -> execute();
                $awayStmt -> store_result();

                $disusedClubStmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE epl_clubs.ClubName = ? ");
                $disusedClubStmt -> bind_param("s", $finalClubName);
                $disusedClubStmt -> execute();
                $disusedClubStmt -> store_result();

                $totalRows = (int) ($stmt->num_rows + $awayStmt->num_rows);
                $disusedClubCount = $disusedClubStmt->num_rows;
                $stmt -> close();
                $awayStmt -> close();

                if($totalRows > 0) {
                    http_response_code(403);
                    $replyMessage = "This club is part of {$totalRows} match records, please delete all associated records first.  The club has not been deleted";
                    apiReply($replyMessage);
                    die();
                } elseif ($totalRows == 0 && $disusedClubCount > 0) {
                    // get the clubID first
                    $stmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ");
                    $stmt -> bind_param("s", $finalClubName);
                    $stmt -> execute();
                    $stmt -> store_result();
                    $stmt -> bind_result($clubID);
                    $stmt -> fetch();
                    $totalRows = (int) $stmt->num_rows;

                    if ($totalRows == 0) {
                        http_response_code(400);
                        $replyMessage = "Unknown club, please select a club from the database";
                        apiReply($replyMessage);
                        die();
                    } else {
                        // final delete statement
                        $finalStmt = $conn->prepare("DELETE FROM `epl_clubs` WHERE `epl_clubs`.`ClubID` = ? ");
                        $finalStmt -> bind_param("i", $clubID);
                        $finalStmt -> execute();

                        if ($finalStmt) {
                            http_response_code(204);
                            die();
                        } else {
                            http_response_code(500);
                            $replyMessage = "Club has not been deleted, please try again later";
                            apiReply($replyMessage);
                            die();
                        }
                    }
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