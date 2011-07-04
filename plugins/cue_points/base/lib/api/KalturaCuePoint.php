<?php
/**
 * @package plugins.cuePoint
 * @subpackage api.objects
 */
class KalturaCuePoint extends KalturaObject implements IFilterable 
{
	/**
	 * @var string
	 * @filter eq,in
	 * @readonly
	 */
	public $id;
	
	/**
	 * @var KalturaCuePointType
	 * @filter eq,in
	 */
	public $type;
	
	/**
	 * @var KalturaCuePointStatus
	 * @filter eq,in
	 */
	public $status;
	
	/**
	 * @var string
	 * @filter eq,in
	 * 
	 */
	public $entryId;
	
	/**
	 * @var int
	 * @readonly
	 * 
	 */
	public $partnerId;
	
	/**
	 * @var int
	 * @filter gte,lte,order
	 * @readonly
	 */
	public $createdAt;

	/**
	 * @var int
	 * @filter gte,lte,order
	 * @readonly
	 */
	public $updatedAt;
	
	/**
	 * @var string
	 */
	public $tags;
	

	/**
	 * @var int 
	 * @filter gte,lte,order
	 */
	public $startTime;
	
	/**
	 * @var string
	 * @filter eq,in
	 * @readonly
	 */
	public $userId;
	
	/**
	 * @var string
	 */
	public $partnerData;
	
	/**
	 * @var int
	 * @filter eq,in,gte,lte,order
	 */
	public $partnerSortValue;
	
	/**
	 * @var KalturaNullableBoolean
	 * @filter eq,in
	 */
	public $forceStop;
	
	/**
	 * @var int
	 */
	public $thumbOffset;

	
	
	private static $map_between_objects = array
	(
		"id",
		"type",
		"status",
		"entryId",
		"partnerId",
		"createdAt",
		"updatedAt",
		"tags",
		"startTime",
		"userId" => "puserId",
		"partnerData",
		"partnerSortValue",
		"forceStop",
		"thumbOffset",
	);
	
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}
	
	public function getExtraFilters()
	{
		return array();
	}
	
	public function getFilterDocs()
	{
		return array();
	}
	
	public function fromObject($dbCuePoint)
	{
		parent::fromObject($dbCuePoint);
		
		if($dbCuePoint->getKuserId() !== null){
			$dbKuser = kuserPeer::retrieveByPK($dbCuePoint->getKuserId());
			$this->userId = $dbKuser->getPuserId();
		}
	}
	
	/**
	 * @param CuePoint $dbCuePoint
	 * @param array $propsToSkip
	 * @return CuePoint
	 */
	public function toInsertableObject($dbCuePoint = null, $propsToSkip = array())
	{
		if(is_null($dbCuePoint))
			$dbCuePoint = new CuePoint();
			
		return parent::toInsertableObject($dbCuePoint, $propsToSkip);
	}
	
	/*
	 * @param string $cuePointId
	 * @throw KalturaAPIException
	 */
	public function validateEntryId($cuePointId = null)
	{	
		$dbEntry = entryPeer::retrieveByPK($this->entryId);
		if (!$dbEntry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $this->entryId);
			
		if($cuePointId !== null){ // update
			$dbCuePoint = CuePointPeer::retrieveByPK($cuePointId);
			if(!$dbCuePoint)
				throw new KalturaAPIException(KalturaCuePointErrors::INVALID_OBJECT_ID, $cuePointId);
				
			if($this->entryId !== null && $this->entryId != $dbCuePoint->getEntryId())
				throw new KalturaAPIException(KalturaCuePointErrors::CANNOT_UPDATE_ENTRY_ID);
		}
	}
	
	/*
	 * @param string $cuePointId
	 * @throw KalturaAPIException
	 */
	public function validateEndTime($cuePointId = null)
	{
		if(($this->startTime === null) && ($this->endTime !== null))
				throw new KalturaAPIException(KalturaCuePointErrors::END_TIME_WITHOUT_START_TIME);
		
		if ($this->endTime === null)
			$this->endTime = $this->startTime;
			
		if($this->endTime < $this->startTime)
			throw new KalturaAPIException(KalturaCuePointErrors::END_TIME_CANNOT_BE_LESS_THAN_START_TIME, $this->parentId);
		
		if($cuePointId !== null)
		{
			$dbCuePoint = CuePointPeer::retrieveByPK($cuePointId);
			if(!$dbCuePoint)
				throw new KalturaAPIException(KalturaCuePointErrors::INVALID_OBJECT_ID, $cuePointId);
				
			$dbEntry = entryPeer::retrieveByPK($dbCuePoint->getEntryId());
		}
		else //add
		{ 
			$dbEntry = entryPeer::retrieveByPK($this->entryId);
			if (!$dbEntry)
				throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $this->entryId);
		}
		if (!$dbEntry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $this->entryId);
		
		if($dbEntry->getLengthInMsecs() < $this->endTime)
			throw new KalturaAPIException(KalturaCuePointErrors::END_TIME_IS_BIGGER_THAN_ENTRY_END_TIME, $this->endTime, $dbEntry->getLengthInMsecs());	
	}
	
	/*
	 * @param string $cuePointId
	 * @throw KalturaAPIException
	 */
	public function validateStartTime($cuePointId = null)
	{	
		if ($this->startTime === null)
			$this->startTime = 0;
		
		if($this->startTime < 0)
			throw new KalturaAPIException(KalturaCuePointErrors::START_TIME_CANNOT_BE_LESS_THAN_0);
		
		if($cuePointId !== null){ //update
			$dbCuePoint = CuePointPeer::retrieveByPK($cuePointId);
			if(!$dbCuePoint)
				throw new KalturaAPIException(KalturaCuePointErrors::INVALID_OBJECT_ID, $cuePointId);
				
			$dbEntry = entryPeer::retrieveByPK($dbCuePoint->getEntryId());
		}
		else //add
		{ 
			$dbEntry = entryPeer::retrieveByPK($this->entryId);
			if (!$dbEntry)
				throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $this->entryId);
		}
		
		if($dbEntry->getLengthInMsecs() < $this->startTime)
			throw new KalturaAPIException(KalturaCuePointErrors::START_TIME_IS_BIGGER_THAN_ENTRY_END_TIME, $this->startTime, $dbEntry->getLengthInMsecs());
	}
	
	public function validateForInsert()
	{
		parent::validateForInsert();
		
		$this->validatePropertyNotNull("entryId");
		$this->validateEntryId();
		$this->validateStartTime();
		$this->validateEndTime();	
				
		if($this->text != null)
			$this->validatePropertyMaxLength("text", CuePointPeer::MAX_TEXT_LENGTH);
			
		if($this->tags != null)
			$this->validatePropertyMaxLength("tags", CuePointPeer::MAX_TAGS_LENGTH);
	}
	
	public function validateForUpdate($source_object)
	{
		if($this->text !== null)
			$this->validatePropertyMaxLength("text", CuePointPeer::MAX_TEXT_LENGTH);
		
		if($this->tags !== null)
			$this->validatePropertyMaxLength("tags", CuePointPeer::MAX_TAGS_LENGTH);
		
		if($this->entryId !== null)
			$this->validateEntryId($source_object->getId());
		
		if($this->startTime !== null)
			$this->validateStartTime($source_object->getId());
		
		if($this->endTime !== null)
			$this->validateEndTime($source_object->getId());
					
		return parent::validateForUpdate($source_object);
	}
}
