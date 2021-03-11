<?php
    // functions here only used within the API to mimic a seperate server
    function dbQueryCheckReturn($sqlQuery) {
        require("dbconn.php");
        $queriedValue = $conn->query($sqlQuery);
        if (!$queriedValue) {
            echo $conn->error;
            die();
        } else {
            return $queriedValue;
        }
    }

    // insert data and if it fails, print error message
    function dbQueryAndCheck($sqlQuery) {
        require("dbconn.php");
        $queryValue = $conn->query($sqlQuery);
        if (!$queryValue) {
            echo $conn->error;
            die();
        }
    }

    // take a users Referee Name entry and parse into a format required for the database
    function parseRefereeName($userRefNameEntry) {
        // remove anything not a letter
        $nonLetterRegex = '/[^A-Za-z- .]/';
        $cleanedRefNameEntry = preg_replace($nonLetterRegex, '', $userRefNameEntry);
    
        // breakup into first and last names;
        $namesArray = explode(" ", $cleanedRefNameEntry);
        $firstName = $namesArray[0];
        $secondName = $namesArray[1];
        $firstInitial = strtoupper($firstName[0]);
        $secondNameFirstInitial = strtoupper($secondName[0]);
        $secondNameRemainder = strtolower(substr($secondName, 1, 40));
    
        $finalNameForDB = "{$firstInitial}. {$secondNameFirstInitial}{$secondNameRemainder}";
        return $finalNameForDB;
    }

    function removeUnderScores($originalString) {
        $regex = '/[_+]/i';
        $newString = preg_replace($regex, ' ', $originalString);
        return $newString;
    }

    function addUnderScores($originalString) {
        $trimmedString = trim($originalString);
        $regex = '/[ +]/i';
        $newString = preg_replace($regex, '_', $trimmedString);
        return $newString;
    }

    function checkSeasonRegex($stringToCheck) {
        $seasonRegex = '/2[0-9]{3}-2[0-9]{3}/';
        if (preg_match($seasonRegex, $stringToCheck)) {
            return true;
        } else {
            return false;
        }
    }

    function queryPagination() {
        require("dbconn.php");
        $matchCount = $conn->real_escape_string($_GET['count']);
        $matchCount = (int) htmlentities($matchCount);

        if (is_numeric($matchCount) && $matchCount > 0) {
            if (isset($_GET['startat'])) {
                $startFromNum = $conn->real_escape_string($_GET['startat']);
                $startFromNum = (int) htmlentities($startFromNum);
                if (is_numeric($startFromNum) && !($startFromNum < 0)) {
                    if ($startFromNum <= $matchCount) {
                        $limitQuery = "LIMIT {$startFromNum}, {$matchCount};";
                    } else {
                        $limitQuery = "LIMIT {$matchCount};";
                    }
                }
            } else {
                $limitQuery = "LIMIT {$matchCount};";
            }
        }
        return $limitQuery;
    }

    function getCurrentSeason() {
        $currentSeasonURL = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/seasons?current_season";
        require("api_auth.php");
        $currentSeasonData = file_get_contents($currentSeasonURL, false, $context);
        $currentSeasonArray = json_decode($currentSeasonData, true);

        foreach($currentSeasonArray as $row){
            $currentSeason = $row["currentSeason"];
        }
        return $currentSeason;
    }
    
    function checkSeasonYearOrder($fullSeasonEntryToCheck) {
        $seasonEntryArray = explode("-", $fullSeasonEntryToCheck);
        $seasonStartYear = (int) $seasonEntryArray[0];
        $seasonEndYear = (int) $seasonEntryArray[1];
        if (($seasonStartYear >= $seasonEndYear) || ($seasonEndYear - $seasonStartYear > 1)) {
            return false;
        } else {
            return true;
        }
    }
    
    // todo need to text this for the ADD SEASON API call!
    function findNextSuggestedSeason() {
        require("api_auth.php");
        $allSeasonsAPIurl = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/seasons?all_seasons_list";
        $allSeasonsAPIdata = file_get_contents($allSeasonsAPIurl);
        $seasonList = json_decode($allSeasonsAPIdata, true);
        $dbHighestSeasonEntered = $seasonList[0]["season"];

        $seasonYearsArray = explode("-", $dbHighestSeasonEntered);
        $seasonEndYear = (int) $seasonYearsArray[1];
        $nextSeasonEndYear = $seasonEndYear + 1;
        return "{$seasonEndYear}-{$nextSeasonEndYear}";
    }

    // take a users Referee Name entry and parse into a format required for the database
    function parseClubName($clubEntry) {
        // remove anything not a letter
        $nonLetterRegex = '/[^A-Za-z ]/';
        $cleanedClubName = preg_replace($nonLetterRegex, '', $clubEntry);

        $spaceRegex = '/[ ]/';
        if (preg_match($spaceRegex, $cleanedClubName)) {
            // breakup into first and last names;
            $wordsArray = explode(" ", $cleanedClubName);
            $firstPartName = $wordsArray[0];
            $secondPartName = $wordsArray[1];
            $firstPartNameFirstLetter = strtoupper($firstPartName[0]);
            $firstPartNameRemainder = strtolower(substr($firstPartName, 1, 40));
            $secondPartNameFirstLetter = strtoupper($secondPartName[0]);
            $secondPartNameRemainder = strtolower(substr($secondPartName, 1, 40));
            $finalNameForDB = "{$firstPartNameFirstLetter}{$firstPartNameRemainder} {$secondPartNameFirstLetter}{$secondPartNameRemainder}";
        } else {
            $firstPartNameFirstLetter = strtoupper($cleanedClubName[0]);
            $firstPartNameRemainder = strtolower(substr($cleanedClubName, 1, 40));
            $finalNameForDB = "{$firstPartNameFirstLetter}{$firstPartNameRemainder}";
        }
        return $finalNameForDB;
    }

    function apiReply($replyString) {
        $reply = array(
            "reply_message" => "$replyString"
        );
        $replyArray[] = $reply;
        echo json_encode($replyArray);
    }

    function apiValidateKey($keyToCheck, $orgName) {
        require("dbconn.php");
        $stmt = $conn->prepare("SELECT id FROM `epl_api_users` WHERE UserKey = ? AND OrganisationName = ? ;");
        $stmt -> bind_param("ss", $keyToCheck, $orgName);
        $stmt -> execute();
        $stmt -> store_result();

        if ($stmt->num_rows == 1){
            return true;
        } else {
            return false;
        }
    }

    function checkAPIKey() {
        // $getHeaders = getallheaders();
        if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            // $providedOrgName = base64_decode($_SERVER['PHP_AUTH_USER']);
            // $providedAPIKey = base64_decode($_SERVER['PHP_AUTH_PW']);
            $providedOrgName = $_SERVER['PHP_AUTH_USER'];
            $providedAPIKey = $_SERVER['PHP_AUTH_PW'];
            if (apiValidateKey($providedAPIKey, $providedOrgName) === false) {
                http_response_code(401);
                $replyMessage = "Details incorrect, please check the details provided at registration, or register for a key at http://tkilpatrick01.lampt.eeecs.qub.ac.uk/a_assignment_code/api_registration.php";
                apiReply($replyMessage);
                die();
            } else  {
                return true;
            }
        } else {
            header("WWW-Authenticate: Basic realm='Admin Dashboard'");
            header("HTTP/1.0 401 Unauthorized");
            echo "You need to enter a valid organisation name and key.";
            exit;
        }
    }
?>