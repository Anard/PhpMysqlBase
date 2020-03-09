<?php
require_once ('Field.php');

// INTERFACE for File SQL fields
interface FileInterface {
	// STATIC
	// Get preload file name
	public static function preloadFileName ($field);
	
	// PUBLIC
	public function print_errors($data = []);
	// Get current File object
	public function getFileInfo ();
	// Delete file
	public function delete();
	// Preload file in tmp dir (acces via JS)
	public function preload ($field);
	// Upload file in DB
	public function upload ($table, $field);
	// Validate POST field
	public function validatePostedFile ($field);
}

interface UI_FileInterface {
	// STATIC
	public static function draw_fieldset ($field, $table, $maxSize, $image = "");
}

// ERRORS
class FILE_ERR extends FIELD_ERR {
	const NOFILE =	100;
	const SIZE =	101;
	const TYPE =	102;
	const UPLOAD =	110;
	
	// Print error
	public static function print_error ($error, $rplmtStr = '', $data = []) // $data is from getFileInfo()
	{
		switch ($error) {
			case self::NOFILE:
				echo '<h3 class="alert">Le fichier ';
				self::replaceFields ('__path|choisi__', $data);
				echo ' est introuvable.</h3>';
				break;
			case self::SIZE:
				echo '<h3 class="alert">Votre fichier est trop volumineux</h3>';
				echo '<p class="alert">Merci de respecter la limite des '.($data['size'] / 1000000).'Mo par fichier.</p>';
				break;
			case self::TYPE:
				echo '<h3 class="alert">Votre fichier est de type incorrect</h3>';
				echo '<p class="alert">Merci de choisir un fichier parmi les formats supportés : '.$data['type'].'</p>';
				break;
			case self::UPLOAD:
				echo '<h3 class="alert">Un erreur est survenue pendant le téléchargement de votre fichier</h3>';
				echo '<p class="alert">Merci de bien vouloir réessayer plus tard.</p>';
				break;
			
			default:
				return (parent::print_error ($error, $rplmtStr, $data));
		}
		return false;
	}
}

// File TYPES
class FILE_TYPE extends ExtdEnum {
	const __default = NULL;
	
	const JPG = ['jpg', 'jpeg'];
	const PNG = ['png'];
	const GIF = ['gif'];
	const PDF = ['pdf'];
}

// ---------- CLASS FILE_FIELD ----------- //
class FileField extends Field implements FileInterface {

	// Constants
	// Chemin vers fichiers finaux
	const PATH_UPLOAD = array (
		'tmp'		=> '../Administration/tmp/',
		'assos'		=> '../Animations/Files/',
		'assos_pages' => '../Animations/Files/',
		'event'		=> '../Agenda/Files/',
		'thumbs'	=> '../Collection/Files/thumbs/',
		'galerie'	=> '../Collection/Files/masters/'
	);
	const FILIGRANE = '../Administration/Images/logofiligrane.png';
	// Fichiers temporaires considérés trop vieux
	const DELAY_OLDFILE = 1800; // 1800s = 30min
	
	const HANDLERS = [	IMAGETYPE_JPEG => [	'type' => FILE_TYPE::JPG,
											'load' => 'imagecreatefromjpeg',
											'save' => 'imagejpeg',
											'quality' => 96,
											'header' => 'image/jpeg'],
						IMAGETYPE_PNG => [	'type' => FILE_TYPE::PNG,
											'load' => 'imagecreatefrompng',
											'save' => 'imagepng',
											'quality' => 0,
											'header' => 'image/png'],
						IMAGETYPE_GIF => [	'type' => FILE_TYPE::GIF,
											'load' => 'imagecreatefromgif',
											'save' => 'imagegif',
											'header' => 'image/gif']
	];
	
	const PRELOAD_FILENAME = 'preload';
	
	// Properties
	public $Types = [ FILE_TYPE::__default ];
	public $MaxSize;	// Taille max des fichiers
	// Internal
	private $Preload = NULL;				// preload field
	public $type = FILE_TYPE::__default;	// final type

