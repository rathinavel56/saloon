<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class RestaurantPromo extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurant_promos';
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
		'code',
		'amount'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'code' => 'sometimes|required',
		'amount' => 'sometimes|required'
    );
}
