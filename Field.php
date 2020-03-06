<?php
require_once ('Generic.php');

// Mysql data types
class TYPE extends ExtdEnum
{
	const __default = self::NONE;
	const NONE =	-1;
	const ID =		0;
	const PARENT =	1;
	const NUM =		2;
	const BOOL =	3;
	const TEXT =	4;
	
	const MAIL =	10;
	const TEL =		11;
	const DATE =	12;
	const HOUR =	13;
	
	const COLOR =	20;
	const LINK =	21;
	const PASSWD =	22;
	
	const FILE =	30;
}

// Fields
class Field
{
	public $Type = TYPE::__default;
	public $Name = ''; // nom qui apparaîtra dans les erreurs
	public $Required = false;	// true, false or number if one of corresponding number is required
	public $Unique = false;
	public $Errors = array();
	public $Default = NULL;
	public $value = NULL;		// value is loaded from DB
	
	function __construct ($type = TYPE::__default, $name = '', $default = NULL, $required = false, $unique = false) {
		$this->Type = TYPE::getKey($type);
		switch ($this->Type) {
			case TYPE::ID:
			case TYPE::PARENT:
				$required = true;
				
			default: break;
		}
		if (is_bool($required) || is_numeric($required))
			$this->Required = $required;
		else return NULL;
		if (is_bool($unique))
			$this->Unique = $unique;
		else return NULL;

		$this->Name = DataManagement::secureText($name);
		$this->Default = $default;
	}
}
?>
