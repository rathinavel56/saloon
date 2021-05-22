<?php
/**
 * EmailTemplate
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class EmailTemplate extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'email_templates';
    protected $fillable = array(
        'created_at',
		'updated_at', 
		'from',
		'reply_to',	 
		'name',
		'description',	 
		'subject',
		'text_email_content',	 
		'html_email_content', 
		'notification_content',	 
		'sms_content',
		'email_variables',	 
		'is_html',
		'is_notify',
		'display_name'
    );
    public $rules = array();
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where('name', 'ilike', '%' . $params['q'] . '%');
        }
    }
}
