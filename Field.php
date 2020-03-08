<?php
require_once ('Generic.php');

// INTERFACE for SQL Fields
interface FieldInterface {
	// PUBLIC
	public function print_errors($data = []);
	// Récupération d'une donnée d'un type précis (BDD ou entrée utilisateur)
	public function secure_data ($data, $record = false);
	// Contrôle de la validité d'un champ, return error
	public function isValidValue ($value);
	// Validate posted value to recording (requested value or default if error recorded)
	public function validate_data ($value);
}

// ERRORS
class FIELD_ERR extends ERR {
	// std errors
	const UNKNOWN =	10;

	// type errors
	const NOTNUM =	20;
	const NOTMAIL =	21;
	const NOTTEL =	22;
	const NOTDATE =	23;
	const NOTHOUR =	24;
	const NOTLINK =	25;
	const NOTCOLOR = 26;
	
	// required field errors
	const NEEDED =	30;
	const NEED1 =	31;	// 1 == true
	const NEED2 =	32;
	const NEED3 =	33;
	const NEED4 =	34;
	const NEED5 =	35;
	const NEED6 =	36;
	const NEED7 =	37;
	const NEED8 =	38;
	const NEED9 =	39;
		
	// other field errors
	const CORRESPWD =	40;
	const EXISTS =		41;
	
	// Print error
	public static function print_error ($error, $rplmtStr = '', $data = []) {
		switch ($error) {
			case self::NOTNUM:
				echo '<h3 class="alert">Le champ ';
				echo self::replaceFields($rplmtStr, $data);
				echo ' doit être numérique</h3>';
				break;
			case self::NOTMAIL:
				echo '<h3 class="alert">Votre adresse mail est invalide</h3>';
				break;
			case self::NOTTEL:
				echo '<h3 class="alert">Votre numéro de téléphone est invalide</h3>';
				echo '<p class="alert">Un numéro de téléphone doit comporter dix chiffres, séparés éventuellement par des espaces.</p>';
				break;
			case self::NOTDATE:
				echo '<h3 class="alert">Date invalide</h3>';
				echo '<p class="alert">Merci de renseigner la date au format jj/mm/aaaa</p>';
				break;
			case self::NOTHOUR:
				echo '<h3 class="alert">Heure invalide</h3>';
				echo '<p class="alert">Merci de renseigner l\'heure au format HH:MM</p>';
				break;
			case self::NOTLINK:
				echo '<h3 class="alert">Lien invalide</h3>';
				echo '<p class="alert">Le lien fourni est invalide</p>';
				break;
			
			case self::NEEDED:
			case self::NEED1:	// true == 1
				echo '<h3 class="alert">Le champ ';
				echo self::replaceFields($rplmtStr, $data);
				echo ' est requis</h3>';
				break;
			case self::NEED2:
			case self::NEED3:
			case self::NEED4:
			case self::NEED5:
			case self::NEED6:
			case self::NEED7:
			case self::NEED8:
			case self::NEED9:
				echo '<h3 class="alert">Veillez renseigner au moins un des champs ';
				echo self::replaceFields($rplmtStr, $data);
				echo '</h3>';
				break;
				
			case self::CORRESPWD:
				echo '<h3 class="alert">Les mots de passe ne correspondent pas</h3>';
				break;
			case self::EXISTS:
				echo '<h3 class="alert">Le champ ';
				echo self::replaceFields($rplmtStr, $data);
				echo ' est déjà utilisé avec cette valeur.</h3>';
				break;
				
			default:
				return (parent::print_error ($error, $data, $rplmtStr));
		}
		
		return false;
	}
}
	
