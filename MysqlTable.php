<?php
function array_splice_by_key (&$array, $key, $length='ALL', $replacement=array()) {
	if (!is_array($array)) return false;
	if ($length == 'ALL') $length = count($array);
	if (!is_numeric($length)) return false;

	$pos = array_search ($key, array_keys($array), true);
	if ($pos === false) return $array;
	
	return array_splice ($array, $pos, $length, $replacement);
}

// BASE CLASS FOR MYSQL TABLES, have to be extended at least by BASE_ExtendTable
require_once ('Session.php');
require_once ('../Fonctions/texts.php');

// ERRORS
abstract class SQL_ERR extends ERR {
	const INVALID =	-1;	// generic invalid (should not happen as normally ever checked (see update & insert on isValidField))
	
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
	
	// Print errors
	public static function print_errors ($Errors, $data = []) {
		foreach ($Errors as $name => $errors) {
			foreach ($errors as $error) {
				switch ($error) {
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
	   					
					default:
						$Error = 	[	'name' => $name,
										'type' => $error
									];
	   					if (parent::print_errors ($Error, $data) !== false) return true;
	   					else break;
				}
			}
		}
		
		return false;
	}
}


// ENUMS
// Mysql data types
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
// Rights on tables
class AUTHORISED extends ExtdEnum
{
	const __default = self::ALL;
	const ALL =		-1;	// no restriction
	const ADMIN =	0;	// admin only
	const MMM = 	1;	// authorised on main asso
	const SELF =	2;	// only self (users)
	const ASSO =	10;	// in autorised table asso
	const NEWS =	11;	// "		"		" news
	const PARENT =	20;	// inherit rights from parent

	// define authorisation tables (reserved)
	const _TABLE =	[	self::MMM =>	'assos_authorised',
						self::ASSO =>	'assos_authorised',
						self::NEWS =>	'news_authorised'
					];
	const _ITEM =	[	self::MMM =>	'id_asso',
						self::ASSO =>	'id_asso',
						self::NEWS =>	'id_news'
					];
}
// Read/Write access
class ACCESS extends ExtdEnum {
	const __default = self::READ;
	const READ =	0;
	const WRITE =	1;
}
// Get values
class GET extends ExtdEnum {
	// default is ID of wanted data
	// * value will get all in Sql requests when applied to fields
	const __default = self::ALL;
	const ALL =		'*';
	const LIST =	-1;
}

// Fields
class Field
{
	public $Type = TYPE::__default;
	public $Name = ''; // nom qui apparaîtra dans les erreurs
	public $Required = false;	// true, false or number if one of corresponding number is required
	public $Unique = false;
	public $Errors = array();
	
	function __construct ($type = TYPE::__default, $name = '', $required = false, $unique = false) {
		if (is_bool($required) || is_numeric($required))
			$this->Required = $required;
		else return false;
		if (is_bool($unique))
			$this->Unique = $unique;
		else return false;
		$this->Type = TYPE::getKey($type);
		$this->Name = $name;
		
		return true;
	}
}

// ----------- GLOBAL Table INTERFACE to implement in child classes ------------
interface Table {
	// Set defaults and table
	// Retourne SQL_ERR::RIGHTS si on n'a pas les droits sur l'idLoad
	function __construct();
	
	//  Static methods
	public static function randomColor();
	
	// GETTERS
	public function print_errors();
	// Get table short name
	public function getTableName();
	// Get defaults
	public function getDefaults($field = GET::ALL);
	// Get current ID
	public function getIdLoad();
	// Return true if default access is write access
	public function isAdmin();
	// Check if authorized
	public function rights_control ($read_write = NULL, $id = 0, $userid = 0);
	// Search if data exists from DB
	public function is_data ($id = 0);
	// Return data array from DB, $read_write n'est utilisé que pour GET::LIST pour obtenir la list des entréees autorisées
	public function get_data ($get = GET::__default, $fields = GET::ALL, $read_write = NULL);
	
	// Admin list
	// Check if authorised on multiple entries
	public function need_list ();
	
	// SETTERS
	public function send_form (); // values are from form's POST variables
}

// UI interface
interface UI_Table {
	// Draw specific form's fieldset (innerHTML, could be a div or anything else)
	public static function draw_fieldset ($action, $data, $table);
	
	// Draw admin list
	public static function draw_list ($list, $deploy = true, $deleteButtons = false);
}

// ---------- GLOBAL BASE CLASS ------------
abstract class MysqlTable implements Table
{
	// Constants
	const DEFAULT_ACCESS = NULL;
	
