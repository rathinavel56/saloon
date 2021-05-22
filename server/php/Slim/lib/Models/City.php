<?php
/**
 * City
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class City extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cities';
	public $hidden = array(
        'created_at',
        'updated_at',
		'slug',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'user_id',
		'created_at',
		'updated_at',
		'name',
		'count',
		'slug',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required',
		'description' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
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
		if (!empty($params['city_ids'])) {
            $query->Where('id', $params['city_ids']);
        }
        if (!empty($params['not_zero'])) {
            $query->Where('count', '<>', 0);
        }
    }
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'City');
    }
}
