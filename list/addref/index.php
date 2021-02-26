<?php
    // TODO need to set auth keys here and check here! else kick out!
    require("../../apifunctions.php");
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['addnewref'])) {
        $userRefNameEntry = htmlentities(trim($_POST['refereename']));

        // check user entered a space in the name
        $regex = '/[ ]/i';
        
        // only process the name if theres a space in the name and its > 4 chars
        if (preg_match($regex, $userRefNameEntry) 
            && strlen($userRefNameEntry) > 4
            && strlen($userRefNameEntry) < 30) {
            $finalNameForDB = parseRefereeName($userRefNameEntry);

            require("../../dbconn.php");
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
                echo "Referee already exists";
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
                } else {
                    http_response_code(500);
                }
                $stmt->close();
            }            
        } else {
            http_response_code(400);
        }
    }
?>