	// Properties
	protected $Table;			// name of Mysql base table with prefix
	protected $Parent = NULL;	// Parent de la classe finalle éventuel
	protected $parentItem; 		// nom de l'id du parent dans la BDD
	protected $rights = [	ACCESS::READ => AUTHORISED::__default,
							ACCESS::WRITE => AUTHORISED::__default
						];
	protected $Fields = array();	// list of fields
	protected $bdd;					// bdd
	protected $default_access;		// default ACCESS value
	// readable au niveau supérieur (getter)
	protected $table;				// name of Mysql base table
	protected $idLoad;				// Page loaded id
	protected $Defaults;			// defaults fields values
	private $Ordering;				// default ordering of data
	private $Limiting;				// default exclusion when getting data
	
	
	// Constructor => inherit : lien veers la table héritée)
	function _constructInit ($table, $ordering = "", $limiting = "") {
		if ($this->Parent == NULL) {
			$connecting = 'insideClass';
			include ('../Config/connexion.php');
		}
		else {
			$this->bdd = $this->Parent->bdd;
			include ('../Config/config.php');
		}
		
		// Init consts
		$this->table = $table;
		$this->Table = $prefixe.$table;
		// Init fields
		$reponse = $this->bdd->query('SHOW COLUMNS FROM '.$this->Table);
		while ($donnees = $reponse->fetch()) {
			$this->Fields[$donnees['Field']] = new Field();
		}
		$reponse->closeCursor();
		if (sizeof($this->Fields) == 0) {
			if (!headers_sent()) header ('HTTPS/1.1 501 Not Implemented');
			return false;
		}
		
		$this->Ordering = $ordering;
		$this->Limiting = $limiting;
		return true;
	}
	function _constructExit ($read_write = ACCESS::__default, $loadGetVar = 'idload') {
		if (!array_key_exists('id', $this->Fields)) return SQL_ERR::KO;
		$this->default_access = ACCESS::getKey($read_write);
		$this->idLoad = $this->Defaults['id'];
		foreach ($this->Fields as $field => $content) {
			if ($content->Type == TYPE::PARENT) {
				$this->parentItem = $field;
				break;
			}
		}
		
		if (isset($_GET[$loadGetVar]))
			return $this->load_id ($_GET[$loadGetVar], $this->default_access);
		else return SQL_ERR::OK;
	}

	// Destructor
	function __destruct () {
		$this->bdd = NULL;
	}
	
	// Static methods
	public static function randomColor() {
		$color = "";
		$characters = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'a', 'b', 'c', 'd', 'e', 'f');
		
		for ($i=0; $i<6; $i++) {
			$color .= $characters[array_rand($characters)];
		}
		
