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
	const INVALID =	3;	// generic invalid (should not happen as normlly ever checked (see update & insert on isValidField))
	
	const NOTNUM =	10;
	const NOTMAIL =	11;
	const NOTTEL =	12;
	const NOTDATE =	13;
	const NOTHOUR =	14;
	const NOTLINK =	15;
	
	// required field
	const NEEDED =	20;
	const NEED1 =	21;	// 1 == true
	const NEED2 =	22;
	const NEED3 =	23;
	const NEED4 =	24;
	const NEED5 =	25;
	const NEED6 =	26;
	const NEED7 =	27;
	const NEED8 =	28;
	const NEED9 =	29;

		
	// other field errors
	const CORRESPWD =	30;
	const EXISTS =		31;
	const SENDMAIL =	32;
	
	// updating BDD
	const INSERT =	40;
	const UPDATE =	41;
	const DELETE =	42;
	
	// session
	const LOGIN =	50;
	const PASS =	51;
	const BANNED =	52;

	// Remplacement de champs dans les textes
	// Syntaxe : [...] __Field-Name|Default-Value__ [...]
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
	public static function print_errors ($Errors, $data = []) {
		foreach ($Errors as $name => $errors) {
			//if (!is_array($errors)) $errors[0] = $errors;
			foreach ($errors as $error) {
				switch ($error) {
					case self::UNKNOWN:
	   					echo '<h3 class="alert">';
	   					echo self::replaceFields($name, $data);
	   					echo ' introuvable</h3>';
	   					return false;
	   				case self::ACCESS:
	   					echo '<h3 class="alert">Vous n\'avez pas les droits requis pour accéder à cette ressource</h3>';
	   					return false;
	   					
					case self::NOTNUM:
	   					echo '<h3 class="alert">Le champ ';
	   					echo self::replaceFields($name, $data);
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
	   					echo '<p class="alert">Le lien foourni est invalide</p>';
						break;

	   				case self::CORRESPWD:
	   					echo '<h3 class="alert">Les mots de passe ne correspondent pas</h3>';
	   					break;
	   				case self::EXISTS:
	   					echo '<h3 class="alert">Le champ ';
	   					echo self::replaceFields($name, $data);
	   					echo ' est déjà utilisé avec cette valeur.</h3>';
	   					break;
	    			case self::SENDMAIL:
	   					echo '<h3 class="alert">L\'envoi du message a échoué en raison d\'une erreur du serveur.</h3>';
	   					include ('../Config/headers.php');
	   					echo '<p class="centered">Merci de bien vouloir <a href="mailto:'.$emailasso.'">contacter l\'administrateur</a>.</p>';
	   					break;
	  					
					case self::INSERT:
	   					echo '<h3 class="alert">Une erreur est survenue lors de la création de ';
	   					echo self::replaceFields(strtolower($name), $data);
	   					echo '</span></h3>';
	   					break;
	   				case self::UPDATE:
	   					echo '<h3 class="alert">Une erreur est survenue lors de la mise à jour de ';
	   					echo self::replaceFields(strtolower($name), $data);
	   					echo '</span></h3>';
	   					break;
	  					case self::DELETE:
	   					echo '<h3 class="alert">Une erreur est survenue lors de la suppression de ';
	   					echo self::replaceFields(strtolower($name), $data);
	   					echo '</span></h3>';
	   					break;
	   				
					case self::NEEDED:
					case self::NEED1:	// true == 1
	   					echo '<h3 class="alert">Le champ ';
	   					echo self::replaceFields($name, $data);
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
	   					echo self::replaceFields($name, $data);
	   					echo '</h3>';
	   					break;
	   				
					case self::OK: break;					
					default:
	   					echo '<h3 class="alert">Erreur(s) inconnue(s) <span class="reduit">(';
	   					echo $error;
	   					echo ')</span></h3>';
	   					break;
				}
			}
		}
	}
}
?>