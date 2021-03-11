<?php
    // todo add auth headers
    header('Content-Type: application/json');
    require("../apifunctions.php");
    require("../dbconn.php");

    if (checkAPIKey()) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ref_list'])) {
            // get all referees
            $finalDataSet = array();
            $refereeNameQuery = "SELECT RefereeName FROM `epl_referees` ORDER BY RefereeName ASC;";
            $refereeQueryData = dbQueryCheckReturn($refereeNameQuery);
            while ($row = $refereeQueryData->fetch_assoc()) {
                $ref = array(
                    "refname" => $row["RefereeName"],
                );
                $finalDataSet[] = $ref;
            }
            // encode the final data set to JSON
            echo json_encode($finalDataSet);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_GET['addnewref'])) {
                $userRefNameEntry = htmlentities(trim($_POST['refereename']));

                // check user entered a space in the name
                $regex = '/[ ]/i';
                
                // only process the name if theres a space in the name and its > 4 chars
                if (preg_match($regex, $userRefNameEntry) 
                    && strlen($userRefNameEntry) > 4
                    && strlen($userRefNameEntry) < 30) {
                    $finalNameForDB = parseRefereeName($userRefNameEntry);

                    require("../dbconn.php");
                    // check if the referee already exists, else add the referee
                    $stmt = $conn->prepare("SELECT * FROM `epl_referees` WHERE `RefereeName` = ?");
                    $stmt -> bind_param("s", $finalNameForDB);
                    $stmt -> execute();
                    $stmt -> store_result();
                    $stmt -> bind_result($refID, $refName);
                    $stmt->fetch();

                    if ($stmt->num_rows > 0) {
                        $stmt->close();
                        http_response_code(400);
                        $replyMessage = "Referee already exists in the database";
                        apiReply($replyMessage);
                        die();
                    } else {
                        // referee doesnt currently exist - 
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO `epl_referees` (`RefereeID`, `RefereeName`) VALUES (NULL, ?)");
                        $stmt -> bind_param("s", $finalNameForDB);
                        $stmt -> execute();
                        $stmt->fetch();
                        
                        if ($stmt) {
                            http_response_code(201);
                            $replyMessage = "Referee Entered Successfully";
                            apiReply($replyMessage);
                            die();
                        } else {
                            http_response_code(500);
                            $replyMessage = "Unknown Error, please try again";
                            apiReply($replyMessage);
                            die();
                        }
                        $stmt->close();
                    }            
                } else {
                    http_response_code(400);
                    $replyMessage = "Referee Addition unsuccessful, Please enter a first name and last name and try again";
                    apiReply($replyMessage);
                    die();
                }
            } elseif (isset($_POST['deleted_referee'])) {
                $refToDelete = htmlentities(trim($_POST['deleted_referee']));
                // check if any home or away team has a record against this club pre deletion?
                $stmt = $conn->prepare("SELECT * FROM `epl_matches`
                    INNER JOIN epl_referees ON epl_referees.RefereeID = epl_matches.RefereeID
                    WHERE epl_referees.RefereeName = ? ;");
                $stmt -> bind_param("s", $refToDelete);
                $stmt -> execute();
                $stmt -> store_result();
                $totalRows = (int) $stmt -> num_rows;
                $stmt -> close();

                if ($totalRows > 0) {
                    http_response_code(403);
                    $replyMessage = "This referee is part of {$totalRows} match records, please delete all associated records first. The Referee has not been deleted";
                    apiReply($replyMessage);
                    die();
                } else {
                    $refCheckStmt = $conn->prepare("SELECT RefereeID FROM epl_referees WHERE RefereeName = ? ");
                    $refCheckStmt -> bind_param("s", $refToDelete);
                    $refCheckStmt -> execute();
                    $refCheckStmt -> store_result();
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
                            die();
                        } else {
                            http_response_code(500);
                            $replyMessage = "Referee has not been deleted, please try again later";
                            apiReply($replyMessage);
                            die();
                        }
                    }
                }                
            } elseif (isset($_GET['edit'])) {
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
            } 
        } else {
            http_response_code(400);
            $replyMessage = "Unknown request";
            apiReply($replyMessage);
            die();
        }
    } else {
        http_response_code(401);
        $replyMessage = "Access unauthorized, please add your API Key, or register for a key at http://tkilpatrick01.lampt.eeecs.qub.ac.uk/a_assignment_code/api_registration.php";
        apiReply($replyMessage);
        die();
    }
?>