		return '#'.$color;
	}

	
	// ------------ Default Table methods ---------------
	// GETTERS
	public function print_errors() {
		foreach ($this->Fields as $content) {
			if (SQL_ERR::print_errors ([ $content->Name => $content->Errors ], $this->get_data($this->idLoad) !== false)
				return true;
		}
		
		return false;
	}
	
	// Get table short name
	public function getTableName() {
		return $this->table;
	}

	// Get defaults
	public function getDefaults($field = GET::ALL) {
		if ($field == GET::ALL)
			return $this->Defaults;
		elseif (isset($this->Defaults[$field]))
			return $this->Defaults[$field];
		else return NULL;
	}

	// Get current ID
	public function getIdLoad() {
		return $this->idLoad;
	}
	
	// Is default access ACCESS::WRITE ?
	public function isAdmin() {
		return ($this->default_access == ACCESS::WRITE);	
	}
	
	// Check if authorized
	private function _rights_control_basics ($read_write = NULL) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return false;
		if ($this->rights[$read_write] == AUTHORISED::ALL) return true;
		if (SessionManagement::isAdmin()) return true;
		if ($this->rights[$read_write] == AUTHORISED::PARENT)
			return $this->Parent->rights_control($read_write);
		if ($this->rights[$read_write] == AUTHORISED::MMM)
			return $this->_rights_control($read_write, 1, $userid);
		return 0; // still unknown
	}
	private function _rights_control ($read_write = NULL, $id = 0, $userid = 0) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return false;
		if ($userid == 0) $userid = SessionManagement::getSessId();
		if (!is_numeric($id)) $id = 0;
		if (!$this->is_data($id)) $id = 0;	// do not block if id doesn't exist
		if ($id == 0) {
			if ($this->rights[$read_write] == AUTHORISED::SELF)	return false;
			else return true;
		}

		include ('../Config/config.php');
		switch ($this->rights[$read_write]) {
			case AUTHORISED::SELF:
				if ($id != SessionManagement::getSessId()) return false;
				$test = $this->bdd->prepare('SELECT id FROM '.$this->Table.' WHERE id = :userid');
				break;
			case AUTHORISED::MMM:
			case AUTHORISED::ASSO:
			case AUTHORISED::NEWS:
				if ($this->rights[$read_write] == AUTHORISED::MMM)
					$id = 1;
				$Table = $prefixe.AUTHORISED::_TABLE[$this->rights[$read_write]];
				$item = AUTHORISED::_ITEM[$this->rights[$read_write]];
				$test = $this->bdd->prepare('SELECT * FROM '.$Table.' WHERE '.$item.' = :itemid AND id_user = :userid');
				break;

			default:
				return false;
		}
		if ($this->rights[$read_write] != AUTHORISED::SELF)
			$test->bindParam('itemid', $id, PDO::PARAM_INT);
		$test->bindParam('userid', $userid, PDO::PARAM_INT);
		$test->execute();
		$ret = ($test->fetch() ? true : false);
		$test->closeCursor();
		return $ret;
	}
	// Contrôle des droits sur une entrée, push array & return true or false
	public function rights_control ($read_write = NULL, $id = 0, $userid = 0) {
		if (is_null($read_write)) $read_write = $this->default_access;
		$ret = $this->_rights_control_basics ($read_write);
		if (!is_bool($ret)) $ret = $this->_rights_control ($read_write, $id, $userid);
		return $ret; 
	}
	
	// Search if data exists from DB
	public function is_data ($id = 0) {
		$reponse = $this->bdd->prepare('SELECT id FROM '.$this->Table.' WHERE id = :id');
		$reponse->bindParam('id', $id, PDO::PARAM_INT);
		$reponse->execute();
		$donnees = $reponse->fetch();
		$reponse->closeCursor();
		return ($donnees) ? true : false;
	}

	// Return data array from DB
	public function get_data ($get = GET::__default, $fields = GET::ALL, $read_write = NULL) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return NULL;
		if (!GET::hasKey($get) && !is_numeric($get)) $get = GET::__default;
		switch ($get) {
			case GET::ALL:
				if (!$this->rights_control($read_write)) return NULL;
				else break;
			case GET::LIST:
				if (!$this->need_list($read_write)) return NULL;
				else break;
			default:
				if (!$this->rights_control($read_write, $get)) return NULL;
				else break;
		}

		if ($fields != GET::ALL) {
			if (is_array($fields)) {
				$arrayFields = $fields;
				foreach ($fields as $key => $field) {
					if (!array_key_exists($field, $this->Fields))
						array_splice ($fields, $key, 1);	
				}
				if (sizeof($fields) == 0) return NULL;
				else $fields = implode (', ', $fields);
			}
			elseif (!array_key_exists($fields, $this->Fields)) return NULL;
		}

		$limit = "";
		switch ($get) {
			// Get list
			case GET::LIST:
				// If Parent is defined, LIST is Parent's full list
				if ($this->Parent !== NULL) {
					if ($this->Limiting != "") $limit = ' AND '.$this->Limiting;
					$reponse = $this->bdd->prepare('SELECT '.$fields.' FROM '.$this->Table.' WHERE '.$this->parentItem.' = :parent'.$limit.$this->getOrdering());
					$reponse->bindParam('parent', $this->Parent->idLoad, PDO::PARAM_INT);
					$reponse->execute();
					$donnees = $reponse->fetchAll();
					$reponse->closeCursor();
						
					foreach ($donnees as &$data)
						$data = $this->secure_data($data);
					unset($data); // break the reference with the last element
					if ($donnees) {
						// append additionnal informations
						$nombre = sizeof($donnees);
						$donnees['listData'] = array(
							'table' => $this->table,
							'parentTable' => $this->Parent->table,
							'currentId' => $this->idLoad,
							'parentId' => $this->Parent->idLoad,
							'nombre' => $nombre,
						);
					}
					return $donnees;
				}
		
				// Authorised list
				$total = 0;
				if (SessionManagement::isAdmin()) {
					if ($this->Limiting != "") $limit = ' WHERE '.$this->Limiting;
					$reponse = $this->bdd->prepare ('SELECT '.$fields.' FROM '.$this->Table.$limit.$this->getOrdering().' LIMIT :nombre OFFSET :debut');
				}
				
				else {
					switch ($this->rights[$read_write]) {
						case AUTHORISED::MMM:
						case AUTHORISED::ASSO:
						case AUTHORISED::NEWS:
							$userid = SessionManagement::getSessId();
							include ('../Config/config.php');
							$Table = $prefixe.AUTHORISED::_TABLE[$this->rights[$read_write]];
							$item = AUTHORISED::_ITEM[$this->rights[$read_write]];
							if ($this->rights[$read_write] == AUTHORISED::MMM)
								$reponse = $this->bdd->prepare ('SELECT '.$item.' FROM '.$Table.' WHERE id_user = :userid AND '.$item.' = 1');
							else $reponse = $this->bdd->prepare ('SELECT '.$item.' FROM '.$Table.' WHERE id_user = :userid');
							$reponse->bindParam('userid', $userid, PDO::PARAM_INT);
							$reponse->execute();
							$donnees = $reponse->fetchAll();
							if (!$donnees) return NULL;
							$ids = [];
							foreach ($donnees as $donnee)
								array_push ($ids, $donnee[$item]);
							if (sizeof($ids) == 0) return NULL;
							
							if ($this->Limiting != "") $limit = ' AND '.$this->Limiting;
							$reponse = $this->bdd->prepare ('SELECT '.$fields.' FROM '.$this->Table.' WHERE id REGEXP :ids'.$limit.$this->getOrdering().' LIMIT :nombre OFFSET :debut');
							$reponse->bindValue('ids', '^('.implode('|', $ids).')$');
							break;
												
						case AUTHORISED::ALL:
							if ($this->Limiting != "") $limit = ' WHERE '.$this->Limiting;
							$reponse = $this->bdd->prepare ('SELECT '.$fields.' FROM '.$this->Table.$limit.$this->getOrdering().' LIMIT :nombre OFFSET :debut');
							break;
											
						// Find all authorised entries
						default:
							if ($this->Limiting != "") $limit = ' WHERE '.$this->Limiting;
							$reponse = $this->bdd->query ('SELECT id FROM '.$this->Table.$limit);
							$ids = [];
							while ($donnees = $reponse->fetch()) {
								if ($this->rights_control($read_write, $donnees['id']))
									array_push ($ids, $donnees['id']);
							}
							$reponse->closeCursor();
							if (sizeof($ids) == 0) return NULL;
							
							if ($this->Limiting != "") $limit = ' AND '.$this->Limiting;
							$reponse = $this->bdd->prepare ('SELECT '.$fields.' FROM '.$this->Table.' WHERE id REGEXP :ids'.$limit.$this->getOrdering().' LIMIT :nombre OFFSET :debut');
							$reponse->bindValue('ids', '^('.implode('|', $ids).')$');
							break;
					}
				}
				
				// Réduction éventuelle de la page
				$first = 0;
				$nombre = UI_MysqlTable::DefaultListLength;
				if (isset($_GET['deb']) && is_numeric ($_GET['deb'])) { $first=intval($_GET['deb']); }
				if (isset($_GET['nbr']) && is_numeric ($_GET['nbr'])) { $nombre=intval($_GET['nbr']); }
				if ($first < 0) { $first = 0; }
				if ($nombre < 0) { $nombre = self::DefaultListLength; }
				if ($first > $nombre) { $prec = $first-$nombre; }
				else { $prec = 0; }
				$suiv = $first+$nombre;
				
				$reponse->bindParam('nombre', $nombre, PDO::PARAM_INT);
				$reponse->bindParam('debut', $first, PDO::PARAM_INT);
				$reponse->execute();
				$donnees = $reponse->fetchAll();
				$reponse->closeCursor();
				foreach ($donnees as &$data)
					$data = $this->secure_data($data);
				unset($data); // break the reference with the last element
				if ($donnees) {
					// append additionnal informations
					$donnees['listData'] = array(
						'table' => $this->table,
						'listName' => $this->Fields['id']->Name,
						'defaultId' => $this->Defaults['id'],
						'currentId' => $this->idLoad,
						'first' => $first,
						'nombre' => $nombre,
						'prec' => $prec,
						'suiv' => $suiv
					);
				}
				
				return $donnees;
		
			// Get all
			case GET::ALL:
				if (!is_numeric($get)) {
					if (!$this->rights_control($read_write)) return NULL;
					if ($this->Limiting != "") $limit = ' WHERE '.$this->Limiting;
					$reponse = $this->bdd->query('SELECT '.$fields.' FROM '.$this->Table.$limit.$this->getOrdering());
					$donnees = $reponse->fetchAll();
					$reponse->closeCursor();
					foreach ($donnees as &$data)
						$data = $this->secure_data($data);
					unset($value); // break the reference with the last element
					break;
				}

			// Get one ID
			default:
				if (!$this->rights_control($read_write, $get)) return NULL;
				if (!$this->is_data($get)) {
					if ($fields == GET::ALL) $donnees = $this->Defaults;
					elseif (isset($arrayFields)) {
						foreach ($this->Defaults as $field => $value) {
							if (in_array($field, $arrayFields))
								array_push ($donnees, [$field => $value]);
						}
						
					}
					elseif (array_key_exists($fields, $this->Fields))
						$donnees = $this->Defaults[$fields];
					else return NULL;
					// if get isn't null and Parent exists, set parent id if known
					if ($this->Parent !== NULL) {
						if (array_key_exists($this->parentItem, $donnees))
							$donnees[$this->parentItem] = $this->Parent->idLoad;
					}
				}
				else {
					if ($this->Limiting != "") $limit = ' AND '.$this->Limiting;
					$reponse = $this->bdd->prepare('SELECT '.$fields.' FROM '.$this->Table.' WHERE id = :id'.$limit.$this->getOrdering());
					$reponse->bindParam('id', $get, PDO::PARAM_INT);
					$reponse->execute();
					$donnees = $reponse->fetch();
					$reponse->closeCursor();
				}
				if ($donnees) {
					$donnees = $this->secure_data($donnees);
					// tranform array $donnees in single value;
					if ($fields != GET::ALL && !is_array($fields)) $donnees = $donnees[$fields];
				}
				break;
		}
		
		return $donnees;
	}
	
	// Admin list
	// Check if authorised on multiple entries
	public function need_list ($read_write = NULL) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return false;
		if ($this->Parent !== NULL)
			return ($this->rights_control ($read_write));
		
		$request = $this->bdd->query('SELECT id FROM '.$this->Table);
		$request->execute();
		$ret = false;
		while ($donnees = $request->fetch()) {
			if ($this->rights_control ($read_write, $donnees['id'])) {
				$ret = true;
				break;
			}
		}
		$request->closeCursor();
		return $ret;
	}
	
	// SETTERS		
	// only final function is public (need at least to check 'action' first)
	protected function _send_form ($postedValues) {
		// record settings
		SessionManagement::updateCookies();
		
		// prepare data
		$validatedValues = $this->_validate_posted_data($postedValues);
		// post
		return $this->_record_changes ($validatedValues);
	}
	
	// ----------- Internal Methods ------------
	// GETTERS
	// Get Order defaults
	protected function getOrdering() {
		if ($this->Ordering == '') return '';
		else return ' ORDER BY '.$this->Ordering;
	}

	// Recherche d'une autre entrée avec la même valeur sur un champ
	protected function isUnique ($field, $value) {
		if (!array_key_exists($field, $this->Fields)) return true;
		
		// exclude posted value
		if (isset($_POST['id']) && $this->is_data($_POST['id']))
			$exclude = $this->get_data ($_POST['id'], $field);
			
		// search value
		$reponse = $this->bdd->prepare('SELECT id, '.$field.' FROM '.$this->Table.' WHERE '.$field.' = :value');
		switch ($this->Fields[$field]->Type) {
			case TYPE::ID:
			case TYPE::PARENT:
			case TYPE::NUM:
				$reponse->bindParam('value', $value, PDO::PARAM_INT);
				break;
			default:
				$reponse->bindParam('value', $value, PDO::PARAM_STR);
				break;
		}
		$reponse->execute();
		$data = $reponse->fetch();
		$reponse->closeCursor();
		
		// found value ?
		if ($data && (!isset($exclude) || $data[$field] != $exclude)) return false;
		else return true;
	}

	// Contrôle de la validité d'un champ, return error
	protected function isValidValue ($field, $value) {
		if (!array_key_exists($field, $this->Fields)) return SQL_ERR::KO;
		
		if ($this->Fields[$field]->Unique && !$this->isUnique($field, $value)) return SQL_ERR::EXISTS;

		switch ($this->Fields[$field]->Type) {
			case TYPE::ID:
				return ($this->is_data($value) ? SQL_ERR::OK : SQL_ERR::UNKNOWN);
			case TYPE::PARENT:
				return ($this->Parent->is_data($value) ? SQL_ERR::OK : SQL_ERR::UNKNOWN);				
			case TYPE::NUM: return (is_numeric($value) ? SQL_ERR::OK : SQL_ERR::NOTNUM);
			case TYPE::MAIL: return (filter_var($value, FILTER_VALIDATE_EMAIL) ? SQL_ERR::OK : SQL_ERR::NOTMAIL);
			case TYPE::TEL: return ((preg_match ('#^[0-9]{10}$#', preg_replace ('#\s#', '', $value)) == 1) ? SQL_ERR::OK : SQL_ERR::NOTTEL);
			case TYPE::DATE: return ((preg_match ('#^[0-9]{2,4}-[0-9]{2}-[0-9]{2}$#',$value) == 1) ? SQL_ERR::OK : SQL_ERR::DATE);
			case TYPE::HOUR: return ((preg_match ('#^([0-1][0-9])|(2[0-3]):[0-5][0-9](:[0-5][0-9])?$#',$value) == 1) ? SQL_ERR::OK : SQL_ERR::HOUR);
			case TYPE::LINK: return (filter_var($value, FILTER_VALIDATE_URL) ? SQL_ERR::OK : SQL_ERR::NOTLINK);
			default: return SQL_ERR::OK;
		}
	}
	
	// check also if required, don't return error (boolean)
	protected function isValidField ($field, $value) {
		if (!array_key_exists($field, $this->Fields)) return false;

		switch ($this->Fields[$field]->Required) {
			case false: return ($value == "" || $this->isValidValue ($field, $value) === SQL_ERR::OK);
			case true: return ($value != "" && $this->isValidValue ($field, $value) === SQL_ERR::OK);
			
			default:
				if ($value != "") return ($this->isValidValue ($field, $value) === SQL_ERR::OK);
				else return $this->Fields[$field]->Required;
		}
	}
	
	// Sécurisation des données issues de la BDD
	protected function secure_data ($data) {
		foreach ($data as $key => &$value) {
			if (!array_key_exists($key, $this->Fields)) continue;
			switch ($this->Fields[$key]->Type) {
				case TYPE::ID:
				case TYPE::PARENT:
				case TYPE::NUM:
					break;
					
				case TYPE::DATE:
					$value = afficheDate($value);
					break;
				case TYPE::HOUR:
					$value = afficheTime($value);
					break;
					
				case TYPE::BOOL:
					$value = ($value ? true : false);
					break;
					
				default:
					$value = secureText($value);
					break;	
			}
		}
		unset($value); // break the reference with the last element
		
		return $data;
	}
			
	// SETTERS
	// Définition des types
	protected function set_field ($field, $type, $name = '', $required = false, $unique = false) {
		if (!array_key_exists($field, $this->Fields)) return false;

		if ($type == TYPE::ID || $type == TYPE::PARENT) $required = true;
		
		return ($this->Fields[$field] = new Field($type, $name, $required, $unique));
	}

	// Validate posted data and push errors
	protected function _validate_posted_data ($postedValues) {
		foreach ($postedValues as $field => $value) {
			if (!array_key_exists($field, $this->Fields)) continue;
			
			// search errors
			$err = (($value != "") ? $this->isValidValue($field, $value) : SQL_ERR::OK);
			if ($err && ($this->Fields[$field]->Type != TYPE::ID || $value > 0))
				array_push($this->Fields[$field]->Errors, $err);
			
			// is required and set without error ?
			switch ($this->Fields[$field]->Required) {
				case false: break;
				case true:
					switch ($this->Fields[$field]->Type) {
						case TYPE::ID: case TYPE::PARENT: break;
						case TYPE::NUM:
							if ($value == 0 || sizeof($this->Fields[$field]->Errors) > 0)
								array_push($this->Fields[$field]->Errors, SQL_ERR::NEEDED);
							break;
						default:
							if ($value == "" || sizeof($this->Fields[$field]->Errors) > 0)
								array_push($this->Fields[$field]->Errors, SQL_ERR::NEEDED);
							break;
					}
					break;
				default:
					$numId = $this->Fields[$field]->Required;
					if (isset ($isSet[$numId]) && $isSet[$numId] === true) break;
					else {
						switch ($this->Fields[$field]->Type) {
							case TYPE::NUM:
								$isSet[$numId] = ($value > 0 && !isset($this->Fields[$field]->Errors)); break;
							default:
								$isSet[$numId] = ($value != "" && !isset($this->Fields[$field]->Errors)); break;
						}
					}
					break;
			}
			
			// search for multiple values unset
			if (isset ($isSet)) {
				foreach ($isSet as $couple => $result) {
					if (!$result)
						array_push ($this->Fields['id']->Errors, (SQL_ERR::NEEDED + $couple));
				}
			}
		}
		
		// create return array
		$validValues = [];
		foreach ($postedValues as $field => $value) {
			if (!array_key_exists ($field, $this->Fields)) continue;
			$nbErrors = sizeof ($this->Fields[$field]->Errors);
			switch ($this->Fields[$field]->Type) {
				case TYPE::ID: 
				case TYPE::PARENT:
					$validValues[$field] = $value;
					break;
					
				case TYPE::PASSWD:
					if ($nbErrors > 0 && ($nbErrors > 1 || $this->Fields[$field]->Errors[0] != SQL_ERR::NEEDED))
						$validValues[$field] = $this->Defaults[$field];
					else $validValues[$field] = password_hash ($value, PASSWORD_DEFAULT);
					break;
								
				default:
					if ($nbErrors > 0 && ($nbErrors > 1 || $this->Fields[$field]->Errors[0] != SQL_ERR::NEEDED))
						$validValues[$field] = $this->Defaults[$field];
					else $validValues[$field] = $value;
					break;
			}
		}
		
		return $validValues;
	}
	
	protected function _record_changes ($validatedValues) {
		// Insert
		if ($validatedValues['id'] == 0) {
			foreach ($validatedValues as $field => $value) {
				if (!array_key_exists($field, $this->Fields)) continue;
				if (sizeof($this->Fields[$field]->Errors) > 0) return false;
			}
	
			$ret = $this->insert_data ($validatedValues);
			if (is_numeric ($ret) && $ret > 0) $this->load_id ($ret, ACCESS::WRITE);
			return $ret;
		}

		elseif (sizeof($this->Fields['id']->Errors) == 0) {
			// Update
			if (sizeof($this->Fields['id']->Errors) > 0) return false;
			$ret = true;
			foreach ($validatedValues as $field => $value) {
				if (sizeof($this->Fields[$field]->Errors) == 0 && $value != $this->get_data($validatedValues['id'], $field)) {
					$ret = ($this->update_field ($validatedValues['id'], $field, $validatedValues[$field]) ? $ret : false);
				}
			}
			if ($ret) $this->load_id ($validatedValues['id']);
			return $ret;	
		}
		
		return false;
	}

	// Update DB Functions : necessary contrlos, but not on Errors, which should be set and checked first
	// Delete entry in DB (directly called from child's send_form function, after checking action)
	protected function delete_entry ($id) {
		if (!$this->rights_control(ACCESS::WRITE, $id)) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::ACCESS);
			return false;
		}
		if (!$this->is_data($id)) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::UNKNOWN);
			return false;
		}
		
		$reponse = $this->bdd->prepare('DELETE FROM '.$this->Table.' WHERE id = :id');
		$reponse->bindParam('id', $id, PDO::PARAM_INT);
		$ret = ($reponse->execute());
		$reponse->closeCursor();
		
		if (!$ret) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::DELETE);
			return $ret;
		}

		// Now delete data in authorised table
		switch ($this->rights[ACCESS::WRITE]) {
			case AUTHORISED::ASSO:
			case AUTHORISED::NEWS:
				include ('../Config/config.php');
				$Table = $prefixe.AUTHORISED::_TABLE[$this->rights[ACCESS::WRITE]];
				$item = AUTHORISED::_ITEM[$this->rights[ACCESS::WRITE]];
				$userid = SessionManagement::getSessId();
				$reponse = $this->bdd->prepare('DELETE FROM '.$Table.' WHERE '.$item.' = :itemid AND id_user = :userid');
				$reponse->bindParam('itemid', $id, PDO::PARAM_INT);
				$reponse->bindParam('userid', $userid, PDO::PARAM_INT);
				$ret = $reponse->execute();
				$reponse->closeCursor();
				break;
			default: break;
		}
		return $ret;
	}
	
	// Update field in DB
	protected function update_field ($id, $field, $value) {
		if (!array_key_exists($field, $this->Fields)) return true;
		if (!$this->rights_control(ACCESS::WRITE, $id)) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::ACCESS);
			return false;
		}
		if (!$this->is_data($id)) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::UNKNOWN);
			return false;
		}
		if (!$this->isValidField($field, $value)) {
			array_push ($this->Fields[$field]->Errors, SQL_ERR::INVALID);
			return false;
		}
		
		$reponse = $this->bdd->prepare('UPDATE '.$this->Table.' SET '.$field.' = :value WHERE id = :id');
		$endValue = $this->format_data($field, $value);
		switch ($this->Fields[$field]->Type) {
			case TYPE::ID:
			case TYPE::PARENT:
			case TYPE::NUM:
				$reponse->bindParam('value', $endValue, PDO::PARAM_INT); break;
			default: $reponse->bindParam('value', $endValue, PDO::PARAM_STR); break;
		}
		$reponse->bindParam('id', $id, PDO::PARAM_INT);
		$ret = ($reponse->execute());
		$reponse->closeCursor();
		
		if (!$ret) array_push ($this->Fields[$field]->Errors, SQL_ERR::UPDATE);
		return $ret;
	}

	// Private
	// Insert full entry in DB, return false or ID of created entry
	private function insert_data ($fields) {
		if (!$this->rights_control(ACCESS::WRITE, 0, SessionManagement::getSessId())) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::ACCESS);
			return false;
		}

		$echoValues = [];
		foreach ($fields as $field => $value) {
			if (!array_key_exists($field, $this->Fields) || $this->Fields[$field]->Type == TYPE::ID) {
				array_splice_by_key ($fields, $field, 1);
				continue;
			}
			if (!$this->isValidField($field, $value)) {
				array_push ($this->Fields[$field]->Errors, SQL_ERR::INVALID);		
				return false;
			}
			$echoValues[$field] = ':'.strtolower($field);
		}
	
		$fullFields = implode(', ', array_keys($fields));
		$fullValues = implode(', ', $echoValues);
		$req = 'INSERT INTO '.$this->Table.' ( '.$fullFields.' ) VALUES ( '.$fullValues.' )';
		$reponse = $this->bdd->prepare($req);
