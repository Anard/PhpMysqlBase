<?php
require_once ('ErrorHandling.php');
abstract class ECHEC extends ExtdEnum {
	const __default = self::NONE;
	const NONE =	0;
	const COOKIE =	1;
	const PASSWD =	2;	
}

// COOKIES
abstract class TICKET extends ExtdEnum {
	// Session's ticket
	const SESSION = [	'name' =>	'passwd'
					]; 
	// Cookie's ticket
	const COOKIE = [	'name' =>	'crud',
						'expire' =>	(20 * 60), // 20 min
						'size' =>	10	// pass lenght
					];
}

abstract class PREFS extends ExtdEnum {
	// Apercu bb code à la frappe
	const APERCU_BB = [	'name' =>		'bbLive',
						'expire' =>		(24 * 3600), // 1 day
						'default' =>	true
					]; 
}

interface Session {
	// CONSTANTS
	// Bannisseement
	const COOKIESTOBAN = 2;
	const PASSWDSTOBAN = 5;
	// Redirections habitelles
	const ADMIN_HOME = 'Administration/Users.php';
	const HOME = 'Musee/';
	// Fichiers considérés trop vieux
	const DELAY_OLDFILE = 1800; // 1800s = 30min
// Sel de chiffrage
//const SALT = 'bf156è§4563ko%2545FTYfecqnibh54fvdwbhgyiG';


	// METHODS
	// Set defaults and start session
	function __construct();
	
	// Static methods (accessible witout instanciation)
	public static function generatePasswd($size = self::DEFAULT_COOKSIZE);
	// Return true if admin
	public static function isAdmin();
	// Return session id
	public static function getSessId();
	// Get last login by user
	public static function getLastLog ($userid = 0);
	// Update preferences
	public static function updateCookies();
	
	// Instance methods
	// Try to login (send connect form)
	public function startSession();
	// Called when loading page to check if logged
	// fullCheck = true if in front of page (false for local divs)
	public function checkSession ($fullCheck = false);
	// LogOut
	public function logout($target = self::HOME);
	// Print session error
	public function print_error ();
	
	// Unban an IP
	public function freeIp($db, $ip, $type);
}

final class SessionManagement implements Session
{
	
	// Properties
	//immutable
	private $Table; 
	//mutable
	private $Error = ERR::__default;
	
	// Constructor
	function __construct () {
		if (session_status() !== PHP_SESSION_ACTIVE)
			session_start();
		
		if (isset($_GET['error']) && ERR::hasKey($_GET['error']))
			$this->Error = $_GET['error'];
		
		include ('../Config/config.php');
		$this->Table = $prefixe.'echecs';

		return true;
	}
	
	// ------------ Default Session methods ---------------
	// Static methods
	// $size : longueur du mot passe voulue
	public static function generatePasswd($size = self::DEFAULT_COOKSIZE)
	{
	    $password = "";
	    // Initialisation des caractères utilisables
	    $characters = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "-", "_", "/", "#", "@");
	
	    for($i=0;$i<$size;$i++)
	    {
	        $password .= ($i%3) ? strtoupper($characters[array_rand($characters)]) : $characters[array_rand($characters)];
	    }
			
