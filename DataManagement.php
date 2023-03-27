<?php
date_default_timezone_set('Europe/Paris');

// STATIC basic functions for Data Management
interface DataManagement_Interface {
	// Affichage
	public static function secureText($texte);
	public static function decodeText($original);
	/* Mise en forme avec retours à la ligne en supprimant simplement le bbCode */
	public static function simplifyText($original);
	public static function afficheDate($date, $dest = 'print');
	public static function afficheHour($hour, $dest = 'print');
	public static function afficheMail($mail);
	public static function randomColor();
	// Mise en forme des données pour enregistrement vers la BDD
	public static function formatDate ($date);
	// Mise en forme d'une url de la BDD
	public static function formatURL($type, $nom, $id1, $id2);
	// get int value from hour
	public static function timetoint($time);
	// Return true id date is a Sunday
	public static function isSunday($date);
}

class DataManagement implements DataManagement_Interface {
	//prevent instanciation
	function __construct () { }
	
	// Static Methods
	// Affichage
	public static function secureText($texte) {
		return htmlentities($texte, ENT_QUOTES);
	}
	/* Mise en forme d'un long texte avec retours la ligne et bbCode */
	public static function decodeText($original) {
		$corrige = nl2br($original);
		$corrige = self::bbCode($corrige, 0);

		return $corrige;
	}
	/* Mise en forme avec retours à la ligne en supprimant simplement le bbCode */
	public static function simplifyText($original) {
		$corrige = nl2br($original);
		$corrige = self::bbCode($corrige, 1);
		
		return $corrige;
	}
	
	public static function afficheDate($date, $dest = 'print') {
		switch ($dest) {
			case 'form':	return $date->format('Y-m-d');
			case 'short':	return $date->format('j/m/y');
			default:		return $date->format('j/m/Y');
		}
	}
	public static function afficheHour($hour, $dest = 'print') {
		$time = strtotime($hour);
		switch ($dest) {
			case 'form':
			case 'schema':	return date('H:i', $time);//return $H.':'.$M;
			default:
				if (date('i', $time) > 0)
					return date('G\hi', $time);
				else return date('G\h', $time);
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
			if ($date->format('Y') < 2018) return "";
			return $date->format ('Y-m-d');
		}
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
	
	// Mise en forme d'une url de la BDD
	public static function formatURL($type, $nom, $id1, $id2) {
		/* url : on met le nom puis le numéro d'asso puis le numero de chapitre
			Le nom ne sert à rien, c'est juste esthétique */
			
		$nom = preg_replace ('#[^A-Za-z-_]#', '', $nom);
		switch ($type) {
			case 'asso':
						// Rewrite mod écrit index.php?asso=`id_asso`&chap=`id_chap`
				$url = '../Animations/'.$nom.$id1.'-'.$id2;
				break;
				
			default: break;
		}
		
		return $url;
	}
	
	// get int value from hour
	public static function timetoint($time) {
		return (preg_replace ('#[a-zA-Z:]#', '', $time));
	}
	// Return true id date is a Sunday
	public static function isSunday($date) {
		if (!is_object($date)) return (date('w', strtotime($date)) == 0);
		return ($date->format('w') == 0);
	}


		
	// PRIVATE
	/* bbCode :	· [H]...[/H]	: Titre
				· [b]...[/b]	: gras
				· [i]...[/i]	: italique
				· [u]...[/u]	: souligné
				· ...[P]...	: nouveau paragraphe
			$remove=1 pour retirer simplement toutes les balises
	*/
	private static function bbCode($original, $remove) {

		switch ($remove) {
		  case 1:
			// Gras, souligné, italique ([b|u|i]...[b|u|i])
			$corrige = preg_replace ('#\[\/?b\]#iU','',$original);
			$corrige = preg_replace ('#\[\/?i\]#iU','',$corrige);
			$corrige = preg_replace ('#\[\/?u\]#iU','',$corrige);
			
			// Titres
			$corrige = preg_replace ('#((<br ?\/?>)|\n|\r)*\[H\]((\n|\r|.)*)\[\/H\]#iU',' ',$corrige);
			
			// URLs
			$corrige = preg_replace ('#\[url=("|\')?([^\'"]*)("|\')?\](.+)?\[\/url\]#iU', '$4',$corrige);
		
			// Paragraphes (...[p]...)
			$corrige = preg_replace('#((<br ?\/?>)|\n|\r)+#i', ' ', $corrige);
			$corrige = preg_replace('#\[p\]#i', ' ', $corrige);
				
			// Retirer les retours inutiles et les paragraphes vides
			$corrige = preg_replace('# +#i', ' ', $corrige);
		
			// Retirer les balises non-fermées
			$corrige = preg_replace('#(\[\/?[^\]]*\]?)#i','',$corrige);
			break;

		  default:
			// Gras, souligné, italique ([b|u|i]...[b|u|i])
			$corrige = preg_replace ('#\[b\]((\n|\r|.)*)\[\/b\]#iU','<b>$1</b>',$original);
			$corrige = preg_replace ('#\[i\]((\n|\r|.)*)\[\/i\]#iU','<i>$1</i>',$corrige);
			$corrige = preg_replace ('#\[u\]((\n|\r|.)*)\[\/u\]#iU','<u>$1</u>',$corrige);
			
			// Titres
			$corrige = preg_replace ('#((<br ?\/?>)|\n|\r)*\[H\]((\n|\r|.)*)\[\/H\]#iU','</p><h4>$3</h4><p>',$corrige);
			
			// URLs
			$corrige = preg_replace ('#\[url=("|\')?([^\'"]*)("|\')?\](.+)\[\/url\]#iU', '<a href="$2">$4</a>',$corrige);
			$corrige = preg_replace ('#\[url=("|\')?([^\'"]*)("|\')?\]\[\/url\]#iU', '<a href="$2">$2</a>',$corrige);
		
			// Paragraphes (...[p]...)
			$corrige = preg_replace('#((<br ?\/?>)|\n|\r)*\[p\]#i', '</p><p>', $corrige);
				
			// Encapsuler le tout dans un paragraphe
			$corrige = '<p>'.$corrige.'</p>';
			
			// Retirer les retours inutiles et les paragraphes vides
			$corrige = preg_replace('#<p><\/p>#i', '', $corrige);
			$corrige = preg_replace('#<p>((<br ?\/?>)|\n|\r)*#i', '<p>', $corrige);
		
			// Retirer les balises non-fermées
			$corrige = preg_replace('#(\[\/?[^\]]*\]?)#i','',$corrige);
			break;
		}
		
		return $corrige;
	}


}
?>
