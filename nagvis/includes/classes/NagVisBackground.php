<?php
/**
 * Class for printing the map in NagVis
 */
class NagVisBackground extends NagVisMap {
	var $MAINCFG;
	var $MAPCFG;
	var $BACKEND;
	var $GRAPHIC;
	
	var $objects;
	var $image;
	var $imageType;
	
	var $intOk;
	var $intWarning;
	var $intCritical;
	var $intUnknown;
	
	/**
	 * Class Constructor
	 *
	 * @param 	GlobalMainCfg 	$MAINCFG
	 * @param 	GlobalMapCfg 	$MAPCFG
	 * @param 	GlobalBackend 	$BACKEND
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function NagVisBackground(&$MAINCFG,&$MAPCFG,&$LANG,&$BACKEND) {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::NagVisBackground($MAINCFG,$MAPCFG,$LANG,$BACKEND)');
		$this->MAINCFG = &$MAINCFG;
		$this->MAPCFG = &$MAPCFG;
		$this->LANG = &$LANG;
		$this->BACKEND = &$BACKEND;
		
		$this->user = $this->getUser();
		$this->MAINCFG->setRuntimeValue('user',$this->user);
		
		$this->GRAPHIC = new GlobalGraphic();
		
		$this->checkPreflight();
		$this->initImage();
		
		parent::NagVisMap($MAINCFG,$MAPCFG,$LANG,$BACKEND);
		
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::NagVisBackground()');
	}
	
	
	/**
	 * Gets the User
	 *
	 * @return	String	String with Username
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getUser() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method GlobalPage::getUser()');
		if(isset($_SERVER['PHP_AUTH_USER'])) {
			if (DEBUG&&DEBUGLEVEL&1) debug('End method GlobalPage::getUser(): '.$_SERVER['PHP_AUTH_USER']);
			return $_SERVER['PHP_AUTH_USER'];
		} elseif(isset($_SERVER['REMOTE_USER'])) {
			if (DEBUG&&DEBUGLEVEL&1) debug('End method GlobalPage::getUser(): '.$_SERVER['REMOTE_USER']);
			return $_SERVER['REMOTE_USER'];
		}
	}
	
	function checkMemoryLimit($tryToFix=TRUE) {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::checkMemoryLimit('.$tryToFix.')');
		$fileSize = filesize($this->MAINCFG->getValue('paths', 'map').$this->MAPCFG->BACKGROUND->getFileName());
		$memoryLimit = preg_replace('/[a-z]/i','',ini_get('memory_limit'))*1024*1024;
		if (DEBUG&&DEBUGLEVEL&2) debug('Memory Limit: '.$memoryLimit);
		
		$imageSize = getimagesize($this->MAINCFG->getValue('paths', 'map').$this->MAPCFG->BACKGROUND->getFileName());
		if (is_array($imageSize)) {
			$imageWidth = $imageSize[0];
			$imageHeight = $imageSize[1];
			$imageDepth = $imageSize['bits'];
			
			// i think this should be calculated /8 also to get bytes, but removed it cause it works like it is atm
			$rawSize = $imageWidth*$imageHeight*$imageDepth;
		}
		
		
		// is fileSize or actual used memory + rawSize + 10% puffer bigger than memory limit?
		// there is no waranty that the calculations are correct but that could be a good basic
		// a) big file size
		// b) big image size
		// c) big color depth
		// DEBUG: echo $fileSize." > ".$memoryLimit." <bR>";
		// DEBUG: echo ((memory_get_usage() + $rawSize)*1.10)." > ".$memoryLimit;
		$memoryUsage = $this->memoryGetUsage();
		if($memoryLimit > 0) {
			if($fileSize > $memoryLimit || ($memoryUsage + $rawSize)*1.10 > $memoryLimit) {
				if($tryToFix) {
					ini_set('memory_limit',round(($memoryUsage + $rawSize)*1.15 / 1024 /1024).'M');
					$return = $this->checkMemoryLimit(FALSE);
					if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::checkMemoryLimit(): '.$return);
					return $return;
				}
				if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::checkMemoryLimit(): FALSE');
				return FALSE;
			} else {
				if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::checkMemoryLimit(): TRUE');
				return TRUE;
			}
		} else {
			if (DEBUG&&DEBUGLEVEL&2) debug('Memory Limit is less or equal 0, aborting method and hope the Best...');
			if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::checkMemoryLimit(): TRUE');
			return TRUE;
		}
	}
	
	/**
	 * memoryGetUsage - php function memory_get_usage() is not present in many used php versions,
	 * try to code an own method to replace this 
	 *
	 * @return	Int		memory usage of the process
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function memoryGetUsage() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::memoryGetUsage()');
		$iReturn = 0;
		
		// If function already exists in PHP, use it!
		$sLog = 'Check for memory_get_usage()...';
		if(function_exists('memory_get_usage')) {
			$sLog .= 'exists!';
			$iReturn = memory_get_usage();
		} else {
			$sLog .= 'not exists!';
		}
		if (DEBUG&&DEBUGLEVEL&2) debug($sLog);
		
		
		if($iReturn <= 0) {
			// If its Windows
			// Tested on Win XP Pro SP2. Should work on Win 2003 Server too
			// If you need it to work for 2000 look at http://us2.php.net/manual/en/function.memory-get-usage.php#54642
			$sLog = 'Check if WIN/UNIX...';
			if(substr(PHP_OS,0,3) == 'WIN') {
				$sLog .= 'WIN (PHP_OS: '.PHP_OS.')';
				if (DEBUG&&DEBUGLEVEL&2) debug($sLog);
				
				$output = array();
				exec( 'tasklist /FI "PID eq ' . getmypid() . '" /FO LIST', $output );
				if (DEBUG&&DEBUGLEVEL&2) debug('Exec: tasklist /FI "PID eq \' . '.getmypid().' . \'" /FO LIST: '.implode(',',$output));
				
				$iReturn = preg_replace( '/[\D]/', '', $output[5] ) * 1024;
			} else {
				$sLog .= 'UNIX (PHP_OS: '.PHP_OS.')';
				if (DEBUG&&DEBUGLEVEL&2) debug($sLog);
				// We now assume the OS is UNIX
				// Tested on Mac OS X 10.4.6 and Linux Red Hat Enterprise 4
				// This should work on most UNIX systems
				$pid = getmypid();
				if (DEBUG&&DEBUGLEVEL&2) debug('Process ID: '.$pid);
	
				if($pid == 0) {
					$iReturn = 0;
				} else {
					exec('ps -eo%mem,rss,pid | grep '.$pid, $output);
					if (DEBUG&&DEBUGLEVEL&2) debug('Exec: ps -eo%mem,rss,pid | grep '.$pid.': '.implode(',',$output));
					$output = explode('  ', $output[0]);
					
					// rss is given in 1024 byte units
					$iReturn = $output[1] * 1024;
				}
			}
		}
		
		if($iReturn < 0) {
			if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::memoryGetUsage(): 0');
			return 0;
		} else {
			if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::memoryGetUsage(): '.$iReturn);
			return $iReturn;
		}
	}
	
	function initImage() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::initImage()');
		$fileName = $this->MAPCFG->BACKGROUND->getFileName();
		$this->imageType = strtolower(substr($fileName, strrpos($fileName, '.') + 1));
		
		switch($this->imageType) {
			case 'jpg':
				$this->image = imagecreatefromjpeg($this->MAINCFG->getValue('paths', 'map').$fileName);
			break;
			case 'png':
				$this->image = imagecreatefrompng($this->MAINCFG->getValue('paths', 'map').$fileName);
			break;
			default:
				$this->errorBox($this->LANG->getMessageText('onlyPngOrJpgImages'));
			break;
		}
		
		// set some options
		$this->intOk = imagecolorallocate($this->image, 0,255,0);
		$this->intWarning = imagecolorallocate($this->image, 255, 255, 0);
		$this->intCritical = imagecolorallocate($this->image, 255, 0, 0);
		$this->intUnknown = imagecolorallocate($this->image, 255, 128, 0);
		
		$this->GRAPHIC->init($this->image);
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::initImage()');
	}
	
	/**
	 * Do preflight checks
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function checkPreflight() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::checkPreflight()');
		if(!$this->MAPCFG->BACKGROUND->checkFileExists(0)) {
			$this->errorBox($this->LANG->getMessageText('backgroundNotExists','IMGPATH~'.$this->MAINCFG->getValue('paths', 'map').$this->MAPCFG->getImage()));
		}
		if(!$this->MAPCFG->BACKGROUND->checkFileReadable(0)) {
			$this->errorBox($this->LANG->getMessageText('backgroundNotReadable','IMGPATH~'.$this->MAINCFG->getValue('paths', 'map').$this->MAPCFG->getImage()));
		}
		if(!$this->checkMemoryLimit()) {
			$this->errorBox($this->LANG->getMessageText('maybePhpMemoryLimitToLow'));	
		}
		if(!$this->MAPBOBJ->checkPermissions($this->MAPCFG->getValue('global',0, 'allowed_user'),0)) {
			$this->errorBox($this->LANG->getMessageText('permissionDenied','USER~'.$this->MAINCFG->getRuntimeValue('user')));
		}
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::checkPreflight()');
	}
	
	/**
	 * Parses the Map and the Objects
	 *
	 * @return	Array 	Array with Html Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function parseMap() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::parseMap()');
		switch($this->imageType) {
			case 'jpg':
				header('Content-type: image/jpeg');
				// HTTP/1.1
				header('Cache-Control: no-store, no-cache, must-revalidate');
				header('Cache-Control: post-check=0, pre-check=0', false);
				// HTTP/1.0
				header('Pragma: no-cache');
				imagejpeg($this->image);
				imagedestroy($this->image);
				if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::parseMap()');
				if (DEBUG&&DEBUGLEVEL&4) debugFinalize();
			break;
			case 'png':
				header('Content-type: image/png');
				// HTTP/1.1
				header('Cache-Control: no-store, no-cache, must-revalidate');
				header('Cache-Control: post-check=0, pre-check=0', false);
				// HTTP/1.0
				header('Pragma: no-cache');
				imagepng($this->image);
				imagedestroy($this->image);
				if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::parseMap()');
				if (DEBUG&&DEBUGLEVEL&4) debugFinalize();
			break;
			default: 
				// never reach this, error handling at the top
				if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::parseMap(): Error');
				if (DEBUG&&DEBUGLEVEL&4) debugFinalize();
				exit;
			break;
		}
	}
	
	/**
	 * Prints out an error box
	 *
	 * @param	String	$msg	String with error message
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function errorBox($msg) {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::errorBox('.$msg.')');
		$this->image = @imagecreate(800,80);
		$this->imageType = 'png';
		$ImageFarbe = imagecolorallocate($this->image,243,243,243); 
		$schriftFarbe = imagecolorallocate($this->image,10,36,106);
		$schrift = imagestring($this->image, 5,10, 10, $msg, $schriftFarbe);
		
		$this->parseMap();
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::errorBox()');
		exit;
	}
	
	/**
	 * Parses the Objects
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function parseObjects() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::parseObjects()');
		foreach($this->MAPOBJ->getMapObjects() AS $OBJ) {
			switch(get_class($OBJ)) {
				case 'NagVisMapObj':
				case 'NagVisTextbox':
					// do nothing for this objects in background image
					// should never reach this -> method NagVisBackground::getState don't read this objects
				break;
				case 'NagVisHost':
				case 'NagVisService':
				case 'NagVisHostgroup':
				case 'NagVisServicegroup':
				case 'NagVisShape':
					if(isset($OBJ->line_type)) {
						$this->parseLine($OBJ);
					} else {
						// do nothing for this objects in background image
						// should never reach this -> method NagVisBackground::getState don't read this objects
					}
				break;
			}
		}
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::parseObjects(): Array(...)');
	}
	
	/**
	 * Parses a line on the image
	 *
	 * @param	Array	$obj
	 * @return	Array 	Array with Html Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function parseLine(&$OBJ) {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::parseLine()');
		if($OBJ->line_type == '10'){
			list($x_from,$x_to) = explode(',', $OBJ->getX());
			list($y_from,$y_to) = explode(',', $OBJ->getY());
			$x_middle = $this->GRAPHIC->middle($x_from,$x_to);
			$y_middle = $this->GRAPHIC->middle($y_from,$y_to);
			
			$this->GRAPHIC->drawArrow($this->image,$x_from,$y_from,$x_middle,$y_middle,3,1,$this->getColor($OBJ->getSummaryState()));
			$this->GRAPHIC->drawArrow($this->image,$x_to,$y_to,$x_middle,$y_middle,3,1,$this->getColor($OBJ->getSummaryState()));
		} elseif($OBJ->line_type == '11') {
			list($x_from,$x_to) = explode(',', $OBJ->getX());
			list($y_from,$y_to) = explode(',', $OBJ->getY());
			
			$this->GRAPHIC->drawArrow($this->image,$x_from,$y_from,$x_to,$y_to,3,1,$this->getColor($OBJ->getSummaryState()));
		}
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::parseLine()');	
	}
	
	/**
	 * Gets the color to print for the different states
	 *
	 * @param	String	$state
	 * @return	Integer		Color to use
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getColor($state){
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisBackground::getColor('.$state.')');
		if($state == 'OK' || $state == 'UP') {
			$color = $this->intOk;
		} elseif($state == 'WARNING') {
			$color = $this->intWarning;
		} elseif($state == 'CRITICAL' || $state == 'DOWN') {
			$color = $this->intCritical;
		} else {
			$color = $this->intUnknown;
		}
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisBackground::getColor(): '.$color);
		return $color;
	}
}
?>