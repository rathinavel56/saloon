<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Question extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'questions';
	public $hidden = array(
        'updated_at',
		'is_active',
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'name'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Advertisement');
    }
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];                
            });
        }
		if (!empty($params['is_active'])) {
            $query->Where('is_active', $params['is_active']);
        }
    }
}
