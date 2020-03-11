<?php
require_once ('ErrorHandling.php');
require_once ('Field.php');
	
// INTERFACE for Session Management
interface Session {
	// CONSTANTS
	// Bannisseement
	const COOKIESTOBAN = 2;
	const PASSWDSTOBAN = 5;
	// Redirections habitelles
	const ADMIN_HOME = 'Users.php';
	const HOME = '../Musee/';
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
	public function print_errors();
	// Try to login (send connect form)
	public function startSession();
	// Called when loading page to check if logged
	// fullCheck = true if in front of page (false for local divs)
	public function checkSession ($fullCheck = false);
	// LogOut
	public function logout($target = self::HOME);
	// Unban an IP
	public function freeIp($db, $ip, $type);
}

// ERRORS
class SESS_ERR extends ERR {
	// session
	const UNKNOWN =	10;
	const PASS =	11;
	const EXPIRE =	12;
	const BANNED =	20;
	const COOKIE =	21;
	
	// Print error
	public static function print_error ($error, $rplmtStr = 'Utilisateur', $data = []) {
		switch ($error) {
			case self::UNKNOWN:
				echo '<h3 class="alert">Membre inconnu</h3>';
				break;
			case self::PASS:
				echo '<h3 class="alert">Mot de passe erroné</h3>';
				break;
			case self::EXPIRE:
				echo '<h3 class="alert">Votre session a expiré</h3>';
				echo '<p class="alert">Meci de bien vouloir vous reconnecter.</p>';
				break;
			case self::BANNED:
				echo '<h3 class="alert">Votre adresse IP est bannie</h3>';
				echo '<p class="alert">En raison d\'une activité suspecte, votre adresse a été bannie du serveur pour 24 heures. Revenez plus tard ou contactez l\'administrateur pour plus d\'informations.</p>';
				return true;
			case self::COOKIE:
				echo '<h3 class="alert">Merci d\'autoriser les "cookies" pour ce site</h3>';
				echo '<p class="alert">Les cookies enregistrent une partie de vos préférences de navigation sur votre ordinateur. Ils sont nécessaires au fonctionnement de cette application et n\'enregistrent aucune donnée personnelle. Ils seront immédiatement supprimés lors de votre déconnexion</p>';
				break;
				
			default:
				if (parent::print_error ($error, $rplmtStr, $data) !== false) return true;
				else break;
		}
		return false;
	}
}

// ECHECS
class ECHEC extends ExtdEnum {
	const __default = self::NONE;
	const NONE =	0;
	const COOKIE =	1;
	const PASSWD =	2;	
}

// COOKIES
class TICKET extends ExtdEnum {
	// Session's ticket
	const SESSION = [	'name' =>	'passwd'
					]; 
	// Cookie's ticket
	const COOKIE = [	'name' =>	'crud',
						'expire' =>	(20 * 60), // 20 min
						'size' =>	10	// pass lenght
					];
}

// PREFERENCES
class PREFS extends ExtdEnum {
	// Apercu bb code à la frappe
	const APERCU_BB = [	'name' =>		'bbLive',
						'type' =>		TYPE::BOOL,
						'expire' =>		(24 * 3600), // 1 day
						'default' =>	true
					]; 
}
	
// --------- FINAL CLASS SessionManagement ----------- //
final class SessionManagement implements Session
{
	// Properties
	//immutable
	private $Table; 
	//mutable
	private $Errors = [];
	private $finalTarget;
	
	// Constructor
	function __construct () {
		if (session_status() !== PHP_SESSION_ACTIVE)
			session_start();
		
		if (isset($_GET['error']) && $_GET['error'] != "")
			array_push ($this->Errors, DataManagement::secureText($_GET['error']));
		if (isset($_GET['goto']) && $_GET['goto'] != "")
			$finalTarget = DataManagement::secureText($_GET['goto']);
		
		include ('../Config/config.php');
		$this->Table = $prefixe.'echecs';

		return true;
	}
	
