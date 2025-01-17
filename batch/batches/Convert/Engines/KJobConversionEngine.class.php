<?php
/**
 * base class for the real ConversionEngines in the system - ffmpeg,menconder and flix. 
 * 
 * @package Scheduler
 * @subpackage Conversion.engines
 */
require_once(__DIR__.'/../../../../alpha/apps/kaltura/lib/dateUtils.class.php');
require_once(__DIR__.'/../../../../alpha/apps/kaltura/lib/storage/kFileUtils.php');
abstract class KJobConversionEngine extends KConversionEngine
{
	const SEC_TIMEOUT = 10;
	
	/**
	 * @param KalturaConvertJobData $data
	 * @return array<KConversioEngineResult>
	 */
	protected function getExecutionCommandAndConversionString ( KalturaConvertJobData $data )
	{
		$tempPath = dirname($data->destFileSyncLocalPath);
		$this->logFilePath = $data->logFileSyncLocalPath;
		// assume there always will be this index
		$conv_params = $data->flavorParamsOutput;
 
		$cmd_line_arr = $this->getCmdArray($conv_params->commandLinesStr);

		$conversion_engine_result_list = array();
		
		foreach ( $cmd_line_arr as $type => $cmd_line )
		{
			if($type != $this->getType())
				continue;
				
			$cmdArr = explode(self::MILTI_COMMAND_LINE_SEPERATOR, $cmd_line);
			$lastIndex = count($cmdArr) - 1;
			
			foreach($cmdArr as $index => $cmd)
			{
				if($index == 0)
				{
					$this->inFilePath = $this->getSrcActualPathFromData($data);
				}
				else
				{
					$this->inFilePath = $this->outFilePath;
				}
			
				if($lastIndex > $index)
				{
					$uniqid = uniqid("tmp_convert_", true);
					$this->outFilePath = $tempPath . DIRECTORY_SEPARATOR . $uniqid;
				}
				else
				{
					$this->outFilePath = $data->destFileSyncLocalPath;	
				}
				
				$cmd = trim($cmd);
				if($cmd == self::FAST_START_SIGN)
				{
					$exec_cmd = $this->getQuickStartCmdLine(true);
				}
				else
				{
					$exec_cmd = $this->getCmdLine ( $cmd , true, $data->estimatedEffort );
				}
				$conversion_engine_result = new KConversioEngineResult( $exec_cmd , $cmd );
				$conversion_engine_result_list[] = $conversion_engine_result;
			}	
		}
		
		return $conversion_engine_result_list;			
	}	
	
	public function simulate ( KalturaConvartableJobData $data )
	{
		return  $this->simulatejob ( $data );
	}	
	
	private function simulatejob ( KalturaConvertJobData $data )
	{
		return  $this->getExecutionCommandAndConversionString ( $data );
	}
	
	public function convert ( KalturaConvartableJobData &$data, $jobId = null )
	{
		return  $this->convertJob ( $data, $jobId );
	}
	
	public function convertJob ( KalturaConvertJobData &$data, $jobId = null )
	{

		$error_message = "";  
		$actualFileSyncLocalPath = $this->getSrcActualPathFromData($data);
		if ( ! kFile::checkFileExists( $actualFileSyncLocalPath ) )
		{
			$error_message = "File [{$actualFileSyncLocalPath}] does not exist";
			KalturaLog::err(  $error_message );
			return array ( false , $error_message );
		}

		if ( ! $data->logFileSyncLocalPath )
		{
			$data->logFileSyncLocalPath = $data->destFileSyncLocalPath . ".log";
		}
		
		$log_file = $data->logFileSyncLocalPath;
	
		// will hold a list of commands
		// there is a list (most probably holding a single command)
		// just incase there are multiple commands such as in FFMPEG's 2 pass
		$conversion_engine_result_list = $this->getExecutionCommandAndConversionString ( $data );
		
		$this->addToLogFile ( $log_file , "Executed by [" . $this->getName() . "] flavor params id [" . $data->flavorParamsOutput->flavorParamsId . "]" ) ;
		
		// add media info of source 
		$this->logMediaInfo ( $log_file , $actualFileSyncLocalPath );
		
		$duration = 0;
		foreach ( $conversion_engine_result_list as $conversion_engine_result )
		{
			$execution_command_str = $conversion_engine_result->exec_cmd;
			$conversion_str = $conversion_engine_result->conversion_string; 
			
			$this->addToLogFile ( $log_file , $execution_command_str ) ;
			$this->addToLogFile ( $log_file , $conversion_str ) ;
				
			KalturaLog::info ( $execution_command_str );

                        if(isset($data->urgency))
                                $urgency = $data->urgency;
                        else
                                $urgency = null;
	
			$start = microtime(true);
			
			$sharedChunkPath = null;
			if(isset(KBatchBase::$taskConfig->params->sharedChunkPath))
			{
				$sharedChunkPath = KBatchBase::$taskConfig->params->sharedChunkPath;
			}
			
			// TODO add BatchEvent - before conversion + conversion engine
			$output = $this->execute_conversion_cmdline($execution_command_str, $return_value, $urgency, $jobId, $sharedChunkPath);
			// TODO add BatchEvent - after conversion + conversion engine
			$end = microtime(true);
	
			// 	TODO - find some place in the DB for the duration
			$duration += ( $end - $start );
						 
			KalturaLog::info ( $this->getName() . ": [$return_value] took [$duration] seconds" );
			
			$this->addToLogFile ( $log_file , $output ) ;
			
			if ( $return_value != 0 ) 
				return array ( false , "return value: [$return_value]"  );
		}
		// add media info of target
		$this->logMediaInfo ( $log_file , $data->destFileSyncLocalPath );
		
		// Export job CPU metrics 
		$obj=KChunkedEncodeSessionManager::GetSessionStatsJSON($log_file);
		if(isset($obj->userCpu))
			$data->userCpu = round($obj->userCpu);
		
		return array ( true , $error_message );// indicate all was converted properly
	}

