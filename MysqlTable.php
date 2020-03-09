<?php
require_once ('Session.php');
require_once ('DataManagement.php');
require_once ('FileField.php');

// ----------- GLOBAL Mysql Table INTERFACE to implement in child classes ------------
interface Table {
	// Set defaults and table
	function __construct();

	// PUBLIC
	// GETTERS
	public function print_errors();
	// Get table short name
	public function getTableName();
	// Get defaults
	public function getDefaults($field = GET::ALL);
	// Get current ID
	public function getIdLoad();
	// Return true if default access is write access
	public function isAdminSection();
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

// UI for Table CLASS
interface UI_Table {
	// Draw specific form's fieldset (innerHTML, could be a div or anything else)
	public static function draw_fieldset ($action, $data, $table);
	
	// Draw admin list
	public static function draw_list ($list, $deploy = true, $deleteButtons = false);
}

// ERRORS
class SQL_ERR extends ERR {
	// generics
	const ACCESS =		10;
	const SENDMAIL =	11;

	// updating BDD
	const INSERT =	20;
	const UPDATE =	21;
	const DELETE =	22;
	
	// Print errors
	public static function print_error ($error, $rplmtStr = '', $data = []) {
		switch ($error) {
			case self::ACCESS:
				echo '<h3 class="alert">Vous n\'avez pas les droits requis pour accéder à cette ressource</h3>';
				return true;	// stop
			case self::SENDMAIL:
				echo '<h3 class="alert">L\'envoi du message a échoué en raison d\'une erreur du serveur.</h3>';
				include ('../Config/headers.php');
				echo '<p class="centered">Merci de bien vouloir <a href="mailto:'.$emailasso.'">contacter l\'administrateur</a>.</p>';
				break;
				
			case self::INSERT:
				echo '<h3 class="alert">Une erreur est survenue lors de la création de ';
				echo self::replaceFields(strtolower($rplmtStr), $data);
				echo '</span></h3>';
				break;
			case self::UPDATE:
				echo '<h3 class="alert">Une erreur est survenue lors de la mise à jour de ';
				echo self::replaceFields(strtolower($rplmtStr), $data);
				echo '</span></h3>';
				break;
				case self::DELETE:
				echo '<h3 class="alert">Une erreur est survenue lors de la suppression de ';
				echo self::replaceFields(strtolower($rplmtStr), $data);
				echo '</span></h3>';
				break;
				
			default:
				return (parent::print_error ($error, $rplmtStr, $data));
		}
		
		return false;
	}
}


// ENUMS
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
	// '*' value will get all in Sql requests when applied to fields
	const __default = self::ALL;
	const ALL =		'*';
	const LIST =	-1;
}

// ---------- GLOBAL BASE CLASS ------------
abstract class MysqlTable implements Table
{
	// Constants
	const DEFAULT_ACCESS = NULL;
	
	// Properties
	protected $Table;				// name of Mysql base table with prefix
	protected $Parent = NULL;		// Parent de la classe finalle éventuel
	protected $parentItem; 			// nom de l'id du parent dans la BDD
	protected $childsTables = array(); 	// Enfants de la classe finalle éventuels
	protected $rights = [	ACCESS::READ => AUTHORISED::__default,
							ACCESS::WRITE => AUTHORISED::__default
						];			// Droits
	protected $Fields = array();	// List of fields
	protected $bdd;					// bdd
	protected $default_access;		// default ACCESS value
	protected $FileMgmt = array();	// File management
	// readable au niveau supérieur (getter)
	protected $table;				// name of Mysql base table
	private	$Ordering;				// default ordering of data
	private $Limiting;				// default exclusion when getting data

	// errors
	private $Errors = [];

