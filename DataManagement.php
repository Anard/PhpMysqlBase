<?php
class DataManagement {
	//prevent instanciation
	function ___construct () { }
	
	// Static Methods
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
		return str_replace (' @ ', ' [AT] ', $mail);
	}
}
?>
