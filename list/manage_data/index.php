<?php
    // TODO need to set auth keys here and check here! else kick out!
    header('Content-Type: application/json');
    require("../../apifunctions.php");
    require("../../dbconn.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_GET['edit'])) {
            if (isset($_POST['ref_to_change'])) {
                $refereeToChange = htmlentities(trim($_POST['ref_to_change']));
                $newRefereeName = htmlentities(trim($_POST['new_ref_name']));

                $finalRefereename = parseRefereeName($newRefereeName);

                // get refereeID
                $stmt = $conn->prepare("SELECT RefereeID FROM `epl_referees` WHERE RefereeName = ? ;");
                $stmt -> bind_param("s", $refereeToChange);
                $stmt -> execute();
                $stmt -> store_result();
                $stmt -> bind_result($refID);
                $stmt -> fetch();

                // if there is only one referee, update, else dont!
                if($stmt -> num_rows == 1) {
                    $stmt = $conn->prepare("UPDATE `epl_referees` SET `RefereeName` = ? WHERE `epl_referees`.`RefereeID` = ? ;");
                    $stmt -> bind_param("si", $finalRefereename, $refID);
                    $stmt -> execute();

                    if ($stmt) {
                        http_response_code(204);
                        $replyMessage = "Name updated successfully";
                        apiReply($replyMessage);
                        die();
                    } else {
                        http_response_code(500);
                        $replyMessage = "Name has not been updated, please try again";
                        apiReply($replyMessage);
                        die();
                    }
                } else {
                    http_response_code(404);
                    $replyMessage = "Referee name is ambiguous or unknown, please try again";
                    apiReply($replyMessage);
                    die();
                }
            } elseif (isset($_POST['club_to_change'])) {
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
                        $replyMessage = "Name updated successfully";
                        apiReply($replyMessage);
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
                $replyMessage = "No data provided to modify, please try again";
                apiReply($replyMessage);
                die();
            }
        } elseif (isset($_GET['delete'])) {
            if (isset($_POST['deleted_club'])) {
                $clubToDelete = htmlentities(trim($_POST['deleted_club']));

                // check if any home or away team has a record against this club pre deletion?
                $stmt = $conn->prepare("SELECT ClubID FROM epl_clubs INNER JOIN epl_home_team_stats ON epl_clubs.ClubID = epl_home_team_stats.HomeClubID where epl_clubs.ClubName = ? ");
                $stmt -> bind_param("s", $clubToDelete);
                $stmt -> execute();
                
                $awayStmt = $conn->prepare("SELECT ClubID FROM epl_clubs INNER JOIN epl_away_team_stats ON epl_clubs.ClubID = epl_away_team_stats.AwayClubID WHERE epl_clubs.ClubName = ? ");
                $awayStmt -> bind_param("s", $clubToDelete);
                $awayStmt -> execute();
                
                $totalRows = (int) ($stmt->num_rows + $awayStmt->num_rows);
                $stmt -> close();
                $awayStmt -> close();

                if($totalRows > 0) {
                    http_response_code(403);
                    $replyMessage = "This club is part of {$totalRows} match records, please delete all associated records first.  The club has not been deleted";
                    apiReply($replyMessage);
                    die();
                } else {
                    // get the clubID
                    $stmt = $conn->prepare("SELECT ClubID FROM epl_clubs WHERE ClubName = ? ");
                    $stmt -> bind_param("s", $clubToDelete);
                    $stmt -> execute();
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
                        $finalStmt -> bind_param("i", (int) $clubID);
                        $finalStmt -> execute();
    
                        if ($finalStmt) {
                            http_response_code(204);
                            $replyMessage = "Club Deleted";
                            apiReply($replyMessage);
                            die();
                        } else {
                            http_response_code(500);
                            $replyMessage = "Club has not been deleted, please try again later";
                            apiReply($replyMessage);
                            die();
                        }
                    }
                }

            } elseif (isset($_POST['deleted_referee'])) {
                $refToDelete = htmlentities(trim($_POST['deleted_referee']));
                // check if any home or away team has a record against this club pre deletion?
                $stmt = $conn->prepare("SELECT * FROM `epl_matches`
                    INNER JOIN epl_referees ON epl_referees.RefereeID = epl_matches.RefereeID
                    WHERE epl_referees.RefereeName = ? ;");
                $stmt -> bind_param("s", $refToDelete);
                $stmt -> execute();

                $totalRows = (int) $stmt -> num_rows;
                $stmt -> close();

                if ($totalRows > 0) {
                    http_response_code(403);
                    $replyMessage = "This referee is part of {$totalRows} match records, please delete all associated records first.  
                    The Referee has not been deleted";
                    apiReply($replyMessage);
                    die();
                } else {
                    $refCheckStmt = $conn->prepare("SELECT RefereeID FROM epl_referees WHERE RefereeName = ? ");
                    $refCheckStmt -> bind_param("s", $refToDelete);
                    $refCheckStmt -> execute();
                    $refCheckStmt -> bind_result($refID);
                    $refCheckStmt -> fetch();
                    $refTotalRows = (int) $refCheckStmt->num_rows;
                    echo $refTotalRows;

                    if ($refTotalRows == 0) {
                        http_response_code(400);
                        $replyMessage = "Unknown referee, please select a referee who exists inside the database";
                        apiReply($replyMessage);
                        die();
                    } else {
                        $finalStmt = $conn->prepare("DELETE FROM `epl_referees` WHERE `epl_referees`.`RefereeID` = ? ;");
                        $finalStmt -> bind_param("i", $refID);
                        $finalStmt -> execute();

                        if ($finalStmt) {
                            http_response_code(204);
                            $replyMessage = "Referee Deleted";
                            apiReply($replyMessage);
                            die();
                        } else {
                            http_response_code(500);
                            $replyMessage = "Referee has not been deleted, please try again later";
                            apiReply($replyMessage);
                            die();
                        }
                    }
                }                
            } elseif (isset($_POST['deleted_season'])) {
                $seasonToDelete = htmlentities(trim($_POST['deleted_season']));

                $stmt = $conn->prepare("SELECT * FROM `epl_matches`
                    INNER JOIN epl_seasons ON epl_seasons.SeasonID = epl_matches.SeasonID
                    WHERE epl_seasons.SeasonYears = ? ;");
                $stmt -> bind_param("s", $seasonToDelete);
                $stmt -> execute();

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
                    $stmt -> bind_result($seasonID);
                    $stmt -> fetch();
                    $totalRows = (int) $stmt->num_rows;

                    if ($totalRows == 0) {
                        http_response_code(400);
                        $replyMessage = "Unknown season, please enter season years in the format YYYY-YYYY";
                        apiReply($replyMessage);
                        die();
                    } else {
                        $finalStmt = $conn->prepare("DELETE FROM `epl_seasons` WHERE `epl_seasons`.`SeasonID` = ? ;");
                        $finalStmt -> bind_param("i", $seasonID);
                        $finalStmt -> execute();

                        if ($finalStmt) {
                            http_response_code(204);
                            $replyMessage = "Season Deleted";
                            apiReply($replyMessage);
                            die();
                        } else {
                            http_response_code(500);
                            $replyMessage = "Season has not been deleted, please try again later";
                            apiReply($replyMessage);
                            die();
                        }
                    }
                }
            } else {    
                http_response_code(400);
                $replyMessage = "No data provided to delete, please try again";
                apiReply($replyMessage);
                die();
            }
        } 
    }
?>