	// Constructor => inherit : lien vers la table héritée)
	function _constructInit ($table, $childsTables = [], $ordering = "", $limiting = "") {
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
		foreach ($childsTables as $childsTable)
			array_push ($this->childsTables, $prefixe.$childsTable);
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
		foreach ($this->Fields as $field => $Field) {
			if ($Field->Type == TYPE::PARENT) {
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
	
	// ------------ GLOBAL INTERFACE METHODS ----------- //
	// GETTERS
	public function print_errors () {
		$data = [];
		foreach ($this->Fields as $field => &$Field)
			$data[$field] = $Field->value;
		unset ($Field);
		foreach ($this->Errors as $error) {
			if (SQL_ERR::print_error ($error, $this->Fields['id']->Name, $data) !== false) return true;
		}
		foreach ($this->Fields as &$Field) {
			if ($Field->print_errors($data) !== false) { unset ($Field); return true; }
		}
		unset ($Field); return false;
	}
	
	// Get table short name
	public function getTableName() {
		return $this->table;
	}

	// Get defaults
	public function getDefaults($fields = GET::ALL) {
		if ($fields == GET::ALL) {
			$ret = [];
			foreach ($this->Fields as $field => $Field)
				$ret[$field] = $Field->Default;
			return $ret;
		}
		if (is_array($fields)) {
			$ret = [];
			foreach ($fields as $field) {
				if (!array_key_exists ($field, $this->Fields)) continue;
				$ret[$field] = $this->Fields[$field]->Default;
			}
			return $ret;
		}
		elseif (array_key_exists ($fields, $this->Fields))
			return $this->Fields[$field]->Default;
		else return NULL;
	}

	// Get current ID
	public function getIdLoad() {
		return $this->Fields['id']->value;
	}
	
	// Is default access ACCESS::WRITE ?
	public function isAdminSection() {
		return ($this->default_access == ACCESS::WRITE);	
	}
	
	// Check if authorized
	private function _rights_control_basics ($read_write = NULL, $userid = 0) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return false;
		if ($this->rights[$read_write] == AUTHORISED::ALL) return true;
		if (SessionManagement::isAdmin($userid)) return true;
		if ($this->rights[$read_write] == AUTHORISED::PARENT)
			return $this->Parent->rights_control($read_write);
		if ($this->rights[$read_write] == AUTHORISED::MMM)
			return $this->_rights_control($read_write, 1, $userid);
		return 0; // still unknown
	}
	private function _rights_control ($read_write = NULL, $id = 0, $userid = 0) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return false;
		if (!is_numeric($id)) $id = 0;
		if (!is_numeric($userid)) $userid = 0;
		if (!$this->is_data($id)) $id = 0;	// do not block if id doesn't exist
		if ($id == 0) {
			if ($this->rights[$read_write] == AUTHORISED::SELF)	return false;
			else return true;
		}

		include ('../Config/config.php');
		if ($userid == 0) $userid = SessionManagement::getSessId();
		else {
			$Table = $prefixe.'users';
			$reponse = $this->bdd->prepare ('SELECT Admin FROM '.$Table.' WHERE id = :userid');
			$reponse->bindParam ('userid', $userid, PDO::PARAM_INT);
			$reponse->execute();
			$donnees = $reponse->fetch();
			$reponse->closeCursor();
			if ($donnees && $donnees['Admin'] == 1) return true;
		}
		
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
		$ret = $this->_rights_control_basics ($read_write, $userid);
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

		// select fields
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

		// get data
		$limit = "";
		switch ($get) {
			// Get list
			case GET::LIST:
				// Authorised list
				//$total = 0;
				// If Parent is defined, LIST is Parent's full list
				if ($this->Parent !== NULL) {
					if ($this->Limiting != "") $limit = ' AND '.$this->Limiting;
					$reponse = $this->bdd->prepare('SELECT '.$fields.' FROM '.$this->Table.' WHERE '.$this->parentItem.' = :parent'.$limit.$this->getOrdering());
					$reponse->bindParam('parent', $this->Parent->Fields['id']->value, PDO::PARAM_INT);
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
							'currentId' => $this->getIdLoad(),
							'parentId' => $this->Parent->Fields['id']->value,
							'nombre' => $nombre,
						);
					}
					return $donnees;
				}
		
				elseif (SessionManagement::isAdmin()) {
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
						'defaultId' => $this->Fields['id']->Default,
						'currentId' => $this->getIdLoad(),
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
					unset($data); // break the reference with the last element
					break;
				}

			// Get one ID
			default:
				if (!$this->rights_control($read_write, $get)) return NULL;
				if (!$this->is_data($get)) {
					if ($fields == GET::ALL) $donnees = $this->getDefaults();
					elseif (isset($arrayFields)) {
						foreach ($this->getDefaults() as $field => $value) {
							if (in_array($field, $arrayFields))
								array_push ($donnees, [$field => $value]);
						}
						
					}
					elseif (array_key_exists($fields, $this->Fields))
						$donnees = $this->getDefaults([$fields]);
					else return NULL;
					// if get isn't null and Parent exists, set parent id if known
					if ($this->Parent !== NULL) {
						if (array_key_exists($this->parentItem, $donnees))
							$donnees[$this->parentItem] = $this->Parent->getidLoad();
					}
				}
				else {
					// do not limit if getting single id
					$reponse = $this->bdd->prepare('SELECT '.$fields.' FROM '.$this->Table.' WHERE id = :id'.$this->getOrdering());
					$reponse->bindParam('id', $get, PDO::PARAM_INT);
					$reponse->execute();
					$donnees = $reponse->fetch();
					$reponse->closeCursor();
				}
				if ($donnees) {
					// tranform array $donnees in single value;
					if ($fields != GET::ALL && !is_array($fields)) $donnees = $this->Fields[$fields]->secure_data ($donnees[$fields]);
					else $donnees = $this->secure_data($donnees);
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
	protected function _send_form () {
		// record settings
		SessionManagement::updateCookies();
		
		// prepare data
		$postedValues = array_merge ($_POST, $_FILES);
		
		$validatedValues = $this->_validate_posted_data($this->secure_data($postedValues, true));
		// post
		return $this->_record_changes ($validatedValues);
	}
	
	// ----------- INTERNAL METHODS ------------
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
			case TYPE::BOOL:
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
		if (!array_key_exists($field, $this->Fields)) return FIELD_ERR::KO;
		switch ($this->Fields[$field]->Type) {
			case TYPE::ID:
				if ($this->is_data($value)) break;
				else return FIELD_ERR::UNKNOWN;
			case TYPE::PARENT:
				if ($this->Parent->is_data($value)) break;
				else return FIELD_ERR::UNKNOWN;
				
			case TYPE::FILE:
				$err = $this->Fields[$field]->isValidValue ($field);
				if ($err == FIELD_ERR::OK) break;
				else return $err;
			default:
				$err = $this->Fields[$field]->isValidValue ($value);
				if ($err == FIELD_ERR::OK) break;
				else return $err;
		}
		
		if ($this->Fields[$field]->Unique && !$this->isUnique($field, $value)) return FIELD_ERR::EXISTS;
		return FIELD_ERR::OK;
	}
	
	// check also if required, don't return error (boolean)
	/*protected function isValidField ($field, $value) {
		if (!array_key_exists($field, $this->Fields)) return false;

		switch ($this->Fields[$field]->Required) {
			case false: return ($value == "" || $this->isValidValue ($field, $value) === FIELD_ERR::OK);
			case true: return ($value != "" && $this->isValidValue ($field, $value) === FIELD_ERR::OK);
			
			default:
				if ($value != "") return ($this->isValidValue ($field, $value) === FIELD_ERR::OK);
				else return $this->Fields[$field]->Required;
		}
	}*/
	
	// Validate posted data and push errors
	protected function _validate_posted_data ($postedValues) {
		foreach ($postedValues as $field => $value) {
			if (!array_key_exists($field, $this->Fields)) continue;
			
			// search errors
			$err = (($value != "" || $this->Fields[$field]->Type == TYPE::FILE) ? $this->isValidValue($field, $value) : FIELD_ERR::OK);
			if ($err && ($this->Fields[$field]->Type != TYPE::ID || $value > 0)) // ID is 0 too insert data
				array_push($this->Fields[$field]->Errors, $err);
			
			$nbErrors = sizeof($this->Fields[$field]->Errors);
			// is required and set without error ?
			switch ($this->Fields[$field]->Required) {
				case false: break;
				case true:
					switch ($this->Fields[$field]->Type) {
						case TYPE::ID: case TYPE::PARENT: break;
						case TYPE::NUM:
							if ($value == 0 || $nbErrors > 0)
								array_push($this->Fields[$field]->Errors, FIELD_ERR::NEEDED);
							break;
						case TYPE::FILE:
							if ($nbErrors > 0)
								array_push ($this->Fields[$field]->Errors, FIELD_ERR::NEEDED);
							
						default:
							if ($value == "" || $nbErrors > 0)
								array_push($this->Fields[$field]->Errors, FIELD_ERR::NEEDED);
							break;
					}
					break;
				default:
					$numId = $this->Fields[$field]->Required;
					if (isset ($isSet[$numId]) && $isSet[$numId] === true) break;
					else {
						switch ($this->Fields[$field]->Type) {
							case TYPE::NUM:
								$isSet[$numId] = ($value > 0 && $nbErrors == 0); break;
							case TYPE::FILE:
								$isSet[$numId] = ($nbErrors == 0); break;
							default:
								$isSet[$numId] = ($value != "" && $nbErrors == 0); break;
						}
					}
					break;
			}
			
			// search for multiple values unset
			if (isset ($isSet)) {
				foreach ($isSet as $couple => $result) {
					if (!$result)
						array_push ($this->Fields['id']->Errors, (FIELD_ERR::NEEDED + $couple));
				}
			}
		}
		
		// create return array
		$validValues = [];
		foreach ($postedValues as $field => $value) {
			if (!array_key_exists ($field, $this->Fields)) continue;
			$validValues[$field] = $this->Fields[$field]->validate_data ($value);
		}
		return $validValues;
	}
	
	// SETTERS
	// Définition des types
	protected function set_field ($field, $type, $name = '', $default = NULL, $required = false, $unique = false) {
		if (!array_key_exists($field, $this->Fields)) return false;
		if ($default == NULL) {
			switch ($this->Fields[$field]->Type) {
				case TYPE::ID: break;
				case TYPE::PARENT:
					$default = $this->Parent->getDefaults('id');
					$name = $this->Parent->Fields['id']->Name;
					break;
				case TYPE::COLOR:
					DataManagement::randomColor(); break;
				case TYPE::NUM:
					$default = 0; break;
				default:
					$default = ""; break;
			}
		}
		
		return ($this->Fields[$field] = new Field($type, $name, $default, $required, $unique));
	}
	
	// Record validated values to DB
	protected function _record_changes ($validatedValues) {
		// Insert
		if ($validatedValues['id'] == 0) {
			$ret = $this->insert_data ($validatedValues);
			if (is_numeric ($ret) && $ret > 0) $this->load_id ($ret, ACCESS::WRITE);
			return $ret;
		}

		// Update
		elseif (sizeof($this->Fields['id']->Errors) == 0) {
			$ret = false;	// $ret is true if one modif executed
			foreach ($validatedValues as $field => $value)
				$ret = ($this->update_field ($validatedValues['id'], $field, $value) === true ? true : $ret);
			$this->load_id ($validatedValues['id']);
			return $ret;
		}
		
		return false;
	}

	// Update DB Functions : necessary contrlos, but not on Errors, which should be set and checked first
	// Delete entry in DB (directly called from child's send_form function, after checking action)
	protected function delete_entry ($id) {
		if (!$this->rights_control(ACCESS::WRITE, $id)) {
			array_push ($this->Errors, SQL_ERR::ACCESS);
			return false;
		}

		// First delete dependencies
		// delete linked files
		foreach ($this->Fields as $field => $Field) {
			if ($Field->Type == TYPE::FILE)
				$Field->delete($this->get_data($id, $field, ACCESS::WRITE));
		}
		
		// childrens & authorisations
		switch ($this->rights[ACCESS::WRITE]) {
			case AUTHORISED::ASSO:
			case AUTHORISED::NEWS:
				include ('../Config/config.php');
				$item = AUTHORISED::_ITEM[$this->rights[ACCESS::WRITE]];
				// Delete children
				foreach ($this->childsTables as $childTable) {
					$reponse = $this->bdd->prepare ('DELETE FROM '.$childTable.' WHERE '.$item.' = :id');
					$reponse->bindParam('id', $id, PDO::PARAM_INT);
					$ret = $reponse->execute();
					if (!$ret) return $ret;
				}
				// Delete in authorised table, prevent deleting when deleting from a child
				$Table = $prefixe.AUTHORISED::_TABLE[$this->rights[ACCESS::WRITE]];
				if ($Table == $this->Table) {
					$userid = SessionManagement::getSessId();
					$reponse = $this->bdd->prepare('DELETE FROM '.$Table.' WHERE '.$item.' = :itemid AND id_user = :userid');
					$reponse->bindParam('itemid', $id, PDO::PARAM_INT);
					$reponse->bindParam('userid', $userid, PDO::PARAM_INT);
					$ret = $reponse->execute();
					$reponse->closeCursor();
				}
				break;
			default: break;
		}
				
		// Finally delete entry
		$reponse = $this->bdd->prepare('DELETE FROM '.$this->Table.' WHERE id = :id');
		$reponse->bindParam('id', $id, PDO::PARAM_INT);
		$ret = ($reponse->execute());
		$reponse->closeCursor();
		
		if (!$ret) {
			array_push ($this->Errors, SQL_ERR::DELETE);
			return $ret;
		}

		return $ret;
	}
	
	// Update field in DB, return field as been modified
	protected function update_field ($id, $field, $value="") {
		if (!array_key_exists($field, $this->Fields)) return false;
		$type = $this->Fields[$field]->Type;
		if (!$this->rights_control(ACCESS::WRITE, $id)) {
			array_push ($this->Errors, SQL_ERR::ACCESS);
			return false;
		}
		if (!$this->is_data($id)) {
			array_push ($this->Fields['id']->Errors, FIELD_ERR::UNKNOWN);
			return false;
		}
		if (sizeof($this->Fields[$field]->Errors) > 0) return false;
		$curValue = $this->get_data ($id, $field);
		if ($value == $curValue) return false;
		if ($type == TYPE::PASSWD && $value == "") return false;
		
		if ($type == TYPE::FILE) {
			$value = $this->Fields[$field]->upload($this->table, $field);
			if (!$value) return false;
		}
		
		$reponse = $this->bdd->prepare('UPDATE '.$this->Table.' SET '.$field.' = :value WHERE id = :id');
		switch ($this->Fields[$field]->Type) {
			case TYPE::ID:
			case TYPE::PARENT:
			case TYPE::NUM:
			case TYPE::BOOL:
				$reponse->bindParam('value', $value, PDO::PARAM_INT); break;
				
			default: $reponse->bindParam('value', $value, PDO::PARAM_STR); break;
		}
		$reponse->bindParam('id', $id, PDO::PARAM_INT);
		$ret = ($reponse->execute());
		$reponse->closeCursor();
		
		if (!$ret) array_push ($this->Errors, SQL_ERR::UPDATE);
		return $ret;
	}

	// PRIVATE
	// Insert full entry in DB, return false or ID of created entry
	private function insert_data ($validatedValues) {
		if (!$this->rights_control(ACCESS::WRITE, 0, SessionManagement::getSessId())) {
			array_push ($this->Errors, SQL_ERR::ACCESS);
			return false;
		}
		if (sizeof($this->Fields['id']->Errors) > 0)
			return false;

		$echoValues = [];
		foreach ($validatedValues as $field => &$value) {
			$type = $this->Fields[$field]->Type;
			if (!array_key_exists($field, $this->Fields) || $type == TYPE::ID) {
				unset ($validatedValues[$field]);
				continue;
			}
			if (sizeof($this->Fields[$field]->Errors) > 0) {
				unset ($value);
				return false;
			}
			
			if ($type == TYPE::FILE) {
				$value = $this->Fields[$field]->upload($this->table, $field);
				if (!$value) $value = "";
			}
		
			$echoValues[$field] = ':'.strtolower($field);
		}
		unset ($value);
		
		$fullFields = implode(', ', array_keys($validatedValues));
		$fullValues = implode(', ', $echoValues);
		$reponse = $this->bdd->prepare ('INSERT INTO '.$this->Table.' ('.$fullFields.') VALUES ('.$fullValues.')');

		foreach ($validatedValues as $field => $value) {
			switch ($this->Fields[$field]->Type) {
				case TYPE::PARENT:
				case TYPE::NUM:
				case TYPE::BOOL:
					$reponse->bindParam(strtolower($field), $value, PDO::PARAM_INT);
					break;	
				default:
					//$reponse->bindParam(strtolower($field), $value, PDO::PARAM_STR);
					$reponse->bindValue(strtolower($field), $value);
					break;
			}
		}
		$ret = ($reponse->execute());
		$reponse->closeCursor();
		if (!$ret) {
			array_push ($this->Errors, SQL_ERR::INSERT);
			return false;
		}
		
		$reponse = $this->bdd->query('SELECT id FROM '.$this->Table.' ORDER BY id DESC LIMIT 1');
		$donnees = $reponse->fetch();
		$reponse->closeCursor();
		
		if (!$donnees) {
			array_push ($this->Errors, SQL_ERR::INSERT);
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
				if (!$ret) array_push ($this->Errors, SQL_ERR::INSERT);
				break;
			default: break;
		}

		return $donnees['id'];
	}
	
	// Secure full array for printing, set $record = true destination is DB
	protected function secure_data ($data, $record = false) {
		foreach ($data as $field => &$value) {
			if (!array_key_exists($field, $this->Fields)) continue;
			if ($value != "")
				$value = $this->Fields[$field]->secure_data ($value, $record);
		}
		unset($value); // break the reference with the last element
		
		return $data;
	}
	
	// try to page load an id
	private function load_id ($loadid = NULL, $read_write = NULL) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return SQL_ERR::ACCESS;
		// If Parent is defined, loadid = NULL if working on parent, loadid = 0 if working on child, these 2 values are valid even if no data
		if ($this->Parent !== NULL && ($loadid === NULL || $loadid == 0))
			$this->Fields['id']->value = $loadid;
		
		elseif ($loadid && is_numeric($loadid) && $loadid > 0) {
			if ($this->is_data($loadid)) {
				if ($this->rights_control($read_write, $loadid)) {
					$data = $this->get_data ($loadid, GET::ALL, $read_write);
					foreach ($this->Fields as $field => &$Field)
						$Field->value = $data[$field];
					unset ($Field);
				}
				else {
					if (!headers_sent()) header ('HTTP/1.1 401 Unauthorized');
					array_push ($this->Errors, SQL_ERR::ACCESS);
					return SQL_ERR::ACCESS;
				}
			}
		}

		if ($this->Parent === NULL || !is_numeric($this->getIdLoad()) || $this->getIdLoad() == 0) return SQL_ERR::OK;
		// Load parent
		return $this->Parent->load_id($this->get_data($this->getIdLoad(), $this->parentItem, $read_write), $read_write);
	}
}

// ---------- GLOBAL BASE UI CLASS ------------
abstract class UI_MysqlTable implements UI_Table
{
	// Constants
	const DefaultListLength = 10;	// longueur des listes admin
	
	// Properties
	public static $choixListe = [10, 15, 20, 30];	// choix nb entrées par liste

	// prevent instanciation
    function __construct() { }
	
	// SPECIFICS
	// Draw Delete form
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
				if ($listData['prec'] == 0) echo 'style="visibility: hidden;" ';
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