print_r($_POST); echo '<br ?>';
print_r($fields);
		foreach ($fields as $field => $value) {
			switch ($this->Fields[$field]->Type) {
				case TYPE::NUM:
					$reponse->bindParam(strtolower($field), $value, PDO::PARAM_INT);
					break;	
				default:
					$reponse->bindValue(strtolower($field), $this->format_data($field, $value));
					break;
			}
		}
		$ret = ($reponse->execute());
		$reponse->closeCursor();
		if (!$ret) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::INSERT);
			return false;
		}
		
		$reponse = $this->bdd->query('SELECT id FROM '.$this->Table.' ORDER BY id DESC LIMIT 1');
		$donnees = $reponse->fetch();
		$reponse->closeCursor();
		
		if (!$donnees) {
			array_push ($this->Fields['id']->Errors, SQL_ERR::INSERT);
			return false;
		}
		// Now insert data in authorised table
		switch ($this->rights[ACCESS::WRITE]) {
			case AUTHORISED::ASSO:
			case AUTHORISED::NEWS:
				include ('../Config/config.php');
				$Table = $prefixe.AUTHORISED::_TABLE[$this->rights[ACCESS::WRITE]];
				$item = AUTHORISED::_ITEM[$this->rights[ACCESS::WRITE]];
				$userid = SessionManagement::getSessId();
				$reponse = $this->bdd->prepare('INSERT INTO '.$Table.' ('.$item.', id_user) VALUES (:itemid, :userid)');
				$reponse->bindParam('itemid', $donnees['id'], PDO::PARAM_INT);
				$reponse->bindParam('userid', $userid, PDO::PARAM_INT);
				$ret = ($reponse->execute());
				$reponse->closeCursor();
				if (!$ret) array_push ($this->Fields['id']->Errors, SQL_ERR::INSERT);
				break;
			default: break;
		}

		return $donnees['id'];
}
	
	// Mise en forme des données pour enregistrement dans la BDD
	private function format_data ($field, $data) {
		if (array_key_exists($field, $this->Fields)) $type = $this->Fields[$field]->Type;
		elseif (TYPE::hasKey($field)) $type = $field;
		else return NULL;
		
		switch ($type) {
			case TYPE::DATE: return formatDate($data);
			case TYPE::HOUR: return formatTime($data);
			case TYPE::BOOL: return ($data ? 1 : 0);
			default: return $data;	
		}
	}
	
	// try to page load an id
	private function load_id ($loadid = NULL, $read_write = NULL) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return SQL_ERR::ACCESS;
		// If Parent is defined, loadid = NULL if working on parent, loadid = 0 if working on child, these 2 values are valid even if no data
		if ($this->Parent !== NULL && ($loadid === NULL || $loadid == 0))
			$this->idLoad = $loadid;
		
		elseif ($loadid && is_numeric($loadid) && $loadid > 0) {
			if ($this->is_data($loadid)) {
				if ($this->rights_control($read_write, $loadid))
					$this->idLoad = $loadid;
				else {
					if (!headers_sent()) header ('HTTP/1.1 401 Unauthorized');
					array_push ($this->Fields['id']->Errors, SQL_ERR::ACCESS);
					return SQL_ERR::ACCESS;
				}
			}
		}

		if ($this->Parent === NULL || !is_numeric($this->idLoad) || $this->idLoad == 0) return SQL_ERR::OK;
		// Load parent
		return $this->Parent->load_id($this->get_data($this->idLoad, $this->parentItem, $read_write), $read_write);
		
	}
}