// Mysql Data TYPES
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
class Field implements FieldInterface
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
		$this->Default = $this->secure_data ($default);
	}
	
	// PUBLIC
	public function print_errors($data = []) {
		foreach ($this->Errors as $error) {
			if (FIELD_ERR::print_error($error, $this->Name, $data) !== false) return true;
		}
		return false;
	}
	
	// Récupération d'une donnée d'un type précis (BDD ou entrée utilisateur)
	public function secure_data ($data, $record = false) {
		switch ($this->Type) {
			case TYPE::BOOL:
				return ($data ? true : false);
			case TYPE::ID:
			case TYPE::PARENT:
			case TYPE::NUM:
				return intval($data);

			case TYPE::DATE:
				if (preg_match ('#^[0-9]+$#', $data) == 1)
					return new DateTime (strtotime($data));
				else return DateTime::createFromFormat ('Y-m-d', $data);
				//return date('j/m/Y', strtotime($data));
			case TYPE::HOUR:
				return date('H:m', strtotime($data));
			
			case TYPE::COLOR:
				return preg_replace('#\s#', '', DataManagement::secureText($data));
			case TYPE::FILE: if (is_array($data)) break;	// files' $value is an array [ 'tmp_name', 'name', 'error', etc ] only when posted
															// it's text when from DB
				
			default:
				if ($record) return $data;
				else return DataManagement::secureText($data);
		}
	}
	
	// Contrôle de la validité d'un champ, return error
	public function isValidValue ($value) {
		switch ($this->Type) {
			// ID and parent's types have to be ever checked, need access to db
			case TYPE::ID:
			case TYPE::PARENT:	return FIELD_ERR::KO;
				
			case TYPE::NUM:		return (is_numeric($value) ? FIELD_ERR::OK : FIELD_ERR::NOTNUM);
			case TYPE::MAIL:	return (filter_var($value, FILTER_VALIDATE_EMAIL) ? FIELD_ERR::OK : FIELD_ERR::NOTMAIL);
			case TYPE::TEL:		return ((preg_match ('#^[0-9]{10}$#', preg_replace ('#\s#', '', $value)) == 1) ? FIELD_ERR::OK : FIELD_ERR::NOTTEL);
			case TYPE::DATE: //return ((preg_match ('#^[0-9]{2,4}-[0-9]{2}-[0-9]{2}$#', $value) == 1) ? FIELD_ERR::OK : FIELD_ERR::DATE);
								return ($value !== false ? FIELD_ERR::OK : FIELD_ERR::NOTDATE);
			case TYPE::HOUR:	return ((preg_match ('#^([0-1][0-9])|(2[0-3]):[0-5][0-9](:[0-5][0-9])?$#',$value) == 1) ? FIELD_ERR::OK : FIELD_ERR::HOUR);
			case TYPE::LINK:	return (filter_var($value, FILTER_VALIDATE_URL) ? FIELD_ERR::OK : FIELD_ERR::NOTLINK);
			case TYPE::COLOR:	return ((preg_match ('/^(#[0-9a-fA-F]{6})|(rgb\((\s*[01]?[0-9]?[0-9]|2[0-4][0-9]|25[0-5]\s*,){2}\s*[01]?[0-9]?[0-9]|2[0-4][0-9]|25[0-5]\s*\))$/',$value) == 1) ? FIELD_ERR::OK : FIELD_ERR::NOTCOLOR);
			
			// FileField override this function
			case TYPE::FILE:	return FIELD_ERR::KO;
			default:			return FIELD_ERR::OK;
		}
	}
	
	// Validate posted value to recording (requested value or default if error recorded)
	public function validate_data ($value) {
		$nbErrors = sizeof ($this->Errors);
		switch ($this->Type) {
			case TYPE::FILE:
				if ($nbErrors == 0) return 'UPLOAD'; // just to give a value, FileField->upload will finally upload right one on last moment
				break;

			case TYPE::ID:
			case TYPE::PARENT:
				$nbErrors = 0; // and continue (return formatData)
			default:
				if ($nbErrors > 0 && ($nbErrors > 1 || $this->Errors[0] != FIELD_ERR::NEEDED))
					break;
				else return $this->formatData($value);
				break;
		}
	
		return $this->Default;
	}
	
	private function formatData ($data) {
		switch ($this->Type) {
			case TYPE::ID:
			case TYPE::PARENT:
			case TYPE::NUM:			return intval ($data);

			case TYPE::DATE:		return DataManagement::formatDate($data);
			case TYPE::HOUR:		return DataManagement::formatTime($data);
			case TYPE::BOOL:		return ($data ? 1 : 0);
			case TYPE::COLOR:		return DataManagement::removeSpaces($data);
			case TYPE::PASSWD:		if ($data != "") // do not hash empty value, which won't be empty any more !
										return password_hash ($data, PASSWORD_DEFAULT);
									else return "";
			default: return $data;
		}
	}
}
?>
