<?php
    // todo lockdown the API properly
    header('Content-Type: application/json');
    
    require("../apifunctions.php");
    require("../dbconn.php");
    require("../part_authenticate.php"); {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['register'])) {
            if (isset($_POST['firstname']) && isset($_POST['surname']) 
                && isset($_POST['email']) && isset($_POST['hashedpassword'])) {
                $userFirstName = htmlentities(trim($_POST['firstname']));
                $userSurname = htmlentities(trim($_POST['surname']));
                $userEmail = htmlentities(trim($_POST['email']));
                $hashedPassword = htmlentities(trim($_POST['hashedpassword']));

                $stmt = $conn->prepare("SELECT `AdminEmail` FROM `epl_admins` WHERE AdminEmail = ?");
                $stmt -> bind_param("s", $userEmail);
                $stmt -> execute();
                $stmt -> store_result();

                if ($stmt->num_rows() > 0) {
                    http_response_code(400);
                    $replyMessage = "That user has already registered, please log in";
                    apiReply($replyMessage);
                    die();
                }
                $stmt -> close();

                if (strlen($userFirstName) > 3 && strlen($userSurname) > 3 
                    && strlen($userEmail) > 3 && strlen($hashedPassword) > 3) {

                    $stmt = $conn->prepare("INSERT INTO `epl_admins` (`AdminID`, `AdminName`, `AdminSurname`, `AdminEmail`, `Password`) VALUES (NULL, ?, ?, ?, ?);");
                    $stmt -> bind_param("ssss", 
                                        $userFirstName,
                                        $userSurname,
                                        $userEmail,
                                        $hashedPassword
                                    );
                    $stmt -> execute();
                    $stmt -> store_result();

                    if ($stmt) {
                        http_response_code(201);
                        $replyMessage = "You have successfully registered, please log in";
                        apiReply($replyMessage);
                        die();
                    } else {
                        http_response_code(500);
                        $replyMessage = "User has not been registered, please try again";
                        apiReply($replyMessage);
                        die();
                    }
                } else {
                    http_response_code(400);
                    $replyMessage = "One of your entries is invalid, please try again";
                    apiReply($replyMessage);
                    die();
                }
            } else {
                http_response_code(400);
                $replyMessage = "All information has not been provided, please try again";
                apiReply($replyMessage);
                die();
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['validate'])) {
            $userEmail = htmlentities(trim($_POST['email']));
            $hashedPassword = htmlentities(trim($_POST['hashedpassword']));
            
            $stmt = $conn->prepare("SELECT `Password` FROM `epl_admins` WHERE AdminEmail = ?");
            $stmt -> bind_param("s", $userEmail);
            $stmt -> execute();
            $stmt -> store_result();
            $stmt -> bind_result($passwordToCompare);
            $stmt -> fetch();

            if ($stmt->num_rows() > 0) {
                // user email exists, check hashed passwords
                if(password_verify($passwordToCompare, $hashedPassword)) {
                    $replyMessage = "Logged in";
                    apiReply($replyMessage);
                    die();
                }
            } else {
                http_response_code(404);
                $replyMessage = "Email or password doesnt match, please try again";
                apiReply($replyMessage);
                die();
            }
        } else {
            http_response_code(400);
            $replyMessage = "Unknown Request, please try again";
            apiReply($replyMessage);
        }
    }
?>