	// ------------ Default Session methods ---------------
	// STATIC
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
	public static function isAdmin($userid = 0) {
		if (session_status() !== PHP_SESSION_ACTIVE || !is_numeric($userid)) return false;
		elseif ($userid == 0) return ($_SESSION['Admin'] === true);
		else {
			include ('../Config/connexion.php');
			$Table = $prefixe.'users';
			$reponse = $bdd->prepare ('SELECT Admin FROM '.$Table.' WHERE id = :userid');
			$reponse->bindParam ('userid', $userid, PDO::PARAM_INT);
			$reponse->execute();
			$donnees = $reponse->fetch();
			$reponse->closeCursor();
			$bdd = NULL;
			return ($donnees && $donnees['Admin']);
		}
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

	// Update Session's Login
	public static function updateLogin ($db) {
		$user = self::getUserInfo ($db);
		$_SESSION['Login'] = $user['Login'];
	}
	
	// Update preferences
	public static function updateCookies ($cookies = []) {
		foreach ($cookies as $cookie) {
			if (!PREFS::hasKey($cookie)) continue;
			$value = $cookie['default'];
			switch ($cookie['type']) {
				case TYPE::BOOL:	// checkboxes are sent only if checked, with value 'on'. Else value isn't sent in form
					if (isset($_POST[$cookie['name']]) && $_POST[$cookie['name']] == 'on')
						$value = 1;
					else $value = 0;
					break;
				
				case TYPE::NUM:
					if (!isset($_POST[$cookie['name']])) break;
					if (is_numeric($_POST[$cookie['name']]))
						$value = $_POST[$cookie['name']];
					break;
				
				default:
					if (!isset($_POST[$cookie['name']])) break;
					$value = DataManagement::seureText ($_POST[$cookie['name']]);
					break;

			}
			
			if ((isset ($_COOKIE[$cookie['name']]) && $_COOKIE[$cookie['name']] != $value) || $value != $cookie['default'])
				setcookie ($cookie['name'], $value, time()+$cookie['expire']);
		}
	}

	// PUBLIC
	public function print_errors() {
		foreach ($this->Errors as $error) {
			if (SESS_ERR::print_error ($error) !== false) return true;
		}
		return false;
	}
	
	// Try to start session
	public function startSession() {
		$target = self::ADMIN_HOME;
		// Check special target
		if (isset($_POST['goto'])) {
			
			if (preg_match('#^((Users?([0-9]*))|(News([0-9]*))|(Asso(s)?([0-9]*)(-([0-9]*))?)|(Agenda([0-9]*))|(Agenda-([0-9]+))|(Galerie([0-9]*))|(Album([0-9]*))|(confirmDate-([0-9]+))(\.(php|html?)))$#', $_POST['goto']) > 0)
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
			    		$this->jumpToTarget('index.php?error='.SESS_ERR::BANNED.'&goto='.$target);
					else $this->jumpToTarget('index.php?error='.SESS_ERR::BANNED);

			    	return false;
			    }
			}
				
			// Check user
			$user = self::getUserInfo($bdd, $_POST['login']);
			if (!$user) {
				$bdd = NULL;
				session_destroy();
		    	if ($target != self::ADMIN_HOME)
		    		$this->jumpToTarget('index.php?error='.SESS_ERR::UNKNOWN.'&goto='.$target);
				else $this->jumpToTarget('index.php?error='.SESS_ERR::UNKNOWN);
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
			    		$this->jumpToTarget('index.php?error='.SESS_ERR::PASS.'&goto='.$target);
					else $this->jumpToTarget('index.php?error='.SESS_ERR::PASS);
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
		else return true;
	}

	private function _checkSession ($fullCheck = false) {
		// Check existing Ticket
		if (!isset($_COOKIE[TICKET::COOKIE['name']]) || !isset($_SESSION[TICKET::SESSION['name']])) {
			$target = end (explode ('/', $_SERVER['REQUEST_URI']));
			if ($target != self::ADMIN_HOME)
				$this->logout('index.php?error='.SESS_ERR::EXPIRE.'&goto='.$target);
			else $this->logout('index.php?error='.SESS_ERR::EXPIRE);
			return true;
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
		$userInfo = self::getUserInfo($bdd);
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
		if (!headers_sent()) {
			if (substr($target, 0, 1) != '.') $target = '../Administration/'.$target;
			header ('Location: '.$target);
		}
		// SEE HOW TO JUMP WITHOUT HEADER FUNCTION
		else exit();
	}
	
	private static function getUserInfo ($db, $Login='') {
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
		require_once ('../ClassesBase/FileField.php');
		$now = time();
		if ($dossier = opendir (FileField::PATH_UPLOAD['tmp'])) {
			while (false !== ($file = readdir($dossier))) {
			    if ($file != '.' && $file != '..' && $file != 'index.php') {
			    	$date = filemtime (FileField::PATH_UPLOAD['tmp'].$file);
			    	if ($date < $now - FileField::DELAY_OLDFILE) {
			    		unlink (FileField::PATH_UPLOAD['tmp'].$file);
			    	}
			    }
			}
			closedir($dossier);
		}
	}
}
?>
