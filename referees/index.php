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