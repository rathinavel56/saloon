<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class PushNotification extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'push_notifications';
    public $hidden = array(
        'updated_at'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'user_id',
		'title',
		'body',
		'logs',
		'is_read'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'title' => 'sometimes|required',
		'body' => 'sometimes|required',
		'logs' => 'sometimes|required',
		'is_read' => 'sometimes|required'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
     	if (!empty($params['user_id'])) {
            $query->Where('user_id', $params['user_id']);
        }
    }
}