	// Construct : list of supported types, maxSize (Mo)
	function __construct ($field, $name = '', $okTypes = [], $maxSize, $default = NULL, $required = false, $unique = false) {
		if (!is_numeric($maxSize)) return FILE_ERR::KO;
		$this->MaxSize = $maxSize * 1000000;
    	foreach ($okTypes as $type) {
    		if (FILE_TYPE::hasKey($type)) {
				array_push ($this->Types, $type);
    		}
    	}
    	if (sizeof ($this->Types) == 1) return FILE_ERR::KO;
    	// delete FILE_TYPE::__default value
    	else array_shift($this->Types);
		
		if (!parent::__construct(TYPE::FILE, $name, $default, $required, $unique)) return NULL;
		// create preload field if needed
		//if ($name != self::preloadFileName($name))
		$this->Preload = new Field (TYPE::TEXT, self::preloadFileName($field), '');
	}
	
	// ------- Interface Methods ---------
	// STATIC
	// return preload File field name
	public static function preloadFileName ($field) {
		return self::PRELOAD_FILENAME.'_'.DataManagement::secureText($field);
	}

	// PUBLIC	
	public function print_errors($data = []) {
		$data = array_merge ($data, $this->getFileInfo());
		foreach ($this->Errors as $error) {
			if (FILE_ERR::print_error($error, $this->Name, $data) !== false) return true;
		}
		return false;
	}
	
	// OVERRIDE Field's methods
	public function isValidValue ($field) {
		return $this->validatePostedFile ($field);
	}
	
	// SPECIFICS
	// Getters
	public function getFileInfo ($path = '') {
		if ($path == '') $path = $this->value;
		$path = substr ($path, strripos ($path, '/') + 1);

		foreach ($this->Types as $types)
			$arrayTypes[] = implode (', ', $types);
		$type = implode (', ', $arrayTypes);
		$size = $this->MaxSize;
		
		return array (	'path' => $path,
						'type' => $type,
						'size' => $size
		);
	}
	
	// Setters
	// Delete file
	public function delete() {
		if (file_exists($this->value)) {
			unlink ($this->value);
			return true;
		}
		return false;
	}

	// Preload POST file in tmp dir (updload via JS)
	public function preload ($field) {
		// field is a regular posted file, just record it in temp directory and return full path to uploaded file
		$err = $this->validatePostedFile ($field);
		if ($err) {
			array_push ($FileMgmt->Errors, $err);
			return false;
		}
		$tmpFileName = $this->upload('tmp', $field);
		if (!$tmpFileName) return false;
		else echo self::PATH_UPLOAD['tmp'].$tmpFileName;
		return true;
	}
	
	// Upload file, returns final file name
	public function upload ($table, $field) {
		if (!array_key_exists($table, self::PATH_UPLOAD)) {
			array_push ($this->Errors, FILE_ERR::KO);
			return NULL;
		}
		// Before upload, data should have been validated. If we use preloaded file, $_FILES[$field] have been unset;
		// ever uploaded in temp dir
		if (!isset($_FILES[$field])) {
			if (!isset($_POST[$this->Preload->Name]) || $_POST[$this->Preload->Name] == '')
				return NULL;
			else {
				$preloadedFile = DataManagement::secureText($_POST[$this->Preload->Name]);
				$file = array (
					'name'		=> preg_replace ('#((.*)\/)+(tmp[0-9]+\.[A-Za-z]{3,4})$#', '$3', $preloadedFile),
					'tmp_name'	=> $preloadedFile
				);
				$field = $this->Preload->Name;
			}
		}
		// Sinon, on uploade
		else $file = $_FILES[$field];
		
		$path = self::PATH_UPLOAD[$table];
		$extension = strtolower(substr(strrchr($file['name'], '.'), 1));
		$nom = $table.time().'.'.$extension;
		
		if ($field == $this->Preload->Name)
			$move = rename($file['tmp_name'], $path.$nom);
		else $move = move_uploaded_file($file['tmp_name'], $path.$nom);
		
		if ($move) return $nom;
		array_push ($this->Errors, FILE_ERR::UPLOAD);
		return NULL;
	}

	// Validate POST field
	public function validatePostedFile ($field) {
		$field = DataManagement::secureText($field);
		$err = FILE_ERR::OK;
		// Contrôles préalables (erreurs PHP)
		if (!isset($_FILES[$field])) $err = FILE_ERR::KO;
		elseif ($_FILES[$field]['error'] > 0) {
			switch ($_FILES[$field]['error']) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:	$err = FILE_ERR::SIZE; break;
				case UPLOAD_ERR_PARTIAL:	$err = FILE_ERR::UPLOAD; break;
				case UPLOAD_ERR_NO_FILE:	$err = FILE_ERR::NOFILE; break;
				default:					$err = FILE_ERR::KO; break;
			}
		}
		
