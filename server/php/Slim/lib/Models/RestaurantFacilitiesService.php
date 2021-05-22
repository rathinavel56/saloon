<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class RestaurantFacilitiesService extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurant_facilities_services';
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
		'facilities_service_id'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'facilities_service_id' => 'sometimes|required'
    );
	public function facilities_service()
	{
		return $this->belongsTo('Models\FacilitiesService', 'facilities_service_id', 'id');
	}
}
