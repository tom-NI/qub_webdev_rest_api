<?php
    // put this at the top of restricted files and the API to auth user!
    if (($_SERVER['PHP_AUTH_USER']) != 'APIuser' || ($_SERVER)['PHP_AUTH_PW'] != "eplAPIaccess") {
        header("WWW-Authenticate: Basic realm='Admin Dashboard'");
        header("HTTP/1.0 401 Unauthorized");
        echo "Please enter a valid username and password.";
        exit;
    } else 
        // page specific code here;
        // add in the curly brackets on each page for this code block
?>