<?php
    // modularized here as the code is the same for both adding and editing match
    // this code checks club, season and referee provided for match inserts and deletes exists in the database
    // stops users entering poor data and bypassing data quality checks for refs, clubs and seasons.

     // check referee exists in DB
     $refStmt = $conn->prepare("SELECT RefereeName FROM `epl_referees` WHERE RefereeName = ? ");
     $refStmt -> bind_param("s", $finalRefereeName);
     $refStmt -> execute();
     $refStmt -> store_result();
     if ($refStmt -> num_rows == 0 || $refStmt -> num_rows > 1) {
         http_response_code(404);
         $replyMessage = "There was a problem with the Referee Entered, please review and try again. If the Referee doesnt currently exist, please add it to the database first";
         apiReply($replyMessage);
         die();
     }

     // check home club exists in the db
     $homeStmt = $conn->prepare("SELECT ClubName FROM `epl_clubs` WHERE ClubName = ? ");
     $homeStmt -> bind_param("s", $finalHomeClubName);
     $homeStmt -> execute();
     $homeStmt -> store_result();
     if ($homeStmt -> num_rows == 0) {
         http_response_code(404);
         $replyMessage = "There was a problem with the Home Team Entered, please review and try again. If the Club doesnt currently exist, please add it to the database first";
         apiReply($replyMessage);
         die();
     }

     // check away club exists in the db
     $awayStmt = $conn->prepare("SELECT ClubName FROM `epl_clubs` WHERE ClubName = ? ");
     $awayStmt -> bind_param("s", $finalAwayClubName);
     $awayStmt -> execute();
     $awayStmt -> store_result();
     if ($awayStmt -> num_rows == 0) {
         http_response_code(404);
         $replyMessage = "There was a problem with the Away Team Entered, please review and try again. If the Club doesnt currently exist, please add it to the database first";
         apiReply($replyMessage);
         die();
     }

?>