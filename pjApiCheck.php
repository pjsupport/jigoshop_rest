<?php
$url = 'https://api.payjunction.com/transactions/1';
$appkey = '3b2d0c91-ab7e-4f6f-a243-33df8b6b5dc1';
if (isset($_POST['login']) && $_POST['login'] != '') {
    $apilogin = $_POST['login'];
} else {
    sendError("Please provide a login name to test.");
    return;
}

if (isset($_POST['password']) && $_POST['password'] != '') {
    $apipassword = $_POST['password'];
} else {
    sendError("Please provide a password to test.");
    return;
}

checkApiCredentials($apilogin, $apipassword);

function checkApiCredentials($login, $pass) {
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'X-PJ-Application-Key: ' . $appkey));
	curl_setopt($ch, CURLOPT_USERPWD, $login . ':' . $pass);    
	
	$content = curl_exec($ch);
	$curl_errno = curl_errno($ch);
	$curl_error = curl_error($ch);
	curl_close($ch);
	
	if ($curl_errno) {
	    sendError('cURL Error: '.$curl_errno.' - '.$curl_error);
	    return;
	}
	
	$content = json_decode($content, true);
	
	if (isset($content['transactionId'])) { // Valid response
	    sendSuccess();
	    return;
	} elseif (isset($content['errors'])) {
	    foreach ($content['errors'] as $err) {
	        if (strpos($err['message'], 'Authentication failed') != false) {
	            sendError("The API login and/or password are incorrect");
	            return;
	        } elseif (strpos($err['message'], 'Transaction Id') != false) { // No Transaction Id 1, still valid
	            sendSuccess();
	            return;
	        }
	    }
	}
}

function sendSuccess() {
    echo "Success";
}

function sendError($error) {
    echo "Error: ".$error;
}

?>