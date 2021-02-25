<?php
    $userAuth = "APIuser";
    $pwAuth = "eplAPIaccess";

    $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Authorization: Basic ".base64_encode("$userAuth:$pwAuth")
            )
        );
    $context = stream_context_create($opts);
?>