	protected function isConversionProgressing($currentModificationTime)
	{
		if (kFile::checkFileExists($this->inFilePath) && !kFile::checkIsDir($this->inFilePath))
		{
			$newModificationTime = kFile::getFileLastUpdatedTime($this->inFilePath);
		}
		else
		{
			$newModificationTime = kFileUtils::getMostRecentModificationTimeFromDir($this->inFilePath);
		}

		if ($newModificationTime !== false && $newModificationTime > $currentModificationTime)
		{
			return $newModificationTime;
		}
		return false;
	}

	protected function getReturnValues($handle)
	{
		$return_var = 1;
		$output = false;
		if ($handle)
		{
			$return_var = 0;
			$file = $this->outFilePath;
			if (kFile::checkFileExists($file))
			{
				KalturaLog::debug('output file is : '. $file);
				$output = kFile::getLinesFromFileTail($file);
			}
		}
		return array($output, $return_var);
	}

	/**
	 *
	 */
	protected function execute_conversion_cmdline($command, &$return_var, $urgency, $jobId = null, $sharedChunkPath = null)
	{
		if (isset(KBatchBase::$taskConfig->params->usingSmartJobTimeout) && KBatchBase::$taskConfig->params->usingSmartJobTimeout == 1)
		{
			return $this->executeConversionCmdlineSmartTimeout($command, $return_var, $jobId);
		}
		else
		{
			$output = system($command, $return_var);
			return $output;
		}
	}

	/*
	 * This function is executing the conversion while also checking if the conversion is progressing.
	 * progress in Convert FFMPEG - we check the modification time on file: inFilePath
	 * progress in Chunked FFMPEG - we check the modification time on the files in the directory: inFilePath_chunkenc/
	 *
	 * after every $extendTime - if the file/s are keep progressing we will extend the expiration of the batch job lock,
	 * and this will postponed the timeout of the job.
	 * We keep the $timeout parameter to know when timeout is reached.
	 * if the conversion is not progressing, and the timeout is Reached - we will end it even if the execution was not finished.
	 */
	protected function executeConversionCmdlineSmartTimeout($command, &$return_var, $jobId = null)
	{
		$handle = popen($command, 'r');
		stream_set_blocking ($handle,0) ;
		$currentModificationTime = 0;
		$lastTimeOutSet = time();
		$maximumExecutionTime = KBatchBase::$taskConfig->maximumExecutionTime;
		$extendTime = $maximumExecutionTime ? ($maximumExecutionTime / 3) : dateUtils::HOUR;
		$timeout = $maximumExecutionTime;
		while(!feof($handle))
		{
			clearstatcache();
			$buffer = fread($handle,1);
			$newModificationTime = $this->isConversionProgressing($currentModificationTime);
			if($newModificationTime)
			{
				if ($lastTimeOutSet + $extendTime < time())
				{
					$timeout = self::extendExpiration($jobId, $maximumExecutionTime, $timeout);
					$lastTimeOutSet = time();
					KalturaLog::debug('Previous modification time was:  ' . $currentModificationTime . ', new modification time is: '. $newModificationTime);
				}
				$currentModificationTime = $newModificationTime;
			}
			sleep(self::SEC_TIMEOUT);
			if(self::isReachedTimeout($timeout))
			{
				pclose($handle);
				$return_var = 1;
				return false;
			}
		}
		list($output, $return_var) = $this->getReturnValues($handle);
		pclose($handle);
		return $output;
	}

	protected static function extendExpiration($jobId, $maximumExecutionTime, $timeout)
	{
		if ($jobId)
		{
			try
			{
				KBatchBase::$kClient->batch->extendLockExpiration($jobId, $maximumExecutionTime);
				$timeout += $maximumExecutionTime;
			}
			catch (Exception $e)
			{
				KalturaLog::debug('Extend batch job lock failed. '. $e->getMessage());
			}
		}
		return $timeout;
	}

	protected static function isReachedTimeout(&$timeout)
	{
		$timeout -= self::SEC_TIMEOUT;
		if($timeout <= 0)
		{
			KalturaLog::debug("Reached to TIMEOUT");
			return true;
		}
		return false;
	}
}


