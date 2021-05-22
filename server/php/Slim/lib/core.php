<?php
use Illuminate\Database\Capsule\Manager as Capsule;
/**
 * Core configurations
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 */
/**
 * sendmail
 *
 * @param string $template    template name
 * @param array  $replace_content   replace content
 * @param string  $to  to email address
 * @param string  $reply_to_mail  reply email address
 *
 * @return true or false
 */
function sendMail($template, $replace_content, $to, $reply_to_mail = '', $data = null, $isSms = false, $isPushNotify = false)
{
    global $_server_domain_url;
	global $authUser;
    $default_content = array(
        '##SITE_NAME##' => SITE_NAME,
        '##SITE_URL##' => $_server_domain_url,
        '##FROM_EMAIL##' => SITE_FROM_EMAIL,
        '##CONTACT_EMAIL##' => SITE_CONTACT_EMAIL
    );
    $emailFindReplace = array_merge($default_content, $replace_content);
    $email_templates = Models\EmailTemplate::where('name', $template)->first();
    if (count($email_templates) > 0) {
		$email_templates['is_html'] = true;
        if ($email_templates['is_html']) {
            $content = $email_templates['html_email_content'];
            $content_type = 'text/html';
        } else {
            $content = $email_templates['text_email_content'];
            $content_type = 'text/plain';
        }
        $message = strtr($content, $emailFindReplace);
        $subject = strtr($email_templates['subject'], $emailFindReplace);
        $from_email = strtr($email_templates['from'], $emailFindReplace);
		if (EMAIL_NOTIFY == '1') {
			$curl = curl_init();
			$toAddress = array();
			$toAddress[] = array(
				"email" => $to
			);
			$data = array();
			$data['personalizations'][] = array(
				'to' => $toAddress
			);
			$data['from']['email'] = SEND_GRID_EMAIL;
			$data['subject'] = $subject;
			$content = array(
				"type" => $content_type,
				  "value"=>  $message
			);
			$data['content'][] = $content;
			if (!empty($toAddress)) {
				curl_setopt_array($curl, array(
				  CURLOPT_URL => "https://api.sendgrid.com/v3/mail/send",
				  CURLOPT_RETURNTRANSFER => true,
				  CURLOPT_ENCODING => "",
				  CURLOPT_MAXREDIRS => 10,
				  CURLOPT_TIMEOUT => 30,
				  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				  CURLOPT_CUSTOMREQUEST => "POST",
				  CURLOPT_POSTFIELDS => json_encode($data),
				  CURLOPT_HTTPHEADER => array(
					"authorization: Bearer ".SEND_GRID_TOKEN,
					"cache-control: no-cache",
					"content-type: application/json"
				  ),
				));
				$response = curl_exec($curl);
				$emailLog = new Models\EmailLog;
				$emailLog->user_id = $emailFindReplace['##USERID##'];
				$emailLog->logs = $response;
				$emailLog->save();
			}
		}
		$user = '';
		if (!empty($emailFindReplace['##USERID##'])) {
			$user = Models\User::find($emailFindReplace['##USERID##'])->toArray();
		}
		userNotification($email_templates, $emailFindReplace, $isPushNotify, $isSms, strtr($email_templates['notification_content'], $emailFindReplace), strtr($email_templates['sms_content'], $emailFindReplace), $user);
    }
}
function generalSendMail($to, $subject ,$message)
{
    if (EMAIL_NOTIFY == '1') {
		$curl = curl_init();
		$toAddress = array();
		$toAddress[] = array(
			"email" => $to
		);
		$data = array();
		$data['personalizations'][] = array(
			'to' => $toAddress
		);
		$data['from']['email'] = SEND_GRID_EMAIL;
		$data['subject'] = $subject;
		$content = array(
			"type" => 'text/html',
			"value"=> $message
		);
		$data['content'][] = $content;
		if (!empty($toAddress)) {
			curl_setopt_array($curl, array(
			  CURLOPT_URL => "https://api.sendgrid.com/v3/mail/send",
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => "",
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 30,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => "POST",
			  CURLOPT_POSTFIELDS => json_encode($data),
			  CURLOPT_HTTPHEADER => array(
				"authorization: Bearer ".SEND_GRID_TOKEN,
				"cache-control: no-cache",
				"content-type: application/json"
			  ),
			));
			$response = curl_exec($curl);
		}
    }
}
function pushNotification($emailFindReplace,$user, $title, $body) {
	if (!empty($user['device_token'])) {
		$data = array();
		$data['to'] = $user['device_token'];
		$data['priority'] = "high";
		$data['content_available'] = true;
		$data['data'] = $emailFindReplace['##PUSHNOTIFICATION_DATA##'];
		$notificationData = array();
		$notificationData['title'] = $title;
		$notificationData['body'] = $body;
		$notificationData['sound'] = 'default';
		$notificationData['click_action'] = 'Home';
		$data['notification'] = $notificationData;
		$header = array(
			'Content-Type: application/json',
			'Authorization: key='.FIREBASE_PUSH_NOTIFICATION_TOKEN
		);
		$response = curl_post('https://fcm.googleapis.com/fcm/send', $header, json_encode($data));
	} else {
		$response = '{error: "Device Token Empty"}';
	}
	$pushNotification = new Models\PushNotification;
	$pushNotification->user_id = $user['id'];
	$pushNotification->title = $title;
	$pushNotification->body = $body;
	$pushNotification->logs = $response;
	$pushNotification->save();
}
function userNotification($email_templates, $emailFindReplace, $isPushNotify, $isSms, $pushData, $smsBody, $user) {
	$mobile = '';
	if (!empty($user)) {
		$mobile = $user['mobile'];
	}
	if ($isPushNotify == true && PUSH_NOTIFICATION == '1' && !empty($pushData)) {
		pushNotification($emailFindReplace,$user, $email_templates['display_name'], $pushData);
	}
	if ($isSms == true && SMS_NOTIFY == '1' && $mobile !='' && $smsBody !='') {
		$response = send_twilio_text_sms($mobile, $smsBody);
		$smsLog = new Models\SmsLog;
		$smsLog->user_id = $user['id'];
		$smsLog->logs = json_encode($response);
		$smsLog->save();
	}
}
function xmlToJson($xml_string) {
	$xml = simplexml_load_string($xml_string);
	$json = json_encode($xml);
	return json_decode($json,TRUE);
}
/**
 * Insert current access ip address into IPs table
 *
 * @return int IP id
 */
function saveIp()
{
    $ip = new Models\Ip;
    $ips = $ip->where('ip', $_SERVER['REMOTE_ADDR'])->first();
    if (!empty($ips)) {
        return $ips['id'];
    } else {
        $save_ip = new Models\Ip;
        $save_ip->ip = $_SERVER['REMOTE_ADDR'];
        $save_ip->host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        $save_ip->save();
        return $save_ip->id;
    }
}
/**
 * Checking already username is exists in users table
 *
 * @return true or false
 */
function checkAlreadyUsernameExists($username)
{
    $user = Models\User::where('username', $username)->first();
    if (!empty($user)) {
        return true;
    }
    return false;
}
/**
 * Checking already email is exists in users table
 *
 * @return true or false
 */
function checkAlreadyEmailExists($email)
{
    $user = Models\User::where('email', $email)->first();
    if (!empty($user)) {
        return true;
    }
    return false;
}
/**
 * Checking already mobile is exists in users profile table
 *
 * @return true or false
 */
function checkAlreadyMobileExists($mobile)
{
    $user = Models\User::where('mobile', $mobile)->first();
    if (!empty($user)) {
        return true;
    }
    return false;
}
/**
 * Checking already language is exists in languages table
 *
 * @return true or false
 */
function checkAlreadyLanguageExists($name)
{
    $language = Models\Language::where('name', $name)->first();
    if (!empty($language)) {
        return true;
    }
    return false;
}
/**
 * Returns an OAuth2 access token to the client
 *
 * @param array $post Post data
 *
 * @return mixed
 */