// ---------- GLOBAL BASE UI CLASS ------------
abstract class UI_MysqlTable implements UI_Table
{
	// Constants
	const DefaultListLength = 10;	// longueur des listes admin
	
	// Properties
	public static $choixListe = [10, 15, 20, 30];	// choix nb entrées par liste

	// Draw Delete form
	//protected static function _draw_delete_form ($action, $data, $table) {
	protected static function _draw_delete_form ($action, $data, $table, $texte) {
		do {
			if (next ($data) === false) break;
		} while (is_numeric (key($data)) || is_numeric(current($data)));
		$mainField = key($data);
		reset ($data);
		
		//$scriptsuppr = 'confirmsuppr('.$data['id'].', "'.$table.'", "'.$data[$mainField].'");';
		$scriptsuppr = 'confirmsuppr('.$data['id'].', "'.$table.'", "'.$texte.'");';
		
		?>
		<form class="imgButton" method="post" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" id="suppr<?php echo $table.$data['id']; ?>">
			<input type="hidden" name="action" value="<?php echo $action; ?>" />
			<input type="hidden" name="id" value="<?php echo $data['id']; ?>" />
		</form>
		<img class="imgButton" title="Supprimer <?php echo $data[$mainField]; ?>" alt="-" src="../Styles/remove.png" onmousedown="this.src='../Styles/remove-clic.png';" onmouseup="this.src='../Styles/remove.png';" onmouseout="this.src='../Styles/remove.png';" onclick='<?php echo $scriptsuppr; ?>' />
		<?php 	}

