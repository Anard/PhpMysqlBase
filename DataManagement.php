<?php
date_default_timezone_set('Europe/Paris');

	// STATIC basic functions for Data Management
class DataManagement {
	//prevent instanciation
	function ___construct () { }
	
	// Static Methods
	// Affichage
	public static function secureText($texte) {
		return htmlentities($texte, ENT_QUOTES);
	}
	public static function afficheDate($date, $dest = 'print') {
		switch ($dest) {
			case 'form':	return $date->format('Y-m-d');
			case 'short':	return $date->format('j/m/y');
			default:		return $date->format('j/m/Y');
		}
	}
	public static function afficheMail($mail) {
		return str_replace ('@', ' [AT] ', $mail);
	}

	public static function randomColor() {
		$color = "";
		$characters = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'a', 'b', 'c', 'd', 'e', 'f');
		
		for ($i=0; $i<6; $i++) {
			$color .= $characters[array_rand($characters)];
		}
		
		return '#'.$color;
	}
	
	// Mise en forme des données pour enregistrement vers la BDD
	public static function formatDate ($date) {
		if (is_object($date)) {
			if ($date->formt('Y') < 2018) return "";
			return $date->format ('Y-m-d');
		}
		echo 'DEBUG ::: Date '.$date.' is not Object<br />';
		if (preg_match ('#^([\d]{1,2})\/([\d]{1,2})\/([\d]{2,4})$#', $date) == 1)
			$date = preg_replace ('#^([\d]{1,2})\/([\d]{2})\/([\d]{2,4})$#', '$3-$2-$1', $date);
		if (intval(date('Y', strtotime($date)) < 2018)) return "";
		return date('Y-m-d', strtotime($date));
	}
	public static function formatTime ($time) {
		return date('His', strtotime($time));
	}
	public static function removeSpaces ($data) {
		return preg_replace ('#\s#', '', $data);
	}
}
?>
