<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class RestaurantAtmosphere extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurant_atmospheres';
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
		'atmosphere_id'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'atmosphere_id' => 'sometimes|required'
    );
	public function atmosphere()
	{
		return $this->belongsTo('Models\Atmosphere', 'atmosphere_id', 'id');
	}
}
