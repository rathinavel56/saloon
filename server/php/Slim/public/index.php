<?php
/**
 * Base API
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 * https://www.oreilly.com/library/view/paypal-apis-up/9781449321666/ch04.html
 */
 //$e->getMessage()
require_once '../lib/bootstrap.php';
use Illuminate\Database\Capsule\Manager as Capsule;
date_default_timezone_set(SITE_TIMEZONE);
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://mysite')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});
/**
 * GET oauthGet
 * Summary: Get site token
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/oauth/token', function ($request, $response, $args) {
    $post_val = array(
        'grant_type' => 'client_credentials',
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET
    );
    $response = getToken($post_val);
    return renderWithJson($response);
});
/**
 * GET oauthRefreshTokenGet
 * Summary: Get site refresh token
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/oauth/refresh_token', function ($request, $response, $args) {
    $post_val = array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $_GET['token'],
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET
    );
    $response = getToken($post_val);
    if (!empty($response) && $response['access_token'] != '') {
		return renderWithJson($response);
	} else {
		return renderWithJson(array(), 'Session Expired.', '', 1);
	}
});
/**
 * POST usersRegisterPost
 * Summary: new user
 * Notes: Post new user.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/register', function ($request, $response, $args) {
    global $_server_domain_url;
    $args = $request->getParsedBody();
    $result = array();
    $user = new Models\User;
    $validationErrorFields = $user->validate($args);
    if (!empty($validationErrorFields)) {
        $validationErrorFields = $validationErrorFields->toArray();
    }
	if (!empty($args['email'])) {
		$usernameData = explode('@', $args['email']);
		$args['username'] = $usernameData[0];
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
        foreach ($args as $key => $arg) {
            if ($key == 'password') {
                $user->{$key} = getCryptHash($arg);
            } else {
                $user->{$key} = $arg;
            }
        }
        try {
            $user->is_email_confirmed = (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1) ? 0 : 1;
            $user->is_active = (USER_IS_ADMIN_ACTIVATE_AFTER_REGISTER == 1) ? 0 : 1;
            if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                $user->is_email_confirmed = 1;
                $user->is_active = 1;
            }
			echo '1-------';
            $user->role_id = \Constants\ConstUserTypes::User;
            $user->save();
			echo '2-------';
			if (!empty($args['image'])) {
                saveImage('UserAvatar', $args['image'], $user->id);
            }
			echo '3-------';
            if (!empty($args['cover_photo'])) {
                saveImage('CoverPhoto', $args['cover_photo'], $user->id);
            }
            // send to admin mail if USER_IS_ADMIN_MAIL_AFTER_REGISTER is true
            if (USER_IS_ADMIN_MAIL_AFTER_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##USEREMAIL##' => $user->email,
                    '##SUPPORT_EMAIL##' => SUPPORT_EMAIL
                );
                sendMail('newuserjoin', $emailFindReplace, SITE_CONTACT_EMAIL);
            }
            if (USER_IS_WELCOME_MAIL_AFTER_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##SUPPORT_EMAIL##' => SUPPORT_EMAIL
                );
                // send welcome mail to user if USER_IS_WELCOME_MAIL_AFTER_REGISTER is true
                sendMail('welcomemail', $emailFindReplace, $user->email);
            }
            if (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##ACTIVATION_URL##' => $_server_domain_url . '/activation/' . $user->id . '/' . md5($user->username)
                );
                sendMail('activationrequest', $emailFindReplace, $user->email);
            }
            if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                $scopes = '';
				if($user->role_id == \Constants\ConstUserTypes::Admin) {
					$scopes = 'canAdmin';
				} else if($user->role_id == \Constants\ConstUserTypes::Employer) {
					$scopes = 'canContestantUser';
				} else {
					$scopes = 'canUser';	
				}
                $post_val = array(
                    'grant_type' => 'password',
                    'username' => $user->username,
                    'password' => $user->password,
                    'client_id' => OAUTH_CLIENT_ID,
                    'client_secret' => OAUTH_CLIENT_SECRET,
                    'scope' => $scopes
                );
                $response = getToken($post_val);
				$enabledIncludes = array(
                    'attachment',
                    'role'
                );
                $userData = Models\User::with($enabledIncludes)->find($user->id);
                $result = $response + $userData->toArray();
            } else {
                $enabledIncludes = array(
                    'attachment'
                );
                $user = Models\User::with($enabledIncludes)->find($user->id);
                $result = $user->toArray();
            }
            return renderWithJson($result, 'User registered successfully','', 0);
        } catch (Exception $e) {
			return renderWithJson($result, 'User could not be added. Please, try again.', $e->getMessage(), 1);
        }
    } else {
		if (!empty($validationErrorFields)) {
			foreach ($validationErrorFields as $key=>$value) {
				if ($key == 'unique') {
					return renderWithJson($result, ucfirst($value[0]).' already exists. Please, try again login.','', 1);
				} else if (!empty($value[0]) && !empty($value[0]['numeric'])) {
					return renderWithJson($result, $value[0]['numeric'], $e->getMessage(), 1);
				} else {
					return renderWithJson($result, $value[0], $e->getMessage(), 1);
				}
				break;
			}
		} else {
			return renderWithJson($result, 'User could not be added. Please, try again.', $validationErrorFields, 1);
		}
    }
});
/**
 * PUT usersUserIdActivationHashPut
 * Summary: User activation
 * Notes: Send activation hash code to user for activation. \n
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users/activation/{userId}/{hash}', function ($request, $response, $args) {
    $result = array();
    $user = Models\User::where('id', $request->getAttribute('userId'))->first();
    if (!empty($user)) {
        if($user->is_email_confirmed != 1) {
            if (md5($user['username']) == $request->getAttribute('hash')) {
                $user->is_email_confirmed = 1;
                $user->is_active = (USER_IS_ADMIN_ACTIVATE_AFTER_REGISTER == 0 || USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) ? 1 : 0;
                $user->save();
                if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                    $scopes = '';
                    if (isset($user->role_id) && $user->role_id == \Constants\ConstUserTypes::User) {
                        $scopes = implode(' ', $user['user_scopes']);
                    } else {
                        $scopes = '';
                    }
                    $post_val = array(
                        'grant_type' => 'password',
                        'username' => $user->username,
                        'password' => $user->password,
                        'client_id' => OAUTH_CLIENT_ID,
                        'client_secret' => OAUTH_CLIENT_SECRET,
                        'scope' => $scopes
                    );
                    $response = getToken($post_val);
                    $result['data'] = $response + $user->toArray();
                } else {
                    $result['data'] = $user->toArray();
                }
                return renderWithJson($result, 'Your account has been activated successfully','', 0);
            } else {
                return renderWithJson($result, 'Invalid user details.', $e->getMessage(), 1);
            }
        } else {
            return renderWithJson($result, 'Invalid Request', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Invalid user details.', $e->getMessage(), 1);
    }
});
/**
 * POST usersLoginPost
 * Summary: User login
 * Notes: User login information post
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/login', function ($request, $response, $args) {
    $body = $request->getParsedBody();
	$result = array();
	$user = new Models\User;
	$enabledIncludes = array(
		'attachment',
		'role'
	);
	if (checkEmail($body['email'])) {
		$log_user = $user->where('email', $body['email'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->first();
	} else {
		$log_user = $user->where('username', $body['username'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->first();
	}
	$password = crypt($body['password'], $log_user['password']);
	$validationErrorFields = $user->validate($body);
	$validationErrorFields = array();
	if (empty($validationErrorFields) && !empty($log_user) && ($password == $log_user['password'])) {
		$scopes = '';
		if($log_user['role']['id'] == \Constants\ConstUserTypes::Admin) {
			$scopes = 'canAdmin';
		} else if($log_user['role']['id'] == \Constants\ConstUserTypes::Employer) {
			$scopes = 'canContestantUser';
		} else if($log_user['role']['id'] == \Constants\ConstUserTypes::Company) {
			$scopes = 'canCompanyUser';
		} else {
			$scopes = 'canUser';	
		}
		$post_val = array(
			'grant_type' => 'password',
			'username' => $log_user['username'],
			'password' => $password,
			'client_id' => OAUTH_CLIENT_ID,
			'client_secret' => OAUTH_CLIENT_SECRET,
			'scope' => $scopes
		);
		$response = getToken($post_val);
		if (!empty($response['refresh_token'])) {
			$result = $response + $log_user->toArray();
			$userLogin = new Models\UserLogin;
			$userLogin->user_id = $log_user->id;
			$userLogin->ip_id = saveIp();
			$userLogin->user_agent = $_SERVER['HTTP_USER_AGENT'];
			$userLogin->save();
			return renderWithJson($result, 'LoggedIn Successfully');
		} else {
			return renderWithJson($result, 'Your login credentials are invalid.', $e->getMessage(), 1);
		}
	} else {
		return renderWithJson($result, 'Your login credentials are invalid.', $validationErrorFields, 1);
	}
});
/**
 * Get userSocialLoginGet
 * Summary: Social Login for twitter
 * Notes: Social Login for twitter
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/social_login', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    if (!empty($queryParams['type'])) {
        $response = social_auth_login($queryParams['type']);
		return renderWithJson($response);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
});
/**
 * POST userSocialLoginPost
 * Summary: User Social Login
 * Notes:  Social Login
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/social_login', function ($request, $response, $args) {
    $body = $request->getParsedBody();
	try {
		$result = array();
		if (!empty($_GET['type'])) {
			$response = social_auth_login($_GET['type'], $body);
			// return (($response && $response['error'] && $response['error']['code'] == 1) ? renderWithJson($response) : renderWithJson($result, 'Unable to fetch details', '', 1));
			// $response['cart_count'] = Models\Cart::where('is_purchase', false)->where('user_id', $response['id'])->count();
			return renderWithJson($response, 'LoggedIn Successfully');
		} else {
			return renderWithJson($result, 'Please choose one provider.', $e->getMessage(), 1);
		}
	} catch(Exception $e) {
		return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
	}
});
/**
 * POST usersForgotPasswordPost
 * Summary: User forgot password
 * Notes: User forgot password
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/forgot_password', function ($request, $response, $args) {
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::where('email', $args['email'])->first();
    if (!empty($user)) {
        $validationErrorFields = $user->validate($args);
        if (empty($validationErrorFields) && !empty($user)) {
            $password = uniqid();
            $user->password = getCryptHash($password);
            try {
                $user->save();
                $emailFindReplace = array(
                    '##USERNAME##' => $user['username'],
                    '##PASSWORD##' => $password,
                );
                sendMail('forgotpassword', $emailFindReplace, $user['email']);
                return renderWithJson($result, 'An email has been sent with your new password', '', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'Email Not found', $e->getMessage(), 1);
            }
        } else {
            return renderWithJson($result, 'Process could not be found', $validationErrorFields, 1);
        }
    } else {
        return renderWithJson($result, 'No data found', $e->getMessage(), 1);
    }
});
/**
 * PUT UsersuserIdChangePasswordPut .
 * Summary: update change password
 * Notes: update change password
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users/change_password', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::find($authUser->id);
    $validationErrorFields = $user->validate($args);
    $password = crypt($args['password'], $user['password']);
    if (empty($validationErrorFields)) {
        if ($password == $user['password']) {
            $change_password = $args['new_password'];
            $user->password = getCryptHash($change_password);
            try {
                $user->save();
                $emailFindReplace = array(
                    '##PASSWORD##' => $args['new_password'],
                    '##USERNAME##' => $user['username']
                );
                if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin) {
                    sendMail('adminchangepassword', $emailFindReplace, $user->email);
                } else {
                    sendMail('changepassword', $emailFindReplace, $user['email']);
                }
                $result['data'] = $user->toArray();
                return renderWithJson($result, 'Your Password has been changed successfully','', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'Your Password could not be updated. Please, try again', $e->getMessage(), 1);
            }
        } else {
            return renderWithJson($result, 'Your Password is invalid . Please, try again', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Your Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * POST AdminChangePasswordToUser .
 * Summary: update change password
 * Notes: update change password
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/change_password', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::find($args['user_id']);
    $validationErrorFields = $user->validate($args);
    $validationErrorFields['unique'] = array();
    if (!empty($args['new_password']) && !empty($args['new_confirm_password']) && $args['new_password'] != $args['new_confirm_password']) {
        array_push($validationErrorFields['unique'], 'Password and confirm password should be same');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields)) {
        $change_password = $args['new_password'];
        $user->password = getCryptHash($change_password);
        try {
            $user->save();
            $emailFindReplace = array(
                '##PASSWORD##' => $args['new_password'],
                '##USERNAME##' => $user['username']
            );
            sendMail('adminchangepassword', $emailFindReplace, $user->email);
            $result['data'] = $user->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User Password could not be updated. Please, try again', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'User Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET usersLogoutGet
 * Summary: User Logout
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/logout', function ($request, $response, $args) {
    if (!empty($_GET['token'])) {
        try {
            $oauth = Models\OauthAccessToken::where('access_token', $_GET['token'])->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result, 'You have logout successfully','', 0);
        } catch (Exception $e) {
            return renderWithJson(array(), 'Please verify in your token', $e->getMessage(), 1);
        }
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * POST UserPost
 * Summary: Create New user by admin
 * Notes: Create New user by admin
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
    $result = array();
    $user = new Models\User($args);
    $validationErrorFields = $user->validate($args);
    $validationErrorFields['unique'] = array();
    $validationErrorFields['required'] = array();
    if (checkAlreadyUsernameExists($args['username'])) {
        array_push($validationErrorFields['unique'], 'username');
    }
    if (checkAlreadyEmailExists($args['email'])) {
        array_push($validationErrorFields['unique'], 'email');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields['required'])) {
        unset($validationErrorFields['required']);
    }
    if (!empty($args['is_active'])) {
        $user->is_active = $args['is_active'];
     }
     if (!empty($args['is_email_confirmed'])) {
        $user->is_email_confirmed = $args['is_email_confirmed'];
     } 
    if (empty($validationErrorFields)) {
        $user->password = getCryptHash($args['password']);
        $user->role_id = $args['role_id'];  
        try {
            unset($user->image);
            unset($user->cover_photo);       
            $user->save();
            if (!empty($args['image'])) {
                saveImage('UserAvatar', $args['image'], $user->id);
            }
            if (!empty($args['cover_photo'])) {
                saveImage('CoverPhoto', $args['cover_photo'], $user->id);
            }
            $emailFindReplace_user = array(
                '##USERNAME##' => $user->username,
                '##LOGINLABEL##' => (USER_USING_TO_LOGIN == 'username') ? 'Username' : 'Email',
                '##USEDTOLOGIN##' => (USER_USING_TO_LOGIN == 'username') ? $user->username : $user->email,
                '##PASSWORD##' => $args['password']
            );
            sendMail('adminuseradd', $emailFindReplace_user, $user->email);
            $enabledIncludes = array(
                'attachment',
                'cover_photo'
            );
            $result = Models\User::with($enabledIncludes)->find($user->id)->toArray();
            return renderWithJson($result, 'User account successfully added','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User could not be added. Please, try again.', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'User could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET UseruserIdGet
 * Summary: Get particular user details
 * Notes: Get particular user details
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/{userId}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$enabledIncludes = array(
					'attachments'
				);
		$enabledUserIncludes = array(
			'attachment',
			'address'
		);
		$user = Models\User::with($enabledUserIncludes)->where('id', $request->getAttribute('userId'))->orWhere('username', $request->getAttribute('userId'))->first();
		$_GET['user_id'] = $user->id;
		$authUserId = null;
		if (!empty($authUser['id'])) {
			$authUserId = $authUser['id'];
			$current_user = '';
			if ($user->id != $authUserId) {
				$current_user = Models\User::with($enabledUserIncludes)->where('id', $authUserId)->first();
				$user_model = new Models\User;
				$current_user->makeVisible($user_model->hidden);
			} else {
				$user_model = new Models\User;
				$user->makeVisible($user_model->hidden);
				$count = Models\Attachment::where('user_id', $authUser->id)->where('is_admin_approval', 0)->count();
				$user->is_admin_approval = ($count > 0) ? true : false;
			}			
		}
		if (!empty($user)) {
			$user = $user->toArray();
			$result['data'] = $user;
			if (!empty($_GET['type']) && $_GET['type'] == 'view' && (empty($authUser) || (!empty($authUser) && $authUser['id'] != $request->getAttribute('userId')))) {
				insertViews($request->getAttribute('userId'), 'User');
			}
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'No record found', '', 1, 404);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
});
/**
 * GET AuthUserID
 * Summary: Get particular user details
 * Notes: Get particular user details
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/me', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'attachment'
    );
    $user = Models\User::with($enabledIncludes)->where('id', $authUser->id)->first();
    $user_model = new Models\User;
    if (!empty($user)) {
        $result['data'] = $user;
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * PUT UsersuserIdPut
 * Summary: Update user
 * Notes: Update user
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();	
    $result = array();
    $user = Models\User::find($authUser->id);
    $validation = true;
    if (!empty($user)) {
		if ($authUser['role_id'] != \Constants\ConstUserTypes::Admin) {
			unset($args['username']);
			unset($args['is_paypal_connect']);
			unset($args['is_stripe_connect']);
			unset($args['subscription_end_date']);
			unset($args['votes']);
			unset($args['rank']);
		}
        if ($validation) {
            $address = $args['address'];
			if (isset($args['image']) && $args['image'] != '') {
				$image = $args['image'];
				saveImage('UserAvatar', $image, $user->id);
				unset($args['image']);
			}
			if (isset($args['cover_photo']) && $args['cover_photo'] != '') {
				saveImage('CoverPhoto', $args['cover_photo'], $user->id);
				unset($args['cover_photo']);
			}
			if (isset($args['address']) && $args['address'] != '') {
				$userAdd = Models\UserAddress::where('user_id', $authUser->id)->where('is_active', 1)->first();
				if ($userAdd && !empty($userAdd)) {
					Models\UserAddress::where('user_id', $authUser->id)->where('is_default', true)->update($args['address']);
				} else {
					$address = new Models\UserAddress;
					$address->addressline1 = $args['address']['addressline1'];
					$address->addressline2 = $args['address']['addressline2'];
					$address->city = $args['address']['city'];
					$address->state = $args['address']['state'];
					$address->country = $args['address']['country'];
					$address->zipcode = $args['address']['zipcode'];
					$address->user_id = $user->id;
					$address->is_default = true;
					$address->name = 'Default';
					$address->save();
				}
				unset($args['address']);
			}
			$user->fill($args);
            try {
                $user->save();                
                $enabledIncludes = array(
                    'attachment'
                );
                $user = Models\User::with($enabledIncludes)->find($user->id);
                $result['data'] = $user->toArray();
                return renderWithJson($result, 'Profile updated Successfully','', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'User could not be updated. Please, try again.', $e->getMessage(), 1);
            }
        } else {
            return renderWithJson($result, 'Country is required', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Invalid user Details, try again.', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/user_image', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();	
    $result = array();
    if (isset($args['image']) && $args['image'] != '') {
		$image = $args['image'];
		saveImage('UserAvatar', $args['image'], $authUser->id);
		return renderWithJson(array(), 'Profile image updated Successfully','', 0);
	} else {
		return renderWithJson(array(), 'Profile image could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * DELETE UseruserId Delete
 * Summary: DELETE user by admin
 * Notes: DELETE user by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/users/{userId}', function ($request, $response, $args) {
    $result = array();
    $user = Models\User::find($request->getAttribute('userId'));
    $data = $user;
    if (!empty($user)) {
        try {
            $user->delete();
            $emailFindReplace = array(
                '##USERNAME##' => $data['username']
            );
            sendMail('adminuserdelete', $emailFindReplace, $data['email']);
            $result = array(
                'status' => 'success',
            );
            Models\UserLogin::where('user_id', $request->getAttribute('userId'))->delete();
            return renderWithJson($result, 'Your account removed successfully','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Your account could not be deleted. Please, try again.', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Invalid account details.', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET ProvidersGet
 * Summary: all providers lists
 * Notes: all providers lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/providers', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $providers = Models\Provider::Filter($queryParams)->paginate($count)->toArray();
        $data = $providers['data'];
        unset($providers['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $providers
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
});
/**
 * GET  ProvidersProviderIdGet
 * Summary: Get  particular provider details
 * Notes: GEt particular provider details.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/providers/{providerId}', function ($request, $response, $args) {
    $result = array();
    $provider = Models\Provider::find($request->getAttribute('providerId'));
    if (!empty($provider)) {
        $result['data'] = $provider->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
});
/**
 * PUT ProvidersProviderIdPut
 * Summary: Update provider details
 * Notes: Update provider details.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/providers/{providerId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $provider = Models\Provider::find($request->getAttribute('providerId'));
    $validationErrorFields = $provider->validate($args);
    if (empty($validationErrorFields)) {
        $provider->fill($args);
        try {
            $provider->save();
            $result['data'] = $provider->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Provider could not be updated. Please, try again', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Provider could not be updated. Please, try again', $validationErrorFields, 1);
    }
});
/**
 * GET RoleGet
 * Summary: Get roles lists
 * Notes: Get roles lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/roles', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $roles = Models\Role::Filter($queryParams)->paginate($count)->toArray();
        $data = $roles['data'];
        unset($roles['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $roles
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET RolesIdGet
 * Summary: Get paticular email templates
 * Notes: Get paticular email templates
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/roles/{roleId}', function ($request, $response, $args) {
    $result = array();
    $role = Models\Role::find($request->getAttribute('roleId'));
    if (!empty($role)) {
        $result = $role->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET UsersUserIdTransactionsGet
 * Summary: Get user transactions list.
 * Notes: Get user transactions list.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/{userId}/transactions', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'user',
            'other_user',
            'foreign_transaction',
            'payment_gateway'
        );
        $transactions = Models\Transaction::with($enabledIncludes);
        if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
        $transactions = $transactions->Filter($queryParams)->paginate($count);
        $data = $transactions->toArray();
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET paymentGatewayGet
 * Summary: Filter  payment gateway
 * Notes: Filter payment gateway.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/payment_gateways', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $paymentGateways = Models\PaymentGateway::with('attachment')->where('is_active', true)->Filter($queryParams)->get()->toArray();
		$payGateway = array();
		$addCard = array();
		if (!empty($paymentGateways)) {
			foreach($paymentGateways as $paymentGateway) {
				//if ($paymentGateway['name'] != 'Add Card') {
				//	$payGateway[] = $paymentGateway;
				//} else {
					$addCard[] = $paymentGateway;
				// }
			}
		}
        // $cards = Models\Card::select('id', 'card_display_number', 'expiry_date', 'name')->where('user_id', $authUser->id)->get()->toArray();
        $result = array(
            'data' => $addCard // array_merge(array_merge($payGateway, $cards), $addCard)
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/payment_gateway/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$paymentGateway = Models\PaymentGateway::find($request->getAttribute('id'));
	$result = array();
	try {
		if (!empty($args['image']) && $paymentGateway->id) {
			saveImage('PaymentGateway', $args['image'], $paymentGateway->id);
		}
		$result = $paymentGateway->toArray();
		return renderWithJson($result, 'Success','', 0);		
	} catch (Exception $e) {
		return renderWithJson($result, 'PaymentGateway could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
/**
 * POST pagePost
 * Summary: Create New page
 * Notes: Create page.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/pages', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $page = new Models\Page($args);
    $validationErrorFields = $page->validate($args);
    if (empty($validationErrorFields)) {
        $page->slug = getSlug($page->title);
        try {
            $page->save();
            $result = $page->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page user could not be added. Please, try again.', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET PagePageIdGet.
 * Summary: Get page.
 * Notes: Get page.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/pages/{slug}', function ($request, $response, $args) {
    $result = array();
    $queryParams = $request->getQueryParams();
    try {
        $page = Models\Page::where('id', $request->getAttribute('slug'))->first();
        if (!empty($page)) {
            $result['data'] = $page->toArray();
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found.', '', 1, 404);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'No record found.', '', 1, 404);
    }
});
$app->GET('/api/v1/pages', function ($request, $response, $args) {
    $pages = Models\Page::select('title', 'url')->get()->toArray();
	$results = array(
		'data' => $pages
	);
	return renderWithJson($results, 'pages details list fetched successfully','', 0);
});
/**
 * PUT PagepageIdPut
 * Summary: Update page by admin
 * Notes: Update page by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/pages/{pageId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $page = Models\Page::find($request->getAttribute('pageId'));
    $validationErrorFields = $page->validate($args);
    if (empty($validationErrorFields)) {
        $oldPageTitle = $page->title;
        $page->fill($args);
        if ($page->title != $oldPageTitle) {
            $page->slug = $page->slug = getSlug($page->title);
        }
        try {
            $page->save();
            $result['data'] = $page->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page could not be updated. Please, try again.', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
/**
 * DELETE PagepageIdDelete
 * Summary: DELETE page by admin
 * Notes: DELETE page by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/pages/{pageId}', function ($request, $response, $args) {
    $result = array();
    $page = Models\Page::find($request->getAttribute('pageId'));
    try {
        if (!empty($page)) {
            $page->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', $e->getMessage(), 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Page could not be deleted. Please, try again.', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));

/**
 * GET SettingcategoriesSettingCategoryIdGet
 * Summary: Get setting categories.
 * Notes: GEt setting categories.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/setting_categories/{settingCategoryId}', function ($request, $response, $args) {
    $result = array();
    $settingCategory = Models\SettingCategory::find($request->getAttribute('settingCategoryId'));
    if (!empty($settingCategory)) {
        $result['data'] = $settingCategory->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET SettingGet .
 * Summary: Get settings.
 * Notes: GEt settings.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/settings', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['is_mobile'])) {
            $settings = Models\Setting::select('name', 'value')->where('is_mobile', true)->get()->toArray();
        } else if (!empty($queryParams['is_web'])) {
			$settings = Models\Setting::select('name', 'value')->where('is_web', true)->get()->toArray();
		}
		$data = array();
		foreach($settings as $setting) {
			$data[$setting['name']] = $setting['value'];
		}
		if (!empty($queryParams['is_web'])) {
			$file = __DIR__ . '/admin-config.php';
			$resultSet = array();
			if (file_exists($file)) {
				require_once $file;
				$data['MENU'] = $menus;
				//if (!empty($authUser)) {
					//if ($authUser->role_id == \Constants\ConstUserTypes::Admin) {
						
					//} else if ($authUser->role_id == \Constants\ConstUserTypes::Company) {						
					//}
				//}
			}
		}
		$result = array(
			'data' => $data
		);
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
});
/**
 * GET settingssettingIdGet
 * Summary: GET particular Setting.
 * Notes: Get setting.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/settings/{settingId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'setting_category'
    );
    $setting = Models\Setting::with($enabledIncludes)->find($request->getAttribute('settingId'));
    if (!empty($setting)) {
        $result['data'] = $setting->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * PUT SettingsSettingIdPut
 * Summary: Update setting by admin
 * Notes: Update setting by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/settings/{settingId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $setting = Models\Setting::find($request->getAttribute('settingId'));
    $setting->fill($args);
    try {
        if (!empty($setting)) {
            if ($setting->name == 'ALLOWED_SERVICE_LOCATIONS') {
                $country_list = array();
                $city_list = array();
                $allowed_locations = array();
                if (!empty(!empty($args['allowed_countries']))) {
                    foreach ($args['allowed_countries'] as $key => $country) {
                        $country_list[$key]['id'] = $country['id'];
                        $country_list[$key]['name'] = $country['name'];
                        $country_list[$key]['iso_alpha2'] = '';
                        $country_details = Models\Country::select('iso_alpha2')->where('id', $country['id'])->first();
                        if (!empty($country_details)) {
                            $country_list[$key]['iso_alpha2'] = $country_details->iso_alpha2;
                        }
                    }
                    $allowed_locations['allowed_countries'] = $country_list;
                }
                if (!empty(!empty($args['allowed_cities']))) {
                    foreach ($args['allowed_cities'] as $key => $city) {
                        $city_list[$key]['id'] = $city['id'];
                        $city_list[$key]['name'] = $city['name'];
                    }
                    $allowed_locations['allowed_cities'] = $city_list;
                }
                $setting->value = json_encode($allowed_locations);
            }
            $setting->save();
            // Handle watermark image uploaad in settings
            if ($setting->name == 'WATERMARK_IMAGE' && !empty($args['image'])) {
                saveImage('WaterMark', $args['image'], $setting->id);
            }
            $result['data'] = $setting->toArray();
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found.', $e->getMessage(), 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Setting could not be updated. Please, try again.', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET EmailTemplateemailTemplateIdGet
 * Summary: Get paticular email templates
 * Notes: Get paticular email templates
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/email_templates/{emailTemplateId}', function ($request, $response, $args) {
    $result = array();
    $emailTemplate = Models\EmailTemplate::find($request->getAttribute('emailTemplateId'));
    if (!empty($emailTemplate)) {
        $result['data'] = $emailTemplate->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * PUT EmailTemplateemailTemplateIdPut
 * Summary: Put paticular email templates
 * Notes: Put paticular email templates
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/email_templates/{emailTemplateId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $emailTemplate = Models\EmailTemplate::find($request->getAttribute('emailTemplateId'));
    $validationErrorFields = $emailTemplate->validate($args);
    if (empty($validationErrorFields)) {
        $emailTemplate->fill($args);
        try {
            $emailTemplate->save();
            $result['data'] = $emailTemplate->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Email template could not be updated. Please, try again', $e->getMessage(), 1);
        }
    } else {
        return renderWithJson($result, 'Email template could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/attachments_profile', function ($request, $response, $args) {
	global $authUser;
	$userFiles = Models\Attachment::where('foreign_id', $authUser->id)->where('class', 'UserProfile')->get()->toArray();
	$response = array(
		'data' => $userFiles,
		'error' => array(
			'code' => 0,
			'message' => ''
		)
	);
	return renderWithJson($response);
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/attachments', function ($request, $response, $args) {
    global $configuration;
	global $authUser;
    $args = $request->getQueryParams();
	$file = $request->getUploadedFiles();
	$class = $args['class'];
	$ispaid = isset($args['ispaid']) ? $args['ispaid']: null;
	if ($class == "UserProfile" || $class == "City") {
		if (isset($args['direct'])) {
			$attachment = new Models\Attachment;
			$width = $info[0];
			$height = $info[1];
			$attachment->filename = $args['url'];
			$attachment->width = $width;
			$attachment->height = $height;
			$attachment->dir = '';
			$attachment->location = $args['location'];
			$attachment->caption = $args['caption'];
			$attachment->foreign_id = $args['id'];
			$attachment->class = $class;
			$attachment->user_id = $authUser->id;
			$attachment->save();
			$response = array(
				'error' => array(
					'code' => 0,
					'message' => 'Successfully uploaded'
				)
			);
			return renderWithJson($response);
		}
		$fileArray = $_FILES['file'];
		$imageFileArray = $_FILES['image'];
		$isError = false;
		$user_category = null; 
		if ($class == "UserProfile") {
			$user_category = Models\UserCategory::where('user_id', $authUser->id)->where('category_id', $args['category_id'])->first();
		}
		$attachmentArray = array();
		if(!empty($file['file'])) {
			$i = 0;
			foreach($file['file'] as $newfile) {
				$type = pathinfo($newfile->getClientFilename(), PATHINFO_EXTENSION);
				$fileName = str_replace(' ', '_', str_replace('.'.$type,"",$newfile->getClientFilename()).'_'.time().'.'.$type);
				$attachmentArray[] = $fileName;
				$attachment_settings = getAttachmentSettings($class);
				$file_formats = explode(",", $attachment_settings['allowed_file_formats']);
				$file_formats = array_map('trim', $file_formats);
				$kilobyte = 1024;
				$megabyte = $kilobyte * 1024;
				$fileArray["type"][$i] = get_mime($fileArray['tmp_name'][$i]);				
				$current_file_size = round($fileArray["size"][$i] / $megabyte, 2);
				//if (in_array($fileArray["type"][$i], $file_formats) || empty($attachment_settings['allowed_file_formats'])) {
					if ($class == "UserProfile" && preg_match('/video\/*/',$fileArray["type"][$i])) {
						$filePath = APP_PATH.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'UserProfile'.DIRECTORY_SEPARATOR.$user_category->id.DIRECTORY_SEPARATOR;
						if (!file_exists($filePath)) {
							mkdir($filePath,0777,true);
						}
						if (move_uploaded_file($newfile->file, $filePath.$fileName) === true) {
							$info = getimagesize($filePath.$fileName);
							$width = $info[0];
							$height = $info[1];
							$attachment = new Models\Attachment;
							$attachment->filename = $fileName;
							$attachment->width = $width;
							$attachment->height = $height;
							$attachment->location = $args['location'];
							$attachment->caption = $args['caption'];
							$attachment->dir = $class .DIRECTORY_SEPARATOR . $user_category->id;
							$attachment->foreign_id = $user_category->id;
							$attachment->class = $class;
							$attachment->ispaid = $ispaid;
							$attachment->mimetype = $info['mime'];
							$attachment->user_id = $authUser->id;
							$attachment->save();
							$attAttImageId = $attachment->id;
							$j = 0;
							foreach($file['image'] as $imageNewfile) {
								$imagetype = pathinfo($imageNewfile->getClientFilename(), PATHINFO_EXTENSION);
								$imageFileName = str_replace(' ', '_', str_replace('.'.$imagetype, '',$imageNewfile->getClientFilename()).'_'.time().'.'.$imagetype);
								$imageFileArray["type"][$j] = get_mime($imageFileArray['tmp_name'][$j]);				
								$current_file_size = round($imageFileArray["size"][$j] / $megabyte, 2);
								$imageClass = 'UserProfileVideoImage';
								$imageFilePath = APP_PATH.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.$imageClass.DIRECTORY_SEPARATOR.$user_category->id.DIRECTORY_SEPARATOR;
								if (!file_exists($imageFilePath)) {
									mkdir($imageFilePath,0777,true);
								}
								if (move_uploaded_file($imageNewfile->file, $imageFilePath.$imageFileName) === true) {
									$attachment = new Models\Attachment;
									$imageInfo = getimagesize($imageFilePath.$imageFileName);
									$width = $imageInfo[0];
									$height = $imageInfo[1];
									$attachment->filename = $imageFileName;
									$attachmentArray[] = $imageFileName;
									$attachment->width = $width;
									$attachment->height = $height;
									$attachment->location = $args['location'];
									$attachment->caption = $args['caption'];
									$attachment->ispaid = $ispaid;
									$attachment->dir = $imageClass .DIRECTORY_SEPARATOR . $user_category->id;
									$attachment->foreign_id = $attAttImageId;
									$attachment->class = $imageClass;
									$attachment->mimetype = $imageInfo['mime'];
									$attachment->user_id = $authUser->id;
									$attachment->save();
								} else {
									$isError = true;
								}
								$j++;
							}
						} else {
							$isError = true;
						}
					} else {
						if (!file_exists(APP_PATH . '/media/tmp/')) {
							mkdir(APP_PATH . '/media/tmp/',0777,true);
						}
						if ($type == 'php') {
							$type = 'txt';
						}
						if (move_uploaded_file($newfile->file, APP_PATH . '/media/tmp/' . $fileName) === true) {
							if ($class == "UserProfile") {
								$category_id = isset($args['category_id']) ? $args['category_id']: null;
								saveImage('UserProfile', $fileName, $user_category->id, true, $authUser->id, $ispaid, $args);
							}
						} else {
							$isError = true;
						}
					}
				//}
				$i++;
			}
		}
		if ($isError != true) {		
			$response = array(
								'attachments' => $attachmentArray,
								'error' => array(
									'code' => 0,
									'message' => 'Successfully uploaded'
								)
							);
		} else {
			$response = array(
									'error' => array(
										'code' => 1,
										'message' => 'Attachment could not be added.',
										'fields' => ''
									)
								);
		}
		return renderWithJson($response);
	} else {
		$class = $args['class'];
		$user_category = null; 
		if(!empty($file)) {
			$newfile = $file['file'];
			$type = pathinfo($newfile->getClientFilename(), PATHINFO_EXTENSION);
			$fileName = str_replace('.'.$type,"",$newfile->getClientFilename()).'_'.time().'.'.$type;
			$name = md5(time());
			$attachment_settings = getAttachmentSettings($class);
			$file = $_FILES['file'];
			
			$file_formats = explode(",", $attachment_settings['allowed_file_formats']);
			$file_formats = array_map('trim', $file_formats);
			$max_file_size = $attachment_settings['allowed_file_size'];
			$kilobyte = 1024;
			$megabyte = $kilobyte * 1024;
			$file["type"] = get_mime($file['tmp_name']);  
			
			$current_file_size = round($file["size"] / $megabyte, 2);
			if (in_array($file["type"], $file_formats) || empty($attachment_settings['allowed_file_formats'])) {
				if (empty($max_file_size) || (!empty($max_file_size) && $current_file_size <= $max_file_size)) {
					if (!file_exists(APP_PATH . '/media/tmp/')) {
						mkdir(APP_PATH . '/media/tmp/',0777,true);
					}
					if ($type == 'php') {
						$type = 'txt';
					}
					if (move_uploaded_file($newfile->file, APP_PATH . '/media/tmp/' . $name . '.' . $type) === true) {
						$filename = $name . '.' . $type;
						if ($class == "UserProfile") {
							$category_id = isset($args['category_id']) ? $args['category_id']: null;
							saveImage('UserProfile', $filename, $user_category->id, true, $authUser->id, null);
						}
						$response = array(
							'attachment' => $filename,
							'error' => array(
								'code' => 0,
								'message' => 'Successfully uploaded'
							)
						);
					} else {
						$response = array(
							'error' => array(
								'code' => 1,
								'message' => 'Attachment could not be added.',
								'fields' => ''
							)
						);
					}
				} else {
					$response = array(
						'error' => array(
							'code' => 1,
							'message' => "The uploaded file size exceeds the allowed " . $attachment_settings['allowed_file_size'] . "MB",
							'fields' => ''
						)
					);
				}
			} else {
				$response = array(
					'error' => array(
						'code' => 1,
						'message' => "File couldn't be uploaded. Allowed extensions: " . $attachment_settings['allowed_file_extensions'],
						'fields' => ''
					)
				);
			}
		} else {
			$userFiles = Models\Attachment::where('foreign_id', $authUser->id)->where('class', 'UserProfile')->get()->toArray();
			$response = array(
				'data' => $userFiles,
				'error' => array(
					'code' => 0,
					'message' => ''
				)
			);
		}
		return renderWithJson($response);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/attachments_mutiple', function ($request, $response, $args) {
    global $configuration;
	global $authUser;
    $args = $request->getQueryParams();
	$file = $request->getUploadedFiles();
	$class = $args['class'];
	$fileArray = $_FILES['file'];
	$isError = false;
	$attachmentArray = array();
	if(!empty($file['file'])) {
		$i = 0;
		foreach($file['file'] as $newfile) {
			$type = pathinfo($newfile->getClientFilename(), PATHINFO_EXTENSION);
			$fileName = str_replace(' ', '_', str_replace('.'.$type,"",$newfile->getClientFilename()).'_'.time().'.'.$type);
			$attachment_settings = getAttachmentSettings($class);
			$file_formats = explode(",", $attachment_settings['allowed_file_formats']);
			$file_formats = array_map('trim', $file_formats);
			$kilobyte = 1024;
			$megabyte = $kilobyte * 1024;
			$fileArray["type"][$i] = get_mime($fileArray['tmp_name'][$i]);				
			$current_file_size = round($fileArray["size"][$i] / $megabyte, 2);					
			if (!file_exists(APP_PATH . '/media/tmp/')) {
				mkdir(APP_PATH . '/media/tmp/',0777,true);
			}
			if ($type == 'php') {
				$type = 'txt';
			}
			if (move_uploaded_file($newfile->file, APP_PATH . '/media/tmp/' . $fileName) === true) {
				$attachmentArray[] = $fileName;
			} else {
				$isError = true;
			}
			$i++;
		}
	}
	if ($isError != true) {		
		$response = array(
							'attachments' => $attachmentArray,
							'error' => array(
								'code' => 0,
								'message' => 'Successfully uploaded'
							)
						);
	} else {
		$response = array(
								'error' => array(
									'code' => 1,
									'message' => 'Attachment could not be added.',
									'fields' => ''
								)
							);
	}
	return renderWithJson($response);
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/settings', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        if (empty($queryParams['sortby'])) {
            $queryParams['sortby'] = 'ASC';
        }
		$queryParams['is_active'] = true;
        $settingCategories = Models\SettingCategory::Filter($queryParams);
        // We are not implement Widget now, So we doen't return Widget data
        $settingCategories = $settingCategories->paginate($count)->toArray();
        $data = $settingCategories['data'];
        unset($settingCategories['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $settingCategories
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/admin/settings/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$settings = Models\Setting::where('setting_category_id', $request->getAttribute('id'))->where('is_active', true)->get();	
		$result = array();
		$result['data'] = $settings;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/settings/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		foreach ($args as $key=>$value) {
			Models\Setting::where('name', $key)->update(array(
				'value' => $value
			));
		}
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/payment_gateways/{id}', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $paymentGateways = Models\PaymentGateway::where('id', $request->getAttribute('id'))->first();
		$paymentGateways_model = new Models\PaymentGateway;
		$paymentGateways->makeVisible($paymentGateways_model->hidden);
		$paymentGateways = $paymentGateways->toArray();
		$data = array();
		if ($request->getAttribute('id') == 1) {
			$subarray = array();
			$subarray['name'] = 'sanbox_paypal_email';
			$subarray['label'] = 'Sanbox Paypal Email';
			$subarray['value'] = $paymentGateways['sanbox_paypal_email'];
			$subarray['is_required'] = true;
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['name'] = 'sanbox_userid';
			$subarray['label'] = 'Sanbox Userid';
			$subarray['value'] = $paymentGateways['sanbox_userid'];
			$subarray['is_required'] = true;
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['name'] = 'sanbox_password';
			$subarray['label'] = 'Sanbox Password';
			$subarray['is_required'] = true;
			$subarray['value'] = $paymentGateways['sanbox_password'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;			
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_signature';
			$subarray['label'] = 'Sanbox Signature';
			$subarray['value'] = $paymentGateways['sanbox_signature'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_application_id';
			$subarray['label'] = 'Sanbox Application Id';
			$subarray['value'] = $paymentGateways['sanbox_application_id'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['name'] = 'live_paypal_email';
			$subarray['label'] = 'Live Paypal Email';
			$subarray['value'] = $paymentGateways['live_paypal_email'];
			$subarray['is_required'] = true;
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_userid';
			$subarray['label'] = 'Live Userid';
			$subarray['value'] = $paymentGateways['live_userid'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_password';
			$subarray['label'] = 'Live Password';
			$subarray['value'] = $paymentGateways['live_password'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;			
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_signature';
			$subarray['label'] = 'Live Signature';
			$subarray['value'] = $paymentGateways['live_signature'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_application_id';
			$subarray['label'] = 'Live Application Id';
			$subarray['value'] = $paymentGateways['live_application_id'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_more_ten';
			$subarray['label'] = 'Paypal Fee More Then 10$ percentage';
			$subarray['value'] = $paymentGateways['paypal_more_ten'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_more_ten_in_cents';
			$subarray['label'] = 'Paypal Fee More Then 10$ In cents';
			$subarray['value'] = $paymentGateways['paypal_more_ten_in_cents'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_less_ten';
			$subarray['label'] = 'Paypal Fee Less Then 10$ percentage';
			$subarray['value'] = $paymentGateways['paypal_less_ten'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_less_ten_in_cents';
			$subarray['label'] = 'Paypal Fee Less Then 10$ In cents';
			$subarray['value'] = $paymentGateways['paypal_less_ten_in_cents'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
		} else if ($request->getAttribute('id') == 2) {
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_secret_key';
			$subarray['label'] = 'Sanbox Secret key';
			$subarray['value'] = $paymentGateways['sanbox_secret_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_publish_key';
			$subarray['label'] = 'Sanbox Publish Key';
			$subarray['value'] = $paymentGateways['sanbox_publish_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_secret_key';
			$subarray['label'] = 'Live Secret Key';
			$subarray['value'] = $paymentGateways['live_secret_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_publish_key';
			$subarray['label'] = 'Live Publish Key';
			$subarray['value'] = $paymentGateways['live_publish_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
		}
		$subarray = array();
		$subarray['name'] = 'is_test_mode';
		$subarray['label'] = 'Test Mode';
		$subarray['value'] = ($paymentGateways['is_test_mode'] == 1) ? true : false;
		$subarray['type'] = 'checkbox';
		$subarray['edit'] = true;
		$data[] = $subarray;
		
		$subarray = array();
		$subarray['name'] = 'is_active';
		$subarray['label'] = 'Active';
		$subarray['value'] = ($paymentGateways['is_active'] == 1) ? true : false;
		$subarray['type'] = 'checkbox';
		$subarray['edit'] = true;
		$data[] = $subarray;
		
		$result = array(
            'data' => $data
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/payment_gateways/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\PaymentGateway::where('id', $request->getAttribute('id'))->update($args);
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/payment_gateways', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $paymentGateways = Models\PaymentGateway::with('attachment')->get()->toArray();
		$result = array(
            'data' => $paymentGateways
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/static_content', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $pages = Models\Page::Filter($queryParams)->paginate($count)->toArray();
        $data = $pages['data'];
        unset($pages['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $pages
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/static_content/{id}', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$page = Models\Page::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $page;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/admin/static_content/{id}', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		Models\Page::where('id', $request->getAttribute('id'))->update(array(
			'content' => $args['content'],
			'title' => $args['title'],
			'dispaly_url' => $args['dispaly_url'],
			'url' => '/page/'.$args['dispaly_url']
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/restaurant/detail/{id}', function ($request, $response, $args) {
	global $authUser;
	$restaurant = array();
	if ($authUser->role_id === \Constants\ConstUserTypes::Employer) {
		$restaurant = Models\Restaurant::with('slots', 'attachments', 'menus', 'special_conditions', 'facilities_services', 'atmospheres', 'languages', 'operating_hours', 'hours', 'booking_types','about', 'user', 'city', 'country', 'payment', 'themes', 'cuisines', 'promos')->where('user_id', $authUser->id)->first();
	} else if ($authUser->role_id === \Constants\ConstUserTypes::Company) {
		$restaurant = Models\Restaurant::with('slots', 'attachments', 'menus', 'special_conditions', 'facilities_services', 'atmospheres', 'languages', 'operating_hours', 'hours','booking_types','about', 'user', 'city', 'country', 'payment', 'themes', 'cuisines', 'promos')->where('brand_id', $authUser->id)->where('id', $request->getAttribute('id'))->first();
	} else {
		$restaurant = Models\Restaurant::with('slots', 'attachments', 'menus', 'special_conditions', 'facilities_services', 'atmospheres', 'languages', 'operating_hours', 'hours','booking_types','about', 'user', 'city', 'country', 'payment', 'themes', 'cuisines', 'promos')->where('id', $request->getAttribute('id'))->first();
	}
	
	if (!empty($restaurant)) {
		$restaurant = $restaurant->toArray();
		$operating_hours = array();
		if (!empty($restaurant['operating_hours'])) {
			$i = 0;
			foreach ($restaurant['operating_hours'] as $operatingHours) {
				$hours = array();
				if (!empty($restaurant['hours'])) {
					$hours = array_filter($restaurant['hours'], function($obj) use ($operatingHours) {
						return ($operatingHours['day']['id'] == $obj['day']['id']);
					});
				}
				$hourArray = array();
				if (!empty($hours)) {
					foreach($hours as $hour) {
						if ($hour['type'] == 1) {
							$hourArray[] = array(
								'name' => 'Breakfast',
								'type' => 1,
								'start_time' => $hour['from'],
								'end_time' => $hour['to']
							);
						} else if ($hour['type'] == 2) {
							$hourArray[] = array(
								'name' => 'Lunch',
								'type' => 2,
								'start_time' => $hour['from'],
								'end_time' => $hour['to']
							);
						} else if ($hour['type'] == 3) {
							$hourArray[] = array(
								'name' => 'Dinner',
								'type' => 3,
								'start_time' => $hour['from'],
								'end_time' => $hour['to']
							);
						}
					}
				} else {
					$hourArray[] = array(
								'name' => 'Breakfast',
								'type' => 1,
								'start_time' => 'Select',
								'end_time' => 'Select'
							);
					$hourArray[] = array(
								'name' => 'Lunch',
								'type' => 2,
								'start_time' => 'Select',
								'end_time' => 'Select'
							);
					$hourArray[] = array(
								'name' => 'Dinner',
								'type' => 3,
								'start_time' => 'Select',
								'end_time' => 'Select'
							);
				}
				$operating_hours[] = array('day'=> $operatingHours['day']['name'],
                                'holiday'=> $operatingHours['holiday'] == 1 ? true : false,
                                'allday'=> $operatingHours['allday'] == 1 ? true : false,
                                'hours'=> $hourArray);
			}
			$restaurant['operating_hours'] = $operating_hours;
		}
		$result = array(
            'data' => $restaurant
        );
		return renderWithJson($result, 'Successfully','', 0);
	} else {
		return renderWithJson($result, 'No Records', $e->getMessage(), 1);
	}	
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/restaurant/delete/{id}', function ($request, $response, $args) {
	global $authUser;
	$restaurant = array();
	if ($authUser->role_id === \Constants\ConstUserTypes::Employer) {
		$restaurant = Models\Restaurant::where('user_id', $authUser->id)->where('id', $request->getAttribute('id'))->first();
	} else if ($authUser->role_id === \Constants\ConstUserTypes::Company) {
		$restaurant = Models\Restaurant::where('brand_id', $authUser->id)->where('id', $request->getAttribute('id'))->first();
	}
	if (!empty($restaurant)) {
		$result = array(
            'data' => $restaurant
        );
		return renderWithJson($result, 'Successfully','', 0);
	} else {
		return renderWithJson($result, 'No Records', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/restaurants', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		if ($authUser->role_id === \Constants\ConstUserTypes::Employer) {
			$queryParams['user_id'] = $authUser->id;
		} else if ($authUser->role_id === \Constants\ConstUserTypes::Company) {
			$queryParams['brand_id'] = $authUser->id;
			$queryParams['is_admin_deactived'] = true;
		}
		$queryParams['is_active'] = true;
        $respones = Models\Restaurant::with('user')->Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->POST('/api/v1/admin/restaurants', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$result = array();
    try {
		$user = new Models\User($args);
		$validationErrorFields = $user->validate($args);
		if (!empty($validationErrorFields)) {
			$validationErrorFields = $validationErrorFields->toArray();
		} else {
			$validationErrorFields = array();
		}
		$validationErrorFields['unique'] = array();
		$validationErrorFields['required'] = array();

		if (checkAlreadyUsernameExists($args['username'])) {
			array_push($validationErrorFields['unique'], 'username');
		}
		if (checkAlreadyEmailExists($args['email'])) {
			array_push($validationErrorFields['unique'], 'email');
		}
		if (empty($validationErrorFields['unique'])) {
			unset($validationErrorFields['unique']);
		}
		if (empty($validationErrorFields['required'])) {
			unset($validationErrorFields['required']);
		}
		if (!empty($args['is_active'])) {
			$user->is_active = $args['is_active'];
		 }
		 if (!empty($args['is_email_confirmed'])) {
			$user->is_email_confirmed = $args['is_email_confirmed'];
		 } 
		if (empty($validationErrorFields)) {
			$user->password = getCryptHash($args['password']);
			$user->role_id = \Constants\ConstUserTypes::Employer;
			$user->save();
		} else {
			return renderWithJson($result, 'Restaurant could not be added. Since following fields should be unique '.implode(', ', $validationErrorFields['unique']).' Please, try again.', $validationErrorFields, 1);
		}
        $restaurant = new Models\Restaurant;
		$restaurant->user_id = $user->id;
		$restaurant->brand_id = $authUser->id;
		$restaurant->title = $args['title'];
		$restaurant->address = $args['address'];
		$restaurant->latitude = $args['latitude'];
		$restaurant->longitude = $args['longitude'];
		$restaurant->description = $args['description'];
		$restaurant->disclaimer = $args['disclaimer'];
		$restaurant->timezone_id = $args['timezone_id'];
		$restaurant->max_person = $args['maxperson'];
		$restaurant->state = $args['state'];
		$restaurant->is_promo_code = (!empty($args['promos'])) ? 1 : 0;
		if (!isset($args['country_id'])) {
			$country = $args['country'];
			$countryDetail = Models\Country::where('name', $country)->first();
			if (!empty($countryDetail)) {
				$restaurant->country_id = $countryDetail['id'];
			}
		} else {
			$restaurant->country_id = $args['country_id'];
		}
		$city = $args['city'];
		$cityDetail = Models\City::where('name',$city)->first();
		if (!empty($cityDetail)) {
			$restaurant->city_id = $cityDetail->id;
		} else {
			$city = new Models\City;
			$city->country_id = $restaurant->country_id;
			$city->name = $args['city'];
			$city->save();
			$restaurant->city_id = $city->id;
		}
		$restaurant->is_active = true;
		if ($restaurant->save()) {
			if ($restaurant->id) {
				if (!empty($args['specialConditions'])) {
					foreach($args['specialConditions'] as $specialCondition) {
						$specialConditions = new Models\SpecialCondition;
						$specialConditions->restaurant_id = $restaurant->id;
						$specialConditions->condition = $specialCondition['name'];
						$specialConditions->save();
					}
				}
				if (!empty($args['facilitity_others'])) {
					$facilitiesService = new Models\FacilitiesService;
					$facilitiesService->name = $args['facilitity_others'];
					$facilitiesService->save();
					$restaurant->city_id = $facilitiesService->id;
					$args['facilities'] = array_merge(array(array('id' => array($facilitiesService->id))), $args['facilities']);
				}
				if (!empty($args['atmospheres_others'])) {
					$atmosphere = new Models\Atmosphere;
					$atmosphere->name = $args['atmospheres_others'];
					$atmosphere->save();
					$args['atmospheres'] = array_merge(array(array('id' => array($atmosphere->id))), $args['atmospheres']);
				}
				if (!empty($args['languages_others'])) {
					$language = new Models\Language;
					$language->name = $args['languages_others'];
					$language->save();
					$args['languages'] = array_merge(array(array('id' => array($language->id))), $args['languages']);
				}
				if (!empty($args['themes_others'])) {
					$theme = new Models\Theme;
					$theme->name = $args['themes_others'];
					$theme->save();
					$args['themes'] = array_merge(array(array('id' => array($theme->id))), $args['themes']);
				}
				if (!empty($args['cuisines_others'])) {
					$cuisine = new Models\Cuisine;
					$cuisine->name = $args['cuisines_others'];
					$cuisine->save();
					$args['cuisines'] = array_merge(array(array('id' => array($cuisine->id))), $args['cuisines']);
				}
				if (!empty($args['facilities'])) {
					foreach($args['facilities'] as $facility) {
						$facilitiesService = new Models\RestaurantFacilitiesService;
						$facilitiesService->restaurant_id = $restaurant->id;
						$facilitiesService->facilities_service_id = $facility['id'];
						$facilitiesService->save();
					}
				}
				if (!empty($args['menus'])) {
					foreach($args['menus'] as $menu) {
						$menuService = new Models\Menu;
						$menuService->restaurant_id = $restaurant->id;
						$menuService->name = $menu['name'];
						$menuService->price = $menu['price'];
						$menuService->is_active = true;
						$menuService->save();
					}
				}
				if (!empty($args['atmospheres'])) {
					foreach($args['atmospheres'] as $atmosphere) {
						$atmosphereService = new Models\RestaurantAtmosphere;
						$atmosphereService->restaurant_id = $restaurant->id;
						$atmosphereService->atmosphere_id = $atmosphere['id'];
						$atmosphereService->save();
					}
				}
				if (!empty($args['languages'])) {
					foreach($args['languages'] as $language) {
						$languageService = new Models\RestaurantLanguage;
						$languageService->restaurant_id = $restaurant->id;
						$languageService->language_id = $language['id'];
						$languageService->save();
					}
				}
				if (!empty($args['payments'])) {
					foreach($args['payments'] as $payment) {
						$paymentService = new Models\RestaurantPayment;
						$paymentService->restaurant_id = $restaurant->id;
						$paymentService->payment_id = $payment['id'];
						$paymentService->save();
					}
				}
				if (!empty($args['themes'])) {
					foreach($args['themes'] as $theme) {
						$themeService = new Models\RestaurantTheme;
						$themeService->restaurant_id = $restaurant->id;
						$themeService->theme_id = $theme['id'];
						$themeService->save();
					}
				}
				if (!empty($args['cuisines'])) {
					foreach($args['cuisines'] as $cuisine) {
						$cuisineService = new Models\RestaurantCuisine;
						$cuisineService->restaurant_id = $restaurant->id;
						$cuisineService->cuisine_id = $cuisine['id'];
						$cuisineService->save();
					}
				}
				if (!empty($args['about'])) {
					$about = new Models\RestaurantAboutUs;
					$about->restaurant_id = $restaurant->id;
					$about->about = $args['about'];
					$about->save();
				}
				if (!empty($args['promos'])) {
					foreach($args['promos'] as $promo) {
						$restaurantPromo = new Models\RestaurantPromo;
						$restaurantPromo->restaurant_id = $restaurant->id;
						$restaurantPromo->code = $promo['code'];
						$restaurantPromo->amount = $promo['amount'];
						$restaurantPromo->save();
					}
				}
				if (!empty($args['booking_types'])) {
					foreach($args['booking_types'] as $bookingType) {
						$restaurantBookingType = new Models\RestaurantBookingType;
						$restaurantBookingType->restaurant_id = $restaurant->id;
						$restaurantBookingType->booking_type_id = $bookingType['id'];
						$restaurantBookingType->save();
					}
				}
				if (!empty($args['operating_hours'])) {
					$i = 1;
					foreach($args['operating_hours'] as $operating_hour) {
						$operatingHour = new Models\OperatingHour;
						$operatingHour->type = 0;
						$operatingHour->day_id = $i;
						$operatingHour->restaurant_id = $restaurant->id;
						$operatingHour->holiday = $operating_hour['holiday'];
						$operatingHour->allday = $operating_hour['allday'];
						$operatingHour->save();
						if (!empty($operating_hour['hours'])) {
							foreach($operating_hour['hours'] as $hour) {
								if ($hour['start_time'] != '' && $hour['end_time'] != '' && $hour['start_time'] != 'Select' && $hour['end_time'] != 'Select' && $hour['start_time'] != '' && $hour['end_time'] != '') {
									$operatingHour = new Models\OperatingHour;
									if ($hour['name'] == 'Breakfast') {
										$operatingHour->type = 1;
									} else if ($hour['name'] == 'Lunch') {
										$operatingHour->type = 2;
									} else {
										$operatingHour->type = 3;
									}
									$operatingHour->restaurant_id = $restaurant->id;
									$operatingHour->day_id = $i;
									$operatingHour->from = $hour['start_time'];
									$operatingHour->to = $hour['end_time'];
									$operatingHour->save();
								}
							}
						}
						$i++;
					}
				}
				if (!empty($args['attachments'])) {
					foreach($args['attachments'] as $attachment) {
						saveImage('Restaurant', $attachment, $restaurant->id);
					}
				}
				restaurantCountUpdate();
				return renderWithJson($result, 'Successfully added','', 0);
			}
		} else {
			return renderWithJson($result, 'Restaurant could not be added. Please, try again.', $e->getMessage(), 1);
		}        
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->PUT('/api/v1/admin/restaurants/{id}', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$result = array();
    try {
		$restaurant_id = $request->getAttribute('id');
		$country_id = 0;
		$city_id = 0;
		if (!isset($args['country_id'])) {
			$country = $args['country'];
			$countryDetail = Models\Country::where('name',$country)->first();
			if (!empty($countryDetail)) {
				$country_id = $countryDetail['id'];
			}
		} else {
			$country_id = $args['country_id'];
		}
		$city = $args['city'];
		$cityDetail = Models\City::where('name', $city)->first();
		if (!empty($cityDetail)) {
			$city_id = $cityDetail->id;
		} else {
			$city = new Models\City;
			$city->country_id = $country_id;
			$city->name = $args['city'];
			$city->save();
			$city_id = $city->id;
		}
		Models\Restaurant::where('id', $restaurant_id)->update(array(
			'title' => $args['title'],
			'address' => $args['address'],
			'latitude' => $args['latitude'],
			'longitude' => $args['longitude'],
			'description' => $args['description'],
			'disclaimer' => $args['disclaimer'],
			'max_person' => $args['maxperson'],
			'state' => $args['state'],
			'country_id' => $country_id,
			'city_id' => $city_id,
			'timezone_id' => $args['timezone_id'],
			'is_active' => $args['is_active'],
			'is_promo_code' => (!empty($args['promos'])) ? 1 : 0
		));
		Capsule::select('Delete from special_conditions where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_facilities_services where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from menus where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_atmospheres where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_languages where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_payments where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_about_us where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_themes where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_cuisines where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from operating_hours where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_promos where restaurant_id='.$restaurant_id);
		Capsule::select('Delete from restaurant_booking_types where restaurant_id='.$restaurant_id);
		if (!empty($args['attachmentsDeleted'])) {
			Capsule::select('Delete from attachments where class=\'Restaurant\' and id in ('.implode(",", $args['attachmentsDeleted']).')');				
		}
		if (!empty($args['operating_hours'])) {
			$i = 1;
			foreach($args['operating_hours'] as $operating_hour) {
				$operatingHour = new Models\OperatingHour;
				$operatingHour->type = 0;
				$operatingHour->day_id = $i;
				$operatingHour->restaurant_id = $restaurant_id;
				$operatingHour->holiday = $operating_hour['holiday'];
				$operatingHour->allday = $operating_hour['allday'];
				$operatingHour->save();
				if (!empty($operating_hour['hours'])) {
					foreach($operating_hour['hours'] as $hour) {
						if ($hour['start_time'] != '' && $hour['end_time'] != '' && $hour['start_time'] != 'Select' && $hour['end_time'] != 'Select' && $hour['start_time'] != '' && $hour['end_time'] != '') {
							$operatingHour = new Models\OperatingHour;
							if ($hour['name'] == 'Breakfast') {
								$operatingHour->type = 1;
							} else if ($hour['name'] == 'Lunch') {
								$operatingHour->type = 2;
							} else {
								$operatingHour->type = 3;
							}
							$operatingHour->restaurant_id = $restaurant_id;
							$operatingHour->day_id = $i;
							$operatingHour->from = $hour['start_time'];
							$operatingHour->to = $hour['end_time'];
							$operatingHour->save();
						}
					}
				}
				$i++;
			}
		}
		if (!empty($args['facilitity_others'])) {
			$facilitiesService = new Models\FacilitiesService;
			$facilitiesService->name = $args['facilitity_others'];
			$facilitiesService->save();
			$restaurant->city_id = $facilitiesService->id;
			$args['facilities'] = array_merge(array(array('id' => $facilitiesService->id)), $args['facilities']);
		}
		
		if (!empty($args['atmospheres_others'])) {
			$atmosphere = new Models\Atmosphere;
			$atmosphere->name = $args['atmospheres_others'];
			$atmosphere->save();
			$args['atmospheres'] = array_merge(array(array('id' => $atmosphere->id)), $args['atmospheres']);
		}
		if (!empty($args['languages_others'])) {
			$language = new Models\Language;
			$language->name = $args['languages_others'];
			$language->save();
			$args['languages'] = array_merge(array(array('id' => $language->id)), $args['languages']);
		}
		if (!empty($args['themes_others'])) {
			$theme = new Models\Theme;
			$theme->name = $args['themes_others'];
			$theme->save();
			$args['themes'] = array_merge(array(array('id' => $theme->id)), $args['themes']);
		}
		if (!empty($args['cuisines_others'])) {
			$cuisine = new Models\Cuisine;
			$cuisine->name = $args['cuisines_others'];
			$cuisine->save();
			$args['cuisines'] = array_merge(array(array('id' => $cuisine->id)), $args['cuisines']);
		}
		if (!empty($args['specialConditions'])) {
			foreach($args['specialConditions'] as $specialCondition) {
				$specialConditions = new Models\SpecialCondition;
				$specialConditions->restaurant_id = $restaurant_id;
				$specialConditions->condition = $specialCondition['name'];
				$specialConditions->save();
			}
		}
		if (!empty($args['facilities'])) {
			foreach($args['facilities'] as $facility) {
				$facilitiesService = new Models\RestaurantFacilitiesService;
				$facilitiesService->restaurant_id = $restaurant_id;
				$facilitiesService->facilities_service_id = $facility['id'];
				$facilitiesService->save();
			}
		}
		if (!empty($args['menus'])) {
			foreach($args['menus'] as $menu) {
				$menuService = new Models\Menu;
				$menuService->restaurant_id = $restaurant_id;
				$menuService->name = $menu['name'];
				$menuService->price = $menu['price'];
				$menuService->is_active = true;
				$menuService->save();
			}
		}
		if (!empty($args['languages'])) {
			foreach($args['languages'] as $language) {
				$languageService = new Models\RestaurantLanguage;
				$languageService->restaurant_id = $restaurant_id;
				$languageService->language_id = $language['id'];
				$languageService->save();
			}
		}		
		if (!empty($args['payments'])) {
			foreach($args['payments'] as $payment) {
				$paymentService = new Models\RestaurantPayment;
				$paymentService->restaurant_id = $restaurant_id;
				$paymentService->payment_id = $payment['id'];
				$paymentService->save();
			}
		}
		if (!empty($args['about'])) {
			$about = new Models\RestaurantAboutUs;
			$about->restaurant_id = $restaurant_id;
			$about->about = $args['about'];
			$about->save();
		}
		if (!empty($args['themes'])) {
			foreach($args['themes'] as $theme) {
				$themeService = new Models\RestaurantTheme;
				$themeService->restaurant_id = $restaurant_id;
				$themeService->theme_id = $theme['id'];
				$themeService->save();
			}
		}
		if (!empty($args['cuisines'])) {
			foreach($args['cuisines'] as $cuisine) {
				$cuisineService = new Models\RestaurantCuisine;
				$cuisineService->restaurant_id = $restaurant_id;
				$cuisineService->cuisine_id = $cuisine['id'];
				$cuisineService->save();
			}
		}
		if (!empty($args['attachments'])) {
			foreach($args['attachments'] as $attachment) {
				saveImage('Restaurant', $attachment, $restaurant_id);
			}				
		}
		if (!empty($args['atmospheres'])) {
			foreach($args['atmospheres'] as $atmosphere) {
				$atmosphereService = new Models\RestaurantAtmosphere;
				$atmosphereService->restaurant_id = $restaurant_id;
				$atmosphereService->atmosphere_id = $atmosphere['id'];
				$atmosphereService->save();
			}
		}
		if (!empty($args['promos'])) {
			foreach($args['promos'] as $promo) {
				$restaurantPromo = new Models\RestaurantPromo;
				$restaurantPromo->restaurant_id = $restaurant_id;
				$restaurantPromo->code = $promo['code'];
				$restaurantPromo->amount = $promo['amount'];
				$restaurantPromo->save();
			}
		}
		if (!empty($args['booking_types'])) {
			foreach($args['booking_types'] as $bookingType) {
				$restaurantBookingType = new Models\RestaurantBookingType;
				$restaurantBookingType->restaurant_id = $restaurant_id;
				$restaurantBookingType->booking_type_id = $bookingType['id'];
				$restaurantBookingType->save();
			}
		}
		restaurantCountUpdate();
		return renderWithJson(array(), 'Successfully updated','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->PUT('/api/v1/admin/restaurants/delete/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Restaurant::where('id', $request->getAttribute('id'))->update(array(
			'is_deactived' => 1,
			'is_active' => 1
		));
		restaurantCountUpdate();
		return renderWithJson(array(), 'Successfully deleted','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->GET('/api/v1/countries', function ($request, $response, $args) use ($app)
{
	$countries = Models\Country::with('cites')->where('count', '<>', 0)->get()->toArray();
	$results = array(
		'data' => $countries
	);
	return renderWithJson($results, 'country details list fetched successfully','', 0);
});
$app->GET('/api/v1/admin/categories', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
        $respones = Models\Category::Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/timezones', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = 1000;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
        $respones = Models\Timezone::select('id', 'name')->Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/categories/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$category = Models\Category::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $category;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/categories', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
        $category = new Models\Category;
		$category->name = $args['name'];
		$category->is_active = true;
		$category->save();
        return renderWithJson(array(), 'Successfully added','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/categories/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Category::where('id', $request->getAttribute('id'))->update(array(
			'name' => $args['name']
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/categories/delete/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Category::where('id', $request->getAttribute('id'))->update(array(
			'is_active' => false
		));
		return renderWithJson(array(), 'Successfully delete','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/email_templates', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $emailTemplates = Models\EmailTemplate::Filter($queryParams)->paginate($count)->toArray();
        $data = $emailTemplates['data'];
        unset($emailTemplates['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $emailTemplates
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/admin/email_templates/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$emailTemplate = Models\EmailTemplate::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $emailTemplate;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/email_templates/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\EmailTemplate::where('id', $request->getAttribute('id'))->update(array(
			'subject' => $args['subject'],
			'html_email_content' => $args['html_email_content'],
			'description' => $args['description'],
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/users', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
		if (!empty($queryParams['class'])) {
			if ($queryParams['class'] === 'employer') {
				$queryParams['role_id'] = \Constants\ConstUserTypes::Company;
			} else if ($queryParams['class'] === 'restaurants') {
				$queryParams['role_id'] = \Constants\ConstUserTypes::Employer;
			} else if ($queryParams['class'] === 'employer_list') {
				$queryParams['company_id'] = $authUser->id;
			} else {
				$queryParams['role_id'] = \Constants\ConstUserTypes::User;
			}
			unset($queryParams['class']);
		}
		$respones = Models\User::Filter($queryParams)->paginate($count);
		$user_model = new Models\User;
		$respones->makeVisible($user_model->hidden);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/users/{userId}', function ($request, $response, $args) {
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$enabledIncludes = array(
			'attachment'
		);
		$_GET['user_id'] = $request->getAttribute('userId');
		$user = Models\User::with($enabledIncludes)->where('id', $request->getAttribute('userId'))->first();
		$user->makeVisible(['email']);
		$user = $user->toArray();
		$user['category'] = array();
		if (!empty($user['user_categories'])) {
			foreach($user['user_categories'] as $cat) {
				$user['category'][] = $cat['category'];
			}
			unset($user['user_categories']);
		}
		$result = array();
		$result['data'] = $user;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/users', function ($request, $response, $args) {
	global $authUser;
	try {
		$args = $request->getParsedBody();
		$queryParams = $request->getQueryParams();
		$role_id = \Constants\ConstUserTypes::User;
		$company_id = 0;
		if ($queryParams['class'] === 'employer') {
			$role_id = \Constants\ConstUserTypes::Company;
			$company_id = $authUser->id;
		} else if ($queryParams['class'] === 'restaurants') {
			$role_id = \Constants\ConstUserTypes::Employer;
			$company_id = $authUser->id;
		} else if ($queryParams['class'] === 'employer_list') {
			$role_id = \Constants\ConstUserTypes::User;
			$company_id = $authUser->id;
		} else {
			$role_id = \Constants\ConstUserTypes::User;
		}
		$result = addAdminUser($args, $role_id, $company_id);
		return renderWithJson(array(), $result['message'],'', $result['code']);
	} catch (Exception $e) {
        return renderWithJson(array(), 'No record found.'.$e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/users/delete/{userId}', function ($request, $response, $args) {
	global $authUser;
	$userId = $request->getAttribute('userId');
	if ($userId != 1) {
		$user = Models\User::find($userId);
		if ($user->role_id = \Constants\ConstUserTypes::Company) {
			Models\User::where('company_id', $userId)->update(array(
				'is_active' => false
			));
		}
		Models\User::where('id', $userId)->update(array(
			'is_active' => false
		));
		return renderWithJson(array(), 'User is successfully deleted','', 0);
	}
	return renderWithJson(array(), 'User could not be deleted',$e->getMessage(), 1);
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/users/{userId}', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$image = $args['image'];
	$cover_photo = $args['cover_photo'];
	$addressDetail = $args['address'];
	$categories = $args['category'];
	unset($args['category']);
	$userId = $request->getAttribute('userId');
	unset($args['id']);
	unset($args['image']);
	unset($args['cover_photo']);
	unset($args['address']);
	$user = Models\User::find($userId);
	$user->fill($args);
	$result = array();
	try {
		$validationErrorFields = $user->validate($args);
		if (empty($validationErrorFields)) {
			$user->save();
			if (isset($image) && $image != '') {
				saveImage('UserAvatar', $image, $userId);
			}
			if (isset($cover_photo) && $cover_photo != '') {
				saveImage('CoverPhoto', $cover_photo, $userId);
			}
			if (isset($addressDetail) && $addressDetail != '') {
				$count = Models\UserAddress::where('user_id', $userId)->where('is_default', true)->count();
				if ($count > 1) {
					Models\UserAddress::where('user_id', $userId)->where('is_default', true)->update($addressDetail);
				} else {
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
			}
			$result = $user->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'User could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'User could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/transactions/{id}', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();
	$args = $request->getParsedBody();
    global $authUser;
    $result = array();
    try {
        $enabledIncludes = array(
			'user',
            'other_user',
			'parent_user',
			'payment_gateway',
			'detail',
			'package',
			'subscription'
		);
		$transactions = Models\Transaction::select('id','created_at', 'user_id', 'to_user_id', 'parent_user_id', 'foreign_id','payment_gateway_id', 'amount')->where('id', $request->getAttribute('id'))->with($enabledIncludes)->first();
		$result = array(
            'data' => $transactions
        );
        return renderWithJson($result);
		return renderWithJson($transactions);
    } catch (Exception $e) {
        return renderWithJson(array(), 'No record found.'.$e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/transactions', function ($request, $response, $args) {    
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
			'user',
            'other_user',
			'parent_user',
			'payment_gateway'
		);
		if (!empty($queryParams['class'])) {
			if ($queryParams['class'] == 'Product') {
				$enabledIncludes = array_merge($enabledIncludes,array('detail'));
			} else if ($queryParams['class'] == 'VotePackage' || $queryParams['class'] == 'InstantPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('package'));
			} else if ($queryParams['class'] == 'SubscriptionPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('subscription'));
			}
        }
        $transactions = Models\Transaction::select('id','created_at', 'user_id', 'to_user_id', 'parent_user_id', 'foreign_id','payment_gateway_id', 'amount')->with($enabledIncludes);
		if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
		$transactions = $transactions->Filter($queryParams)->paginate($count);
		$transactionsNew = $transactions;
        $transactionsNew = $transactionsNew->toArray();
        $data = $transactionsNew['data'];
        unset($transactionsNew['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
/**
 * GET ipsGet
 * Summary: Fetch all ips
 * Notes: Returns all ips from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/ips', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'timezone'
        );
        $ips = Models\Ip::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $ips['data'];
        unset($ips['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $ips
        );
        return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * DELETE IpsIdDelete
 * Summary: Delete ip
 * Notes: Deletes a single ip based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/ips/{ipId}', function ($request, $response, $args) {
    global $authUser;
    $ip = Models\Ip::find($request->getAttribute('ipId'));
    $result = array();
    try {
        if (!empty($ip)) {
            $ip->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'Ip could not be deleted. Please, try again.', $e->getMessage(), 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Ip could not be deleted. Please, try again.', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET ipIdGet
 * Summary: Fetch ip
 * Notes: Returns a ip based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/ips/{ipId}', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'timezone'
    );
    $ip = Models\Ip::with($enabledIncludes)->find($request->getAttribute('ipId'));
    if (!empty($ip)) {
        $result['data'] = $ip;
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/cron', function ($request, $response, $args) use ($app)
{
	//Token clean up 
	$now = date('Y-m-d h:i:s');
	Models\OauthAccessToken::where('expires', '<=', $now)->delete();
	Models\OauthRefreshToken::where('expires', '<=', $now)->delete();
	return renderWithJson($result, 'Success','', 0);
	
});
$app->GET('/api/v1/advertisements', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$advertisements = Models\Advertisement::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $advertisements['data'];
		unset($advertisements['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $advertisements
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
});
$app->GET('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
        $advertisement = Models\Advertisement::find($request->getAttribute('id'));
        if (!empty($advertisement)) {
            $result['data'] = $advertisement;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', $e->getMessage(), 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/advertisement', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$result = array();
    $args = $request->getParsedBody();
    $advertisement = new Models\Advertisement($args);
    try {
        $validationErrorFields = $advertisement->validate($args);
        if (empty($validationErrorFields)) {
            $advertisement->is_active = 1;
            $advertisement->user_id = $authUser->id;
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin && !empty($args['user_id'])) {
                $advertisement->user_id = $args['user_id'];
            }
            if ($advertisement->save()) {
				if ($advertisement->id) {
					if (!empty($args['image'])) {
						saveImage('Advertisement', $args['image'], $advertisement->id);
					}
					$result['data'] = $advertisement->toArray();
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Advertisement could not be added. Please, try again.', $e->getMessage(), 1);
			}
        } else {
            return renderWithJson($result, 'Advertisement could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Advertisement could not be added. Please, try again.'.$e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$validationErrorFields = $advertisement->validate($args);
		if (empty($validationErrorFields)) {
			$advertisement->save();
			if (!empty($args['image']) && $advertisement->id) {
				saveImage('Advertisement', $args['image'], $request->getAttribute('id'));
			}
			$result = $advertisement->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->DELETE('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = array();
	$args['is_active'] = false;
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$advertisement->save();
		return renderWithJson(array(), 'Advertisement delete successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/promo_code', function ($request, $response, $args) {
	global $authUser;
    $args = $request->getParsedBody();
    $results = array();
    try {
		$results = array(
			'valid' => true,
			'percentage' => '20'
		);
		return renderWithJson($results, 'Valid Code','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/paypal_connect', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	if (!empty($args) && isset($args['email'])) {
		$isLive = false;
		$url = $isLive ? 'https://svcs.paypal.com/' : 'https://svcs.sandbox.paypal.com/';
		$tokenUrl = $url.'AdaptiveAccounts/GetVerifiedStatus';	
		try {
			$post = array(
				'actionType' => 'PAY',
				'currencyCode' => 'USD',
				'requestEnvelope' => array(
					'errorLanguage' => 'en_US'
				),
				'matchCriteria' => 'NONE',
				'emailAddress' => $args['email']
			);
			$post_string = json_encode($post);
			$header = array(
					'X-PAYPAL-SECURITY-USERID: freehidehide_api1.gmail.com',
					'X-PAYPAL-SECURITY-PASSWORD: AC3BTDPQW5DWV52W',
					'X-PAYPAL-SECURITY-SIGNATURE: AYS.KyRPCh0NqN2ORLAMv8z1H9kWAS3rJdqYkIt.XoOnKgTHdSlTxCrx',
					'X-PAYPAL-REQUEST-DATA-FORMAT: JSON',
					'X-PAYPAL-RESPONSE-DATA-FORMAT: JSON',
					'X-PAYPAL-APPLICATION-ID: APP-80W284485P519543T'
				);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $tokenUrl);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			if ($result) {
				$resultArray = json_decode($result, true);
				$user = Models\User::find($authUser->id);
				if (!empty($resultArray) && !empty($resultArray['responseEnvelope']) && strtolower($resultArray['responseEnvelope']['ack']) == 'success') {
					$user->is_paypal_connect = true;
					$user->paypal_email = $args['email'];
					$user->save();
					$data = array(
						'is_paypal_connect' => $user->is_paypal_connect
					);
					return renderWithJson($data, 'Success','', 0);
				} else { 
					$user->is_paypal_connect = false;
					$user->paypal_email = '';
					$user->save();
					$data = array(
						'is_paypal_connect' => $user->is_paypal_connect
					);					
					return renderWithJson($data, 'Invalid',$e->getMessage(), 1);
				}
			}
			return renderWithJson(array(),'Please check with Administrator', $e->getMessage(), 1);
		} catch (Exception $e) {
			return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Email is empty', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/transactions', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
			'user',
            'other_user',
			'payment_gateway'
		);
		if (!empty($queryParams['class'])) {
			if ($queryParams['class'] == 'Product') {
				$enabledIncludes = array_merge($enabledIncludes,array('detail', 'parent_user'));
			} else if ($queryParams['class'] == 'VotePackage' || $queryParams['class'] == 'InstantPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('package', 'parent_user'));
			} else if ($queryParams['class'] == 'SubscriptionPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('subscription'));
			}
        }
        $transactions = Models\Transaction::select('created_at', 'user_id', 'to_user_id', 'parent_user_id', 'foreign_id','payment_gateway_id', 'amount')->with($enabledIncludes);
		if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
		$transactions = $transactions->Filter($queryParams)->paginate($count);
		$transactionsNew = $transactions;
        $transactionsNew = $transactionsNew->toArray();
        $data = $transactionsNew['data'];
        unset($transactionsNew['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result);
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/attachments', function ($request, $response, $args) {
	global $authUser;
	Models\Attachment::where('user_id', $authUser->id)->where('is_admin_approval', 0)->update(array(
					'is_admin_approval' => 1
				));
				
	 return renderWithJson(array(), 'Approval In-progress','', 0);
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->GET('/api/v1/restaurants', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		if (!empty($queryParams['restaurants'])) {
			$queryParams['restaurants'] = explode (",", $queryParams['restaurants']);
		}
		$restaurants = restaurantsFilter($queryParams);
		if (!empty($restaurants)) {
			$queryParams['restaurants'] = $restaurants;
		}
		$queryParams['is_admin_deactived'] = 0;
		$queryParams['is_active'] = true;
		$respones = Models\Restaurant::with('custom_slots', 'timezone', 'slots', 'attachment', 'favorite')->Filter($queryParams)->get();
		$respones = $respones->toArray();
        $dataList = array();
		if (!empty($respones)) {
			foreach ($respones as $data) {
				$slots = slotsList($data);				
				if (!empty($queryParams['discount']) || !empty($queryParams['slots'])) {
					if (!empty($slots['available_slots'])) {
						if (!empty($queryParams['discount']) && empty($queryParams['slots']) && empty($queryParams['max_person'])) {
							foreach ($slots['available_slots'] as $available_slot) {
								if ($available_slot['discount'] == $queryParams['discount']) {
									$dataList[] = $slots;
									break;
								}
							}
						} else if (empty($queryParams['discount']) && !empty($queryParams['slots']) && empty($queryParams['max_person'])) {
							foreach ($slots['available_slots'] as $available_slot) {
								if ($available_slot['slot'] == $queryParams['slots']) {
									$dataList[] = $slots;
									break;
								}
							}
						} else if (!empty($queryParams['discount']) && !empty($queryParams['slots']) && empty($queryParams['max_person'])) {
							foreach ($slots['available_slots'] as $available_slot) {
								if (($available_slot['discount'] == $queryParams['discount']) && ($available_slot['slot'] == $queryParams['slots'])) {
									$dataList[] = $slots;
									break;
								}
							}
						} else if (!empty($queryParams['discount']) && !empty($queryParams['slots']) && !empty($queryParams['max_person'])) {
							foreach ($slots['available_slots'] as $available_slot) {
								if (($available_slot['discount'] == $queryParams['discount']) && ($available_slot['slot'] == $queryParams['slots']) && ($queryParams['max_person'] <= $available_slot['person'])) {
									$dataList[] = $slots;
									break;
								}
							}
						} else if (empty($queryParams['discount']) && !empty($queryParams['slots']) && !empty($queryParams['max_person'])) {
							foreach ($slots['available_slots'] as $available_slot) {
								if (($available_slot['slot'] == $queryParams['slots']) && ($queryParams['max_person'] <= $available_slot['person'])) {
									$dataList[] = $slots;
									break;
								}
							}
						}
						
					}
				} else {
					$dataList[] = $slots;
				}
			}
		}
		if (!empty($dataList)) {
			array_multisort(array_column($dataList, 'distance'),  SORT_ASC,
                array_column($dataList, 'title'), SORT_ASC,
                $dataList);
		}
        $result = array(
            'data' => $dataList
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
});
$app->GET('/api/v1/restaurant/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$days = Models\Day::get()->toArray();
		$result = array();
		$restaurant = Models\Restaurant::with('custom_slots', 'timezone','slots', 'attachments', 'menus', 'special_conditions', 'facilities_services', 'atmospheres', 'languages', 'operating_hours', 'hours','about', 'reviews', 'favorite')->where('is_active', true)->where('id', $request->getAttribute('id'))->first();
		insertViews($request->getAttribute('id'), 'Restaurant');
		$selectedTime = date('Y-m-d h:i:s');
		$endTime = strtotime("+15 minutes", strtotime($selectedTime));
		$restaurant['people_looking'] = count(Models\View::select('ip_id')->distinct()->where('class', 'Restaurant')->distinct()->where('created_at', '<=', date('Y-m-d h:i:s', $endTime))->where('foreign_id', $request->getAttribute('id'))->where('foreign_id', $request->getAttribute('id'))->get());
		if (!empty($restaurant)) {
			$restaurant = $restaurant->toArray();
			$operating_hours = array();
			if (!empty($restaurant['operating_hours'])) {
				$i = 0;
				foreach ($restaurant['operating_hours'] as $operatingHours) {
					$hours = array();
					if (!empty($restaurant['hours'])) {
						$hours = array_filter($restaurant['hours'], function($obj) use ($operatingHours) {
							return ($operatingHours['day']['id'] == $obj['day']['id']);
						});
					}
					$hourArray = array();
					if (!empty($hours)) {
						foreach($hours as $hour) {
							if ($hour['type'] == 1) {
								$hourArray[] = array(
									'name' => 'Breakfast',
									'type' => 1,
									'start_time' => ($hour['from'] == 'Not Avaiable') ? 'Closed' : $hour['from'],
									'end_time' => ($hour['to'] == 'Not Avaiable') ? 'Closed' : $hour['to']
								);
							} else if ($hour['type'] == 2) {
								$hourArray[] = array(
									'name' => 'Lunch',
									'type' => 2,
									'start_time' => ($hour['from'] == 'Not Avaiable') ? 'Closed' : $hour['from'],
									'end_time' => ($hour['to'] == 'Not Avaiable') ? 'Closed' : $hour['to']
								);
							} else if ($hour['type'] == 3) {
								$hourArray[] = array(
									'name' => 'Dinner',
									'type' => 3,
									'start_time' => ($hour['from'] == 'Not Avaiable') ? 'Closed' : $hour['from'],
									'end_time' => ($hour['to'] == 'Not Avaiable') ? 'Closed' : $hour['to']
								);
							}
						}
					}
					$operating_hours[] = array('day'=> $operatingHours['day']['name'],
									'holiday'=> $operatingHours['holiday'] == 1 ? true : false,
									'allday'=> $operatingHours['allday'] == 1 ? true : false,
									'hours'=> $hourArray);
				}
				$restaurant['operating_hours'] = $operating_hours;
			}
			unset($restaurant['hours']);
			$restaurant = slotsList($restaurant);
		} else {
			return renderWithJson(array(), 'error', 'No Records Found', 1);
		}
		$orverallRating = Capsule::select('SELECT count(rating) as count, rating FROM `reviews` where restaurant_id='.$request->getAttribute('id').' group by rating order by rating');
		if(!empty($orverallRating)) {
			$orverallRating = json_decode(json_encode($orverallRating), true);
			$restaurant['orverall_rating'] = $orverallRating;
		}
		$count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
		$queryParams['distance'] = true;
		$queryParams['is_admin_deactived'] = 0;
		$queryParams['restaurant_not_id'] = array($request->getAttribute('id'));
		$restaurants = restaurantsFilter($queryParams);
		if (!empty($restaurants)) {
			$queryParams['restaurants'] = $restaurants;
		}
		$respones = Models\Restaurant::with('custom_slots', 'timezone', 'slots', 'attachment')->Filter($queryParams)->get();
		$restaurant['nearby'] = $respones;
		if (!empty($restaurant['nearby'])) {
			$dataList = array();
			foreach ($restaurant['nearby'] as $nearby) {
				$slots = slotsList($nearby);	
				if (!empty($queryParams['discount']) || !empty($queryParams['slots'])) {
					if (!empty($slots['available_slots'])) {
						if (!empty($queryParams['discount']) && empty($queryParams['slots'])) {
							foreach ($slots['available_slots'] as $available_slot) {
								if ($available_slot['discount'] == $queryParams['discount']) {
									$dataList[] = $slots;
									break;
								}
							}
						} else if (empty($queryParams['discount']) && !empty($queryParams['slots'])) {
							foreach ($slots['available_slots'] as $available_slot) {
								if ($available_slot['slot'] == $queryParams['slots']) {
									$dataList[] = $slots;
									break;
								}
							}
						} else if (!empty($queryParams['discount']) && !empty($queryParams['slots'])) {
							if (($available_slot['discount'] == $queryParams['discount']) && ($available_slot['slot'] == $queryParams['slot'])) {
								$dataList[] = $slots;
								break;
							}
						}
					}
				} else {
					$dataList[] = $slots;
				}
			}
			array_multisort(array_column($dataList, 'distance'),  SORT_ASC,
                array_column($dataList, 'title'), SORT_ASC,
                $dataList);
			$restaurant['nearby'] = $dataList;
		}
		$result = array();
		$result['data'] = $restaurant;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
});
$app->POST('/api/v1/brand', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		$brand = new Models\Brand;
		$brand->name = $args['name'];
		$brand->save();
		if ($brand->id) {
			if (!empty($args['image'])) {
				saveImage('Brand created successfully', $args['image'], $brand->id);
			}
			$result['data'] = $brand->toArray();
			return renderWithJson($result, 'Success','', 0);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
});
$app->GET('/api/v1/brands', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$queryParams['role_id'] = \Constants\ConstUserTypes::Company;
		$brand = Models\User::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $brand['data'];
		unset($brand['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $brand
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
});
$app->POST('/api/v1/themes', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		$theme = new Models\Theme;
		$theme->name = $args['name'];
		$theme->save();
		if ($theme->id) {
			if (!empty($args['image'])) {
				saveImage('Theme', $args['image'], $theme->id);
			}
			$result['data'] = $theme->toArray();
			return renderWithJson($result, 'Theme created successfully','', 0);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
});
$app->GET('/api/v1/themes', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$theme = Models\Theme::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $theme['data'];
		unset($theme['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $theme
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
});
$app->POST('/api/v1/cuisines', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		$cuisine = new Models\Cuisine;
		$cuisine->name = $args['name'];
		$cuisine->save();
		if ($cuisine->id) {
			if (!empty($args['image'])) {
				saveImage('Cuisine', $args['image'], $cuisine->id);
			}
			$result['data'] = $cuisine->toArray();
			return renderWithJson($result, 'Cuisine created successfully','', 0);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
});
$app->POST('/api/v1/locations', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		$city = new Models\City;
		$city->name = $args['name'];
		$city->country_id = $args['country_id'];
		$city->save();
		if ($city->id) {
			if (!empty($args['image'])) {
				saveImage('City', $args['image'], $city->id);
			}
			$result['data'] = $city->toArray();
			return renderWithJson($result, 'Location created successfully','', 0);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
});
$app->GET('/api/v1/cuisines', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$cuisines = Models\Cuisine::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $cuisines['data'];
		unset($cuisines['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $cuisines
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
});
$app->GET('/api/v1/home', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = 10;
		$queryUserParams = array();
		$queryUserParams['role_id'] = \Constants\ConstUserTypes::Company;
		$queryUserParams['is_active'] = true;
		$queryParams['is_active'] = true;
		$queryParams['not_zero'] = true;
		$queryUserParams['sortby'] = 'asc';
		$queryUserParams['sort'] = 'first_name';
		$queryParams['sortby'] = 'asc';
		$queryParams['sort'] = 'name';
		$brands = Models\User::with('attachment')->Filter($queryUserParams)->paginate($count)->toArray();
		$cities = Models\City::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$themes = Models\Theme::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$cuisines = Models\Cuisine::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$queryParams['sortby'] = 'asc';
		$queryParams['sort'] = 'title';
		$banner = Models\Advertisement::with('attachment')->where('is_active', true)->get();
		$restaurantsAtoZ = Models\Restaurant::select('id', 'city_id', 'country_id', 'brand_id', 'title', 'address', 'reservations', 'latitude', 'longitude', 'star_rating', 'rupee_rating', 'is_promo_code', 'is_gift')->with('attachment')->Filter($queryParams)->orderBy('title', 'DESC')->get()->toArray();
		$restaurantsAtoZFormatted = [];
		$subarray = [];
		foreach ($restaurantsAtoZ as $restaurant) {
			$subarray = $restaurant;
			$subarray['leave'] = false;
			$subarray['leave_text'] = 'not available today';
			//$subarray['leave_text'] = 'not available until Apr 19';
			$subarray['new'] = true;			
			$subarray['distance'] = distance($restaurant['latitude'], $restaurant['longitude'], $_GET['lat'], $_GET['long'], "K");
			$restaurantsAtoZFormatted[] = $subarray;
		}
		$restaurantIds = array();
		if (!empty($authUser)) {
			$restaurantIds = Capsule::select('SELECT foreign_id from views where class="Restaurant" and user_id='.$authUser->id);
			$restaurantIds = json_decode(json_encode($restaurantIds), true);
			if(empty($restaurantIds)) {
				$restaurantIds = array();
			}
		} else {
			$ips = Capsule::select('SELECT id,ip from ips where ip="'.$_SERVER['REMOTE_ADDR'].'"');
			$ips = json_decode(json_encode($ips), true);
			if (!empty($ips)) {
				$restaurantIds = Capsule::select("SELECT foreign_id from views where class='Restaurant' and ip_id='".$ips[0]['id']."'");
				$restaurantIds = json_decode(json_encode($restaurantIds), true);
				if(empty($restaurantIds)) {
					$restaurantIds = array();
				}
			}
		}
		$restaurants = Models\Restaurant::with('custom_slots', 'timezone', 'slots', 'attachment')->Filter($queryParams)->paginate($count)->toArray();
		$restaurantsFormatted = [];
		$subarray = [];
		foreach ($restaurants['data'] as $restaurant) {
			$subarray = $restaurant;
			$subarray['leave'] = false;
			$subarray['leave_text'] = 'not available today';
			//$subarray['leave_text'] = 'not available until Apr 19';
			$subarray['new'] = (strtotime($restaurant['created_at']) >= strtotime('-15 day', strtotime(date('Y-m-d'))));			
			$subarray['distance'] = distance($restaurant['latitude'], $restaurant['longitude'], $_GET['lat'], $_GET['long'], "K");
			$restaurantsFormatted[] = $subarray;
		}
		$queryParams['is_promo_code'] = true;
		$restaurantsPromos = Models\Restaurant::select('id', 'city_id', 'country_id', 'brand_id', 'title', 'address', 'reservations', 'latitude', 'longitude', 'star_rating', 'rupee_rating', 'is_promo_code', 'is_gift')->with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$restaurantsPromosFormatted = [];
		$subarray = [];
		foreach ($restaurantsPromos['data'] as $restaurant) {
			$subarray = $restaurant;
			$subarray['leave'] = false;
			$subarray['leave_text'] = 'not available today';
			//$subarray['leave_text'] = 'not available until Apr 19';
			$subarray['new'] = (strtotime($restaurant['created_at']) >= strtotime('-15 day', strtotime(date('Y-m-d'))));			
			$subarray['distance'] = distance($restaurant['latitude'], $restaurant['longitude'], $_GET['lat'], $_GET['long'], "K");
			$restaurantsPromosFormatted[] = $subarray;
		}
		unset($queryParams['is_promo_code']);
		$queryParams['reservation_not_zero'] = true;
		$queryParams['sortby'] = 'desc';
		$queryParams['sort'] = 'reservations';
		$restaurantsTrends = Models\Restaurant::select('id', 'city_id', 'country_id', 'brand_id', 'title', 'address', 'reservations', 'latitude', 'longitude', 'star_rating', 'rupee_rating', 'is_promo_code', 'is_gift')->with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$restaurantsTrendsFormatted = [];
		$subarray = [];
		foreach ($restaurantsTrends['data'] as $restaurant) {
			$subarray = $restaurant;
			$subarray['leave'] = false;
			$subarray['leave_text'] = 'not available today';
			//$subarray['leave_text'] = 'not available until Apr 19';
			$subarray['new'] = (strtotime($restaurant['created_at']) >= strtotime('-15 day', strtotime(date('Y-m-d'))));			
			$subarray['distance'] = distance($restaurant['latitude'], $restaurant['longitude'], $_GET['lat'], $_GET['long'], "K");
			$restaurantsTrendsFormatted[] = $subarray;
		}
		unset($queryParams['reservation_not_zero']);
		$queryParams['created_at'] =  date('Y-m-d', strtotime('-15 day', strtotime(date('Y-m-d')))); 
		$queryParams['sortby'] = 'asc';
		$queryParams['sort'] = 'title';
		$newRestaurants = Models\Restaurant::with('custom_slots', 'timezone', 'slots', 'attachment')->Filter($queryParams)->paginate($count)->toArray();
		$newRestaurantsFormatted = [];
		$subarray = [];
		foreach ($newRestaurants['data'] as $restaurant) {
			$subarray = $restaurant;
			$subarray['leave'] = false;
			$subarray['leave_text'] = 'not available today';
			//$subarray['leave_text'] = 'not available until Apr 19';
			$subarray['new'] = true;			
			$subarray['distance'] = distance($restaurant['latitude'], $restaurant['longitude'], $_GET['lat'], $_GET['long'], "K");
			$newRestaurantsFormatted[] = $subarray;
		}
		$restaurantsNearMe = $restaurantsAtoZFormatted;
		array_multisort(array_column($restaurantsNearMe, 'distance'),  SORT_ASC,
									array_column($restaurantsNearMe, 'title'), SORT_ASC,
									$restaurantsNearMe);
		$results = array(
			'home' => array(
				array(
					'title' => 'banner',
					'data' => $banner
				),
				array(
					'title' => 'brands',
					'data' => $brands['data']
				),
				array(
					'title' => 'locations',
					'data' => $cities['data']
				),
				array(
					'title' => 'cuisines',
					'data' => $cuisines['data']
				),
				array(
					'title' => 'themes',
					'data' => $themes['data']
				),
				array(
					'title' => 'trending',
					'data' => $restaurantsTrendsFormatted
				),
				array(
					'title' => 'new',
					'data' => $newRestaurantsFormatted
				),
				array(
					'title' => 'hot_promo',
					'data' => $restaurantsPromosFormatted
				),
				array(
					'title' => 'recently_viewed',
					'data' => $restaurantsFormatted
				),
				array(
					'title' => 'restaurants',
					'data' => $restaurantsAtoZ
				),
				array(
					'title' => 'here and now',
					'data' => $restaurantsNearMe
				)
			)
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, 'Home data could not be retrieved. Please, try again.', $e->getMessage(), 1);
    }
});
$app->POST('/api/v1/favorite', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$results = array();
    try {
		$favoriteExist = Models\Favorite::where('user_id', $authUser->id)->where('restaurant_id', $args['restaurant_id'])->first();
		if (!empty($favoriteExist)) {
			return renderWithJson($results, 'It\'s already in your favorites', $e->getMessage(), 1);
		}
		$favorite = new Models\Favorite;
		$favorite->user_id = $authUser->id;
		$favorite->restaurant_id = $args['restaurant_id'];
		$favorite->save();
		return renderWithJson(array(), 'Favorites has been added','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/favorite', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$results = array();
    try {
		$favoritList = array();
		$favorites = Models\Favorite::with('restaurant')->where('user_id', $authUser->id)->orderBy('id', 'DESC')->get();
		if (!empty($favorites)) {
			$favoritList['data'] = $favorites->toArray();
		}
		return renderWithJson($favoritList, 'Favorites','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/reviews', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$results = array();
    try {
		$reviewExist = Models\Review::where('user_id', $authUser->id)->where('restaurant_id', $args['restaurant_id'])->first();
		if (!empty($reviewExist)) {
			return renderWithJson($results, 'Thanks for your interest you have already rated this restaurant', $e->getMessage(), 1);
		}
		$bookingExist = Models\Booking::where('user_id', $authUser->id)->where('restaurant_id', $args['restaurant_id'])->where('status', 1)->first();
		if (empty($bookingExist)) {
			return renderWithJson($results, 'You should have the confirmed booking to review this restaurant', $e->getMessage(), 1);
		}
		$review = new Models\Review;
		$review->user_id = $authUser->id;
		$review->restaurant_id = $args['restaurant_id'];
		$review->comments = $args['comments'];
		$review->rating = $args['rating'];
		$review->rupee_rating = $args['rupee_rating'];
		$review->save();
		$ratings = Capsule::select('SELECT round(avg(rating),0) as count  FROM `reviews` where restaurant_id='. $args['restaurant_id'].' group by restaurant_id');
		if(!empty($ratings)) {
			$ratings = json_decode(json_encode($ratings), true);
			$ratingCount = current($ratings);
			Capsule::select('update restaurants set star_rating='.$ratingCount['count'].' where id='.$args['restaurant_id']);
		}
		$ratings = Capsule::select('SELECT round(avg(rupee_rating),0) as count  FROM `reviews` where restaurant_id='. $args['restaurant_id'].' group by restaurant_id');
		if(!empty($ratings)) {
			$ratings = json_decode(json_encode($ratings), true);
			$ratingCount = current($ratings);
			Capsule::select('update restaurants set rupee_rating='.$ratingCount['count'].' where id='.$args['restaurant_id']);
		}
		return renderWithJson(array(), 'Review has been added','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/booking', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$results = array();
    try {
		$restaurant = Models\Restaurant::with('custom_slots', 'timezone', 'slots')->where('is_deactived', 0)->where('is_admin_deactived', 0)->where('is_active', true)->where('id', $args['restaurant_id'])->first();
		$restaurant = $restaurant->toArray();
		if (!empty($restaurant['timezone'])) {
			date_default_timezone_set($restaurant['timezone']['name']);
		}
		// if ((strtotime(date('Y-m-d h:i:s'))) <= (strtotime($args['reg_date']. ' '.$args['from_timeslot']))) {
			// return renderWithJson($results, 'You can\'t booked a reservation for passed time', '', 1);
		// }
		$bookingExist = Models\Booking::where('user_id', $authUser->id)->where('reg_date', $args['reg_date'])->where('from_timeslot', $args['from_timeslot'])->where('status', '<>', 2)->first();
		if (!empty($bookingExist)) {
			return renderWithJson($results, 'You have already booked the slot', '', 1);
		}
		if (!isset($args['discount']) || $args['discount'] != 0) {
			$_GET['date'] = $args['reg_date'];			
			$restaurant = slotsList($restaurant);		
			if (!empty($restaurant) && !empty($restaurant['available_slots'])) {
				$isSlotFound = '';
				$bookingList = Capsule::select('SELECT sum(max_person) as count, from_timeslot FROM `booking` where status <> 2 and restaurant_id='.$restaurant['id'].' and reg_date="'.$args['reg_date'].'" group by from_timeslot');
				if(!empty($bookingList)) {
					$slots = array();
					$bookingList = json_decode(json_encode($bookingList), true);
					foreach($restaurant['available_slots'] as $slot) {
						$item = null;
						foreach($bookingList as $book) {
							if ($book['from_timeslot'] == $slot['slot']) {
								$slot['person'] = $restaurant['max_person']-$book['count'];
								$item = $slot;
								break;
							}
						}
					}
					if (!empty($item)) {
						if ($args['max_person'] > $item['person']) {
							if ($item['person'] == 0) {
								return renderWithJson($results, 'Sorry no more reservations left', '', 1);
							} else {
								return renderWithJson($results, 'Sorry we are left with reservations for '.$item['person'].' person only', '', 1);
							}
						}
					} else {
						foreach($restaurant['available_slots'] as $slot) {
							$item = null;
							if ($args['from_timeslot'] == $slot['slot']) {
								$slot['person'] = $restaurant['max_person']-$book['count'];
								$item = $slot;
								break;
							}
						}
						if (empty($item)) {
							return renderWithJson($results, 'Sorry no more reservations left', '', 1);
						} else if (!empty($item)) {
							if ($args['max_person'] > $item['person']) {
								if ($item['person'] == 0) {
									return renderWithJson($results, 'Sorry no more reservations left', '', 1);
								} else {
									return renderWithJson($results, 'Sorry we are left with reservations for '.$item['person'].' person only', '', 1);
								}
							}
						}
					}
				}
			}
		}
		$booking = new Models\Booking;
		$booking->user_id = $authUser->id;
		$booking->restaurant_id = $args['restaurant_id'];
		$booking->reg_date = $args['reg_date'];
		$booking->code = str_pad(rand(0,99999), 5, "0", STR_PAD_LEFT);
		$booking->from_timeslot = $args['from_timeslot'];
		$booking->max_person = $args['max_person'];
		$booking->offer_percentage = ($args['discount'] != 0) ? 0 : $item['discount'];
		$booking->save();
		$dataObj = array();
		$dataObj['restaurant_id'] = $args['restaurant_id'];
		$dataObj['type'] = 'Scheduled';
		$user = Models\User::find($authUser->id);
		$emailFindReplace = array(
								'##RESTAURANT##' => $restaurant['title'],
								'##DATE##' => $args['reg_date'],
								'##TIMESLOT##' => $args['from_timeslot'],
								'##MAXPERSON##' => $args['max_person'],
								'##USERNAME##' => $user['first_name'],
								'##USERID##' => $authUser->id,
								'##PUSHNOTIFICATION_DATA##' => $dataObj,
								'##SUPPORT_EMAIL##' => SUPPORT_EMAIL
							);
		sendMail('newbooking', $emailFindReplace, $user['email'], '', $restaurant, true, true);
		$results['data'] = $booking;
		return renderWithJson($results, 'Successfully booked the slot','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/bookings/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$booking = Models\Booking::with('restaurant', 'booking_status')->where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $booking;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/bookings/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		$booking = Models\Booking::with('user', 'restaurant')->find($request->getAttribute('id'));
		$booking = $booking->toArray();
		$user = Models\User::find($authUser->id);
		$status = 0;
		if ($args['status'] === 'Arrived') {
			$status = 1;
			$dataObj = array();
			$dataObj['restaurant_id'] = $args['restaurant_id'];
			$dataObj['type'] = 'Arrived';
			$template = 'confirmbooking';
		} else if ($args['status'] === 'Canceled') {
			$dataObj = array();
			$dataObj['restaurant_id'] = $args['restaurant_id'];
			$dataObj['type'] = 'Canceled';
			$status = 2;
			$template = 'cancelbooking';
		}
		Models\Booking::where('id', $request->getAttribute('id'))->update(array(
			'status' => $status
		));
		$emailFindReplace = array(
								'##RESTAURANT##' => $restaurant['title'],
								'##DATE##' => $args['reg_date'],
								'##TIMESLOT##' => $args['from_timeslot'],
								'##MAXPERSON##' => $args['max_person'],
								'##USERNAME##' => $booking['user']['first_name'],
								'##USERID##' => $booking['user']['id'],
								'##PUSHNOTIFICATION_DATA##' => $dataObj,
								'##SUPPORT_EMAIL##' => SUPPORT_EMAIL
							);
		sendMail($template, $emailFindReplace, $booking['user']['email'], '', $booking['restaurant'], true, true);
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/bookings', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin) {
			$booking = Models\Booking::with('user', 'restaurant', 'booking_status')->Filter($queryParams)->paginate($count)->toArray();
		} else if ($authUser['role_id'] == \Constants\ConstUserTypes::User) {
			$queryParams['user_id_filter'] = $authUser->id;
			$booking = Models\Booking::with('user', 'restaurant', 'booking_status')->Filter($queryParams)->paginate($count)->toArray();
		} else {
			$restaurants = Models\Restaurant::select('id')->where('user_id', $authUser->id)->get();
			if (!empty($restaurants)) {
				$restaurants = $restaurants->toArray();
				$queryParams['restaurants'] = array_column($restaurants, 'id');
				$booking = Models\Booking::with('user', 'restaurant', 'booking_status')->Filter($queryParams)->paginate($count)->toArray();
			} else {
				$booking = array(
					'data' => array()
				);
			}
		}			
		$data = $booking['data'];
		unset($booking['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $booking
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/bookinghistory', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
	$args = $request->getParsedBody();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$condition = '';
		if ($args['status'] === 'upcoming') {
			$condition = '>';
		} else if ($args['status'] === 'completed') {
			$condition = '<';
		}
		$booking = Models\Booking::with('user', 'restaurant')->where('user_id', $authUser->id)->where('reg_date', $condition, $args['date'])->paginate($count)->toArray();
		$data = $booking['data'];
		unset($booking['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $booking
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/theme', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
        $respones = Models\Theme::with('attachment')->Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/admin/theme/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$theme = Models\Theme::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $theme;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/theme', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
        $theme = new Models\Theme;
		$theme->name = $args['name'];
		$theme->is_active = true;
		$theme->save();
		if (!empty($args['image'])) {
			saveImage('Theme', $args['image'], $theme->id);
		}
        return renderWithJson($result, 'Successfully added','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/theme/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Theme::where('id', $request->getAttribute('id'))->update(array(
			'name' => $args['name']
		));
		if (!empty($args['image'])) {
			Capsule::select('Delete from attachments where class="Theme" and foreign_id='.$authUser->id);
			saveImage('Theme', $args['image'], $request->getAttribute('id'));
		}
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/theme/delete/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Theme::where('id', $request->getAttribute('id'))->update(array(
			'is_active' => false
		));
		return renderWithJson(array(), 'Successfully delete','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/static', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
        $payments = Models\Payment::where('is_active', true)->orderBy('name', 'ASC')->get();
		$languages = Models\Language::where('is_active', true)->orderBy('name', 'ASC')->get();
		$themes = Models\Theme::where('is_active', true)->orderBy('name', 'ASC')->get();
		$cuisines = Models\Cuisine::where('is_active', true)->orderBy('name', 'ASC')->get();
		$facilities = Models\FacilitiesService::where('is_active', true)->orderBy('name', 'ASC')->get();
		$atmospheres = Models\Atmosphere::where('is_active', true)->orderBy('name', 'ASC')->get();
		$countries = Models\Country::select('id', 'name')->orderBy('name', 'ASC')->get()->toArray();
		$timezones = Models\Timezone::select('id', 'code')->orderBy('name', 'ASC')->get()->toArray();
		$bookingTypes = Models\BookingType::select('id', 'name')->orderBy('name', 'ASC')->get()->toArray();
		$result = array(
			'data' => array(
				'payments' => $payments,
				'languages' => $languages,
				'themes' => $themes,
				'cuisines' => $cuisines,
				'facilities' => $facilities,
				'atmospheres' => $atmospheres,
				'countries' => $countries,
				'timezones' => $timezones,
				'booking' => $bookingTypes
			)
		);
        return renderWithJson($result, 'Successfully added','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
});
$app->POST('/api/v1/time_slots', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$result = array();
    try {
		$resId = '';
		if (isset($args['restaurant_id']) && !empty($args['restaurant_id'])) {
			$resId = $args['restaurant_id'];
		} else {
			$restaurant = Models\Restaurant::select('id')->where('user_id', $authUser->id)->first();
			$resId = $restaurant->id;
		}
		Capsule::select('Delete from time_slots where restaurant_id='.$resId);
		Capsule::select('Delete from slots where type=0 and restaurant_id='.$resId);
		foreach($args['time_slot'] as $timeSlotData) {
			$timeSlot = new Models\TimeSlot;
			$timeSlot->restaurant_id = $resId;
			$timeSlot->day = $timeSlotData['day'];
			$timeSlot->type = $timeSlotData['type'];
			$timeSlot->save();
			$time_slot_id = $timeSlot->id;
			if ($timeSlot->type === 0) {
				foreach($timeSlotData['timeSlots'] as $timeData) {
					$slot = new Models\Slot;
					$slot->restaurant_id = $resId;
					$slot->time_slot_id = $time_slot_id;
					$slot->type = 0;
					$slot->from_timeslot = $timeData['time'];
					$slot->slot_count = $timeData['slot'];
					$slot->discount = $timeData['slot'];
					$slot->save();
				}
			}
		}
        return renderWithJson($result, 'Successfully saved','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canContestantUser canCompanyUser'));
$app->GET('/api/v1/time_slots', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
	$result = array();
    try {
		$resId = '';
		if (isset($queryParams['restaurant_id']) && !empty($queryParams['restaurant_id'])) {
			$resId = $queryParams['restaurant_id'];
		} else {
			$restaurant = Models\Restaurant::select('id')->where('user_id', $authUser->id)->first();
			$resId = $restaurant->id;
		}
		$settings = Models\TimeSlot::where('restaurant_id', $resId)->with('slots')->get();	
		$result = array();
		$result['data'] = $settings;
		return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canContestantUser canCompanyUser'));
$app->POST('/api/v1/custom_time_slots', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$result = array();
    try {
		$resId = '';
		if (isset($args['restaurant_id']) && !empty($args['restaurant_id'])) {
			$resId = $args['restaurant_id'];
		} else {
			$restaurant = Models\Restaurant::select('id')->where('user_id', $authUser->id)->first();
			$resId = $restaurant->id;
		}
		Capsule::select('Delete from custom_time_slots where restaurant_id='.$resId);
		Capsule::select('Delete from slots where type=1 and restaurant_id='.$resId);
		$customTimeSlot = new Models\CustomTimeSlot;
		$customTimeSlot->restaurant_id = $resId;
		$customTimeSlot->date_detail = $args['date_detail'];
		$customTimeSlot->type = $args['type'];
		$customTimeSlot->save();
		$time_slot_id = $customTimeSlot->id;
		
		if ($customTimeSlot->type === 0) {
			foreach($args['time_slots'] as $timeData) {
				$slot = new Models\Slot;
				$slot->restaurant_id = $resId;
				$slot->time_slot_id = $time_slot_id;
				$slot->type = 1;
				$slot->from_timeslot = $timeData['time'];
				$slot->discount = $timeData['slot'];
				$slot->slot_count = $timeData['slot'];
				$slot->save();
			}
		}
        return renderWithJson($result, 'Successfully saved','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canContestantUser canCompanyUser'));
$app->GET('/api/v1/custom_time_slots', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
	$result = array();
    try {
		$resId = '';
		if (isset($queryParams['restaurant_id']) && !empty($queryParams['restaurant_id'])) {
			$resId = $queryParams['restaurant_id'];
		} else {
			$restaurant = Models\Restaurant::select('id')->where('user_id', $authUser->id)->first();
			$resId = $restaurant->id;
		}
		$customTimeSlot = Models\CustomTimeSlot::where('restaurant_id', $resId)->where('date_detail', $queryParams['date_detail'])->with('slots')->get();	
		$result = array();
		$result['data'] = $customTimeSlot;
		return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $e->getMessage(), 1);
    }
})->add(new ACL('canContestantUser canCompanyUser'));
$app->GET('/api/v1/restaurant_list', function ($request, $response, $args) {
	global $authUser;
	$restaurants = Models\Restaurant::select('id', 'title')->where('is_deactived', 0)->where('is_active', true)->where('brand_id', $authUser->id)->orderBy('title', 'ASC')->get()->toArray();
	$result = array();
	$result['data'] = $restaurants;
	return renderWithJson($result, 'Success','', 0);
})->add(new ACL('canContestantUser canCompanyUser'));
$app->GET('/api/v1/restaurant_filters', function ($request, $response, $args) {
	global $authUser;
	$args = array();
	$args['facilities'] = Models\FacilitiesService::orderBy('name', 'ASC')->get();
	$args['facilities_multiple'] = true;
	$args['menus'] = Models\Menu::where('is_active', true)->orderBy('name', 'ASC')->get();
	$args['menus_multiple'] = true;
	$args['payments'] = Models\Payment::where('is_active', true)->orderBy('name', 'ASC')->get();
	$args['payments_multiple'] = true;
	$args['languages'] = Models\Language::where('is_active', true)->orderBy('name', 'ASC')->get();
	$args['languages_multiple'] = true;
	$args['cities'] = Models\City::where('count', '<>',0)->orderBy('name', 'ASC')->get();
	$args['cities_multiple'] = true;
	$slots = array();
	for ($i = 0; $i <= 23; $i++) {
		$timeValue = (strlen($i) == 1) ? ('0' + $i) : $i;
		$slots[] = $timeValue. ':00';
		$slots[] = $timeValue. ':30';
	}
	$args['slots'] = $slots;
	$args['slots_multiple'] = false;
	$discounts = array();
	for ($i = 1; $i <= 15; $i++) {
		$discounts[] = $i*5;
	}
	$args['discounts'] = $discounts;
	$args['discounts_multiple'] = false;
	$queryUserParams = array();
	$queryUserParams['role_id'] = \Constants\ConstUserTypes::Company;
	$queryUserParams['is_active'] = true;
	$queryParams['is_active'] = true;
	$queryParams['not_zero'] = true;
	$args['brands'] = Models\User::select('id', 'first_name')->Filter($queryUserParams)->get();
	$args['brands_multiple'] = true;
	$args['cities'] = Models\City::Filter($queryParams)->get();
	$args['cities_multiple'] = true;
	$args['themes'] = Models\Theme::Filter($queryParams)->get();
	$args['themes_multiple'] = true;
	$args['cuisines'] = Models\Cuisine::Filter($queryParams)->get();
	$args['cuisines_multiple'] = true;
	return renderWithJson($args, 'Success','', 0);
});
$app->GET('/api/v1/autocomplete', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
	$queryParams['is_active'] = true;
	$queryParams['not_zero'] = true;
	$queryParams['is_admin_deactived'] = 0;
	
	$respones = Models\Restaurant::select('id', 'title', 'address','max_person','city_id')->with('custom_slots', 'timezone', 'slots','attachment', 'cuisines', 'city')->Filter($queryParams)->get();
	$respones = $respones->toArray();
	$dataList = array();
	$cityIds = array();
	$cuisinesIds = array();
	$restaurant_ids = array();
	if (!empty($respones)) {
		foreach ($respones as $data) {
			$slots = slotsList($data);
			if (!empty($slots['available_slots']) && !empty($queryParams['max_person']) && !empty($queryParams['slots'])) {
				foreach ($slots['available_slots'] as $available_slot) {
					if ($queryParams['max_person'] <= $available_slot['person'] && $available_slot['slot'] == $queryParams['slots']) {
						$dataList[] = $slots;
						$restaurant_ids[] = $slots['id'];
						if (!empty($cityIds[$slots['city_id']])) {
							$cityIds[$slots['city_id']]['id'][] = $slots['id'];
							$cityIds[$slots['city_id']]['count'] = $cityIds[$slots['city_id']] + 1;
						} else {
							$cityIds[$slots['city_id']] = array(
								'id' => array($slots['id']),
								'count' => 1
							);
						}
						if (!empty($cuisinesIds[$cuisine['cuisine_id']])) {
							$cuisinesIds[$cuisine['cuisine_id']] = array(
								'id' => array($slots['id']),
								'count' => 0
							);
						}
						foreach ($slots['cuisines'] as $cuisine) {							
							$cuisinesIds[$cuisine['cuisine_id']]['count'] = $cuisinesIds[$cuisine['cuisine_id']] + 1;
						}
						break;
					}
				}
			}
		}
	}
	$queryParams['city_ids'] = array_keys($cityIds);
	$queryParams['cuisines_ids'] = array_keys($cuisinesIds);
	$autocomplete = array();
	$autocomplete['restaurant_ids'] = $restaurant_ids;
	$autocomplete['restaurants'] = $dataList;
	if (!empty($queryParams['city_ids'])) {
		$autocomplete['cities'] = [];
		$cities = Models\City::select('id', 'name','count')->Filter($queryParams)->get();
		foreach($cities as $city) {
			$city['count'] = $cityIds[$city['id']]['count'];
			$city['ids'] = $cityIds[$city['id']]['id'];
			$autocomplete['cities'] = $city;
		}
	} else {
		$autocomplete['cities'] = [];
	}
	if (!empty($queryParams['cuisines_ids'])) {
		$autocomplete['cuisines'] = [];
		$cuisines = Models\Cuisine::Filter($queryParams)->get();
		foreach($cuisines as $cuisine) {
			$cuisine['count'] = $cuisinesIds[$cuisine['id']]['count'];
			$cuisine['ids'] = $cuisinesIds[$cuisine['id']]['id'];
			$autocomplete['cuisines'] = $cuisine;	
		}
	} else {
		$autocomplete['cuisines'] = [];
	}
	$slots = array();
	for ($i = 0; $i <= 23; $i++) {
		$timeValue = (strlen($i) == 1) ? ('0' + $i) : $i;
		$slots[] = $timeValue. ':00';
		$slots[] = $timeValue. ':30';
	}
	$autocomplete['slots'] = $slots;
	$persons = array();
	for ($i = 0; $i <= 24; $i++) {
		$persons[] = (strlen($i) == 1) ? ('0' + $i) : $i;
	}
	$autocomplete['persons'] = $persons;
	return renderWithJson($autocomplete, 'Success','', 0);
});
$app->GET('/api/v1/admin/advertisement', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$queryParams['is_active'] = true;
		$advertisements = Models\Advertisement::with('attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $advertisements['data'];
		unset($advertisements['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $advertisements
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/admin/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
        $advertisement = Models\Advertisement::with('attachment')->find($request->getAttribute('id'));
        if (!empty($advertisement)) {
            $result['data'] = $advertisement;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/admin/advertisement', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$result = array();
    $args = $request->getParsedBody();
	$args['name'] = 'IMA';
    $advertisement = new Models\Advertisement($args);
    try {
        $validationErrorFields = $advertisement->validate($args);
        if (empty($validationErrorFields)) {
            $advertisement->is_active = 1;
            $advertisement->user_id = $authUser->id;
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin && !empty($args['user_id'])) {
                $advertisement->user_id = $args['user_id'];
            }
            if ($advertisement->save()) {
				if ($advertisement->id) {
					if (!empty($args['image'])) {
						saveImage('Advertisement', $args['image'], $advertisement->id);
					}
					$result['data'] = $advertisement->toArray();
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Advertisement could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Advertisement could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Advertisement could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/admin/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$validationErrorFields = $advertisement->validate($args);
		if (empty($validationErrorFields)) {
			$advertisement->save();
			if (!empty($args['image']) && $advertisement->id) {
				saveImage('Advertisement', $args['image'], $request->getAttribute('id'));
			}
			$result = $advertisement->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/admin/advertisement/delete/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = array();
	$args['is_active'] = false;
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$advertisement->save();
		return renderWithJson(array(), 'Advertisement delete successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/admin/questions', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$queryParams['is_active'] = true;
		$questions = Models\Question::Filter($queryParams)->paginate($count)->toArray();
		$data = $questions['data'];
		unset($questions['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $questions
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/questions', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$queryParams['is_active'] = true;
		$questions = Models\Question::Filter($queryParams)->paginate($count)->toArray();
		$data = $questions['data'];
		unset($questions['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $questions
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/admin/questions/{id}', function ($request, $response, $args) {
    global $authUser;
	
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
        $question = Models\Question::find($request->getAttribute('id'));
        if (!empty($question)) {
            $result['data'] = $question;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/admin/questions', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$result = array();
    $args = $request->getParsedBody();
	$question = new Models\Question($args);
    try {
        $validationErrorFields = $question->validate($args);
        if (empty($validationErrorFields)) {
            $question->is_active = 1;
            $question->user_id = $authUser->id;
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin && !empty($args['user_id'])) {
                $question->user_id = $args['user_id'];
            }
            if ($question->save()) {
				if ($question->id) {
					if (!empty($args['image'])) {
						saveImage('Question', $args['image'], $question->id);
					}
					$result['data'] = $question->toArray();
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Question could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Question could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Question could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/admin/questions/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$question = Models\Question::find($request->getAttribute('id'));
	$question->fill($args);
	$result = array();
	try {
		$validationErrorFields = $question->validate($args);
		if (empty($validationErrorFields)) {
			$question->save();
			if (!empty($args['image']) && $question->id) {
				saveImage('Question', $args['image'], $request->getAttribute('id'));
			}
			$result = $question->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'Question could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Question could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/admin/questions/delete/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = array();
	$args['is_active'] = false;
	$question = Models\Question::find($request->getAttribute('id'));
	$question->fill($args);
	$result = array();
	try {
		$question->save();
		return renderWithJson(array(), 'Question delete successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Question could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/news', function ($request, $response, $args) {
	global $authUser;
	$queryParams = $request->getQueryParams();
	$results = array();
	try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$queryParams['user_id'] = $authUser->id;
		$queryParams['is_read'] = false;
		$pushNotifications = Models\PushNotification::Filter($queryParams)->paginate($count)->toArray();
		$data = $pushNotifications['data'];
		unset($pushNotifications['data']);
		$results = array(
			'data' => $data,
			'_metadata' => $pushNotifications
		);
		return renderWithJson($results, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson($results, $e->getMessage(), $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/news', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$result = array();
		$args = $request->getParsedBody();
		foreach($args['ids'] as $id) {
			Models\PushNotification::whereIn('id', $args['ids'])->where('user_id', $authUser->id)->update(array(
				'is_read' => 1
			));
		}
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/feedback', function ($request, $response, $args) use ($app)
{
	$result = array();
	$args = $request->getParsedBody();
	$result['status'] = 'Failed';
	if ($args && $args['message']) {
		$feedback = new Models\Feedback;
		$feedback->email = $args['email'];
		$feedback->question_id = $args['question_id'];
		$feedback->message = $args['message'];
		$feedback->save();
		generalSendMail(SITE_CONTACT_EMAIL,"Feedback", $args['message']);
		return renderWithJson($result, 'Thank you for your feedback','', 0);
	}
	return renderWithJson($result, 'Failed','', 0);
});
$app->GET('/api/v1/admin/feedback', function ($request, $response, $args) use ($app)
{
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
        $respones = Models\Feedback::Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
});
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($req, $res) {
    $handler = $this->notFoundHandler; // handle using the default Slim page not found handler
    return $handler($req, $res);
});
$app->run();
