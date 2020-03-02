<?php
require_once ('Generic.php');

class FILE_ERR extends ERR {
	const NOFILE =	10;
	const SIZE =	11;
	const UPLOAD =	12;
}



class FileManagement {

	const MAX_UPLOAD = 2000000;	// 2Mo
	
	// Format de fichiers acceptés
	const TYPE_UPLOAD = array ('jpg','jpeg','png','gif');
	// Chemin vers fichiers finaux
	const PATH_UPLOAD = array (
		'tmp'		=> '../Administration/tmp/',
		'asso'		=> '../Animations/Files/',
		'event'		=> '../Agenda/Files/',
		'thumbs'	=> '../Collection/Files/thumbs/',
		'galerie'	=> '../Collection/Files/masters/'
	);
	const FILIGRANE = '../Administration/Images/logofiligrane.png';

	// Link image type to correct image loader and saver
	// - makes it easier to add additional types later on
	// - makes the function easier to read
	const IMAGE_HANDLERS = [
		IMAGETYPE_JPEG => [
		    'load' => 'imagecreatefromjpeg',
		    'save' => 'imagejpeg',
		    'quality' => 96,
		    'header' => 'image/jpeg'
		],
		IMAGETYPE_PNG => [
		    'load' => 'imagecreatefrompng',
		    'save' => 'imagepng',
		    'quality' => 0,
		    'header' => 'image/png'
		],
		IMAGETYPE_GIF => [
		    'load' => 'imagecreatefromgif',
		    'save' => 'imagegif',
		    'header' => 'image/gif'
		]
	];
	
	// prevent instanciation
    function __construct() { }
	
	// PUBLIC
	public function draw_fieldset ($field, $image = "") {
		echo '<p>Vous pouvez choisir une image (jpg, png, gif) pour illustrer la page (maximum '.(self::MAX_UPLOAD/1000000).'Mo).</p>';
		echo '<input type="hidden" name="MAX_FILE_SIZE" value="'.self::MAX_UPLOAD.'" />';
		echo '<div class="container">';
			echo '<img id="imageupload" ';
			if ($image != "") echo 'style="display: block;" ';
			echo 'src="'.$image.'" alt="" />';
			echo '<img id="loading" src="../Styles/loading.png" alt="chargement" />';
			echo '<input type="hidden" name="uploaded" id="uploaded" value="'.$image.'" />';
			echo '<img class="imgButton" id="deleteimg" ';
			if ($image == "") { echo 'style="visibility: hidden;" '; }
			echo 'alt="x" title="Supprimer cette image" src="../Styles/remove.png" onmousedown="this.src=\'../Styles/remove-clic.png\';" onmouseup="this.src=\'../Styles/remove.png\';" onmouseout="this.src=\'../Styles/remove.png\';" onclick="supprImage(\''.$image.'\');" ';
			if ($image != "") { echo 'style="visibility: visible;" '; }
			echo '/>';
			echo '<input type="file" id="file" name="'.$field.'" value="" title="Insérer une image" onchange="preloadFile();"><br />';
		echo '</div>';
	}
	
	public function test_file ($champ) {
		// Contrôles préalables (erreurs PHP)
		if (!isset($_FILES[$champ]))
			return FILE_ERR::NOFILE;
		if ($_FILES[$champ]['error'] > 0) {
			switch ($_FILES[$champ]['error']) {
				case UPLOAD_ERR_NO_FILE:
					return FILE_ERR::NOFILE;
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					return FILE_ERR::SIZE;
				case UPLOAD_ERR_PARTIAL:
					return FILE_ERR::NOFILE;
			}
		}
		
		$err = "";
		// Contrôle du format
		$extension = strtolower(substr(strrchr($_FILES[$champ]['name'], '.'), 1));
		if (!in_array($extension, TYPE_UPLOAD)) {
			return 'filetype';
		}
		// Contrôle de la taille
		if ($_FILES[$champ]['size'] > MAX_UPLOAD) {
			$err .= 'filesize';
		}
		// Vérification du contenu
		/*if ($err == '') {
			$err = 0;
			$handle = fopen($_FILES[$champ]['tmp_name'], 'r');
			if ($handle) {
				while (!feof($handle) AND $err == 0) {
				$buffer = fgets($handle);
				switch (true) {
					case strstr($buffer,'<'):
						$err ++; break;
					case strstr($buffer,'>'):
						$err ++; break;
					case strstr($buffer,';'):
						$err ++; break;
					case strstr($buffer,'&'):
						$err ++; break;
					case strstr($buffer,'?'):
						$err ++; break;
					default: break;
				}
				}
				fclose($handle);
			}
			if ($err > 0) $err = 'filetype';
		}*/
		
		return $err;
	}