		// try to validate this file
		if ($err == FILE_ERR::OK)
			$err = $this->validateFileData ($_FILES[$field]['tmp_name']);
		
		switch ($err) {
			case FILE_ERR::KO:
			case FILE_ERR::NOFILE:
				// try preload field
				if (isset($_POST[$this->Preload->Name])) {
					unset ($_FILES[$field]);
					$err = $this->validateFileData (DataManagement::secureText($_POST[$this->Preload->Name]));
				}
				// else continue to return error
			
			case FILE_ERR::OK:
			default: break;
		}
		return $err;
	}
	
	// PRIVATE
	// Setters
	// Validate file source and save data
	private function validateFileData ($src) {
		if (!file_exists($src)) return FILE_ERR::NOFILE;

		// get handlers
		$imageHandlers = false;
		$handlers = NULL;
		
		foreach ($this->Types as $type) {
			switch ($type) {
				// Images
				case FILE_TYPE::JPG:
				case FILE_TYPE::PNG:
				case FILE_TYPE::GIF:
					// handlers remain null il key doesn't exist
                    if (!$imageHandlers)  $imageHandlers = exif_imagetype($src);
					if ($imageHandlers && array_key_exists($imageHandlers, self::HANDLERS) && self::HANDLERS[$imageHandlers]['type'] == $type)
						$handlers = self::HANDLERS[$imageHandlers];
					break;
					
				case FILE_TYPE::PDF:
				default:
					$handlers = NULL;
					break;
			}
			if ($handlers) break;
		}

		if (!$handlers) return FILE_ERR::TYPE;
		$this->value = $src;
		$this->type = $handlers;
		
		return FILE_ERR::OK;
	}
	
	/**
	 * @param $src - a valid file location
	 * @param $filigrane - l'image à apposer (png transparent)
	 * @param $bottom - position en haut, 0 pour placer au milieu
	 * @param $right - position à gauche, 0 pour placer au milieu
	 * @param $recouvr - pourcentage de recouvrement
	 * @param $transp - opacité à appliquer en pourcent (désactivé)
	 */
	private function signImage ($bottom=0, $right=0, $recouvr=30, $transp=15) {
		if ($this->File == NULL) return NULL;

		$image = call_user_func($this->type['load'], $this->value);
		if (!$image) return null;

		$logo = imagecreatefrompng(self::FILIGRANE);
		if (!$logo) return null;
		
		$recouvr = $recouvr / 100;
		$srcwidth = imagesx($image);
		$srcheight = imagesy($image);
		$maxwidth = imagesx($logo);
		
		$ratio = imagesx($logo) / imagesy($logo);
		$width = floor($srcwidth * $recouvr);   
		$height = floor($width / $ratio);
		if ($height > $srcheight) {
			$height = floor($srcheight * $recouvr);
			$width = floor($height * $ratio);
		}
		if ($width > $maxwidth) {
			$width = $maxwidth;
			$height = imagesy($logo);	
		}
		
		if ($maxwidth == $width) $final = $logo;
		else {
			$final = imagecreatetruecolor($width, $height);
			// set transparency options for PNGs
			// make image transparent
			imagecolortransparent(
			    $final,
			    imagecolorallocate($final, 0, 0, 0)
			);
			imagealphablending($final, false);
			imagesavealpha($final, true);
			imagecopyresampled(
				$final,
				$logo,
				0, 0, 0, 0,
				$width, $height,
				imagesx($logo), imagesy($logo)
			);
		}

		if ($bottom == 0) $top = floor(($srcheight/2) - ($height/2));
		else $top = $srcheight - $height - $bottom;
		if ($right == 0) $left = floor(($srcwidth/2) - ($width/2));
		else $left = $srcwidth - $width - $right;

		//imagecopymerge($image, $final, $left, $top, 0, 0, $srcwidth, $srcheight, $transp) ;
		imagecopy ($image, $final, $left, $top, 0, 0, $srcwidth, $srcheight);
		imagedestroy($logo);
		//imagedestroy($final);
		
		return call_user_func(
		    $this->type['save'],
		    $image,
		    $this->value,
		    $this->type['quality']
		);
	}

	/**
	 * @param $src - a valid file location
	 * @param $dest - a valid file target
	 * @param $targetWidth - desired output width
	 * @param $targetHeight - desired output height or null
	 */
	private function createThumbnail ($dest, $targetWidth, $targetHeight = null) {
		// 1. Load the image from the given $src
		// - see if the file actually exists
		// - check if it's of a valid image type
		// - load the image resource
		// get the type of the image
		// we need the type to determine the correct loader
		//$type = exif_imagetype($src);
		// if no valid type or no handler found -> exit
		//if (!$type || !IMAGE_HANDLERS[$type]) {
		//    return null;
		//}
		// load the image with the correct loader
		$image = call_user_func($this->type['load'], $this->value);
		// no image found at supplied location -> exit
		if (!$image) return null;
		// 2. Create a thumbnail and resize the loaded $image
		// - get the image dimensions
		// - define the output size appropriately
		// - create a thumbnail based on that size
		// - set alpha transparency for GIFs and PNGs
		// - draw the final thumbnail
		// get original image width and height
		$width = imagesx($image);
		$height = imagesy($image);
		
		if ($width > $targetWidth) {
			// maintain aspect ratio when no height set
			if ($targetHeight == null) {
				// get width to height ratio
				$ratio = $width / $height;
				// if is portrait
				// use ratio to scale height to fit in square
				//if ($width > $height) {
				    $targetHeight = floor($targetWidth / $ratio);
				//}
				// if is landscape
				// use ratio to scale width to fit in square
				/*else {
				    $targetHeight = $targetWidth;
				    $targetWidth = floor($targetWidth * $ratio);
				}*/
			}
			// create duplicate image based on calculated target size
			$thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
			// set transparency options for GIFs and PNGs
			if ($this->File->Type == FILE_TYPE::GIF || $this->File->Type == FILE_TYPE::PNG) {
				// make image transparent
				imagecolortransparent(
				    $thumbnail,
				    imagecolorallocate($thumbnail, 0, 0, 0)
				);
				// additional settings for PNGs
				if ($this->File->Type == FILE_TYPE::PNG) {
				    imagealphablending($thumbnail, false);
				    imagesavealpha($thumbnail, true);
				}
			}
			// copy entire source image to duplicate image and resize
			imagecopyresampled(
				$thumbnail,
				$image,
				0, 0, 0, 0,
				$targetWidth, $targetHeight,
				$width, $height
			);
			// 3. Save the $thumbnail to disk
			// - call the correct save method
			// - set the correct quality level
			// save the duplicate version of the image to disk
			return call_user_func(
				$this->File->Handlers['save'],
				$thumbnail,
				$dest,
				$this->File->Handlers['quality']
			);
		}
		else {
			if ($this->value != $dest) return copy($this->value, $dest);
			else return 1;
		}
	}
}

