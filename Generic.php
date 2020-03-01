<?php
if (!class_exists('SplEnum')) {
	abstract class SplEnum
	{
		const __default = NULL;
		
	    private static $constCacheArray = NULL;

		// prevent instanciation
		private function __construct() { }
    
   	    static function getConstList($include_default = false) {
	        if (self::$constCacheArray == NULL) {
	            self::$constCacheArray = [];
	        }
	        $calledClass = get_called_class();
	        if (!array_key_exists($calledClass, self::$constCacheArray)) {
	            $reflect = new ReflectionClass($calledClass);
	            self::$constCacheArray[$calledClass] = $reflect->getConstants();
	            
	            if ($include_default === false)
	            	unset (self::$constCacheArray[$calledClass]['__default']);
	        }
	        
	        return self::$constCacheArray[$calledClass];
	    }
    }
}


abstract class ExtdEnum extends SplEnum
{
	// return true if key have been found
    static function hasKey($key) {
        $foundKey = false;
       
        try {
            $enumClassName = get_called_class();
            new $enumClassName($key);
            $foundKey = true;
        } finally {
            return $foundKey;
        }
    }
    // return key or deffault if doesn't exist
    static function getKey($key) {
   		if (self::hasKey($key) === true)
   			return $key;
   		else return self::__default;
   	}
   	// check if value exists strictly, hasKey is true when checking a different enum with same key but different value
   	static function hasValue($value) {
   		$constList = self::getConstList();
   		return in_array($value, $constList, $strict = true);
   	}
}
?>
