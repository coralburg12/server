<?php

/**
 *
 * @service quizUserEntry
 * @package plugins.quiz
 * @subpackage api.services
 */
class QuizUserEntryService extends KalturaBaseService{

	/**
	 * Submits the quiz so that it's status will be submitted and calculates the score for the quiz
	 *
	 * @action submitQuiz
	 * @actionAlias userEntry.submitQuiz
	 * @param int $id
	 * @return KalturaQuizUserEntry
	 * @throws KalturaAPIException
	 */
	public function submitQuizAction($id)
	{
		$dbUserEntry = UserEntryPeer::retrieveByPK($id);
		if (!$dbUserEntry)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
		
		if ($dbUserEntry->getType() != QuizPlugin::getCoreValue('UserEntryType',QuizUserEntryType::QUIZ))
			throw new KalturaAPIException(KalturaQuizErrors::PROVIDED_ENTRY_IS_NOT_A_QUIZ, $id);
		
		$dbUserEntry->setStatus(QuizPlugin::getCoreValue('UserEntryStatus', QuizUserEntryStatus::QUIZ_SUBMITTED));
		$userEntry = new KalturaQuizUserEntry();
		$userEntry->fromObject($dbUserEntry, $this->getResponseProfile());
		$entryId = $dbUserEntry->getEntryId();
		$entry = entryPeer::retrieveByPK($entryId);
		if(!$entry)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $entryId);
		
		$kQuiz = QuizPlugin::getQuizData($entry);
		if (!$kQuiz)
			throw new KalturaAPIException(KalturaQuizErrors::PROVIDED_ENTRY_IS_NOT_A_QUIZ, $entryId);
		
		list($score, $numOfCorrectAnswers) = $dbUserEntry->calculateScoreAndCorrectAnswers();
		$dbUserEntry->setScore($score);
		$dbUserEntry->setNumOfCorrectAnswers($numOfCorrectAnswers);	
		if ($kQuiz->getShowGradeAfterSubmission()== KalturaNullableBoolean::TRUE_VALUE || $this->getKs()->isAdmin() == true)
		{
			$userEntry->score = $score;
		}
		else
		{
			$userEntry->score = null;
		}

		$c = new Criteria();
		$c->add(CuePointPeer::ENTRY_ID, $dbUserEntry->getEntryId(), Criteria::EQUAL);
		$c->add(CuePointPeer::TYPE, QuizPlugin::getCoreValue('CuePointType', QuizCuePointType::QUIZ_QUESTION));
		$questions = CuePointPeer::doSelect($c);
		$dbUserEntry->setNumOfQuestions(count($questions));
		$relevantQuestionCount = 0;
		foreach($questions as $question)
		{
			/* @var QuestionCuePoint $question*/
			if (!$question->getExcludeFromScore())
			{
				$relevantQuestionCount++;
			}
		}
		$dbUserEntry->setNumOfRelevnatQuestions($relevantQuestionCount);
		$dbUserEntry->setStatus(QuizPlugin::getCoreValue('UserEntryStatus', QuizUserEntryStatus::QUIZ_SUBMITTED));
		$dbUserEntry->save();
		QuizUserEntryService::calculateScoreByScoreType($kQuiz,$userEntry, $dbUserEntry, $score);

		return $userEntry;
	}

	protected function calculateScoreByScoreType($kQuiz, $kalturaUserEntry, $dbUserEntry, $currentScore)
	{
		if ($dbUserEntry->getVersion() == 0)
		{
			$calculatedScore = $currentScore;
		}
		else
		{
			$scoreType = $kQuiz->getScoreType();
			//retrieve user entry list order by version desc
			$userEntryVersions = userEntryPeer::retriveUserEntriesSubmitted($dbUserEntry->getKuserId(), $dbUserEntry->getEntryId(), QuizPlugin::getCoreValue('UserEntryType', QuizUserEntryType::QUIZ), false);
			switch ($scoreType)
			{
				case KalturaScoreType::HIGHEST:
					$highest =  $userEntryVersions[0]->getScore();
					foreach ($userEntryVersions as $userEntry)
					{
						if ($userEntry->getScore() > $highest)
						{
							$highest = $userEntry->getScore();
						}
					}
					$calculatedScore = $highest;
					break;

				case KalturaScoreType::LOWEST:
					$lowest =  $userEntryVersions[0]->getScore();
					foreach ($userEntryVersions as $userEntry)
					{
						if ($userEntry->getScore() < $lowest)
						{
							$lowest = $userEntry->getScore();
						}
					}
					$calculatedScore = $lowest;
					break;

				case KalturaScoreType::LATEST:
					$calculatedScore = $userEntryVersions[0]->getScore();
					break;

				case KalturaScoreType::FIRST:
					$countUserEntryVersions = count($userEntryVersions);
					$calculatedScore = $userEntryVersions[$countUserEntryVersions - 1 ]->getScore();
					break;

				case KalturaScoreType::AVERAGE:
					$sumScores = 0;
					foreach ($userEntryVersions as $userEntry)
					{
						$sumScores += $userEntry->getScore();
					}
					ini_set("precision", 3);
					$calculatedScore =  $sumScores / count($userEntryVersions);
					break;
			}
		}

		$dbUserEntry->setCalculatedScore($calculatedScore);
		$dbUserEntry->save();
		if ($kQuiz->getShowGradeAfterSubmission()== KalturaNullableBoolean::TRUE_VALUE || $this->getKs()->isAdmin() == true)
		{
			$kalturaUserEntry->calculatedScore = $calculatedScore;
		}
		else
		{
			$kalturaUserEntry->calculatedScore = null;
		}

	}
}
