<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class RestaurantBookingType extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurant_booking_types';
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
		'booking_type_id'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'booking_type_id' => 'sometimes|required'
    );
}