	// Admin List
	protected static function _draw_list_header ($listData, $listSize, $deploy = true) {
		$listName = $listData['listName'];
		if (substr ($listName, -1) != 's') $listName .= 's';
		
		// deploy link
		echo '<a href="'.substr($_SERVER['REQUEST_URI'], 0, strripos($_SERVER['REQUEST_URI'], '?')).'?deployside=';
		echo !$deploy;
		echo '" onclick="showHideSide(\'list\'); return false;"><img src="../Commun/';
		if ($deploy == 1) { echo 'retour.png" alt="Retour" title="Fermer"'; }
		else { echo 'liste.png" alt="Liste" title="Liste des "'.$listName.'"'; }
		echo ' /></a>';
		?>
		
		<div>
			<?php
			echo '<h3>'.$listName;
			if ($listSize > 1) {
				echo ' <span class="reduit">('.$listData['first'];
				if ($listData['nombre'] > 1) echo '-'.min($listSize, $listData['suiv']);
				if ($listSize > $listData['nombre']) echo ' / '.$listSize;
				echo ')</span>';
			}
			echo '</h3>';
	}
	
	protected static function _draw_list_block ($supprAction, $list, $deleteButtons = false) {
		foreach ($list as $donnees) {
			if ($donnees == $list['listData']) continue;
			do {
				if (next ($donnees) === false) break;
			} while (is_numeric (key($donnees)));
			$mainField = key ($donnees);
			reset ($donnees);
			break;
		}
		$table = $list['listData']['table'];
		$idLoad = $list['listData']['currentId'];
		
		?><ul><?php
		foreach ($list as $donnees) {
			if ($donnees == $list['listData']) continue;
			echo '<li id="entry'.$donnees['id'].'"';
			if ($idLoad == $donnees['id'])
				echo ' class="currentEntry"';
			echo '>';
			if (substr($_SERVER['SCRIPT_NAME'], -5, 1) == 's')
				$dest = substr($_SERVER['SCRIPT_NAME'], 0, -5);
			else $dest = substr($_SERVER['SCRIPT_NAME'], 0, -4);
			$dest .= $donnees['id']; 

			if ($deleteButtons)
				self::_draw_delete_form($supprAction, $donnees, $table);
			
			echo '<a onclick="loadContent('.$donnees['id'].', \''.$table.'\'); return false;" ';
			echo 'href="'.$dest.'">'.$donnees[$mainField].'</a>';
			echo '</li>';					
		} ?></ul><?php
		return SQL_ERR::OK;
	}
	
