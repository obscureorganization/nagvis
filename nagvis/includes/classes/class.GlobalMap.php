<?php
/** 
 * Class for printing the map
 * Should be used by ALL pages of NagVis and NagVisWui
 */
class GlobalMap {
	var $MAINCFG;
	var $MAPCFG;
	var $BACKEND;
	
	var $objects;
	var $linkedMaps;
	
	/**
	 * Class Constructor
	 *
	 * @param 	GlobalMainCfg 	$MAINCFG
	 * @param 	GlobalMapCfg 	$MAPCFG
	 * @param 	GlobalBackend 	$BACKEND
	 * @author 	Lars Michelsen <larsi@nagios-wiki.de>
	 */
	function GlobalMap(&$MAINCFG,&$MAPCFG,&$BACKEND) {
		$this->MAINCFG = &$MAINCFG;
		$this->MAPCFG = &$MAPCFG;
		$this->BACKEND = &$BACKEND;
	}
	
	/**
	 * Check if GD-Libs installed, when GD-Libs are enabled.
	 *
	 * @param	Boolean $printErr
	 * @return	Boolean	Is Successful?
	 * @author 	Lars Michelsen <larsi@nagios-wiki.de>
     */
	function checkGd($printErr) {
		if($this->MAINCFG->getValue('global', 'usegdlibs') == "1") {
        	if(!extension_loaded('gd')) {
        		if($printErr) {
	                $FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'global:global'));
		            $FRONTEND->messageToUser('ERROR','gdLibNotFound');
	            }
	            return FALSE;
            } else {
            	return TRUE;
        	}
        } else {
            return TRUE;
        }
	}
	
	/**
	 * Gets the background of the map
	 *
	 * @param	String	$type	Type of Background (gd/img)
	 * @return	Array	HTML Code
	 * @author 	Lars Michelsen <larsi@nagios-wiki.de>
     */
	function getBackground($type='gd') {
		if($this->MAPCFG->getName() != '') {
			if($this->MAINCFG->getValue('global', 'usegdlibs') == "1" && $type == 'gd' && $this->checkGd(0)) {
				$src = "./draw.php?map=".$this->MAPCFG->getName();
			} else {
				$src = $this->MAINCFG->getValue('paths', 'htmlmap').$this->MAPCFG->getImage();
			}
		} else {
			$src = "./images/internal/wuilogo.jpg";
			$style = "width:600px; height:600px;";
		}
		
		return Array('<img id="background" src="'.$src.'" style="'.$style.'">');
	}
	
	/**
	 * Gets all objects of the map
	 *
	 * @param	Boolean	$getState	With state?
	 * @return	Array	Array of Objects of this map
	 * @author 	Lars Michelsen <larsi@nagios-wiki.de>
     */
	function getMapObjects($getState=1) {
		$objects = Array();
		
		// save mapName in linkedMaps array
		$this->linkedMaps[] = $this->MAPCFG->getName();
		
		$objects = array_merge($objects,$this->getObjectsOfType('map',$getState));
		$objects = array_merge($objects,$this->getObjectsOfType('host',$getState));
		$objects = array_merge($objects,$this->getObjectsOfType('service',$getState));
		$objects = array_merge($objects,$this->getObjectsOfType('hostgroup',$getState));
		$objects = array_merge($objects,$this->getObjectsOfType('servicegroup',$getState));
		$objects = array_merge($objects,$this->getObjectsOfType('textbox',$getState));
		
		return $objects;
	}
	
	/**
	 * Gets all objects of the defined type from a map and print it or just return an array with states
	 *
	 * @param	String	$type		Type of objects
	 * @param	Boolean	$getState	With state?
	 * @return	Array	Array of Objects of this type on the map
	 * @author 	Lars Michelsen <larsi@nagios-wiki.de>
     */
	function getObjectsOfType($type,$getState=1) {
		// object array
		$objects = Array();
		
		// Default object state
		if($type == 'host' || $type == 'hostgroup') {
			$objState = Array('state'=>'UP','stateOutput'=>'Default State');
		} else {
			$objState = Array('state'=>'OK','stateOutput'=>'Default State');
		}
		
		if(is_array($this->MAPCFG->getDefinitions($type))){
			foreach($this->MAPCFG->getDefinitions($type) as $index => $obj) {
				// Workaround
				$obj['id'] = $index;
				// add default state to the object
				$obj = array_merge($obj,$objState);
				
				if($getState == 1) {
					$obj = array_merge($obj,$this->getState($obj));
				}
				
				if($obj['type'] != 'textbox') {
					$obj['icon'] = $this->getIcon($obj);
				}
				
				// add object to array of objects
				$objects[] = $obj;	
			}
			
			return $objects;
		}
	}
	
	/**
	 * Gets the summary state of all objects on the map
	 *
	 * @param	Array	$arr	Array with states
	 * @return	String	Summary state of the map
	 * @author 	Lars Michelsen <larsi@nagios-wiki.de>
     */
	function getMapState($arr) {
		$ret = Array();
		foreach($arr AS $obj) {
			$ret[] = $obj['state'];
		}
		
		return $this->wrapState($ret);
	}
	
	/**
	 * Gets the state of an object
	 *
	 * @param	Array	$obj	Array with object properties
	 * @return	Array	Array with state of the object
	 * @author 	Lars Michelsen <larsi@nagios-wiki.de>
     */
	function getState($obj) {
		if($obj['type'] == 'service') {
			$name = 'host_name';
		} else {
			$name = $obj['type'] . '_name';
		}
		
		switch($obj['type']) {
			case 'map':
				$SUBMAPCFG = new GlobalMapCfg($this->MAINCFG,$obj[$name]);
				$SUBMAPCFG->readMapConfig();
				$SUBMAP = new GlobalMap($this->MAINCFG,$SUBMAPCFG,$this->BACKEND);
				
				// prevent loops in recursion
				if(in_array($SUBMAPCFG->getName(),$this->linkedMaps)) {
	                $FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'global:global'));
		            $FRONTEND->messageToUser('WARNING','loopInMapRecursion');
					
					$state = Array('State' => 'UNKNOWN','Output' => 'Error: Loop in Recursion');
				} else {
					$state = $SUBMAP->getMapState($SUBMAP->getMapObjects(1));
					$state = Array('State' => $state,'Output'=>'State of child map is '.$state);
				}
			break;
			case 'textbox':
				// Check if set a hostname
				if (isset($obj['host_name'])) {
			  		$state = $this->BACKEND->findStateHost($obj['host_name'],$obj['recognize_services'],0);
				}
			break;
			default:
				// get option to recognize the services - object-config, object-global-config, main-config, default
				$recognizeServices = $this->checkOption($obj['recognize_services'],$this->MAPCFG->getValue('global', 0, 'recognize_services'),$this->MAINCFG->getValue('global', 'recognize_services'),"1");
				
				if(isset($obj['line_type']) && $obj['line_type'] == "20") {
					// line with 2 states...
					list($objNameFrom,$objNameTo) = explode(",", $obj[$name]);
					list($serviceDescriptionFrom,$serviceDescriptionTo) = explode(",", $obj['service_description']);
					
					$state1 = $this->BACKEND->checkStates($obj['type'],$objNameFrom,$recognizeServices,$serviceDescriptionFrom,0);
					$state2 = $this->BACKEND->checkStates($obj['type'],$objNameTo,$recognizeServices,$serviceDescriptionTo,0);
				} else {
					$state = $this->BACKEND->checkStates($obj['type'],$obj[$name],$recognizeServices,$obj['service_description'],0);
				}
			break;	
		}
		return Array('state' => $state['State'],'stateOutput' => $state['Output']);
	}
	
	/**
	 * Searches the icon for an object
	 *
	 * @param	Array	$obj	Array with object properties
	 * @return	String	Name of the icon
	 * @author Michael Luebben <michael_luebben@web.de>
	 * @author Lars Michelsen <larsi@nagios-wiki.de>
     */
	function getIcon($obj) {
        $valid_format = array(
                0=>"gif",
                1=>"png",
                2=>"bmp",
                3=>"jpg",
                4=>"jpeg"
        );
		$stateLow = strtolower($obj['state']);
		
		if(isset($obj['iconset'])) {
			$obj['iconset'] = $obj['iconset'];
		} elseif($this->MAPCFG->getValue('global', 0, 'iconset') != '') {
			$obj['iconset'] = $this->MAPCFG->getValue('global', 0, 'iconset');
		} elseif($this->MAINCFG->getValue('global', 'defaulticons') != '') {
			$obj['iconset'] = $this->MAINCFG->getValue('global', 'defaulticons');
		} else {
			$obj['iconset'] = "std_medium";
		}
		
		switch($obj['type']) {
			case 'map':
				switch($stateLow) {
					case 'ok':
					case 'warning':
					case 'critical':
					case 'unknown':
					case 'ack':		
						$icon = $obj['iconset'].'_'.$stateLow;
					break;
					default:
						$icon = $obj['iconset']."_error";
					break;
				}
			break;
			case 'host':
			case 'hostgroup':
				switch($stateLow) {
					case 'down':
					case 'unknown':
					case 'critical':
					case 'unreachable':
					case 'warning':
					case 'ack':
					case 'up':
						$icon = $obj['iconset'].'_'.$stateLow;
					break;
					default:
						$icon = $obj['iconset']."_error";
					break;
				}
			break;
			case 'service':
			case 'servicegroup':
				switch($stateLow) {
					case 'critical':
					case 'warning':
					case 'sack':
					case 'unknown':
					case 'ok':
						$icon = $obj['iconset'].'_'.$stateLow;
					break;
					default:	
						$icon = $obj['iconset']."_error";
					break;
				}
			break;
			default:
					//echo "getIcon: Unknown Object Type (".$obj['type'].")!";
					$icon = $obj['iconset']."_error";
			break;
		}

		for($i=0;$i<count($valid_format);$i++) {
			if(file_exists($this->MAINCFG->getValue('paths', 'icon').$icon.".".$valid_format[$i])) {
            	$icon .= ".".$valid_format[$i];
			}
		}
		
		if(file_exists($this->MAINCFG->getValue('paths', 'icon').$icon)) {	
			return $icon;
		} else {
			return $obj['iconset']."_error.png";
		}
	}
	
	/**
	 * Create a position for a icon on the map
	 *
	 * @param	Array	Array with object properties
	 * @return	Array	Array with object properties
	 * @author	Michael Luebben <michael_luebben@web.de>
	 * @author	Lars Michelsen <larsi@nagios-wiki.de>
	 */
	function fixIconPosition($obj) {
		$size = getimagesize($this->MAINCFG->getValue('paths', 'icon').$obj['icon']);
		$obj['x'] = $obj['x'] - ($size[0]/2);
		$obj['y'] = $obj['y'] - ($size[1]/2);
		
		return $obj;
	}
	
	/**
	 * Merges the options to an final setting
	 *
	 * @param	String	$define		String with definition in object
	 * @param	String	$mapGlobal	String with definition in map global
	 * @param	String	$global		String with definition in nagvis global
	 * @param	String	$default	String with default definition
	 * @return	String	
	 * @author 	Michael Luebben <michael_luebben@web.de>
	 */
	function checkOption($define,$mapGlobal,$global,$default) {
		if(isset($define)) {
			$option = $define;
		} elseif(isset($mapGlobal)) {
			$option = $mapGlobal;
		} elseif(isset($global)) {
			$option = $global;
		} else {
			$option = $default;
		}
		return $option;	
	}
	
	/**
	 * Wraps all states in an Array to a summary state
	 *
	 * @param	Array	Array with objects states
	 * @return	String	Object state (DOWN|CRITICAL|WARNING|UNKNOWN|ERROR)
	 * @author	Lars Michelsen <larsi@nagios-wiki.de>
	 */
	function wrapState($objStates) {
		if(in_array("DOWN", $objStates) || in_array("CRITICAL", $objStates)) {
			return "CRITICAL";
		} elseif(in_array("WARNING", $objStates)) {
			return "WARNING";
		} elseif(in_array("UNKNOWN", $objStates)) {
			return "UNKNOWN";
		} elseif(in_array("ERROR", $objStates)) {
			return "ERROR";
		} else {
			return "OK";
		}
	}
}