	    return $password;
	}

	// Return true if admin
	public static function isAdmin() {
		if (session_status() !== PHP_SESSION_ACTIVE) return false;
		else return ($_SESSION['Admin'] === true);	
	}
	
	// Return Session ID
	public static function getSessId() {
		if (session_status() !== PHP_SESSION_ACTIVE) return false;
		return ($_SESSION['ID']);	
	}
	
	// Get recorded errors
	public static function getLastLog ($user = '') {
		if (!self::isAdmin()) return NULL;
		include ('../Config/connexion.php');
		$Table = $prefixe.'echecs';
		$reponse = $bdd->prepare('SELECT * FROM '.$Table.' WHERE Last_Login = :login ORDER BY Heure DESC LIMIT 1');
		$reponse->bindParam('login', $user, PDO::PARAM_STR);
		$reponse->execute();
		$donnees = $reponse->fetch();
		
		$reponse->closeCursor();
		$bdd = NULL;
		return $donnees;
	}

	// Update preferences
	public static function updateCookies() {
		$cookies = PREFS::getConstList();
		foreach ($cookies as $cookie) {
			if (isset ($_POST[$cookie['name']]) && is_numeric($_POST[$cookie['name']]))
				setcookie ($cookie['name'], $_POST[$cookie['name']], $cookie['expire']); 	
		}
	}

	// Public
	// Try to start session
	public function startSession() {
		$target = self::ADMIN_HOME;
		// Check special target
		if (isset($_POST['goto'])) {
			
			if (preg_match('#^\/Administration\/((Users?([0-9]*))|(News([0-9]*))|(Asso(s)?([0-9]*)(-([0-9]*))?)|(Agenda([0-9]*))|(Agenda-([0-9]+))|(Galerie([0-9]*))|(Album([0-9]*))|(confirmDate-([0-9]+)))$#', $_POST['goto']) > 0)
				$target = $_POST['goto'];
		}
		
		// Try to connect
		if (isset($_POST['login']) && isset($_POST['pwd'])) {
			include ("../Config/connexion.php");
			// check if IP is banned
			$knownIP = $this->getIPFailures($bdd);
			if ($knownIP) {
				$last_record = time() - strtotime($knownIP['Heure']);
				if ($last_record > 86400) { // 24h
					// Init recorded Cookie & Passwd errors
					$reponse = $bdd->prepare('UPDATE '.$this->Table.' SET Cookie = 0, Passwd = 0 WHERE ip = :ip');
					$reponse->bindParam('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
					$reponse->execute();
					$reponse->closeCursor();	
			    }
			    elseif (($knownIP['Cookie'] > self::COOKIESTOBAN) || ($knownIP['Passwd'] > self::PASSWDSTOBAN)) {
			    	// BANNED
			    	$bdd = NULL;
			    	session_destroy();
			    	if ($target != self::ADMIN_HOME)
			    		$this->jumpToTarget('Administration/index.php?error='.ERR::BANNED.'&goto='.$target);
					else $this->jumpToTarget('Administration/index.php?error='.ERR::BANNED);

			    	return false;
			    }
			}
				
			// Check user
			$user = $this->getUserInfo($bdd, $_POST['login']);
			if (!$user) {
				$bdd = NULL;
				session_destroy();
		    	if ($target != self::ADMIN_HOME)
		    		$this->jumpToTarget('Administration/index.php?error='.ERR::LOGIN.'&goto='.$target);
				else $this->jumpToTarget('Administration/index.php?error='.ERR::LOGIN);
				return false;
			}
			else {
				// Wrong passsword ==> Update banned status
				if (password_verify($_POST['pwd'], $user['Passwd']) !== true) {
					if ($knownIP) {
						$reponse = $bdd->prepare('UPDATE '.$this->Table.' SET Passwd = Passwd + 1, Total_Passwd = Total_Passwd + 1, Last_Login = :login WHERE ip = :ip');
					}
					else {
						$reponse = $bdd->prepare('INSERT INTO '.$this->Table.' (ip, Passwd, Total_Passwd, Last_Login) VALUES (:ip, 1, 1, :login)');
					}
					$reponse->bindParam('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
					$reponse->bindParam('login', $_POST['login'], PDO::PARAM_STR);
					$reponse->execute();
					$reponse->closeCursor();
					
					$bdd = NULL;
					session_destroy();
			    	if ($target != self::ADMIN_HOME)
			    		$this->jumpToTarget('Administration/index.php?error='.ERR::PASS.'&goto='.$target);
					else $this->jumpToTarget('Administration/index.php?error='.ERR::PASS);
					return false;
				}
				else {
					//session_start();
					// Pour chaque page on génère un ticket qui sera stocké en session et en cookie. La page suivante le contrôlera et en génèrera un nouveau.
					$this->generateTicket();
					$_SESSION['ID'] = $user['id'];
					$_SESSION['Login'] = $user['Login'];
					
					// update BDD
					if ($knownIP) {
						// La MAJ automatique de l'heure ne se fait que lors d'une modification effective. Si on n'avait pas d'erreur de password et qu'on a pas changé de login, elle ne sera pas faite. On impose donc la MAJ du timestamp.
						$reponse = $bdd->prepare('UPDATE '.$this->Table.' SET Passwd = 0, Last_Login = :login, Heure = NOW() WHERE ip = :ip');
					}
					else $reponse = $bdd->prepare('INSERT INTO '.$this->Table.' (ip, Last_Login) VALUES (:ip, :login)');
					
					$reponse->bindParam('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
					$reponse->bindParam('login', $_SESSION['Login'], PDO::PARAM_STR);
					$reponse->execute();
					$reponse->closeCursor();
					
					// Finally jump to defined target
					$bdd = NULL;
					$this->jumpToTarget($target);
				}
			}
			return true;
		}
		// Connect form not sent but session already set
		elseif (isset($_SESSION['passwd']))
			$this->jumpToTarget($target);
		// Connect form not sent, draw connect page
		else session_destroy();
		return true;
	}
	
	// default : check if cookie and password are set and equals
	// fullCheck ==> Full check of session (start of page) and update ticket if OK
	public function checkSession ($fullCheck = false) {
		if (!$this->_checkSession($fullCheck)) {
			$this->logout();
			return false;
		}
		return true;
	}

	private function _checkSession ($fullCheck = false) {
		// Session not started, redirect to login page with path to requeested page
		if ($fullCheck && !isset($_SESSION['Login'])) {
			$this->logout('Administration/index.php?goto='.$_SERVER['REQUEST_URI']);
			return true;
		}
		
		// Check existing Ticket
		if (!isset($_COOKIE[TICKET::COOKIE['name']]) || !isset($_SESSION[TICKET::SESSION['name']])) {
			return false;
		}
		
		if ($fullCheck) include ('../Config/connexion.php');
		// Check ticket and record error
		if ($_COOKIE[TICKET::COOKIE['name']] != $_SESSION[TICKET::SESSION['name']]) {
			if ($fullCheck) {
				$this->recordFailure($bdd);
				$bdd = NULL;
			}
			return false;
		}
		// return if simple check
		elseif (!$fullCheck) return true;
		
		// Generate new Ticket
		else $this->generateTicket();
		
		// Get logged user
		$userInfo = $this->getUserInfo($bdd);
		$bdd = NULL;
		
		// Check login match
		if (!$userInfo || ($userInfo['Login'] != $_SESSION['Login']))
			return false;
		else {
			// Nettoyage des fichiers temporaires
			$this->cleanTempDir();
			// MAJ des données au cas où elles avaient été modifiées en cours de session
			// À la connexion, on n'a d'ailleurs chargé que le login et l'id. Ici on charge tout le reste.
			if ($userInfo['Admin']) $_SESSION['Admin'] = true;
			else $_SESSION['Admin'] = false;
			$_SESSION['Mail'] = $userInfo['Mail'];
			$_SESSION['Couleur'] = $userInfo['Couleur'];
		}
		
		return true;
	}
			
	// LogOut
	public function logout($target = self::HOME) {
		session_unset();
		$cookies = PREFS::getConstList();
		array_push ($cookies, TICKET::COOKIE);
		foreach ($cookies as $cookie)
			setcookie($cookie['name'], '', 1);
		session_destroy();
		$this->jumpToTarget($target);
	}

	// Print error & return true if banned
	public function print_error () {
		switch ($this->Error) {
			case ERR::OK: break;
			case ERR::LOGIN:
				echo '<h3 class="alert">Membre inconnu</h3>';
    			break;
			case ERR::PASS:
				echo '<h3 class="alert">Mot de passe erroné</h3>';
    			break;
			case ERR::BANNED:
		    	echo '<h3 class="alert">Votre adresse IP est bannie</h3>';
		    	echo '<p class="alert">En raison d\'une activité suspecte, votre adresse a été bannie du serveur pour 24 heures. Revenez plus tard ou contactez l\'administrateur pour plus d\'informations.</p>';
		    	return true;
			case ERR::COOKIE:
		    	echo '<h3 class="alert">Merci d\'autoriser les "cookies" pour ce site</h3>';
		    	echo '<p class="alert">Les cookies enregistrent une partie de vos préférences de navigation sur votre ordinateur. Ils sont nécessaires au fonctionnement de cette application et n\'enregistrent aucune donnée personnelle.</p>';
		    	break;

			default:
				echo '<h3 class="alert">Erreur(s) inconnue(s) <span class="reduit">(';
				echo $this->Error;
				echo ')</span></h3>';
				break;
		}
		
		return false;
	}
	
	// Unban an IP
	public function freeIp($db, $ip, $type) {
		if (!self::isAdmin()) return 'rights';
		
		switch ($type) {
			case ECHEC::PASSWD:
				$free = $db->prepare('UPDATE '.$this->Table.' SET Passwd = 0 WHERE ip = :ip');
				break;
			case ECHEC::COOKIE:
				$free = $db->prepare('UPDATE '.$this->Table.' SET Cookie = 0 WHERE ip = :ip');
				break;
			default:
				return 'wrongtype';
		}
		$free->bindParam('ip', $ip, PDO::PARAM_STR);
		$free->execute();
		$free->closeCursor();
		
		$free = $db->prepare('SELECT * FROM '.$this->Table.' WHERE ip = :ip');
		$free->bindParam('ip', $ip, PDO::PARAM_STR);
		$free->execute();
		$new = $free->fetch();
		$free->closeCursor();
		
		if ($new['Cookie'] > self::COOKIESTOBAN) return 'cookie';
		elseif ($new['Passwd'] > self::PASSWDTOBAN) return 'password';
		else return 'ok';
	}
			
	// ----------- Internal Methods ------------
	// GETTERS
	// Jump with header redirect
	private function jumpToTarget ($target = self::HOME) {
		if (!headers_sent())
			header ('Location: ../'.$target);
		
		// SEE HOW TO JUMP WITHOUT HEADER FUNCTION
		else exit();
	}
	
	private function getUserInfo ($db, $Login='') {
		include ('../Config/config.php');
		$Table = $prefixe.'users';
		if ($Login != '') {
			$reponse = $db->prepare('SELECT * FROM '.$Table.' WHERE Login = :login');
			$reponse->bindParam('login', $Login, PDO::PARAM_STR);			
		}
		else {
			$reponse = $db->prepare('SELECT * FROM '.$Table.' WHERE id = :id');
			$reponse->bindParam('id', $_SESSION['ID'], PDO::PARAM_INT);
		}
		$reponse->execute();
		$donnees = $reponse->fetch();
		$reponse->closeCursor();
		
		return $donnees;
	}
	
	private function getIPFailures ($db) {
		$reponse = $db->prepare('SELECT * FROM '.$this->Table.' WHERE ip = :ip');
		$reponse->bindParam('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
		$reponse->execute();
		$data = $reponse->fetch();
		$reponse->closeCursor();
		
		return $data;
	}
	
	// SETTERS
	private function recordFailure ($db) {
		$knownIP = $this->getIPFailures($db);
		if ($knownIP) {
			$reponse = $db->prepare('UPDATE '.$this->Table.' SET Cookie = Cookie + 1, Total_Cookie = Total_Cookie + 1, Last_Login = :login WHERE ip = :ip');
		}
		else {
			$reponse = $db->prepare('INSERT INTO '.$this->Table.' (ip, Cookie, Total_Cookie, Last_Login) VALUES (:ip, 1, 1, :login)');
		}
		$reponse->bindParam('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
		$reponse->bindParam('login', $_SESSION['Login'], PDO::PARAM_STR);
		$reponse->execute();
		$reponse->closeCursor();
	
		$this->logout();
	}

	private function generateTicket () {
	    $ticket = self::generatePasswd(TICKET::COOKIE['size']).session_id().microtime();
	    $ticket = hash('sha512', $ticket);
	    $_SESSION[TICKET::SESSION['name']] = $ticket;
	    setcookie(TICKET::COOKIE['name'], $ticket, time()+TICKET::COOKIE['expire']);
	}
	
	private function cleanTempDir () {
		require_once ('../Admin/config.php');
		$now = time();
		if ($dossier = opendir (PATH_UPLOAD['tmp'])) {
			while (false !== ($file = readdir($dossier))) {
			    if ($file != '.' && $file != '..') {
			    	$date = filemtime (PATH_UPLOAD['tmp'].$file);
			    	if ($date < $now - self::DELAY_OLDFILE) {
			    		if (file_exists(PATH_UPLOAD['tmp'].$file)) unlink (PATH_UPLOAD['tmp'].$file);
			    	}
			    }
			}
			closedir($dossier);
		}
	}
}
?>