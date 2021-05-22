<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class RestaurantCuisine extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurant_cuisines';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'restaurant_id',
		'cuisine_id'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'cuisine_id' => 'sometimes|required'
    );
	public function cuisine()
	{
		return $this->belongsTo('Models\Cuisine', 'cuisine_id','id');
	}
}
