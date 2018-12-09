<?php

class ESearchCaptionQueryFromFilter extends ESearchQueryFromFilter
{

	const ITEMS = 'items';

	protected $nonSupportedSearchFields = array(
		ESearchCaptionAssetItemFilterFields::PARTNER_ID,
		ESearchCaptionAssetItemFilterFields::FORMAT,
		ESearchCaptionAssetItemFilterFields::STATUS,
		ESearchCaptionAssetItemFilterFields::SIZE,
		ESearchCaptionAssetItemFilterFields::TAGS,
		ESearchCaptionAssetItemFilterFields::PARTNER_DESCRIPTION,
		ESearchCaptionAssetItemFilterFields::ID,
		ESearchCaptionAssetItemFilterFields::DELETED_AT,
		ESearchCaptionAssetItemFilterFields::FLAVOR_PARAMS_ID);

	protected function getNonSupportedFields()
	{
		return $this->nonSupportedSearchFields;
	}

	protected function getSphinxToElasticFieldName($field)
	{
		$fieldsMap = array(
			ESearchCaptionAssetItemFilterFields::CAPTION_ASSET_ID => ESearchCaptionFieldName::CAPTION_ASSET_ID,
			ESearchCaptionAssetItemFilterFields::CONTENT => ESearchCaptionFieldName::CONTENT,
			ESearchCaptionAssetItemFilterFields::START_TIME => ESearchCaptionFieldName::START_TIME,
			ESearchCaptionAssetItemFilterFields::END_TIME => ESearchCaptionFieldName::END_TIME,
			ESearchCaptionAssetItemFilterFields::LANGUAGE => ESearchCaptionFieldName::LANGUAGE,
			ESearchCaptionAssetItemFilterFields::LABEL => ESearchCaptionFieldName::LABEL,
			ESearchCaptionAssetItemFilterFields::ENTRY_ID => ESearchEntryFieldName::ID,
			ESearchCaptionAssetItemFilterFields::CREATED_AT => ESearchEntryFieldName::CREATED_AT ,
			ESearchCaptionAssetItemFilterFields::UPDATED_AT => ESearchEntryFieldName::UPDATED_AT ,
		);

		if(array_key_exists($field, $fieldsMap))
		{
			return $fieldsMap[$field];
		}
		else
		{
			return null;
		}
	}

	protected function getSphinxToElasticSearchItemType($operator)
	{
		$operatorsMap = array(
			baseObjectFilter::EQ => ESearchFilterItemType::EXACT_MATCH,
			baseObjectFilter::IN => ESearchFilterItemType::EXACT_MATCH_MULTI,
			baseObjectFilter::NOT_IN => ESearchFilterItemType::EXACT_MATCH_NOT,
			baseObjectFilter::GTE => ESearchFilterItemType::RANGE_GTE,
			baseObjectFilter::LTE => ESearchFilterItemType::RANGE_LTE,
			baseObjectFilter::LIKE => ESearchFilterItemType::PARTIAL,
			baseObjectFilter::MULTI_LIKE_OR => ESearchFilterItemType::PARTIAL_MULTI_OR,
			baseObjectFilter::MULTI_LIKE_AND => ESearchFilterItemType::PARTIAL_MULTI_AND);

		if(array_key_exists($operator, $operatorsMap))
		{
			return $operatorsMap[$operator];
		}
		else
		{
			return null;
		}
	}

	protected function createSearchItemByFieldType($elasticFieldName)
	{
		$captionFields = array(	ESearchCaptionFieldName::CONTENT,
			ESearchCaptionFieldName::START_TIME,
			ESearchCaptionFieldName::END_TIME,
			ESearchCaptionFieldName::LANGUAGE,
			ESearchCaptionFieldName::LABEL,
			ESearchCaptionFieldName::CAPTION_ASSET_ID);

		if(in_array($elasticFieldName,$captionFields))
		{
			return new ESearchCaptionItem();
		}
		return new ESearchEntryItem();
	}

	public function retrieveElasticQueryCaptions(baseObjectFilter $filter, kPager $pager, $filterOnEntryIds)
	{
		$entrySearch = new kEntrySearch();
		$entrySearch->setFilterOnlyContext();

		if(!$filterOnEntryIds)
		{
			list ($currEntries, $count) = $this->retrieveElasticQueryEntryIds($filter, $pager);
			$filter->setEntryIdIn($currEntries);
		}

		$query = self::createElasticQueryFromFilter($filter, $pager);
		$entrySearch->setForceInnerHitsSizeOverride();
		$elasticResults = $entrySearch->doSearch($query, array(),null, $pager , null);
		list($coreResults, $objectOrder, $objectCount, $objectHighlight) = kESearchCoreAdapter::getElasticResultAsArray($elasticResults,
			$entrySearch->getQueryAttributes()->getQueryHighlightsAttributes());

		$captionAssetItemArray = array();
		foreach ($coreResults as $entryId => $captionsResults)
		{
			foreach($captionsResults as $captionGroup)
			{
					$items = $captionGroup[self::ITEMS];
					foreach ($items as $captionItem)
					{
						$captionAssetItemArray[] = $this->createCaptionAssetItem($entryId, $captionItem);
					}
			}
		}

		return array ($captionAssetItemArray, sizeof($captionAssetItemArray));
	}

	protected function createCaptionAssetItem($entryId, $captionItem)
	{
		$currCaption = new CaptionAssetItem();
		$currCaption->setEntryId($entryId);
		$currCaption->setCaptionAssetId($captionItem->getCaptionAssetId());
		$currCaption->setContent($captionItem->getLine());
		$currCaption->setStartTime($captionItem->getStartsAt());
		$currCaption->setEndTime($captionItem->getEndsAt());
		return $currCaption;
	}

}