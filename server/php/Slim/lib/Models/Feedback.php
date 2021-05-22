<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Feedback extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'feedbacks';
    public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'question_id',
		'email',
		'message',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'message' => 'sometimes|required',
		'email' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
}
