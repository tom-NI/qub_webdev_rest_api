<?php
    // TODO need to set auth keys here and check here! else kick out!
    require("../../apifunctions.php");
    require("../../dbconn.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['addnewclub'])) {
        $newClubName = htmlentities(trim($_POST['newclubname']));
        $newClubLogoURL = htmlentities(trim($_POST['newcluburl']));
        
        // remove extraneous characters and tidy up the club name
        $finalClubName = parseClubName($newClubName);
        
        // check DB to see if the club name already exists first
        require("../../api_auth.php");
        $allClubsURL = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/list?all_clubs";
        $allCLubsAPIData = file_get_contents($allClubsURL);
        $allClubsList = json_decode($allCLubsAPIData, true);

        foreach ($allClubsList as $existingClub) {
            if ($finalClubName == $existingClub['club']) {
                http_response_code(400);
                // todo review all echo statements from an API
                echo "That Club already exists";
                die();
            }
        }
        $stmt = $conn->prepare("INSERT INTO `epl_clubs` (`ClubID`, `ClubName`, `ClubLogoURL`) VALUES (NULL, ?, ?);");
        $stmt -> bind_param("ss", $finalClubName, $newClubLogoURL);
        $stmt -> execute();
        $stmt -> fetch();
        if ($stmt) {
            http_response_code(201);
            echo "Entry Successful";
        } else {
            http_response_code(500);
            echo "Something went wrong, please try again later";
        }
        $stmt -> close();
    } else {
        http_response_code(400);
        echo "Unknown request, please try again";
    }
?>