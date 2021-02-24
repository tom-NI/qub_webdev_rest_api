<?php
    $host = "tkilpatrick01.lampt.eeecs.qub.ac.uk";
    // $host = "lamp-19.eeecs.qub.ac.uk/phpmyadmin";
    // $host = "lamp-19.eeecs.qub.ac.uk";
    $user = "tkilpatrick01";
    $pw = "h2mdZxjGdQLzrf3d";
    $db = "tkilpatrick01";

    $conn = new mysqli($host, $user, $pw, $db);
    
    if ($conn -> connect_error) {
        echo "there has been an error ".$conn->connect_error; 
    }
?>