	protected static function _draw_list_nav ($listData, $listSize, $deploy = true) {
		// Navigation
		echo '<div class="navEntries">';
			// Base pour l'url dure
			if (strpos ($_SERVER['REQUEST_URI'], '_') !== false) {
				$urlbase = substr($_SERVER['REQUEST_URI'],0,strripos($_SERVER['REQUEST_URI'],'_'));
			}
			elseif (strpos ($_SERVER['REQUEST_URI'], '.') != false) {
				$urlbase = substr($_SERVER['REQUEST_URI'],0,strripos($_SERVER['REQUEST_URI'],'.'));	
			}
			else $urlbase = $_SERVER['REQUEST_URI'];
				
				
			// Base pour l'url JavaScript
			switch ($_SERVER['SCRIPT_NAME']) {
				case 'news': $listbase = '../Musee/news'; break;
				default: $listbase = '../Administration/'.$_SERVER['SCRIPT_NAME']; break;
			}

			// Réduction/Augmentation de la page
			if ($listSize >= self::$choixListe[1]) {
				echo 'Voir ';
				
				$count = 0; // sert seulement à placer le tiret entre les choix
				for ($i = 0; $i < sizeof(self::$choixListe) && $listSize >= self::$choixListe[$i]; $i++) {
					$complement = '_'.$listData['first'].'-'.self::$choixListe[$i];
					
					if ($listData['nombre'] != self::$choixListe[$i]) {
						if ($count > 0) echo ' - ';
						$count++;
						// lien JS
						$url = $listbase.$complement;
						echo '<a onclick="load(\''.$url.'\',\''.$listData['table'].'\'); return false;" ';
						// lien dur
						$url = $urlbase.$complement.'?deployside='.$deploy;
						echo 'href="'.$url.'">';
						echo self::$choixListe[$i].'</a>';
					}
				}
				echo ' par page';
			}
				
			// Flèches de navigation (précédent)
			echo '<span style="float: left; ';
			if ($listData['first'] == 0) echo 'visibility: hidden;';	
			echo '">';
				// First
				$complement = '_0-'.$listData['nombre'];
				// lien JS
				$url = $listbase.$complement;
				echo '<a title="Première page" ';
				if ($prec == 0) echo 'style="visibility: hidden;" ';
				echo 'onclick="load(\''.$url.'\',\''.$_SERVER['SCRIPT_NAME'].'\'); return false;" ';
				// lien dur
				$url = $urlbase.$complement.'?deployside='.$deploy;
				echo 'href="'.$url.'">';
				echo '<img src="../Styles/first1.png" alt="<<" /></a>&nbsp;';
				
				// Prec
				$complement = '_'.$listData['prec'].'-'.$listData['nombre'];
				// lien JS
				$url = $listbase.$complement;
				echo '<a title="Page précédente" ';
				echo 'onclick="load(\''.$url.'\',\''.$_SERVER['SCRIPT_NAME'].'\'); return false;" ';
				// lien dur
				$url = $urlbase.$complement.'?deployside='.$deploy;
				echo 'href="'.$url.'">';
				echo '<img src="../Styles/prec.png" alt="<" /></a>';
			echo '</span>';

			// Flèches de navigation (suivant)
			echo '<span style="float: right;';
			if ($listSize <= $listData['suiv']) echo ' visibility: hidden;"';	
			echo '">';
				// Suiv
				$complement = '_'.$listData['suiv'].'-'.$listData['nombre'];
				// lien JS
				$url = $listbase.$complement;
				echo '<a title="Page suivante" ';
				echo 'onclick="load(\''.$url.'\',\''.$_SERVER['SCRIPT_NAME'].'\'); return false;" ';
				// lien dur
				$url = $urlbase.$complement.'?deployside='.$deploy;
				echo 'href="'.$url.'">';
				echo '<img src="../Styles/suiv.png" alt=">" /></a>&nbsp;';
				
				// Last
				$complement = '_'.($listSize-$listData['nombre']).'-'.$listData['nombre'];
				// lien JS
				$url = $listbase.$complement;
				echo '<a title="Dernière page" ';
				if ($listData['suiv'] >= ($listSize-$listData['nombre'])) echo 'style="visibility: hidden;" ';
				echo 'onclick="load(\''.$url.'\',\''.$_SERVER['SCRIPT_NAME'].'\'); return false;" ';
				// lien dur
				$url = $urlbase.$complement.'?deployside='.$deploy;
				echo 'href="'.$url.'">';
				echo '<img src="../Styles/last1.png" alt=">>" /></a>';
			echo '</span>';

		echo '</div>';
	?></div><?php // fin de la div ouverte dans le header
	}
}
?>
