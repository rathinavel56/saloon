<?php
/**
 * Size
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Restaurant extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurants';
	public $hidden = array(
        // 'created_at',
        // 'updated_at'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'user_id',
		'city_id',
		'brand_id',
		'title',
		'address',
		'latitude',
		'longitude',
		'description',
		'disclaimer',
		'reservations',
		'vouchers',
		'star_rating',
		'rupee_rating',
		'max_person',
		'is_admin_deactived',
		'is_deactived',
		'is_promo_code',
		'is_gift',
		'timezone_id'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'name' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'title',
		'address'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['search']) && $params['search'] != 'undefined') {
			$search = $params['search'];
			$query->where('title', 'like', "%$search%");
        }
		if (!empty($params['user_id'])) {
            $query->Where('user_id', $params['user_id']);
        }
		if (!empty($params['user_id'])) {
            $query->Where('user_id', $params['user_id']);
        }
		$query->Where('is_deactived', 0);
		if (empty($params['is_admin_deactived'])) {
            $query->Where('is_admin_deactived', 0);
        }
		if (!empty($params['restaurants'])) {
            $query->whereIn('id', $params['restaurants']);
        }
		if (!empty($params['reservation_not_zero'])) {
            $query->where('reservations', '<>', 0);
        }
		if (!empty($params['restaurant_not_id'])) {
            $query->whereNotIn('id', $params['restaurant_not_id']);
        }
		if (!empty($params['brand_id'])) {
            $query->Where('brand_id', $params['brand_id']);
        }
		if (!empty($params['city_id'])) {
            $query->Where('city_id', $params['city_id']);
        }
		if (!empty($params['is_active'])) {
            $query->Where('is_active', $params['is_active']);
        }
		if (!empty($params['created_at'])) {
            $query->Where('created_at', '>=', $params['created_at']);
        }
		if (!empty($params['is_promo_code'])) {
            $query->Where('is_promo_code', $params['is_promo_code']);
        }
		if (!empty($params['most_reserved'])) {
			// $query->orderBy('reservations', $params['most_reserved']);
		} else if (!empty($params['recommended'])) {
			$query->orderBy('star_rating', $params['most_reserved']);
		} else if (!empty($params['distance'])) {
			$query->orderBy('star_rating', $params['most_reserved']);
		} else if (!empty($params['star_rating'])) {
			$query->orderBy('star_rating', $params['most_reserved']);
		} else if (!empty($params['price'])) {
			$query->orderBy('price', $params['asc']);
		}
    }
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Restaurant');
    }
	public function attachments()
    {
        return $this->hasMany('Models\Attachment', 'foreign_id', 'id')->where('class', 'Restaurant');
    }
	public function custom_slots()
    {
		if (isset($_GET['date']) && !empty($_GET['date'])) {
			$date = date_create($_GET['date']);
			return $this->hasOne('Models\CustomTimeSlot', 'restaurant_id', 'id')->with('slots')->where('date_detail', date_format($date,"Y-m-d"));
		} else {
			return $this->hasOne('Models\CustomTimeSlot', 'restaurant_id', 'id')->with('slots')->where('date_detail', date('Y-m-d'));
		}
    }
	public function slots()
    {
		if (isset($_GET['date']) && !empty($_GET['date'])) {
			return $this->hasOne('Models\TimeSlot', 'restaurant_id', 'id')->with('slots')->where('type', 0)->where('day', date('D', strtotime($_GET['date'])));
		} else {
			return $this->hasOne('Models\TimeSlot', 'restaurant_id', 'id')->with('slots')->where('type', 0)->where('day', date('D', date('Y-m-d')));
		}
    }
	public function menus()
    {
		return $this->hasMany('Models\Menu', 'restaurant_id', 'id')->where('is_active', 1);
    }
	public function special_conditions()
    {
		return $this->hasMany('Models\SpecialCondition', 'restaurant_id', 'id');
    }
	public function facilities_services()
    {
		return $this->hasMany('Models\RestaurantFacilitiesService', 'restaurant_id', 'id')->with('facilities_service');
    }
	public function atmospheres()
    {
		return $this->hasMany('Models\RestaurantAtmosphere', 'restaurant_id', 'id')->with('atmosphere');
    }
	public function languages()
    {
		return $this->hasMany('Models\RestaurantLanguage', 'restaurant_id', 'id')->with('language');
    }
	public function operating_hours()
    {
		return $this->hasMany('Models\OperatingHour', 'restaurant_id', 'id')->where('type', 0)->with('day');
    }
	public function hours()
	{
		return $this->hasMany('Models\OperatingHour', 'restaurant_id', 'id')->where('type', '<>' , 0)->with('day');
	}
	public function about()
	{
		return $this->belongsTo('Models\RestaurantAboutUs', 'id', 'restaurant_id');
	}
	public function reviews()
	{
		return $this->hasMany('Models\Review', 'restaurant_id', 'id')->with('user')->where('is_active', 1);
	}
	public function booking_types()
	{
		return $this->hasMany('Models\RestaurantBookingType', 'restaurant_id', 'id');
	}
	public function themes()
	{
		return $this->hasMany('Models\RestaurantTheme', 'restaurant_id', 'id');
	}
	public function cuisines()
	{
		return $this->hasMany('Models\RestaurantCuisine', 'restaurant_id', 'id')->with('cuisine');
	}
	public function favorite()
	{
		return $this->belongsTo('Models\Favorite', 'id', 'restaurant_id');
	}
	public function user()
	{
		return $this->belongsTo('Models\User', 'user_id', 'id');
	}
	public function city()
	{
		return $this->belongsTo('Models\City', 'city_id', 'id');
	}
	public function country()
	{
		return $this->belongsTo('Models\Country', 'country_id', 'id');
	}
	public function payment()
	{
		return $this->hasMany('Models\RestaurantPayment', 'restaurant_id', 'id');
	}
	public function promos()
	{
		return $this->hasMany('Models\RestaurantPromo', 'restaurant_id', 'id');
	}
	public function timezone()
	{
		return $this->belongsTo('Models\Timezone', 'timezone_id', 'id');
	}
}
