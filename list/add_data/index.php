<?php
    // TODO need to set auth keys here and check here! else kick out!
    header('Content-Type: application/json');
    require("../../apifunctions.php");
    require("../../dbconn.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_GET['addnewclub'])) {
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
    } elseif (isset($_GET['addnewseason'])) {
        $userSeasonEntry = htmlentities(trim($_POST['newseason']));

        // todo - check the order of the season entry!
        $seasonYearsCorrectOrder = checkSeasonYearOrder($userSeasonEntry);
        
        if (checkSeasonRegex($userSeasonEntry) && $seasonYearsCorrectOrder) {
            // check if the season already exists before adding it twice;
            require("../../api_auth.php");
            $allSeasonsAPIurl = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/list?all_seasons_list";
            $allSeasonsAPIdata = file_get_contents($allSeasonsAPIurl);
            $fixtureList = json_decode($allSeasonsAPIdata, true);

            foreach ($fixtureList as $existingSeason) {
                if ($userSeasonEntry == $existingSeason['season']) {
                    http_response_code(400);
                    $replyMessage = "that season already exists";
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
    } elseif (isset($_GET['addnewref'])) {
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
            $stmt -> fetch();

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
    } else {
        http_response_code(400);
        $replyMessage = "Unknown request, please try again";
        apiReply($replyMessage);
        die();
    }
}
?>