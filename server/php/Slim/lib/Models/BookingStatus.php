<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class BookingStatus extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'booking_status';
    protected $fillable = array(
        'id',
		'name',
		'created_at',
		'updated_at',
	);
}
