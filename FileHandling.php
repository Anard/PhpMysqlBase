<?php
require_once ('Field.php');

interface FileInterface {
	public function print_errors();
	// Get current File object
	public function getFileInfo ();
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

class FILE_ERR extends ERR {
	const NOFILE =	100;
	const SIZE =	101;
	const TYPE =	102;
	const UPLOAD =	110;
	
	// Print errors
	public static function print_errors ($Errors, $data = [], $rplmtStr = '') // $$data is from getFileInfo()
	{
		// $Errors contient toutes les erreurs du champ
		foreach ($Errors as $error) {
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
					return (parent::print_errors ($error, $data) !== false);
			}
		}
		return false;
	}
}

class FILE_TYPE extends ExtdEnum {
	const __default = NULL;
	
	const JPG = ['jpg', 'jpeg'];
	const PNG = ['png'];
	const GIF = ['gif'];
	const PDF = ['pdf'];
}

/*class HANDLERS extends ExtdEnum {
	// const NAME is exif's output (const returned by exif_imagetype for images)
	const __default = NULL;
	
	const IMAGE_JPEG = [
			'type' => FILE_TYPE::JPG,
		    'load' => 'imagecreatefromjpeg',
		    'save' => 'imagejpeg',
		    'quality' => 96,
		    'header' => 'image/jpeg'
		];
	const IMAGE_PNG = [
			'type' => FILE_TYPE::PNG,
		    'load' => 'imagecreatefrompng',
		    'save' => 'imagepng',
		    'quality' => 0,
		    'header' => 'image/png'
		];
	const IMAGE_GIF = [
			'type' => FILE_TYPE::GIF,
		    'load' => 'imagecreatefromgif',
		    'save' => 'imagegif',
		    'header' => 'image/gif'
		];
}*/

class File extends Field implements FileInterface {

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

	
	// Properties
	public $Types = [ FILE_TYPE::__default ];
	public $MaxSize;	// Taille max des fichiers
	public $type = FILE_TYPE::__default;
	private $path;
	
	// Construct : list of supported types, maxSize (Mo)
	function __construct ($name = '', $okTypes = [], $maxSize, $required = false, $unique = false) {
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
		
		
		if (!parent::__construct(TYPE::FILE, $name, $required, $unique)) return NULL;
	}
	
	// ------- Interface Methods ---------
	// PUBLIC	
	public function print_errors() {
		foreach ($this->Errors as &$Error)
			FILE_ERR::print_errors($this->Errors, $data);
	}
	
	// Getters
	public function getFileInfo ($path = '') {
		if ($path == '') $path = $this->path;
		$path = substr ($this->path, strripos ($path, '/') + 1);

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
	// Preload POST file in tmp dir (updload via JS)
	public function preload ($field) {
		$err = $this->	validateFileData ($_FILES[$field]['tmp_name']);
		if ($err) FILE_ERR::print_errors([ $err ], $this->getFileInfo($_FILES[$field]['tmp_name']));
        else {
            $tmpFileName = $this->upload('tmp', $field);
            if (!$tmpFileName)
                FILE_ERR::print_errors ($this->Errors, $this->getFileInfo($_FILES[$field]['tmp_name']));
            else echo self::PATH_UPLOAD['tmp'].$tmpFileName;
        }
	}
	
	// Upload file, nom is a prefix
	public function upload ($table, $field, $value='') {
	// return final file path
	
		if (!array_key_exists($table, self::PATH_UPLOAD)) {
			array_push ($this->Errors, FILE_ERR::KO);
			return NULL;
		}
		// image déjà uploadée par JS
		if ($this->Type == TYPE::PRELOAD) {
			$file = array (
				'name'		=> preg_replace ('#((.*)\/)+([0-9]+\.[A-Za-z]{3,4})$#', '$3', $value),
				'tmp_name'	=> $value
			);
		}
		// Sinon, on uploade
		else $file = $_FILES[$field];
		
		$path = self::PATH_UPLOAD[$table];
		$extension = strtolower(substr(strrchr($file['name'], '.'), 1));
		$nom = $table.time().'.'.$extension;
		
		if ($this->Type == TYPE::PRELOAD) $move = rename($file['tmp_name'], $path.$nom);
		else $move = move_uploaded_file($file['tmp_name'], $path.$nom);
		
		if ($move) return $nom;
		array_push ($this->Errors, FILE_ERR_UPLOAD);
		return NULL;
	}

	// Validate POST field
	public function validatePostedFile ($field) {
		// Contrôles préalables (erreurs PHP)
		if (!isset($_FILES[$field])) return FILE_ERR::KO;

		if ($_FILES[$field]['error'] > 0) {
			switch ($_FILES[$field]['error']) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:	return FILE_ERR::SIZE;
				case UPLOAD_ERR_PARTIAL:	return FILE_ERR::UPLOAD;
				case UPLOAD_ERR_NO_FILE:
					$this->src = ''; $this->type = FILE_TYPE::__default;
					return FILE_ERR::OK;	// on ne bloque pas s'il n'y a pas de fichier, mais on retourrne des valeurs nulles
				default:					return FILE_ERR::KO;
			
			}
		}
		
		return $this->validateFileData ($_FILES[$field]['tmp_name']);
	}
	
	// PRIVATE
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
					if (array_key_exists($imageHandlers, self::HANDLERS) && self::HANDLERS[$imageHandlers]['type'] == $type)
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
				
		$this->src = $src;
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

		$image = call_user_func($this->File->Handlers['load'], $this->File->Path);
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
		    $this->File->Handlers['save'],
		    $image,
		    $this->File->Path,
		    $this->File->Handlers['quality']
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
		$image = call_user_func($this->File->Handlers['load'], $this->File->Path);
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
			if ($this->File->Path != $dest) return copy($this->File->Path, $dest);
			else return 1;
		}
	}
}

class UI_File implements UI_FileInterface {
	// prevent instanciation
	function construct() { }
	
	// Methods
	public static function draw_fieldset ($field, $table, $maxSize, $image = "") {
		if (!is_numeric($maxSize)) return false;
		if (!file_exists($image) || !is_file($image)) $image = "";
		echo '<div class="container">';
			echo '<p>Vous pouvez choisir une image (jpg, png, gif) pour illustrer la page (maximum '.$maxSize.'Mo).</p>';
			echo '<input type="hidden" name="MAX_FILE_SIZE" value="'.($maxSize * 1000000).'" />';
			echo '<img id="imageupload" ';
			if ($image != "") echo 'style="display: block;" ';
			echo 'src="'.$image.'" alt="" />';
			echo '<img id="loading" src="../Styles/loading.png" alt="chargement" />';
			echo '<input type="hidden" name="'.UI_MysqlTable::preloadFileName($field).'" id="uploaded" value="'.$image.'" />';
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