	public function upload_file($table, $champ) {
	// une valeur de retour numérique représente une erreur
	// sinon, on retourne le chemin du fichier final
		// image déjà uploadée par JS
		if ($champ == 'uploaded') {
			$file = array (
				'name'		=> preg_replace ('#((.*)\/)+([0-9]+\.[A-Za-z]{3,4})$#', '$3', $_POST['uploaded']),
				'tmp_name'	=> $_POST['uploaded']
			);
		}
		// Sinon, on uploade
		else $file = $_FILES[$champ];
		$extension = strtolower(substr(strrchr($file['name'], '.'), 1));
		
		switch ($table) {
			case 'asso':
			case 'chap':
				$nom = $table;
				$path = PATH_UPLOAD['asso'];
				break;
			
			default:
				$nom = "";
				$path = PATH_UPLOAD[$table];
				break;
		}
		$nom .= time().'.'.$extension;
		
		if ($champ == 'uploaded') $move = rename($file['tmp_name'], $path.$nom);
		else $move = move_uploaded_file($file['tmp_name'], $path.$nom);
		
		if ($move) { return $nom; }
		else { return 1; }
	}

	/**
	 * @param $src - a valid file location
	 * @param $filigrane - l'image à apposer (png transparent)
	 * @param $bottom - position en haut, 0 pour placer au milieu
	 * @param $right - position à gauche, 0 pour placer au milieu
	 * @param $recouvr - pourcentage de recouvrement
	 * @param $transp - opacité à appliquer en pourcent (désactivé)
	 */
	private function signImage($src, $filigrane, $bottom=0, $right=0, $recouvr=30, $transp=15) {
		$type = exif_imagetype($src);
		if (!$type || !IMAGE_HANDLERS[$type]) {
		    return null;
		}
		$image = call_user_func(IMAGE_HANDLERS[$type]['load'], $src);
		if (!$image) {
		    return null;
		}
		$logo = imagecreatefrompng($filigrane);
		if (!$image) {
		    return null;
		}
		
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
		    IMAGE_HANDLERS[$type]['save'],
		    $image,
		    $src,
		    IMAGE_HANDLERS[$type]['quality']
		);
	}

	/**
	 * @param $src - a valid file location
	 * @param $dest - a valid file target
	 * @param $targetWidth - desired output width
	 * @param $targetHeight - desired output height or null
	 */
	function createThumbnail($src, $dest, $targetWidth, $targetHeight = null) {
		// 1. Load the image from the given $src
		// - see if the file actually exists
		// - check if it's of a valid image type
		// - load the image resource
		// get the type of the image
		// we need the type to determine the correct loader
		$type = exif_imagetype($src);
		// if no valid type or no handler found -> exit
		if (!$type || !IMAGE_HANDLERS[$type]) {
		    return null;
		}
		// load the image with the correct loader
		$image = call_user_func(IMAGE_HANDLERS[$type]['load'], $src);
		// no image found at supplied location -> exit
		if (!$image) {
		    return null;
		}
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
			if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG) {
				// make image transparent
				imagecolortransparent(
				    $thumbnail,
				    imagecolorallocate($thumbnail, 0, 0, 0)
				);
				// additional settings for PNGs
				if ($type == IMAGETYPE_PNG) {
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
				IMAGE_HANDLERS[$type]['save'],
				$thumbnail,
				$dest,
				IMAGE_HANDLERS[$type]['quality']
			);
		}
		else {
			if ($src != $dest) return copy($src, $dest);
			else return 1;
		}
	}
}
?>
