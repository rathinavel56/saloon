<?php
/**
 * User
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class User extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';
    protected $fillable = array(
		'created_at',
		'updated_at',
		'company_id',
		'role_id',
		'username',
		'email',
		'email1',
		'gender',
		'mobile',
		'mobile_code',
		'is_archive',
		'password',
		'user_login_count',
		'available_wallet_amount',
		'ip_id',
		'last_login_ip_id',
		'last_logged_in_time',
		'is_active',
		'first_name',
		'is_email_confirmed',
		'last_name',
		'view_count',
		'flag_count',
		'total_votes',
		'votes',
		'instagram_url',
		'tiktok_url',
		'youtube_url',
		'twitter_url',
		'facebook_url',
		'available_credit_count',
		'vote_pay_key',
		'vote_to_purchase',
		'subscription_pay_key',
		'fund_pay_key',
		'donated',
		'subscription_id',
		'paypal_email',
		'is_paypal_connect',
		'is_stripe_connect',
		'subscription_end_date',
		'device_details',
		'instant_vote_pay_key',
		'slug',
		'description'
    );	
    public $qSearchFields = array(
        'first_name',
        'last_name',
        'username',
        'email',
    );
    public $hidden = array(
		'created_at',
		'updated_at',
		'password',
		'user_login_count',
		'available_wallet_amount',
		'ip_id',
		'last_login_ip_id',
		'last_logged_in_time',
		'is_active',
		'is_email_confirmed',
		'view_count',
		'flag_count',
		'available_credit_count',
		'vote_pay_key',
		'vote_to_purchase',
		'subscription_pay_key',
		'subscription_end_date',
		'donated',
		'paypal_email',
		'total_votes',
		'display_name',
		'instant_vote_pay_key',
		'instant_vote_to_purchase',
		'is_paypal_connect',
		'is_stripe_connect',
		'is_archive',
		'role'
    );
    public $rules = array(
       'username' => [
                'sometimes',
                'required',
                'min:3',
                'max:30',
                'regex:/^[A-Za-z][A-Za-z0-9]*(?:_[A-Za-z0-9]+)*$/',
            ],
        'email' => 'sometimes|required|email',
        'password' => [
                'sometimes',
                'required',
                'min:3',
                'max:30'
            ]
    );
    protected $scopes_1 = array(
		'canAdmin',
		'canUser',
        'canContestantUser'
	);
    // User scope
    protected $scopes_2 = array(
        'canUser'
    );
	protected $scopes_3 = array(
        'canUser',
        'canContestantUser'
    );
    /**
     * To check if username already exist in user table, if so generate new username with append number
     *
     * @param string $username User name which want to check if already exists
     *
     * @return mixed
     */
    public function checkUserName($username)
    {
        $userExist = User::where('email', $username)->first();
        if (count($userExist) > 0) {
            $org_username = $username;
            $i = 1;
            do {
                $username = $org_username . $i;
                $userExist = User::where('username', $username)->first();
                if (count($userExist) < 0) {
                    break;
                }
                $i++;
            } while ($i < 1000);
        }
        return $username;
    }
    public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'UserAvatar');
    }
    public function foreign_attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->select('id', 'filename', 'class', 'foreign_id')->where('class', 'UserAvatar');
    }
    public function role()
    {
        return $this->belongsTo('Models\Role', 'role_id', 'id');
    }
	public function foreign()
    {
        return $this->morphTo(null, 'class', 'foreign_id');
    }
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['is_email_confirmed'])) {
            $query->where('is_email_confirmed', $params['is_email_confirmed']);
        }
        if (!empty($params['role_id'])) {
            $query->Where('role_id', $params['role_id']);
        }
		if (!empty($params['is_active'])) {
            $query->Where('is_active', $params['is_active']);
        }
		if (!empty($params['search'])) {
			$search = $params['search'];
			$query->where('username', 'like', "%$search%");
        }
        if (!empty($authUser) && !empty($authUser['role_id'])) {
            if ($authUser['role_id'] != \Constants\ConstUserTypes::Admin) {
                $query->where('role_id', '!=', \Constants\ConstUserTypes::Admin);
            }
            if (!empty($params['role']) && $params['role'] == 'company') {
                $query->whereIn('role_id', array(
                    \Constants\ConstUserTypes::Company
                ));
            } elseif (!empty($params['role']) && $params['role'] == 'employer') {
                $query->whereIn('role_id', array(
                    \Constants\ConstUserTypes::Employer
                ));
            } elseif (!empty($params['role']) && $params['role'] == 'admin') {
                if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin) {
                    $query->where('role_id', \Constants\ConstUserTypes::Admin);
                }
            }
        } else {
            $query->where('role_id', '!=', \Constants\ConstUserTypes::Admin);
        }
    }
}