// ---------- UI CLASS for File Fields ---------- //
class UI_File implements UI_FileInterface {
	// prevent instanciation
	function construct() { }
	
	// Methods
	public static function draw_fieldset ($field, $table, $maxSize, $image = "") {
		if (!is_numeric($maxSize)) return false;
		if (!array_key_exists($table, FileField::PATH_UPLOAD)) return false;
		$image = FileField::PATH_UPLOAD[$table].$image;
		if (!file_exists($image) || !is_file($image)) $image = "";
		echo '<div class="container">';
			echo '<p>Vous pouvez choisir une image (jpg, png, gif) pour illustrer la page (maximum '.$maxSize.'Mo).</p>';
			echo '<input type="hidden" name="MAX_FILE_SIZE" value="'.($maxSize * 1000000).'" />';
			echo '<img id="imageupload" ';
			if ($image != "") echo 'style="display: block;" ';
			echo 'src="'.$image.'" alt="" />';
			echo '<img id="loading" src="../Styles/loading.png" alt="chargement" />';
		echo '<input type="hidden" name="'.FileField::preloadFileName($field).'" id="uploaded" value="'.$image.'" />';
			echo '<img class="imgButton" id="deleteimg" ';
			if ($image == "") { echo 'style="visibility: hidden;" '; }
			echo 'alt="x" title="Supprimer cette image" src="../Styles/remove.png" onmousedown="this.src=\'../Styles/remove-clic.png\';" onmouseup="this.src=\'../Styles/remove.png\';" onmouseout="this.src=\'../Styles/remove.png\';" onclick="supprImage(\''.$image.'\');" ';
			if ($image != "") { echo 'style="visibility: visible;" '; }
			echo '/>';
			echo '<input type="file" id="file" name="'.$field.'" value="" title="Insérer une image" onchange="preloadFile(\''.$table.'\');"><br />';
		echo '</div>';
	}
}
?>
