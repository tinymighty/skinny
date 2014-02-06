<?php

class SkinSkinny extends SkinTemplate{

	/**
	 * A register of valid skin layouts: key=>config
	 */
	protected static $layouts = array();
	
	/**
	 * An array of modules to be loaded by ResourceLoader
	 */
	protected static $modules = array();

	/**
	 * An array of modules keys which should be loaded when the template is initialized
	 */
	protected static $autoloadModules = array();


	/**
	 * An array of modules to be loaded by ResourceLoader
	 */
	protected static $template_paths = array();
	
	
	/**
	 * Boolean to track whether ResourceLoader modules have been added
	 */
	protected static $_modulesRegistered = false;


	/**
	 * Default option values
	 */
	public $defaults = array(
		'layout'=>'default'
	);
	public $options = array();

	function __construct( $options=array() ){
		$this->setOptions( $options );

		$layout = $this->layout = self::$layouts[ $this->options['layout'] ];
		//allow a layout to provide a custom template class
		if( isset($layout['templateClass']) ){
			$this->template = $layout['templateClass'];
		}

	}

	public function setOptions( $options, $reset=false ){
		if( $reset || empty($this->options) ){
			//set all options to their defaults
			$this->options = $this->defaults;
		}
		$this->options = Skinny::mergeOptionsArrays( $this->options, $options );
	}

	/**
	  * Load required modules with ResourceLoader
	  */ 
	public function initPage( OutputPage $out ){
				//load custom modules
		/*if(!empty(self::$modules)){
			foreach( array_keys(self::$modules) as $name){
				$out->addModules($name);
			}
		}*/
		$loadModules = array();
		if( isset( $this->layout['modules'] ) ){
			$loadModules = array_keys( $this->layout['modules'] );
		}
		$loadModules += self::$autoloadModules;

		foreach( $loadModules as $name ){
			$out->addModules($name);
		}
	} 

	/**
	 * Hooking into the template setup process to provide a custom template
	 * and ensure it's initialized with the options it needs.
	 */
	public function setupTemplate( $classname, $repository = false, $cache_dir = false ) {
		//allow current layout to specify a different template class
		$classname = isset($this->layout['templateClass']) ? $this->layout['templateClass'] : $classname;
		//instantiate template with the skin options
		$tpl = new $classname( $this->options );
		//ensure that all template paths registered to this skin are added to the template
		//this allows overriding templates without having to create a new template class
		foreach(self::$template_paths as $path){
			$tpl->addTemplatePath($path);
		}
		return $tpl;
	}

	/**
	 * Called by OutputPage to provide opportunity to add to body attrs
	 */
	public function addToBodyAttributes( $out, &$attrs){
		$attrs['class'] .= ' layout-'.$this->options['layout'];
	}

	/**
	 * Add a new skin layout for this skin
	 */
	public static function addLayout($name, $config=array()){
		if(isset($config['modules'])){
			self::addModules($config['modules']);
		}
		self::$layouts[$name] = $config;
	}



	public static function addTemplatePath( $path ){
		self::$template_paths[] = $path;
	}

	/**
	 * Register resources. This method is called by the ResourceLoaderRegisterModules hook.
	 */
	public static function registerModules( ResourceLoader $rl ){
		self::$_modulesRegistered = true;
		$rl->register( self::$modules );
    return true;
	}

	/**
	 * Build a list of modules to be registered to the ResourceLoader when it initializes.
	 */
	public static function addModules($modules=array(), $load=false){
		if( self::$_modulesRegistered ){
			throw new Exception('Skin is attempting to add modules after modules have already been registered.');
		}
		self::$modules += (array) $modules;
		if($load){
			self::$autoloadModules += array_keys($modules);
		}
	}

	public static function addModulesToLayout( $layout, $modules ){
		if(!isset(self::$layouts[$layout]['modules'])){
			self::$layouts[$layout]['modules'] = array();
		}
		self::addModules($modules);
		self::$layouts[$layout]['modules'] += $modules;
	}

	public static function loadModules( $module_names ){
		self::$autoloadModules += $module_names;
	}
}

