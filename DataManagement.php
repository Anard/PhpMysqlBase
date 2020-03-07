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

	public static function randomColor() {
		$color = "";
		$characters = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'a', 'b', 'c', 'd', 'e', 'f');
		
		for ($i=0; $i<6; $i++) {
			$color .= $characters[array_rand($characters)];
		}
		
		return '#'.$color;
	}
}
?>
