<?php
/**
 * Country
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class Country extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'countries';
    protected $fillable = array(
        'iso_alpha2',
        'iso_alpha3',
        'iso_numeric',
        'fips_code',
        'name',
        'capital',
        'areainsqkm',
        'population',
        'continent',
        'tld',
        'currency',
        'currencyname',
        'phone',
        'postalcodeformat',
        'postalcoderegex',
        'languages',
        'geonameid',
        'neighbours',
        'equivalentfipscode',
		'currency_symbol'
    );
    public $rules = array(
        'name' => 'sometimes|required',
        'fips_code' => 'sometimes|max:2',
        'iso2' => 'sometimes|max:2',
        'iso3' => 'sometimes|max:3',
        'capital' => 'sometimes|alpha',
        'currency' => 'sometimes|max:3'
    );
    public $qSearchFields = array(
        'name'
    );
	public function cites()
    {
        return $this->hasMany('Models\City', 'country_id', 'id')->where('count', '<>', 0);
    }
	
}
