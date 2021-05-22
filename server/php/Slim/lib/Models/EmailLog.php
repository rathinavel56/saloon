<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class EmailLog extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'email_logs';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'logs',
		'user_id',
		'created_at',
		'updated_at',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'logs' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
    );
    public $qSearchFields = array(
        'log'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];                
            });
        }
		if (!empty($params['user_id'])) {
            $query->Where('user_id', $params['user_id']);
        }
    }
}
