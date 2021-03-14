<?php
/**
 * @package plugins.ZoomDropFolder
 * @subpackage api.objects
 */
class KalturaRecordingFile extends KalturaObject
{
	/**
	 * @var string
	 */
	public $id;

	/**
	 * @var string
	 */
	public $recordingStart;

	/**
	 * @var kRecordingFileType
	 */
	public $fileType;

	/**
	 * @var string
	 */
	public $downloadUrl;
	
	/**
	 * @var string
	 */
	public $fileExtension;
	

	/*
	 * mapping between the field on this object (on the left) and the setter/getter on the entry object (on the right)
	 */
	private static $map_between_objects = array(
		'id',
		'recordingStart',
		'fileType',
		'downloadUrl',
		'fileExtension'
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	public function toObject($dbObject = null, $skip = array())
	{
		if (!$dbObject)
			$dbObject = new kRecordingFile();

		return parent::toObject($dbObject, $skip);
	}
}
