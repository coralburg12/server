<?php
/**
* @package plugins.elasticSearch
* @subpackage model.enum
*/
interface ESearchEntryOrderByFieldName extends BaseEnum
{
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';
    const START_DATE = 'start_date';
    const END_DATE = 'end_date';
    const NAME = 'name.keyword';
    const VOTES = 'votes';
    const VIEWS = 'views';
}
