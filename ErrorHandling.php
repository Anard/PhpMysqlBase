<?php
require_once ('Generic.php');

// ---------- BASE Error's CLASS -----------
class ERR extends ExtdEnum
{
	// initial
	const __default = self::OK;
	const OK =		false;
	const KO =		true;
		
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

	// Print error
	// For each case, return false to continue writing errors or true to stop printing
	public static function print_error ($error, $rplmtStr = '', $data = []) {
		switch ($error) {
			case self::UNKNOWN:
				echo '<h3 class="alert">';
				echo self::replaceFields($rplmtStr, $data);
				echo ' introuvable</h3>';
				return true;	// stop
								
			case self::OK: break;
			default:
				echo '<h3 class="alert">Erreur(s) inconnue(s) <span class="reduit">('.error.')</span></h3>';
				break;
		}

		return false;
	}
}
?>
