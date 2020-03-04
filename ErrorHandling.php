<?php
require_once ('Generic.php');

class ERR extends ExtdEnum
{
	// initial
	const __default = self::OK;
	const OK =		false;
	const KO =		true;
	
	// std errors
	const UNKNOWN =	1;
	const ACCESS =	2;
	
	// Remplacement de champs dans les textes
   	// Syntaxe : [...] __Field_Name|Default-Value__ [...]
	public static function replaceFields ($text, $data = []) {
		if (preg_match ('#__([\d\w_-]*)(\|[^_]*)?__#', $text, $fields) == 1) {
			array_shift($fields);
			
			foreach($fields as $field) {
				$pattern = "#__".$field."(\|([^_]*))?__#";
				if (!array_key_exists($field, $data) || $data[$field] == "")
					$text = preg_replace ($pattern, '$2', $text);
				else $text = preg_replace ($pattern, $data[$field], $text);
			}
		}
		
		return $text;
	}

	// Print errors
	public static function print_errors ($Error, $data = [], $rplmtStr = '') {
		switch ($Error) {
			case self::UNKNOWN:
				echo '<h3 class="alert">';
				echo self::replaceFields($rplmtStr, $data);
				echo ' introuvable</h3>';
				return true;	// stop
			case self::ACCESS:
				echo '<h3 class="alert">Vous n\'avez pas les droits requis pour accéder à cette ressource</h3>';
				return true;
								
			case self::OK: break;
			default:
				echo '<h3 class="alert">Erreur(s) inconnue(s) <span class="reduit">('.$Error.')</span></h3>';
				break;
		}

		return false;
	}
}
?>
