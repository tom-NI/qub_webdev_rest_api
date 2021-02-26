<?php
    // TODO need to set auth keys here and check here! else kick out!
    require("../../apifunctions.php");
    require("../../dbconn.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['addnewclub'])) {
        $newClubName = htmlentities(trim($_POST['newclubname']));
        $newClubLogoURL = htmlentities(trim($_POST['newcluburl']));

        // check DB to see if the club name already exists first
        require("../../api_auth.php");
        $allClubsURL = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/list?all_clubs";
        $allCLubsAPIData = file_get_contents($allClubsURL);
        $allClubsList = json_decode($allCLubsAPIData, true);

        foreach ($allClubsList as $existingClub) {
            if ($newClubName == $existingClub['club']) {
                http_response_code(400);
                // todo review all echo statements from an API
                echo "That Club already exists";
                die();
            }
        }

        // TODO;
        // check club logo URL is a valid image (png or jpg), otherwise reject the submission entirely
        // $submittedImageType = exif_imagetype($newClubLogoURL);
        // echo $submittedImageType;

        // if ($submittedImageType != 3 || $submittedImageType != 2) {
        //     http_response_code(400);
        //     echo "URL links to an unsupported image file type, please try again with a .png or .jpeg file";
        //     die();
        // } else {
            $stmt = $conn->prepare("INSERT INTO `epl_clubs` (`ClubID`, `ClubName`, `ClubLogoURL`) VALUES (NULL, ?, ?);");
            $stmt -> bind_param("ss", $newClubName, $newClubLogoURL);
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
        // }
    } else {
        http_response_code(400);
        echo "Unknown request, please try again";
    }
?>