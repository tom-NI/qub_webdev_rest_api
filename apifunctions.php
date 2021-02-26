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
        $regex = '/[ ]/i';
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
        $currentSeasonURL = "http://tkilpatrick01.lampt.eeecs.qub.ac.uk/epl_api_v1/list?current_season";
        require("api_auth.php");
        $currentSeasonData = file_get_contents($currentSeasonURL, false, $context);
        $currentSeasonArray = json_decode($currentSeasonData, true);

        foreach($currentSeasonArray as $row){
            $currentSeason = $row["currentSeason"];
        }
        return $currentSeason;
    }

    function debugOutputToConsole($output, $withtags) {
        $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
        if ($withtags) {
            $js_code = '<script>' . $js_code . '</script>';
        }
        echo $js_code;
    }
?>