function getToken($post)
{
    $old_server_method = $_SERVER['REQUEST_METHOD'];
    if (!empty($_SERVER['CONTENT_TYPE'])) {
        $old_content_type = $_SERVER['CONTENT_TYPE'];
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $_POST = $post;
    OAuth2\Autoloader::register();
    $oauth_config = array(
        'user_table' => 'users'
    );
    $val_array = array(
        'dsn' => R_DB_DRIVER . ':host=' . R_DB_HOST . ';dbname=' . R_DB_NAME . ';port=' . R_DB_PORT,
        'username' => R_DB_USER,
        'password' => R_DB_PASSWORD
    );
    $storage = new OAuth2\Storage\Pdo($val_array, $oauth_config);
    $server = new OAuth2\Server($storage);
    if (isset($_POST['grant_type']) && $_POST['grant_type'] == 'password') {
        $val_array = array(
            'password' => $_POST['password']
        );
        $users = array(
            $_POST['username'] => $val_array
        );
        $user_credentials = array(
            'user_credentials' => $users
        );
        $storage = new OAuth2\Storage\Memory($user_credentials);
        $server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
    } elseif (isset($_POST['grant_type']) && $_POST['grant_type'] == 'refresh_token') {
        $always_issue_new_refresh_token = array(
            'always_issue_new_refresh_token' => true
        );
        $server->addGrantType(new OAuth2\GrantType\RefreshToken($storage, $always_issue_new_refresh_token));
    } elseif (isset($_POST['grant_type']) && $_POST['grant_type'] == 'authorization_code') {
        $server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
    } else {
        $val_array = array(
            'client_secret' => OAUTH_CLIENT_SECRET
        );
        $clients = array(
            OAUTH_CLIENT_ID => $val_array
        );
        $credentials = array(
            'client_credentials' => $clients
        );
        $storage = new OAuth2\Storage\Memory($credentials);
        $server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
    }
    $response = $server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send('return');
    $_SERVER['REQUEST_METHOD'] = $old_server_method;
    if (!empty($old_content_type)) {
        $_SERVER['CONTENT_TYPE'] = $old_content_type;
    }
    return json_decode($response, true);
}
/**
 * To generate random string
 *
 * @param array  $arr_characters Random string options
 * @param string $length         Length of the random string
 *
 * @return string
 */
function getRandomStr($arr_characters, $length)
{
    $rand_str = '';
    $characters_length = count($arr_characters);
    for ($i = 0; $i < $length; ++$i) {
        $rand_str.= $arr_characters[rand(0, $characters_length - 1) ];
    }
    return $rand_str;
}
/**
 * To generate the encrypted password
 *
 * @param string $str String to be encrypted
 *
 * @return string
 */
function getCryptHash($str)
{
    $salt = '';
    if (CRYPT_BLOWFISH) {
        if (version_compare(PHP_VERSION, '5.3.7') >= 0) { // http://www.php.net/security/crypt_blowfish.php
            $algo_selector = '$2y$';
        } else {
            $algo_selector = '$2a$';
        }
        $workload_factor = '12$'; // (around 300ms on Core i7 machine)
        $val_arr = array(
            '.',
            '/'
        );
        $range1 = range('0', '9');
        $range2 = range('a', 'z');
        $range3 = range('A', 'Z');
        $res_arr = array_merge($val_arr, $range1, $range2, $range3);
        $salt = $algo_selector . $workload_factor . getRandomStr($res_arr, 22); // './0-9A-Za-z'
    } elseif (CRYPT_MD5) {
        $algo_selector = '$1$';
        $char1 = chr(33);
        $char2 = chr(127);
        $range = range($char1, $char2);
        $salt = $algo_selector . getRandomStr($range, 12); // actually chr(0) - chr(255), but used ASCII only
    } elseif (CRYPT_SHA512) {
        $algo_selector = '$6$';
        $workload_factor = 'rounds=5000$';
        $char1 = chr(33);
        $char2 = chr(127);
        $range = range($char1, $char2);
        $salt = $algo_selector . $workload_factor . getRandomStr($range, 16); // actually chr(0) - chr(255)
    } elseif (CRYPT_SHA256) {
        $algo_selector = '$5$';
        $workload_factor = 'rounds=5000$';
        $char1 = chr(33);
        $char2 = chr(127);
        $range = range($char1, $char2);
        $salt = $algo_selector . $workload_factor . getRandomStr($range, 16); // actually chr(0) - chr(255)
    } elseif (CRYPT_EXT_DES) {
        $algo_selector = '_';
        $val_arr = array(
            '.',
            '/'
        );
        $range1 = range('0', '9');
        $range2 = range('a', 'z');
        $range3 = range('A', 'Z');
        $res_arr = array_merge($val_arr, $range1, $range2, $range3);
        $salt = $algo_selector . getRandomStr($res_arr, 8); // './0-9A-Za-z'.
    } elseif (CRYPT_STD_DES) {
        $algo_selector = '';
        $val_arr = array(
            '.',
            '/'
        );
        $range1 = range('0', '9');
        $range2 = range('a', 'z');
        $range3 = range('A', 'Z');
        $res_arr = array_merge($val_arr, $range1, $range2, $range3);
        $salt = $algo_selector . getRandomStr($res_arr, 2); // './0-9A-Za-z'
    }
    return crypt($str, $salt);
}
/**
 * To login using social networking site accounts
 *
 * @params $profile
 * @params $provider_id
 * @params $provider
 * @params $adapter
 * @return array
 */
function social_login($profile, $provider_id, $provider, $adapter, $role_id)
{
	$bool = false;
    $provider_details = Models\Provider::where('name', ucfirst($provider))->first();
    $profile_picture_url = !empty($profile->photoURL) ? $profile->photoURL : '';
    $access_token = $profile->access_token;
    $response['profile_access_token'] = $profile->access_token;
    $access_token_secret = $profile->access_token_secret;
    $access_token_arr = (array)$profile->access_token;
    if (!empty($access_token_arr['oauth_token'])) {
        $access_token = $access_token_arr['oauth_token'];
    }
    if (!empty($access_token_arr['oauth_token_secret'])) {
        $access_token_secret = $access_token_arr['oauth_token_secret'];
    }
	$checkProviderUser = Models\ProviderUser::where('provider_id', $provider_id)->where('foreign_id', $profile->identifier)->where('is_connected', true)->first();
	$enabledIncludes = array(
			'attachment',
			'role'
		);
    if (!empty($checkProviderUser)) {
        $isAlreadyExistingUser = Models\User::with($enabledIncludes)->where('id', $checkProviderUser['user_id'])->first();
		$checkProviderUser->access_token = $access_token;
        $checkProviderUser->update();
        $ip_id = saveIp();
        if (!empty($ip_id)) {
            $isAlreadyExistingUser->last_login_ip_id = $ip_id;
        }
		
        $isAlreadyExistingUser->update();
        // Storing user_logins data
        $user_logins_data['user_agent'] = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $user_logins_data['user_id'] = $isAlreadyExistingUser['id'];
        if ($isAlreadyExistingUser['is_email_confirmed']) {
            $bool = true;
            $current_user_id = $checkProviderUser['user_id'];
            $response = array(
                'error' => array(
                    'code' => 0,
                    'message' => 'Already Connected. So just login'
                )
            );
        }
    } else {
		if (!empty($profile->email)) {
            $isAlreadyExistingUser = Models\User::with($enabledIncludes)->where('email', $profile->email)->first();
            if (!empty($isAlreadyExistingUser)) {
                $bool = true;
                $provider_user = Models\ProviderUser::where('user_id', $isAlreadyExistingUser['id'])->where('provider_id', $provider_id)->first();
                if (!empty($provider_user)) {
                    $provider_user->delete();
                }
                $provider_user_ins = new Models\ProviderUser;
                $provider_user_ins->user_id = $isAlreadyExistingUser['id'];
                $provider_user_ins->provider_id = $provider_id;
                $provider_user_ins->foreign_id = $profile->identifier;
                $provider_user_ins->access_token = $access_token;
                $provider_user_ins->access_token_secret = $access_token_secret;
                $provider_user_ins->is_connected = true;
                $provider_user_ins->profile_picture_url = $profile_picture_url;
				$provider_user_ins->save();
                $current_user_id = $isAlreadyExistingUser['id'];
                $response = array(
                    'error' => array(
                        'code' => 0,
                        'message' => 'Connected successfully'
                    )
                );
            } else {
                $user_data = new Models\User;
                $provider_users_data = new Models\ProviderUser;
                $username = strtolower(str_replace(' ', '', $profile->displayName));
                $username = $user_data->checkUserName($username);
                $ip_id = saveIp();
                $user_data->username = Inflector::slug($username, '-');
                $user_data->email = (property_exists($profile, 'email')) ? $profile->email : "";
				$user_data->first_name = $profile->given_name ? $profile->given_name : '';
				$user_data->last_name = $profile->family_name ? $profile->family_name : '';
                $user_data->password = getCryptHash('default'); // dummy password                
                $user_data->is_active = true;
				$user_data->role_id = $role_id;
                $user_data->last_logged_in_time = date('Y-m-d H:i:s');
                if (!empty($ip_id)) {
                    $user_data->last_login_ip_id = $ip_id;
                }
                $user_data->save();
                global $_server_domain_url;
                if (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1 && $provider_id == \Constants\SocialLogins::Twitter) {
					$user_data->is_email_confirmed = false;
		            $emailFindReplace = array(
		                '##USERNAME##' => $user_data->username,
		                '##ACTIVATION_URL##' => $_server_domain_url . '/activation/' . $user_data->id . '/' . md5($user_data->username)
		            );
		            sendMail('activationrequest', $emailFindReplace, $user_data->email);
		        } else {
					$user_data->is_email_confirmed = true;
                }
				$user_data->save();
                $current_user_id = $user_data->id;
				$provider_users_data->user_id = $user_data->id;
                $provider_users_data->provider_id = $provider_id;
                $provider_users_data->foreign_id = $profile->identifier;
                $provider_users_data->access_token = $access_token;
                $provider_users_data->access_token_secret = $access_token_secret;
                $provider_users_data->is_connected = true;
                $provider_users_data->profile_picture_url = $profile_picture_url;
                $provider_users_data->save();
				if ($profile_picture_url != '') {
					social_profile_image_save($profile_picture_url, $current_user_id);
				}
				$response = array(
                    'error' => array(
                        'code' => 0,
                        'message' => 'Registered and connected successfully'
                    )
                );
            }
        } else {
            $response['thrid_party_login_no_email'] = 1;
            $profile->provider_id = $provider_id;
            $profile->provider = $provider;            
        }
    }
    if (!empty($current_user_id)) {
		$enabledIncludes = array(
			'attachment',
			'role'
		);
        $user = Models\User::with($enabledIncludes)->where('id', $current_user_id)->first();
		if (!empty($user) && $profile_picture_url != '' && $user['attachment'] == null) {
            social_profile_image_save($profile_picture_url, $current_user_id);
			$user = Models\User::with($enabledIncludes)->where('id', $current_user_id)->first();
        }
        $scopes = '';
        if (!empty($user['scopes_' . $user['role_id']])) {
            $scopes = implode(' ', $user['scopes_' . $user['role_id']]);
        }
        $post_val = array(
            'grant_type' => 'password',
            'username' => $user['username'],
            'password' => $user['password'],
            'client_id' => OAUTH_CLIENT_ID,
            'client_secret' => OAUTH_CLIENT_SECRET,
            'scope' => $scopes
        );
        $result = getToken($post_val);
        $authUser = $user;
        $response['error']['code'] = 0;
        $response['user'] = $user;
        $userLogin = new Models\UserLogin;
        $userLogin->user_id = $current_user_id;
        $userLogin->ip_id = saveIp();
        $userLogin->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $userLogin->save();
		$response = $result + $user->toArray();
        Models\User::where('id', $current_user_id)->increment('user_login_count', 1);        
    }
    $response['thrid_party_login'] = 1;
    $response['thrid_party_profile'] = $profile;
    $response['already_register'] = ($bool) ? '1' : '0';
    return $response;
}
function social_profile_image_save($profile_picture_url, $current_user_id) {
	$type = pathinfo($profile_picture_url, PATHINFO_EXTENSION);
	$name = md5(time());
	$contents = _doGet($profile_picture_url);
	$fileName = $name . '.' . $type;
	$save_path = APP_PATH . '/media/tmp/' .$fileName;
	$fp = fopen($save_path, 'x');
	fwrite($fp, $contents);
	fclose($fp);
	saveImage('UserAvatar', $fileName, $current_user_id);
}
/**
 * To login using social networking site accounts
 *
 * @params $provider
 * @params $pass_value
 * @return array
 */
function social_auth_login($provider, $pass_value = array())
{
    include 'vendors/Providers/' . $provider . '.php';
    $provider_details = Models\Provider::where('name', ucfirst($provider))->first();
    $provider_id = $provider_details['id'];
    $pass_value['secret_key'] = $provider_details['secret_key'];
    $pass_value['provider_id'] = $provider_details['id'];
    $pass_value['api_key'] = $provider_details['api_key'];
    $class_name = "Providers_" . $provider;
    $adapter = new $class_name();
    if (!empty($pass_value['thrid_party_login'])) {
        return social_email_login($pass_value, $adapter);
    }
	$role_id = '';
	if (!empty($pass_value['register']) && $pass_value['register'] == 'constestant') {
		$role_id = \Constants\ConstUserTypes::Employer;
	} elseif (!empty($pass_value['register']) && $pass_value['register'] == 'company') {
		$role_id = \Constants\ConstUserTypes::Company;
	} else {
		$role_id = \Constants\ConstUserTypes::User;
	}
	if($pass_value['idToken']) {
		$profileArray = array();
		$idTokenResponse = _doGet('https://oauth2.googleapis.com/tokeninfo?id_token='.$pass_value['idToken']);
		if (!isset($idTokenResponse['error'])) {
			$profile = new stdClass;
			$profile->photoURL = !empty($idTokenResponse['picture']) ? $idTokenResponse['picture'] : '';
			$profile->identifier = $idTokenResponse['sub'];
			$profile->access_token = $pass_value['idToken'];
			$profile->access_token_secret = $pass_value['idToken'];
			$profile->email = $idTokenResponse['email'];
			$profile->displayName = $idTokenResponse['given_name'];
			$profile->given_name = $idTokenResponse['given_name'];
			$profile->family_name = $idTokenResponse['family_name'];
			$response = social_login($profile, $provider_id, $provider, $adapter, $role_id);
		} else {
			$response = $idTokenResponse;
		}
	} else {
		if (!empty($pass_value['access_token'])) {
			$access_token = $pass_value['access_token'];
		} else {
			$access_token = $adapter->getAccessToken($pass_value);
		}
		if ($access_token) {
			$profile = $adapter->getUserProfile($access_token, $provider_details);
			$profile->access_token = $profile->access_token_secret = '';
			$profile->access_token = $access_token;
			$response = social_login($profile, $provider_id, $provider, $adapter, $role_id);
		} else {
			$response = null;
		}
	}
    return $response;
}
function social_email_login($data, $adapter)
{
    $profile = (object)$data['thrid_party_profile'];
    if ($data['provider_id'] == \Constants\SocialLogins::Twitter) {
        $profile->email = $data['email'];
    }
	if (!empty($data['register']) && $data['register'] == 'constestant') {
		$profile->role_id = \Constants\ConstUserTypes::Employer;
	} elseif (!empty($data['register']) && $data['register'] == 'company') {
		$profile->role_id = \Constants\ConstUserTypes::Company;
	} else {
		$profile->role_id = \Constants\ConstUserTypes::User;
	}
	
    $provider_id = $data['provider_id'];
    $provider = $profile->provider;
    $isAlreadyRegisteredUser = Models\ProviderUser::where('provider_id', $provider_id)->where('foreign_id', $profile->identifier)->where('is_connected', true)->first();
    $checkUser = Models\User::where('email', $data['email'])->first();
    if (!($isAlreadyRegisteredUser && $checkUser)) {
        //To login using social networking site accounts
        $response = social_login($profile, $provider_id, $provider, $adapter, $profile->role_id);
    } else {
        $response['thrid_party_login'] = 1;
        $response['error']['code'] = 1;
        $response['error']['message'] = 'Already registered email';
    }
    return $response;
}
/**
 * Curl _execute
 *
 * @params string $url
 * @params string $method
 * @params array $method
 * @params string $format
 *
 * @return array
 */
function _execute($url, $method = 'get', $post = array(), $format = 'plain')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($method == 'get') {
        curl_setopt($ch, CURLOPT_POST, false);
    } elseif ($method == 'post') {
        if ($format == 'json') {
            $post_string = json_encode($post);
            $header = array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($post_string)
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        } else {
            $post_string = http_build_query($post, '', '&');
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    } elseif ($method == 'put') {
        if ($format == 'json') {
            $post_string = json_encode($post);
            $header = array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($post_string)
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        } else {
            $post_string = http_build_query($post, '', '&');
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    } elseif ($method == 'delete') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    $response = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	// Note: timeout also falls here...
    if (curl_errno($ch)) {
        $return['error']['message'] = curl_error($ch);
        curl_close($ch);
        return $return;
    }
    switch ($http_code) {
        case 201:
        case 200:
            if (isJson($response)) {
                $return = safe_json_decode($response);
            } else {
                $return = $response;
            }
            break;
		case 400:
			if (isJson($response)) {
                $data = safe_json_decode($response);
				$return['error']['message'] = $data['error_description'] ? $data['error_description'] :'Invalid Request';
            } else {
                $return = $response;
				$return['error']['message'] = 'Invalid Request';
            }
			$return['error']['code'] = 1;
            break;
        case 401:
            $return['error']['code'] = 1;
            $return['error']['message'] = 'Unauthorized';
            break;

        default:
            $return['error']['code'] = 1;
            $return['error']['message'] = 'Not Found';
    }
    curl_close($ch);
    return $return;
}
/**
 * To check whether it is json or not
 *
 * @param json $string To check string is a JSON or not
 *
 * @return mixed
 */
function isJson($string)
{
    json_decode($string);
    //check last json error
    return (json_last_error() == JSON_ERROR_NONE);
}
/**
 * safe Json code
 *
 * @param json $json   json data
 *
 * @return array
 */
function safe_json_decode($json)
{
    $return = json_decode($json, true);
    if ($return === null) {
        $error['error']['code'] = 1;
        $error['error']['message'] = 'Syntax error, malformed JSON';
        return $error;
    }
    return $return;
}
/**
 * Get request by using CURL
 *
 * @param string $url    URL to execute
 *
 * @return mixed
 */
function _doGet($url)
{
    $return = _execute($url);
    return $return;
}
/**
 * Post request by using CURL
 *
 * @param string $url    URL to execute
 * @param array  $post   Post data
 * @param string $format To differentiate post data in plain or json format
 *
 * @return mixed
 */
function _doPost($url, $post = array(), $format = 'plain')
{
    return _execute($url, 'post', $post, $format);
}
/**
 * Render Json Response
 *
 * @param array $response    response
 * @param string  $message  Messgae
 * @param string  $fields  fields
 * @param int  $isError  isError
 * @param int  $statusCode  Status code
 *
 * @return json response
 */
function renderWithJson($response, $message = '', $fields = '', $isError = 0, $statusCode = 200)
{
    global $app;
    $appResponse = $app->getContainer()->get('response');
    // if (!empty($fields)) {
        // $statusCode = 422;
    // }
    $error = array(
        'error' => array(
            'code' => $isError,
            'message' => $message,
            'fields' => $fields
        )
    );
	$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$message = ' Request GET: '.json_encode($_GET).'  Request POST: '.json_encode($_POST).' Response : '.json_encode(array_merge($response,$error));
	$data = "\r\n[BID Logging][".date("Y-m-d h:i:s")."] ".$actual_link."\r\n-----------------".$message.PHP_EOL;
	$filePath = APP_PATH.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'debug.log';
	$fp = fopen($filePath, 'a')or die ("Error - debug.log cannot be opened");;
	fwrite($fp, $data);
    return $appResponse->withJson($response + $error, $statusCode);
}
/**
 * Findorsave city details
 *
 * @params string $data
 * @params int $country_id
 * @params int $state_id
 *
 * @return int IP id
 */
function findOrSaveAndGetCityId($data, $country_id, $state_id)
{
    $city = new Models\City;
    $city_list = $city->where('name', $data)->where('state_id', $state_id)->where('country_id', $country_id)->select('id')->first();
    if (!empty($city_list)) {
        return $city_list['id'];
    } else {
        $city->name = $data;
        $city->slug = Inflector::slug(strtolower($data), '-');
        $city->country_id = $country_id;
        $city->state_id = $state_id;
        $city->save();
        return $city->id;
    }
}
/**
 * Findorsave state details
 *
 * @params string $data
 * @params int $country_id
 *
 * @return int IP id
 */
function findOrSaveAndGetStateId($data, $country_id)
{
    $state = new Models\State;
    $state_list = $state->where('name', $data)->where('country_id', $country_id)->select('id')->first();
    if (!empty($state_list)) {
        return $state_list['id'];
    } else {
        $state->name = $data;
        $state->country_id = $country_id;
        $state->save();
        return $state->id;
    }
}
/**
 * Get country id
 *
 * @param int $iso2  ISO2
 *
 * @return int country Id
 */
function findCountryIdFromIso2($iso2)
{
    $country = Models\Country::where('iso_alpha2', $iso2)->select('id')->first();
    if (!empty($country)) {
        return $country['id'];
    }
}
/*
 * Attachment Save function
 *
 * @param class_name,file,foreign_id
 *
 *
*/
function saveImage($class_name, $file, $foreign_id, $is_multi = false, $user_id = null, $ispaid = 0, $args = array()) {
    if (($class_name == 'UserAvatar' || $class_name == 'Brand' || $class_name == 'City' || $class_name == 'Cuisine' || $class_name == 'Theme' || $class_name == 'Advertisement') && (!empty($file)) && (file_exists(APP_PATH . '/media/tmp/' . $file))) {
        //Removing and re-inserting new image
        $userImg = Models\Attachment::where('foreign_id', $foreign_id)->where('class', $class_name)->first();
        if (!empty($userImg) && !($is_multi)) {
            if (file_exists(APP_PATH . '/media/' . $class_name . '/' . $foreign_id . '/' . $userImg['filename'])) {
                unlink(APP_PATH . '/media/' . $class_name . '/' . $foreign_id . '/' . $userImg['filename']);
                $userImg->delete();
            }
            // Removing Thumb folder images
            $mediadir = APP_PATH . '/client/images/';
			$thumbs = array('big_thumb', 'large_thumb', 'micro_thumb', 'small_thumb', 'medium_thumb', 'normal_thumb', 'original');
            foreach ($thumbs as $value) {
				$list = glob($mediadir . $value . '/' . $class_name . '/' . $foreign_id . '.*');
                if ($list) {
                    @unlink($list[0]);
                }
            }
			$mediadir = APP_PATH . '/client/images/mobile';
			$thumbs = array('big_thumb', 'large_thumb', 'micro_thumb', 'small_thumb', 'medium_thumb', 'normal_thumb', 'original');
            foreach ($thumbs as $value) {
				$list = glob($mediadir . $value . '/' . $class_name . '/' . $foreign_id . '.*');
                if ($list) {
                    @unlink($list[0]);
                }
            }
        }
	}	
	$attachment = new Models\Attachment;
	if (!file_exists(APP_PATH . '/media/' . $class_name . '/' . $foreign_id)) {
		mkdir(APP_PATH . '/media/' . $class_name . '/' . $foreign_id, 0777, true);
	}
	$src = APP_PATH . '/media/tmp/' . $file;
	$dest = APP_PATH . '/media/' . $class_name . '/' . $foreign_id . '/' . $file;
	copy($src, $dest);
	unlink($src);
	$info = getimagesize($dest);
	$width = $info[0];
	$height = $info[1];
	$attachment->user_id = $user_id;
	$attachment->filename = $file;
	$attachment->width = $width;
	$attachment->height = $height;
	$attachment->dir = $class_name . '/' . $foreign_id;
	$attachment->foreign_id = $foreign_id;
	$attachment->class = $class_name;
	if (!empty($args)) {
		$attachment->location =$args['location'];
		$attachment->caption = $args['caption'];
	}
	$attachment->ispaid = $ispaid;
	$attachment->mimetype = $info['mime'];
	$attachment->save();
	$ext = strtolower(substr($file, -4));
	if ($class_name == 'ContestUserDeliveryFile' && $ext == '.zip') {
		$targetdir = $dest;
		$targetzip = APP_PATH . '/media/' . $class_name . '/' . $foreign_id . '/zip/';
		if (is_dir($targetzip)) {
			rmdir_recursive($targetzip);
		}
		mkdir($targetzip, 0777, true);
		$zip = new ZipArchive;
		if ($zip->open($dest) === true) {
			$zip->extractTo($targetzip);
			$zip->close();
		}
	}
	return $attachment->id;
}
function human_filesize($bytes, $decimals = 2)
{
    $factor = floor((strlen($bytes) - 1) / 3);
    if ($factor > 0) {
        $sz = 'KMGT';
    }
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
}
function listFolderFiles($dir, $parent_key = '', $main_dir = array())
{
    $directories = scandir($dir);
    foreach ($directories as $key => $directory) {
        if ($directory != '.' && $directory != '..') {
            if (is_dir($dir . '/' . $directory)) {
                $main_directory = $directory;
                if ($parent_key) {
                    $main_directory = $parent_key . '/' . $directory;
                }
                $main_directories = listFolderFiles($dir . '/' . $directory, $main_directory, $main_dir);
                if ($main_directories) {
                    foreach ($main_directories as $main_directory) {
                        $main_dir[] = ['name' => $main_directory['name'], 'size' => human_filesize(filesize($dir . '/' . $directory)) ];
                    }
                }
            } else {
                if ($parent_key) {
                    $main_dir[] = ['name' => $parent_key . '/' . $directory, 'size' => human_filesize(filesize($dir . '/' . $directory)) ];
                } else {
                    $main_dir[] = ['name' => $directory, 'size' => human_filesize(filesize($dir . '/' . $directory)) ];
                }
            }
        }
    }
    return $main_dir;
}
/**
 * Findorsave Company details
 *
 * @params string $data
 *
 * @return int IP id
 */
function findOrSaveAndGetCompanyId($data, $user_id = 0)
{
    $company = new Models\Company;
    $company_list = $company->where('name', $data['name'])->select('id')->first();
    if (!empty($company_list)) {
        return $company_list['id'];
    } else {
        $company = new Models\Company;
        $validationErrorFields = $company->validate($data);
        if (empty($validationErrorFields)) {
            $company->name = strtolower($data['name']);
            $company->slug = Inflector::slug(strtolower($data['name']), '-');
            if (!empty($data['website'])) {
                $company->website = $data['website'];
            }
            $company->user_id = $user_id;
            $company->save();
            if (!empty($args['image'])) {
                saveImage('Companys', $data['image'], $company->id);
            }
            return $company->id;
        } else {
            return $validationErrorFields;
        }
    }
}
/**
 * Job Table count
 *
 * @params string $tableName, $fieldName, $jobId
 *
 * @return Jobs
 */
function jobTableCountUpdation($tableName, $fieldName, $jobId, $userId)
{
    $table = 'Models\\' . $tableName;
    $count = $table::where('job_id', $jobId)->count();
    $Jobs = Models\Job::find($jobId);
    $Jobs->$fieldName = $count;
    $Jobs->update();
    /**User table update count ***/
    if (!empty($userId)) {
        $userCount = $table::where('user_id', $userId)->count();
        $user = Models\User::find($userId);
        $user->$fieldName = $userCount;
        $user->update();
        return $user;
    }
}
function findAndSavePortfolioSkill($skill)
{
    $portfolio_skill = Models\Skill::where('name', $skill)->select('id')->first();
    if (!empty($portfolio_skill)) {
        return $portfolio_skill['id'];
    } else {
        $portfolio_skill = new Models\Skill;
        $portfolio_skill->name = $skill;
        $portfolio_skill->slug = Inflector::slug(strtolower($skill), '-');
        $portfolio_skill->is_active = 1;
        $portfolio_skill->save();
        return $portfolio_skill->id;
    }
}
function quoteTableCountUpdation($tableName, $fieldName, $quoteId, $userId)
{
    $table = 'Models\\' . $tableName;
    $count = $table::where('quote_service_id', $quoteId)->count();
    $quotes = Models\QuoteService::find($quoteId);
    $quotes->$fieldName = $count;
    $quotes->update();
    /**User table update count ***/
    if (!empty($userId)) {
        $userCount = $table::where('user_id', $userId)->count();
        $user = Models\User::find($userId);
        $user->$fieldName = $userCount;
        $user->update();
        return $user;
    }
}
function quoteCategoriesTabeCountUpdation($tableName, $fieldName, $categoryId)
{
    $table = 'Models\\' . $tableName;
    $count = $table::where('quote_category_id', $categoryId)->count();
    $quotes = Models\QuoteCategory::find($categoryId);
    $quotes->$fieldName = $count;
    $quotes->update();
}
function portfolioTabeCountUpdation($tableName, $fieldName, $portfolioId, $userId)
{
    $table = 'Models\\' . $tableName;
    if ($portfolioId) {
        $count = $table::where('portfolio_id', $portfolioId)->count();
        $portfolio = Models\Portfolio::find($portfolioId);
        $portfolio->$fieldName = $count;
        $portfolio->update();
    }
    /**User table update count ***/
    if (!empty($userId)) {
        $userCount = $table::where('user_id', $userId)->count();
        $user = Models\User::find($userId);
        $user->$fieldName = $userCount;
        $user->update();
        return $user;
    }
}
function gen_uuid()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        // 16 bits for "time_mid"
        mt_rand(0, 0xffff),
        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand(0, 0x0fff) | 0x4000,
        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand(0, 0x3fff) | 0x8000,
        // 48 bits for "node"
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}
function getZazPayObject()
{
    $payment_gateway_settings = Models\PaymentGatewaySetting::where('payment_gateway_id', \Constants\PaymentGateways::ZazPay)->get();
    foreach ($payment_gateway_settings as $value) {
        $sudpay_synchronize['sandbox'][$value['name']] = $value['test_mode_value'];
        $sudpay_synchronize['live'][$value['name']] = $value['live_mode_value'];
    }
    $sanbox_mode = true;
    $payment_gateways = Models\PaymentGateway::select('is_test_mode')->where('id', \Constants\PaymentGateways::ZazPay)->first();
    if (empty($payment_gateways->is_test_mode)) {
        $sanbox_mode = false;
    }
    if ($sanbox_mode) {
        $sudpay_synchronize = $sudpay_synchronize['sandbox'];
    } else {
        $sudpay_synchronize = $sudpay_synchronize['live'];
    }
    $s = new ZazPay_API(array(
        'api_key' => !empty($sudpay_synchronize['zazpay_api_key']) ? $sudpay_synchronize['zazpay_api_key'] : '',
        'merchant_id' => !empty($sudpay_synchronize['zazpay_merchant_id']) ? $sudpay_synchronize['zazpay_merchant_id'] : '',
        'website_id' => !empty($sudpay_synchronize['zazpay_website_id']) ? $sudpay_synchronize['zazpay_website_id'] : '',
        'secret_string' => !empty($sudpay_synchronize['zazpay_secret_string']) ? $sudpay_synchronize['zazpay_secret_string'] : '',
        'is_test' => !empty($sanbox_mode) ? 1 : 0
    ));
    return $s;
}
function register_website_account($recall = false)
{
    $result = array();
    $recall = true;
    $zazpay_file_path = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'Slim' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'Common' . DIRECTORY_SEPARATOR . 'ZazPay' . DIRECTORY_SEPARATOR . 'index.php';
    if (!file_exists($zazpay_file_path)) {
        return $result;
    }
    $setting_account = Models\Setting::where('name', "SITE_IS_WEBSITE_CREATED")->first();
    if (SITE_IS_ENABLE_ZAZPAY_PLUGIN == 0 && $setting_account['value'] == 0 && $recall) {
        $setting_domain_secret_hash = Models\Setting::where('name', "SITE_DOMAIN_SECRET_HASH")->first();
        if (empty($setting_domain_secret_hash['value'])) {
            $domain_hash_value = gen_uuid();
            Models\Setting::where('name', 'SITE_DOMAIN_SECRET_HASH')->update(array(
                'value' => $domain_hash_value
            ));
        } else {
            $domain_hash_value = $setting_domain_secret_hash['value'];
        }
        $paymentGateway = Models\PaymentGateway::where('name', "ZazPay")->first();
        $s = getZazPayObject();
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $postdata['domain_name'] = $protocol . $domainName;
        $postdata['domain_secret_hash'] = $domain_hash_value;
        $credentials = $s->callRegisterWebsiteAccount($postdata);
        if (isset($credentials['error']['code']) && $credentials['error']['code'] == 0) {
            $value_field_name = 'live_mode_value';
            if ($paymentGateway['is_test_mode']) {
                $value_field_name = 'test_mode_value';
            }
            $paymentGatewaySettingNameToBeUpdate = array(
                'zazpay_merchant_id' => $credentials['merchant_id'],
                'zazpay_website_id' => $credentials['website_id'],
                'zazpay_secret_string' => $credentials['secret'],
                'zazpay_api_key' => $credentials['api_key']
            );
            foreach ($paymentGatewaySettingNameToBeUpdate as $tableFieldName => $ZazPayReturnValue) {
                $payment_gateway_settings = Models\PaymentGatewaySetting::where('payment_gateway_id', $paymentGateway['id'])->where('name', $tableFieldName)->update(array(
                    $value_field_name => $ZazPayReturnValue
                ));
            }
            $s1 = getZazPayObject();
            $currentPlan = $s1->callPlan();
            $plantype = $s1->plantype();
            if (!empty($currentPlan['error']['message'])) {
            } else {
                if ($currentPlan['brand'] == 'Transparent Branding') {
                    $plan = $plantype['TransparentBranding'];
                } elseif ($currentPlan['brand'] == 'ZazPay Branding') {
                    $plan = $plantype['VisibleBranding'];
                } elseif ($currentPlan['brand'] == 'Any Branding') {
                    $plan = $plantype['AnyBranding'];
                }
                $paymentGatewaySetting = new Models\PaymentGatewaySetting();
                if ($paymentGateway['is_test_mode']) {
                    $payment_gateway_plan = $paymentGatewaySetting->where('name', 'zazpay_subscription_plan')->where('payment_gateway_id', \Constants\PaymentGateways::ZazPay)->first();
                    $payment_gateway_plan->test_mode_value = $currentPlan['name'];
                    $payment_gateway_plan->save();
                } else {
                    $payment_gateway_plan = $paymentGatewaySetting->where('name', 'zazpay_subscription_plan')->where('payment_gateway_id', \Constants\PaymentGateways::ZazPay)->first();
                    $payment_gateway_plan->live_mode_value = $currentPlan['name'];
                    $payment_gateway_plan->save();
                }
                $gateway_response = $s1->callGateways();
                global $capsule;
                $capsule::statement("TRUNCATE TABLE zazpay_payment_gateways_users CASCADE;");
                $capsule::statement("TRUNCATE TABLE zazpay_payment_gateways CASCADE;");
                $capsule::statement("TRUNCATE TABLE zazpay_payment_groups CASCADE;");
                foreach ($gateway_response['gateways'] as $gateway_group) {
                    $sudo_groups = new Models\ZazpayPaymentGroup;
                    $sudo_groups->zazpay_group_id = $gateway_group['id'];
                    $sudo_groups->name = $gateway_group['name'];
                    $sudo_groups->thumb_url = $gateway_group['thumb_url'];
                    $sudo_groups->save();
                    foreach ($gateway_group['gateways'] as $gateway) {
                        $sudo_payment_gateways = new Models\ZazpayPaymentGateway;
                        $supported_actions = $gateway['supported_features'][0]['actions'];
                        $sudo_payment_gateways->is_marketplace_supported = 0;
                        if (in_array('Marketplace-Auth', $supported_actions)) {
                            $sudo_payment_gateways->is_marketplace_supported = 1;
                        }
                        $sudo_payment_gateways->zazpay_gateway_id = $gateway['id'];
                        $sudo_payment_gateways->zazpay_gateway_details = serialize($gateway);
                        $sudo_payment_gateways->zazpay_gateway_name = $gateway['display_name'];
                        $sudo_payment_gateways->zazpay_payment_group_id = $sudo_groups->id;
                        $sudo_payment_gateways->save();
                    }
                }
                //is_website_account_created settings update
                $settings = Models\Setting::where('name', "SITE_IS_WEBSITE_CREATED")->first();
                $settings->value = 1;
                $settings->id = $settings['id'];
                $settings->save();
                $result = array(
                    'status' => 'success',
                    'message' => 'Website account created'
                );
            }
        } else {
            $result = array(
                'error' => array(
                    'code' => 0,
                    'message' => 'Website account already Created'
                )
            );
        }
        return $result;
    }
}
function quoteRequestTableUpdationForQuoteBid($request_id)
{
    $quoteRequests = Models\QuoteRequest::find($request_id);
    if (!empty($quoteRequests)) {
        $quote_bid_count = Models\QuoteBid::where('quote_request_id', $request_id)->count();
        $quoteRequests->quote_bid_count = $quote_bid_count;
        $quoteRequests->quote_bid_new_count = Models\QuoteBid::where('quote_request_id', $request_id)->where('quote_status_id', \Constants\QuoteStatus::NewBid)->count();
        $quoteRequests->quote_bid_discussion_count = Models\QuoteBid::where('quote_request_id', $request_id)->where('quote_status_id', \Constants\QuoteStatus::UnderDiscussion)->where('is_show_bid_to_requestor', 1)->count();
        $quoteRequests->quote_bid_pending_discussion_count = Models\QuoteBid::where('quote_request_id', $request_id)->where('quote_status_id', \Constants\QuoteStatus::UnderDiscussion)->where('is_show_bid_to_requestor', 0)->count();
        $quoteRequests->quote_bid_hired_count = Models\QuoteBid::where('quote_request_id', $request_id)->where('quote_status_id', \Constants\QuoteStatus::Hired)->count();
        $quoteRequests->quote_bid_completed_count = Models\QuoteBid::where('quote_request_id', $request_id)->where('quote_status_id', \Constants\QuoteStatus::Completed)->count();
        $quoteRequests->quote_bid_closed_count = Models\QuoteBid::where('quote_request_id', $request_id)->where('quote_status_id', \Constants\QuoteStatus::Closed)->count();
        $quoteRequests->quote_bid_not_completed_count = Models\QuoteBid::where('quote_request_id', $request_id)->where('quote_status_id', \Constants\QuoteStatus::NotCompleted)->count();
        $quoteRequests->save();
    }
}
function QuoteServiceTableCountUpdationForQuoteBidStatusChange($quote_service_id)
{
    $quote_service_details = Models\QuoteService::find($quote_service_id);
    if (!empty($quote_service_details)) {
        $quote_service_details->quote_bid_count = Models\QuoteBid::where('quote_service_id', $quote_service_id)->count();
        $quote_service_details->quote_bid_new_count = Models\QuoteBid::where('quote_service_id', $quote_service_id)->where('quote_status_id', \Constants\QuoteStatus::NewBid)->count();
        $quote_service_details->quote_bid_discussion_count = Models\QuoteBid::where('quote_service_id', $quote_service_id)->where('quote_status_id', \Constants\QuoteStatus::UnderDiscussion)->count();
        $quote_service_details->quote_bid_hired_count = Models\QuoteBid::where('quote_service_id', $quote_service_id)->where('quote_status_id', \Constants\QuoteStatus::Hired)->count();
        $quote_service_details->quote_bid_completed_count = Models\QuoteBid::where('quote_service_id', $quote_service_id)->where('quote_status_id', \Constants\QuoteStatus::Completed)->count();
        $quote_service_details->quote_bid_not_completed_count = Models\QuoteBid::where('quote_service_id', $quote_service_id)->where('quote_status_id', \Constants\QuoteStatus::NotCompleted)->count();
        $quote_service_details->quote_bid_closed_count = Models\QuoteBid::where('quote_service_id', $quote_service_id)->where('quote_status_id', \Constants\QuoteStatus::Closed)->count();
        $quote_service_details = $quote_service_details->save();
    }
    return true;
}
function insertViews($foreign_id, $class)
{
    global $authUser;
    $user_id = 0;
    if (!empty($authUser)) {
        $user_id = $authUser->id;
    }
    if (!empty($user_id)) {
        $view = new Models\View;
		$view->created_at = date('Y-m-d h:i:s');
		$view->updated_at = date('Y-m-d h:i:s');
        $view->user_id = $user_id;
        $view->ip_id = saveIp();
        $view->foreign_id = $foreign_id;
        $view->class = $class;
        $view->save();
        $model = 'Models\\' . $class;
        $view_count = count(Models\View::select('ip_id')->distinct()->where('class', $class)->distinct()->where('foreign_id', $foreign_id)->get());
        $model::where('id', $foreign_id)->update(array(
            'view_count' => $view_count
        ));
    } else {
		$view = new Models\View;
		$view->created_at = date('Y-m-d h:i:s');
		$view->updated_at = date('Y-m-d h:i:s');
        $view->ip_id = saveIp();
        $view->foreign_id = $foreign_id;
        $view->class = $class;
        $view->save();
        $model = 'Models\\' . $class;
        $view_count = count(Models\View::select('ip_id')->distinct()->where('class', $class)->distinct()->where('foreign_id', $foreign_id)->get());
        $model::where('id', $foreign_id)->update(array(
            'view_count' => $view_count
        ));
	}
}
function saveMessage($depth, $path, $user_id, $other_user_id, $message_content_id, $parent_id, $class, $foreign_id, $is_sender, $model_id = 0, $is_private)
{
    $message = new Models\Message;
    $message->depth = $depth;
    $message->user_id = $user_id;
    $message->other_user_id = $other_user_id;
    $message->message_content_id = $message_content_id;
    $message->parent_id = $parent_id;
    $message->class = $class;
    $message->is_sender = $is_sender;
    $message->foreign_id = $foreign_id;
    $message->model_id = $model_id;
    $message->is_private = $is_private;
    $message->save();
    $idConverted = base_convert($message->id, 10, 36);
    $materialized_path = sprintf("%08s", $idConverted);
    if (empty($path)) {
        $message->materialized_path = $materialized_path;
    } else {
        $message->materialized_path = $path . '-' . $materialized_path;
    }
    $message->root = checkParentMessage($parent_id, $message->id);
    $message->save();
    Models\Message::where('root', $message->root)->update(array(
        'freshness_ts' => date('Y-m-d h:i:s')
    ));
    return $message->id;
}
function checkParentMessage($parent_id, $id)
{
    $parentMessage = Models\Message::where('id', $parent_id)->select('id', 'parent_id')->first();
    if (!empty($parentMessage)) {
        if (!empty($parentMessage->parent_id)) {
            checkParentMessage($parentMessage->parent_id, $parentMessage->id);
        } else {
            return $parentMessage->id;
        }
    }
    return $id;
}
function insertActivities($user_id, $other_user_id, $class, $foreign_id, $from_status_id, $to_status_id, $activity_type, $model_id = 0, $amount = 0, $model_class = '')
{
    $activity = new Models\Activity;
    $activity->user_id = $user_id;
    $activity->other_user_id = $other_user_id;
    $activity->foreign_id = $foreign_id;
    $activity->class = $class;
    $activity->from_status_id = $from_status_id;
    $activity->to_status_id = $to_status_id;
    $activity->activity_type = $activity_type;
    $activity->model_id = $model_id;
    $activity->model_class = $model_class;
    if (empty($model_class)) {
        $activity->model_class = $class;
    }  
    $activity->amount = $amount;
    if (in_array($class, ['Bid', 'Milestone', 'ProjectBidInvoice', 'ProjectDispute', 'HireRequest'])) {
        $activity->model_class = 'Project';
    } elseif ($class == 'QuoteBid') {
        $activity->model_class = 'QuoteService';
    } elseif ($class == 'ContestUser') {
        $activity->model_class = 'Contest';
    } elseif ($class == 'JobApply') {
        $activity->model_class = 'Job';
    } elseif ($class == 'PortfolioComment') {
        $activity->model_class = 'Portfolio';
    }
    $activity->save();
    if (!empty($other_user_id)) {
        $user = Models\User::where('id', $other_user_id)->select('id')->first();
        $user->is_have_unreaded_activity = 1;
        $user->save();
    }
}
function insertTransaction($user_id, $to_user_id, $class, $transaction_type, $payment_gateway_id, $amount, $site_revenue_from_freelancer, $gateway_fees, $coupon_id = 0, $site_revenue_from_employer = 0, $foreign_id = null, $isSanbox = false, $parent_user_id = 0, $transactionStatus = '', $transactionId = '', $senderTransactionId = '')
{
    $transaction = new Models\Transaction;
    $transaction->user_id = $user_id;
    $transaction->to_user_id = $to_user_id;
    $transaction->class = $class;
    $transaction->transaction_type = $transaction_type;
    $transaction->payment_gateway_id = $payment_gateway_id;
    $transaction->amount = $amount;
    $transaction->site_revenue_from_freelancer = $site_revenue_from_freelancer;
    $transaction->site_revenue_from_employer = $site_revenue_from_employer;
    $transaction->coupon_id = $coupon_id;
    $transaction->foreign_id = $foreign_id;
	$transaction->is_sanbox = ($isSanbox == 1) ? true : false;
	$transaction->parent_user_id = $parent_user_id;
	$transaction->transactionStatus = $transactionStatus;
	$transaction->transactionId = $transactionId;
	$transaction->senderTransactionId = $senderTransactionId;
    $transaction->save();
    return $transaction->id;
}
/**
 * Get country Name
 *
 * @param int $iso2  ISO2
 *
 * @return string country name
 */
function getCountryNameFromId($id)
{
    $country = Models\Country::where('id', $id)->select('name')->first();
    if (!empty($country)) {
        return $country['name'];
    }
}
function getToUserIdFromClass($foreign_id, $class)
{
    $model = 'Models\\' . $class;
    $user_id = 0;
    $modelDetails = $model::where('id', $foreign_id)->select('user_id')->first();
    if (!empty($modelDetails)) {
        $modelDetails = $modelDetails->toArray();
        $user_id = $modelDetails['user_id'];
    }
    return $user_id;
}
function getContestId($foreign_id, $class)
{
    $model = 'Models\\' . $class;
    $contest_id = 0;
    $modelDetails = $model::where('id', $foreign_id)->select('contest_id')->first();
    if (!empty($modelDetails)) {
        $modelDetails = $modelDetails->toArray();
        $contest_id = $modelDetails['contest_id'];
    }
    return $contest_id;
}
function getQuoteServiceId($foreign_id)
{
    $service_id = 0;
    $modelDetails = Models\QuoteBid::where('id', $foreign_id)->select('quote_service_id')->first();
    if (!empty($modelDetails)) {
        $modelDetails = $modelDetails->toArray();
        $service_id = $modelDetails['quote_service_id'];
    }
    return $service_id;
}
function getUserHiddenFields($user_id)
{
    $user = Models\User::select('email', 'username', 'available_wallet_amount')->where('id', $user_id)->first();
    $user->makeVisible(array(
        'email'
    ));
    return $user;
}
//To sand mail to admin when contest has been added
function sendAlertOnContestAdd($contest, $emailType)
{
    global $_server_domain_url;
    $contestUserDetails = getUserHiddenFields($contest->user_id);
    $emailFindReplace = array(
        '##CONTEST_HOLDER##' => $contestUserDetails->username,
        '##CONTEST_NAME##' => $contest->name,
        '##CONTEST_URL##' => $_server_domain_url . '/contests/' . $contest->id,
        '##SITE_NAME##' => SITE_NAME,
        '##SITE_URL##' => $_server_domain_url,
    );
    $user = Models\User::select('email')->where('role_id', \Constants\ConstUserTypes::Admin)->first();
    $user->makeVisible(array(
        'email'
    ));
    sendMail($emailType, $emailFindReplace, $user->email);
}
//To sand mail to participants when contest has been added
function sendAlertToParticipantsOnContestAdd($contest, $emailType)
{
    global $_server_domain_url;
    $participants = Models\ContestUser::where('contest_id', $contest->id)->where('user_id', '!=', 0)->get();
    if (!empty($participants)) {
        foreach ($participants as $participant) {
            $participantUserDetails = getUserHiddenFields($participant->user_id);
            $contestUserDetails = getUserHiddenFields($contest->user_id);
            $emailFindReplace = array(
                '##CONTEST_HOLDER##' => $contestUserDetails->username,
                '##CONTEST_NAME##' => $contest->name,
                '##USER_NAME##' => $participantUserDetails->username,
                '##CONTEST_URL##' => $_server_domain_url . '/contests/' . $contest->id,
                '##SITE_NAME##' => SITE_NAME,
                '##SITE_URL##' => $_server_domain_url,
            );
            sendMail($emailType, $emailFindReplace, $user->email);
        }
    }
}
function sendContestStatusChangeAlert($contest, $status_id)
{
    $contestUsers = Models\ContestUser::where('contest_id', $contest->id)->get();
    global $_server_domain_url;
    $contestStatus = Models\ContestStatus::pluck('name', 'id');
    if (!empty($contestUsers)) {
        foreach ($contestUsers as $contestUser) {
            Models\ContestUser::where('id', $contestUser->id)->update(array(
                'contest_user_status_id' => \Constants\ConstContestUserStatus::Lost
            ));
            $userDetails = getUserHiddenFields($contestUser->user_id);
            $emailFindReplace = array(
                '##PARTICIPANT##' => $userDetails->username,
                '##CONTEST_NAME##' => $contest->name,
                '##CONTEST_STATUS##' => $contestStatus[$status_id],
                '##CONTEST_URL##' => $_server_domain_url . '/contests/' . $contest->id
            );
            sendMail('conteststatuschangealert', $emailFindReplace, $userDetails->email);
        }
    }
}
function sendContestStatusChangeForWinnerSelectAlert($contest, $status_id)
{
    $contestUsers = Models\ContestUser::where('contest_id', $contest->id)->get();
    global $_server_domain_url;
    $contestStatus = Models\ContestStatus::pluck('name', 'id');
    if (!empty($contestUsers)) {
        foreach ($contestUsers as $contestUser) {
            $userDetails = getUserHiddenFields($contestUser->user_id);
            $emailFindReplace = array(
                '##PARTICIPANT##' => $userDetails->username,
                '##CONTEST_NAME##' => $contest->name,
                '##CONTEST_STATUS##' => $contestStatus[$status_id],
                '##CONTEST_URL##' => $_server_domain_url . '/contests/' . $contest->id
            );
            sendMail('conteststatuschangealert', $emailFindReplace, $userDetails->email);
        }
    }
}
function sendContestActivityAlert($contest, $new_status, $old_status)
{
    global $_server_domain_url;
    $contestUserDetails = getUserHiddenFields($contest->user_id);
    $contestStatus = Models\ContestStatus::pluck('name', 'id');
    $emailFindReplace = array(
        '##USERNAME##' => $contestUserDetails->username,
        '##CONTEST_NAME##' => $contest->name,
        '##PREVIOUS_STATUS##' => $contestStatus[$old_status],
        '##CURRENT_STATUS##' => $contestStatus[$new_status],
        '##CONTEST_URL##' => $_server_domain_url . '/contests/' . $contest->id
    );
    sendMail('contestactivityalert', $emailFindReplace, $contestUserDetails->email);
}
function sendEntryStatusChangeAlert($contestUser, $new_status, $old_status)
{
    global $_server_domain_url;
    $contestName = Models\Contest::where('id', $contestUser->contest_id)->select('name')->first();
    $contestUserDetails = getUserHiddenFields($contestUser->user_id);
    $contestUserStatus = Models\ContestUserStatus::pluck('name', 'id');
    $emailFindReplace = array(
        '##PARTICIPANT##' => $contestUserDetails->username,
        '##CONTEST_NAME##' => $contestName->name,
        '##PREVIOUS_STATUS##' => $contestUserStatus[$old_status],
        '##CURRENT_STATUS##' => $contestUserStatus[$new_status],
    );
    sendMail('entrystatuschangealert', $emailFindReplace, $contestUserDetails->email);
}
function updateSiteCommissionFromEmployer($commision_amount, $bid_id, $project_id, $user_id)
{
    $milestoneTotalAmount = Models\Milestone::where('project_id', $project_id)->where('bid_id', $bid_id)->whereIn('milestone_status_id', [\Constants\MilestoneStatus::EscrowFunded, \Constants\MilestoneStatus::Completed, \Constants\MilestoneStatus::RequestedForRelease, \Constants\MilestoneStatus::EscrowReleased])->selectRaw('sum(site_commission_from_employer) as site_commission_from_employer')->first()->toArray();
    $invoiceTotalAmount = Models\ProjectBidInvoice::where('project_id', $project_id)->where('is_paid', 1)->where('bid_id', $bid_id)->selectRaw('sum(site_commission_from_employer) as site_commission_from_employer')->first()->toArray();
    $dispatcher = Models\Bid::getEventDispatcher();
    Models\Bid::unsetEventDispatcher();
    Models\Bid::where('id', $bid_id)->update(array(
        'site_commission_from_employer' => ($milestoneTotalAmount['site_commission_from_employer'] + $invoiceTotalAmount['site_commission_from_employer'])
    ));
    Models\Bid::setEventDispatcher($dispatcher);
    $dispatcher = Models\Project::getEventDispatcher();
    Models\Project::unsetEventDispatcher();
    Models\Project::where('id', $project_id)->update(array(
        'site_commission_from_employer' => ($milestoneTotalAmount['site_commission_from_employer'] + $invoiceTotalAmount['site_commission_from_employer'])
    ));
    Models\Project::setEventDispatcher($dispatcher);
    $employerCommission = Models\Project::where('user_id', $user_id)->selectRaw('sum(site_commission_from_employer) as site_commission_from_employer')->first()->toArray();
    if (!empty($employerCommission['site_commission_from_employer'])) {
         Models\User::where('id', $user_id)->update(array(
            'total_site_revenue_as_employer' => $employerCommission['site_commission_from_employer']
        ));
    }
}
function getSlug($title)
{
    $slug = '';
    $pagesCount = Models\Page::where('title', $title);
    $pagesCount = $pagesCount->count();
    if (!empty($pagesCount)) {
        $slug = Inflector::slug(strtolower($title), '-') . '-' . $pagesCount;
    } else {
        $slug = Inflector::slug(strtolower($title), '-');
    }
    return $slug;
}
function rmdir_recursive($dirname)
{
    if (!is_dir($dirname)) {
        trigger_error(__FUNCTION__ . "({$dirname}): No such file or directory", E_USER_WARNING);
        return false;
    }
    if ($handle = opendir($dirname)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                if (is_dir("{$dirname}/{$file}")) {
                    call_user_func(__FUNCTION__, "{$dirname}/{$file}");
                } else {
                    if (unlink("{$dirname}/{$file}") === false) {
                        return false;
                    }
                }
            }
        }
        closedir($handle);
        if (rmdir($dirname) === false) {
            return false;
        }
        return true;
    }
    return false;
}
function getVideoEmbedCode($video_url)
{
    $MediaEmbed = new \MediaEmbed\MediaEmbed();
    $MediaObject = $MediaEmbed->parseUrl($video_url);
    if ($MediaObject) {
        $MediaObject->setParam(['autoplay' => 0, 'loop' => 1]);
        $MediaObject->setAttribute(['type' => null, 'class' => 'iframe-class', 'data-html5-parameter' => true]);
        return $MediaObject->getEmbedCode();
    } else {
        return 0;
    }
}
function merge_details($tables, $table)
{
    foreach ($table as $key => $merge_table) {
        if (isset($merge_table['listview'])) {
            $tables[$key]['listview']['fields'] = array_merge($tables[$key]['listview']['fields'], $merge_table['listview']['fields']);
        }
        if (isset($merge_table['showview'])) {
            $tables[$key]['showview']['fields'] = array_merge($tables[$key]['showview']['fields'], $merge_table['showview']['fields']);
        }
        if (isset($merge_table['editionview'])) {
            $tables[$key]['editionview']['fields'] = array_merge($tables[$key]['editionview']['fields'], $merge_table['editionview']['fields']);
        }
    }
    return $tables;
}
function merged_menus($menus, $merge_menus)
{
    foreach ($merge_menus as $key => $menu) {
        $menus[$key]['child_sub_menu'] = array_merge($menus[$key]['child_sub_menu'], $menu['child_sub_menu']);
    }
    return $menus;
}
function menu_sub_array_sorting($array, $on, $order = SORT_ASC)
{
    $new_array = array();
    $sortable_array = array();
    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }
        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
                break;

            case SORT_DESC:
                arsort($sortable_array);
                break;
        }
        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }
    return $new_array;
}
function getAttachmentSettings($class)
{
    $result = array();
    if ($class == 'UserAvatar') {
        $result['allowed_file_formats'] = ALLOWED_MIME_TYPES_OF_USER_AVATAR;
        $result['allowed_file_size'] = MAX_UPLOAD_SIZE_OF_USER_AVATAR;
        $result['allowed_file_extensions'] = ALLOWED_EXTENSIONS_OF_USER_AVATAR;
    } elseif ($class == 'UserProfile') {
        $result['allowed_file_formats'] = ALLOWED_MIME_TYPES_OF_USER_PROFILE;
        $result['allowed_file_size'] = MAX_UPLOAD_SIZE_OF_USER_PROFILE;
        $result['allowed_file_extensions'] = ALLOWED_EXTENSIONS_OF_USER_PROFILE;
    } elseif ($class == 'Product') {
        $result['allowed_file_formats'] = ALLOWED_MIME_TYPES_OF_PRODUCT;
        $result['allowed_file_size'] = MAX_UPLOAD_SIZE_OF_PRODUCT;
        $result['allowed_file_extensions'] = ALLOWED_EXTENSIONS_OF_PRODUCT;
    }
    return $result;
}
function get_mime($filename)
{
    $mime = false;
    if ($img_size_arr = getimagesize($filename)) {
        if (isset($img_size_arr['mime'])) {
            $mime = $img_size_arr['mime'];
        }
    }
    if (!$mime) {
        if (function_exists('mime_content_type')) { // if mime_content_type exists use it.
            $mime = mime_content_type($filename);
        } else if (function_exists('finfo_open')) { // if Pecl installed use it
            $finfo = finfo_open(FILEINFO_MIME);
            $mime = finfo_file($finfo, $filename);
            finfo_close($finfo);
        } else { // if nothing left try shell
            if (stripos(PHP_OS, 'WIN') !== false) { // Nothing to do on windows
                $mime = false;
            } else if (stripos(PHP_OS, 'mac') !== false) { // Correct output on macs
                $mime = trim(exec('file -b --mime ' . escapeshellarg($filename)));
            } else { // Regular unix systems
                $mime = trim(exec('file -bi ' . escapeshellarg($filename)));
            }
        }
    }
    return $mime;
}
function deleteActivity($foreign_id, $class) {
    Models\Activity::where('foreign_id', $foreign_id)->where('class', $class)->delete();
}
function eventBriteExecute($url) {
	#https://www.eventbrite.com/platform/docs/events
	#https://www.eventbrite.com/account-settings/apps
	$ch = curl_init();
    $timeout = 15;
	$headers = array(
					'Authorization: Bearer DC2JTA4H7FKYCDTN6A2W',
					'Content-Type: application/json'
				);
	curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSLVERSION,1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);
    return $response;
}
function create_pay_page($values) {
	$values['ip_customer'] = $_SERVER['REMOTE_ADDR'];
	$values['ip_merchant'] = $_SERVER['SERVER_ADDR'];
	return json_decode(runPost(PAYPAGE_URL, $values),true);
}
function verify_payment($values) {
	return json_decode(runPost(VERIFY_URL, $values),true);
}
function runPost($url, $fields) {
	$fields_string = "";
	foreach ($fields as $key => $value) {
		$fields_string .= $key . '=' . $value . '&';
	}
	$fields_string = rtrim($fields_string, '&');
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	$result = curl_exec($ch);
	curl_close($ch);        
	return $result;
}
function getClientRequestIP() {
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip_address = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$ip_address = $_SERVER['REMOTE_ADDR'];
	 }
	return $ip_address;
}
function isDeveloperIP() {
	return (getClientRequestIP() == '49.207.136.236' || getClientRequestIP() == '49.206.127.68');
}
function paypal_pay($post, $method, $paypalDetail) {
	$isLive = ($paypalDetail['is_active'] != 1) ? true:false;
	$url = $isLive ? 'https://svcs.paypal.com/' : 'https://svcs.sandbox.paypal.com/';
	$tokenUrl = $url.$method;
	$payUrl = $isLive ? 'https://www.paypal.com/cgi-bin/webscr?cmd=_ap-payment&paykey=' : 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_ap-payment&paykey=';
	$post_string = json_encode($post);
	if ($isLive) {
		$header = array(
			'X-PAYPAL-SECURITY-USERID: '.$paypalDetail['live_userid'],
			'X-PAYPAL-SECURITY-PASSWORD: '.$paypalDetail['live_password'],
			'X-PAYPAL-SECURITY-SIGNATURE: '.$paypalDetail['live_signature'],
			'X-PAYPAL-REQUEST-DATA-FORMAT: JSON',
			'X-PAYPAL-RESPONSE-DATA-FORMAT: JSON',
			'X-PAYPAL-APPLICATION-ID:'.$paypalDetail['live_application_id'],
		);
	} else {
		$header = array(
			'X-PAYPAL-SECURITY-USERID: '.$paypalDetail['sanbox_userid'],
			'X-PAYPAL-SECURITY-PASSWORD: '.$paypalDetail['sanbox_password'],
			'X-PAYPAL-SECURITY-SIGNATURE: '.$paypalDetail['sanbox_signature'],
			'X-PAYPAL-REQUEST-DATA-FORMAT: JSON',
			'X-PAYPAL-RESPONSE-DATA-FORMAT: JSON',
			'X-PAYPAL-APPLICATION-ID:'.$paypalDetail['sanbox_application_id'],
		);
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $tokenUrl);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	$errors = array(
		'ack' => 'fail'
	);
	if ($result) {
		$resultArray = json_decode($result, true);
		if (!empty($resultArray) && !empty($resultArray['responseEnvelope']) && strtolower($resultArray['responseEnvelope']['ack']) == 'success') {
			$data = array();
			$data['ack'] = 'success';
			$data['payUrl'] = $payUrl.$resultArray['payKey'];
			$data['payKey'] = $resultArray['payKey'];
			$data['response'] = $resultArray;
			return $data;
		}
	}
	return $errors;
}
function stripe_pay($post, $method) {
	$isLive = false;
	$url = 'https://api.stripe.com/v1/customers';
	$post_string = http_build_query($post);
	$header = array(
			'Authorization: Bearer sk_test_2bUJ8rzKzeAyVuk4dytwkAIH'
		);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	echo '<pre>';print_r($result);exit;
	return $errors;
}
function encrypt_decrypt($action, $string) {
    $output = false;
    $encrypt_method = "AES-256-CBC";
    $secret_key = 'This is my secret key';
    $secret_iv = 'This is my secret iv';
    // hash
    $key = hash('sha256', $secret_key);

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_iv), 0, 16);
    if ( $action == 'encrypt' ) {
        $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
    } else if( $action == 'decrypt' ) {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }
    return $output;
}
function videoType($url) {
    if (strpos($url, 'youtube') > 0) {
        return 'youtube';
    } elseif (strpos($url, 'vimeo') > 0) {
        return 'vimeo';
    } else {
        return 'unknown';
    }
}
function addAdminUser($args, $role_id, $company_id) {
	$result = array();
	$user = new Models\User;
	$validationErrorFields = $user->validate($args);
	if (!empty($validationErrorFields)) {
		$validationErrorFields = $validationErrorFields->toArray();
	}
	if (checkAlreadyUsernameExists($args['username']) && empty($validationErrorFields)) {
		$validationErrorFields['unique'] = array();
		array_push($validationErrorFields['unique'], 'username');
	}
	if (checkAlreadyEmailExists($args['email']) && empty($validationErrorFields)) {
		$validationErrorFields['unique'] = array();
		array_push($validationErrorFields['unique'], 'email');
	}
	if (empty($validationErrorFields['unique'])) {
		unset($validationErrorFields['unique']);
	}
	if (empty($validationErrorFields['required'])) {
		unset($validationErrorFields['required']);
	}
	if (empty($validationErrorFields)) {
		//$args['password'] = generateNumericOTP(4);
		$args['password'] = '1234567';
		$image = $args['image'];
		$cover_photo = $args['cover_photo'];
		$addressDetail = $args['address'];
		$categories = $args['category'];
		unset($args['image']);
		unset($args['cover_photo']);
		unset($args['address']);
		unset($args['category']);
		foreach ($args as $key => $arg) {
			if ($key == 'password') {
				$user->{$key} = getCryptHash($arg);
			} else {
				$user->{$key} = $arg;
			}
		}
		$user->is_email_confirmed = 1;
		$user->is_active = 1;
		$user->role_id = $role_id;
		$user->company_id = $company_id;
		$user->save();
		$emailFindReplace_user = array(
			'##USERNAME##' => $user->first_name,
			'##LOGINLABEL##' => 'Username',
			'##USEDTOLOGIN##' => $user->email,
			'##PASSWORD##' => $args['password']
		);
		sendMail('adminuseradd', $emailFindReplace_user, $user->email);
		if (isset($image) && $image != '') {
			saveImage('UserAvatar', $image, $user->id);
		}
		if (isset($cover_photo) && $cover_photo != '') {
			saveImage('CoverPhoto', $cover_photo, $user->id);
		}
		if (isset($addressDetail) && $addressDetail != '') {				
			$address = new Models\UserAddress;
			$address->addressline1 = $addressDetail['addressline1'];
			$address->addressline2 = $addressDetail['addressline2'];
			$address->city = $addressDetail['city'];
			$address->state = $addressDetail['state'];
			$address->country = $addressDetail['country'];
			$address->zipcode = $addressDetail['zipcode'];
			$address->user_id = $user->id;
			$address->is_default = true;
			$address->name = 'Default';
			$address->save();
		}
		if (!empty($categories) && $role_id === \Constants\ConstUserTypes::Employer) {
			foreach ($categories as $category) {
				$userCategory = new Models\UserCategory;
				$userCategory->user_id = $user->id;
				$userCategory->category_id = $category['id'];
				$userCategory->save();
			}
		}
		return array('message' => 'User created successfully', 'code' => 0);
	} else {
		if (!empty($validationErrorFields)) {
			foreach ($validationErrorFields as $key=>$value) {
				if ($key == 'unique') {
					return array('message' => ucfirst($value[0]).' already exists. Please, try again login.', 'code' => 1);
				} else if (!empty($value[0]) && !empty($value[0]['numeric'])) {
					return array('message' => $value[0]['numeric'], 'code' => 1);
				} else {
					return array('message' => $value[0], 'code' => 1);
				}
				break;
			}
		} else {
			return array('message' => 'Error', 'code' => 1);
		}
	}
}
function getPaymentDetails($payment_gateway_id) {
	$paymentGateway = Models\PaymentGateway::where('id', $payment_gateway_id)->where('is_active', true)->get();
	$paymentGateway_model = new Models\PaymentGateway;
	$paymentGateway->makeVisible($paymentGateway_model->hidden);
	$paymentGateway = current($paymentGateway->toArray());
	return $paymentGateway;
}
function generateNumericOTP($n) { 
	$generator = "1357902468"; 
	$result = ""; 
	for ($i = 1; $i <= $n; $i++) { 
		$result .= substr($generator, (rand()%(strlen($generator))), 1); 
	} 
	return $result; 
}
function numberFormat($amount) { 
	return number_format(round($amount, 1), 2, '.', ''); 
}
function offlineToCart($userId) { 
	$offlineCarts = Models\OfflineCart::where('ipaddress', getClientRequestIP())->get()->toArray();
	if (!empty($offlineCarts)) {
		foreach($offlineCarts as $offlineCart) {
			$cart = new Models\Cart;
			$cart->is_active = 1;
			$cart->user_id = $userId;
			$cart->contestant_id = $offlineCart['contestant_id'];
			$cart->company_id = $offlineCart['company_id'];
			$cart->product_detail_id = $offlineCart['product_detail_id'];
			$cart->quantity = $offlineCart['quantity'];
			$cart->product_size_id = $offlineCart['product_size_id'];
			$cart->coupon_id = $offlineCart['coupon_id'];
			$cart->save();
		}
		Models\OfflineCart::where('ipaddress', getClientRequestIP())->delete();
	}
}
function checkEmail($email) {
   $find1 = strpos($email, '@');
   $find2 = strpos($email, '.');
   return ($find1 !== false && $find2 !== false && $find2 > $find1);
}
function restaurantCountUpdate() {
	Capsule::select('update brands set count=0');
	$cities = Capsule::select('SELECT c.id, COALESCE(count(r.brand_id), 0) AS count
	FROM users c
	inner JOIN restaurants r ON c.id = r.brand_id 
	where role_id = '.\Constants\ConstUserTypes::Company.'
	GROUP BY c.id');
	if(!empty($cities)) {
		$cities = json_decode(json_encode($cities), true);
		foreach($cities as $city) {
			Capsule::select('update users set count='.$city['count'].' where id='.$city['id']);
		}
	}
	Capsule::select('update cities set count=0');
	$cities = Capsule::select('SELECT c.id, COALESCE(count(r.city_id), 0) AS count
	FROM cities c
	inner JOIN restaurants r ON c.id = r.city_id
	GROUP BY c.id');
	if(!empty($cities)) {
		$cities = json_decode(json_encode($cities), true);
		foreach($cities as $city) {
			Capsule::select('update cities set count='.$city['count'].' where id='.$city['id']);
		}
	}
	Capsule::select('update countries set count=0');
	$countries = Capsule::select('SELECT c.id,c.name, COALESCE(count(r.country_id), 0) AS count
	FROM countries c
	inner JOIN restaurants r ON c.id = r.country_id
	GROUP BY c.id');
	if(!empty($countries)) {
		$countries = json_decode(json_encode($countries), true);
		foreach($countries as $country) {
			Capsule::select('update countries set count='.$country['count'].' where id='.$country['id']);
		}
	}

	Capsule::select('update themes set count=0');
	$themes = Capsule::select('SELECT c.id, COALESCE(count(r.theme_id), 0) AS count
	FROM themes c
	inner JOIN restaurant_themes r ON c.id = r.theme_id
	GROUP BY c.id');
	if(!empty($themes)) {
		$themes = json_decode(json_encode($themes), true);
		foreach($themes as $theme) {
			Capsule::select('update themes set count='.$theme['count'].' where id='.$theme['id']);
		}
	}

	Capsule::select('update cuisines set count=0');
	$cuisines = Capsule::select('SELECT c.id, COALESCE(count(r.cuisine_id), 0) AS count
	FROM cuisines c
	inner JOIN restaurant_cuisines r ON c.id = r.cuisine_id
	GROUP BY c.id');
	if(!empty($cuisines)) {
		$cuisines = json_decode(json_encode($cuisines), true);
		foreach($cuisines as $cuisine) {
			Capsule::select('update cuisines set count='.$cuisine['count'].' where id='.$cuisine['id']);
		}
	}
}
function slotsList($restaurant) {
	$restaurant['new'] = (strtotime($restaurant['created_at']) >= strtotime('-15 day', strtotime(date('Y-m-d'))));
	if (!empty($restaurant['timezone'])) {
		date_default_timezone_set($restaurant['timezone']['name']);
	}
	$slots = array();
	$restaurant['custom_slots'] = array(); 
	if (!empty($restaurant['custom_slots'])) {
		if ($restaurant['custom_slots']['type'] == 0) {
			foreach($restaurant['custom_slots']['slots'] as $timeSlots) {
				$custom_slots = [];
				$custom_slots['discount'] = $timeSlots['discount'];
				$custom_slots['person'] = $restaurant['max_person'];
				$custom_slots['slot'] = $timeSlots['from_timeslot'];
				$slots[] = $custom_slots;
			}
		}
	} else if (!empty($restaurant['slots'])) {
		if ($restaurant['slots']['type'] == 0) {
			foreach($restaurant['slots']['slots'] as $timeSlots) {
				$custom_slots = [];
				$custom_slots['discount'] = $timeSlots['discount'];
				$custom_slots['person'] = $restaurant['max_person'];
				$custom_slots['slot'] = $timeSlots['from_timeslot'];
				$slots[] = $custom_slots;
			}
		}
	}
	
	$restaurant['distance'] = distance($restaurant['latitude'], $restaurant['longitude'], $_GET['lat'], $_GET['long'], "K");
	$restaurant['available_slots'] = $slots;
	$restaurant['leave'] = false;
	$restaurant['leave_text'] = 'not available today';
	//$restaurant['leave_text'] = 'not available until Apr 19';
	$restaurant['new'] = false;
	if (!empty($slots)) {
		$restaurant = bookingList($restaurant);
	}
	unset($restaurant['custom_slots']);
	unset($restaurant['slots']);	
	return $restaurant;
}
function bookingList($restaurant) {
	$date = '';
	if (isset($_GET['date']) && !empty($_GET['date'])) {
		$date = date_create($_GET['date']);
		$date = date_format($date, "Y-m-d");
	} else {
		$date = date('Y-m-d');
	}
	$booking = Capsule::select('SELECT sum(max_person) as count, from_timeslot FROM `booking` where  status <> 2 and restaurant_id='.$restaurant['id'].' and reg_date="'.$date.'" group by from_timeslot');
	if(!empty($booking)) {
		$slots = array();
		$booking = json_decode(json_encode($booking), true);
		foreach($restaurant['available_slots'] as $slot) {
			$item = null;
			foreach($booking as $book) {
				if ($book['from_timeslot'] == $slot['slot']) {
					$slot['person'] = $restaurant['max_person']-$book['count'];
					$item = $slot;
					break;
				}
			}
			if (!empty($item)) {
				$slots[] = $item;
			} else {
				$slots[] = $slot;
			}
		}
		$restaurant['available_slots'] = $slots;
	}	
	return $restaurant;
}
function distance($lat1, $lon1, $lat2, $lon2, $unit) {
// echo distance(32.9697, -96.80322, 29.46786, -98.53506, "M") . " Miles<br>";
// echo distance(32.9697, -96.80322, 29.46786, -98.53506, "K") . " Kilometers<br>";
// echo distance(32.9697, -96.80322, 29.46786, -98.53506, "N") . " Nautical Miles<br>";
  if (!empty($lat1) && !empty($lon1) && !empty($lat2) && !empty($lon2)) {
	  $theta = $lon1 - $lon2;
	  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
	  $dist = acos($dist);
	  $dist = rad2deg($dist);
	  $miles = $dist * 60 * 1.1515;
	  $unit = strtoupper($unit);

	  if ($unit == "K") {
		  return number_format((float)($miles * 1.609344), 2, '.', '');
	  } else if ($unit == "N") {
		  return number_format((float)($miles * 0.8684), 2, '.', '');
	  } else {
		  return $miles;
	  }
  } else {
	  return null;
  }
}
function restaurantsFilter($queryParams) {
	$restaurants = array();
	if (!empty($queryParams['restaurants'])) {
		$restaurants = $queryParams['restaurants'];
	}
	if (!empty($queryParams['atmospheres'])) {
		$atmospheres = Models\RestaurantAtmosphere::whereIn('atmosphere_id', explode (",", $queryParams['atmospheres']))->get()->toArray();
		if (!empty($atmospheres)) {
			$restaurants = array_merge(array_column($atmospheres, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['facilities'])) {
		$facilities = Models\RestaurantFacilitiesService::whereIn('facilities_service_id', explode (",", $queryParams['facilities']))->get()->toArray();
		if (!empty($facilities)) {
			$restaurants = array_merge(array_column($facilities, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['menus'])) {
		$menus = Models\Menu::whereIn('id', explode (",", $queryParams['menus']))->get()->toArray();
		if (!empty($menus)) {
			$restaurants = array_merge(array_column($menus, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['specialconditions'])) {
		$specialconditions = Models\SpecialCondition::whereIn('id', explode (",", $queryParams['specialconditions']))->get()->toArray();
		if (!empty($specialconditions)) {
			$restaurants = array_merge(array_column($specialconditions, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['languages'])) {
		$languages = Models\RestaurantLanguage::whereIn('language_id', explode (",", $queryParams['languages']))->get()->toArray();
		if (!empty($languages)) {
			$restaurants = array_merge(array_column($languages, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['payments'])) {
		$payments = Models\SpecialCondition::whereIn('id', explode (",", $queryParams['payments']))->get()->toArray();
		if (!empty($payments)) {
			$restaurants = array_merge(array_column($payments, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['themes'])) {
		$themes = Models\RestaurantTheme::whereIn('theme_id', explode (",", $queryParams['themes']))->get()->toArray();
		if (!empty($themes)) {
			$restaurants = array_merge(array_column($themes, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['cuisines'])) {
		$cuisines = Models\RestaurantCuisine::whereIn('cuisine_id', explode (",", $queryParams['cuisines']))->get()->toArray();
		if (!empty($cuisines)) {
			$restaurants = array_merge(array_column($cuisines, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['brands'])) {
		$brands = Models\Restaurant::whereIn('brand_id', explode (",", $queryParams['brands']))->get()->toArray();
		if (!empty($brands)) {
			$restaurants = array_merge(array_column($brands, 'id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['countries'])) {
		$cities = Models\Restaurant::whereIn('country_id', explode (",", $queryParams['countries']))->get()->toArray();
		if (!empty($cities)) {
			$restaurants = array_merge(array_column($cities, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['cities'])) {
		$cities = Models\Restaurant::whereIn('city_id', explode (",", $queryParams['cities']))->get()->toArray();
		if (!empty($cities)) {
			$restaurants = array_merge(array_column($cities, 'restaurant_id'), $restaurants);
		} else {
			$restaurants = array_merge(array(-1), $restaurants);
		}
	}
	if (!empty($queryParams['distance']) && !empty($queryParams['lat']) && !empty($queryParams['long'])) {
		$distances = Capsule::select('SELECT
		  id,title, (
			3959 * acos (
			  cos ( radians('.$queryParams['lat'].') )
			  * cos( radians( latitude) )
			  * cos( radians( longitude) - radians('.$queryParams['long'].') )
			  + sin ( radians('.$queryParams['lat'].') )
			  * sin( radians( latitude) )
			)
		  ) AS distance
		FROM restaurants
		HAVING distance < 100
		ORDER BY distance,title');
		if(!empty($distances)) {
			$distances = json_decode(json_encode($distances), true);
			$restaurants = array_merge(array_column($distances, 'id'), $restaurants);
		}
	}
	return $restaurants;
}