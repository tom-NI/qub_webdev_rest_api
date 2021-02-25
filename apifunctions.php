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

    function removeUnderScores($originalString) {
        $regex = '/[ ]/i';
        $newString = preg_replace($regex, ' ', $originalString);
        return $newString;
    }

    function addUnderScores($originalString) {
        $trimmedString = trim($originalString);
        $regex = '/[ ]/i';
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
?>